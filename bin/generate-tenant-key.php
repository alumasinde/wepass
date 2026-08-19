<?php

/**
 * bin/generate-tenant-key.php — run ONCE, by hand, to generate the
 * encryption key used for per-tenant connection strings
 * (glee_master.tenants.connection_string).
 *
 * Usage:
 *   php bin/generate-tenant-key.php
 *
 * Deliberately does NOT overwrite an existing key — if one already
 * exists, every connection string encrypted with it becomes
 * permanently undecryptable the moment it's replaced. This script
 * refuses to run again once a key file is present; delete the key
 * file yourself first if you genuinely intend to rotate it (and be
 * aware that means re-entering every tenant's connection details
 * from scratch afterward — there's no way to re-encrypt existing
 * values with a key you no longer have the old one to decrypt with).
 *
 * WHERE THIS KEY LIVES, AND WHY:
 *   storage/keys/tenant_connection.key — outside public_html/ (the
 *   actual web root; nothing under storage/ is ever served), and
 *   deliberately NOT in glee_master alongside the encrypted values
 *   themselves. The whole point of encrypting connection_string is
 *   that if glee_master ever leaks on its own (a DB dump, a SQL
 *   injection read, a careless backup copied somewhere it
 *   shouldn't be), the encrypted values inside it are useless
 *   without a key that was never in that database to begin with.
 *   Storing the key in the same table it protects would defeat the
 *   entire purpose.
 *
 * This does NOT protect against someone with full access to the
 * app server itself (they can read this file same as any other) —
 * nothing does, at that point. What it protects against is the
 * much more common failure mode: the DATABASE leaking on its own,
 * independent of the app server.
 *
 * After running: set this file's permissions as tightly as your
 * hosting allows (chmod 600, owned by the app's own user, not
 * group/world readable) and make sure storage/ is included in
 * whatever backup process you use — losing this file means every
 * tenant's stored connection string becomes permanently
 * undecryptable, which is just as bad as losing the tenant
 * database itself.
 */

$keyPath = __DIR__ . '/../storage/keys/tenant_connection.key';

if (file_exists($keyPath)) {
    fwrite(STDERR, "A key already exists at {$keyPath} — refusing to overwrite it.\n");
    fwrite(STDERR, "Every connection string encrypted with the existing key would become\n");
    fwrite(STDERR, "permanently undecryptable if this generated a new one. Delete the file\n");
    fwrite(STDERR, "yourself first if you're certain you want to rotate it.\n");
    exit(1);
}

$keyDir = dirname($keyPath);
if (!is_dir($keyDir)) {
    mkdir($keyDir, 0700, true);
}

// 32 random bytes = a full-strength AES-256 key, base64-encoded for
// safe storage as plain text in the key file.
$key = base64_encode(random_bytes(32));

if (file_put_contents($keyPath, $key) === false) {
    fwrite(STDERR, "Failed to write key file to {$keyPath} — check directory permissions.\n");
    exit(1);
}

chmod($keyPath, 0600);

echo "Key generated: {$keyPath}\n";
echo "Permissions set to 600 (owner read/write only).\n";
echo "\n";
echo "Next steps:\n";
echo "  1. Confirm the file is NOT web-accessible (it shouldn't be — storage/ is outside public_html/).\n";
echo "  2. Make sure your backup process includes storage/keys/ — losing this file\n";
echo "     makes every stored tenant connection string permanently unusable.\n";
echo "  3. You're ready to store an encrypted connection string for a tenant.\n";
