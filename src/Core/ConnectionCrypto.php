<?php

namespace App\Core;

use RuntimeException;

/**
 * ConnectionCrypto — encrypts/decrypts tenant database connection
 * details (glee_master.tenants.connection_string) using AES-256-GCM.
 *
 * GCM specifically (not plain CBC) because it's *authenticated*
 * encryption — decryption fails outright if even one byte of the
 * stored value was altered, rather than silently producing garbage
 * that might partially "look like" valid credentials. That matters
 * here: a corrupted or tampered connection string should be a loud,
 * caught error, never a connection attempt with mangled data.
 *
 * The key lives in storage/keys/tenant_connection.key — outside the
 * web root, outside glee_master itself. See bin/generate-tenant-key.php
 * for why that separation is the entire point of encrypting this at
 * all. This class only ever reads that key; it never writes or
 * rotates it.
 */
final class ConnectionCrypto
{
    private const CIPHER = 'aes-256-gcm';

    private static ?string $keyCache = null;

    /**
     * Encrypts a connection-details array (host, port, database,
     * username, password, ssl, etc. — whatever TenantConnectionManager
     * needs) into a single opaque string safe to store in
     * connection_string. JSON internally rather than a raw DSN
     * string — avoids ambiguity around special characters (a
     * password containing ";" or "=" would corrupt a hand-rolled DSN
     * parser) and keeps this class dumb: it doesn't need to know
     * the shape of what it's encrypting.
     */
    public static function encrypt(array $connectionDetails): string
    {
        $plaintext = json_encode($connectionDetails, JSON_THROW_ON_ERROR);

        $key   = self::key();
        $iv    = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag   = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Failed to encrypt connection details.');
        }

        // iv + tag + ciphertext, concatenated then base64'd as one
        // storable string — iv and tag are fixed-length (12 and 16
        // bytes for GCM), so decrypt() can split them back out
        // unambiguously regardless of ciphertext length.
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Reverses encrypt(). Throws on ANY failure — wrong key,
     * corrupted/tampered data, or malformed input — rather than
     * returning null/false, so a caller can never accidentally
     * proceed with a partially-decrypted or unauthenticated result.
     */
    public static function decrypt(string $encoded): array
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false) {
            throw new RuntimeException('Connection string is not valid base64 — possibly corrupted.');
        }

        $ivLength  = openssl_cipher_iv_length(self::CIPHER);
        $tagLength = 16; // GCM auth tag is always 16 bytes

        if (strlen($raw) < $ivLength + $tagLength) {
            throw new RuntimeException('Connection string is too short to be valid — possibly corrupted.');
        }

        $iv         = substr($raw, 0, $ivLength);
        $tag        = substr($raw, $ivLength, $tagLength);
        $ciphertext = substr($raw, $ivLength + $tagLength);

        $key = self::key();

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            // Wrong key, or the ciphertext/tag was altered — GCM
            // authentication failed. Never guess or fall back here.
            throw new RuntimeException(
                'Failed to decrypt connection string — wrong encryption key, or the stored value was corrupted/tampered with.'
            );
        }

        $decoded = json_decode($plaintext, true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new RuntimeException('Decrypted connection string did not contain a valid structure.');
        }

        return $decoded;
    }

    private static function key(): string
    {
        if (self::$keyCache !== null) {
            return self::$keyCache;
        }

        $keyPath = base_path('storage/keys/tenant_connection.key');

        if (!is_file($keyPath)) {
            throw new RuntimeException(
                "Tenant connection encryption key not found at {$keyPath} — run bin/generate-tenant-key.php once."
            );
        }

        $encoded = trim((string) file_get_contents($keyPath));
        $key     = base64_decode($encoded, true);

        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException(
                "Tenant connection encryption key at {$keyPath} is malformed — expected 32 raw bytes, base64-encoded."
            );
        }

        self::$keyCache = $key;
        return $key;
    }
}
