<?php

namespace App\Core;

use RuntimeException;

/**
 * Mailer — minimal dependency-free SMTP client.
 *
 * Keeps to the project's "no Composer deps unless deliberate" rule:
 * no PHPMailer/Symfony Mailer required. Supports plain, SSL (port
 * 465) and STARTTLS (port 587) SMTP with AUTH LOGIN, which covers
 * Gmail, most transactional providers (SendGrid/Mailgun SMTP relay,
 * Zoho, Office365) and any standard company mail server.
 *
 * Config comes from config/mail.php. Two drivers:
 *   - 'smtp' : actually sends over the wire
 *   - 'log'  : writes to mail_log only — safe default for local/dev
 *              so nothing ever accidentally emails a real inbox
 *              during testing.
 *
 * Every attempt (sent, failed, or logged) is recorded in mail_log
 * for support/audit purposes — this was previously a silent no-op
 * in AuthService (password reset "emails" were never sent at all).
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        $driver = config('mail.driver', 'log');

        try {
            if ($driver !== 'smtp') {
                self::logOnly($to, $subject, $htmlBody);
                return true;
            }

            self::sendSmtp($to, $subject, $htmlBody, $textBody);
            self::record($to, $subject, 'sent', null);
            return true;

        } catch (\Throwable $e) {
            self::record($to, $subject, 'failed', $e->getMessage());

            if (config('app.debug', false)) {
                throw $e;
            }

            return false;
        }
    }

    private static function logOnly(string $to, string $subject, string $body): void
    {
        self::record($to, $subject, 'logged', null);

        // Also drop a plain-text copy on disk so developers can read
        // the actual reset link etc. without a real mailbox.
        $dir = base_path('storage/mail');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = $dir . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.html';
        @file_put_contents($file, "To: {$to}\nSubject: {$subject}\n\n{$body}");
    }

    private static function record(string $to, string $subject, string $status, ?string $error): void
    {
        try {
            DB::connect()->prepare("
                INSERT INTO mail_log (to_email, subject, status, error)
                VALUES (:to, :subject, :status, :error)
            ")->execute([
                ':to'      => $to,
                ':subject' => $subject,
                ':status'  => $status,
                ':error'   => $error,
            ]);
        } catch (\Throwable) {
            // Never let audit logging itself break the request.
        }
    }

    // ── SMTP protocol ────────────────────────────────────────

    private static function sendSmtp(string $to, string $subject, string $htmlBody, ?string $textBody): void
    {
        $host       = (string) config('mail.host', '');
        $port       = (int)    config('mail.port', 587);
        $encryption = (string) config('mail.encryption', 'tls'); // tls | ssl | none
        $username   = (string) config('mail.username', '');
        $password   = (string) config('mail.password', '');
        $fromEmail  = (string) config('mail.from_address', 'no-reply@localhost');
        $fromName   = (string) config('mail.from_name', 'Glee GPMS');
        $timeout    = (int)    config('mail.timeout', 10);

        if ($host === '') {
            throw new RuntimeException('mail.host is not configured.');
        }

        $transport = $encryption === 'ssl' ? "ssl://{$host}" : $host;

        $socket = @stream_socket_client(
            "{$transport}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if (!$socket) {
            throw new RuntimeException("SMTP connection failed: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $timeout);

        self::expect($socket, '220');
        self::command($socket, 'EHLO ' . gethostname(), '250');

        if ($encryption === 'tls') {
            self::command($socket, 'STARTTLS', '220');

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS negotiation failed.');
            }

            self::command($socket, 'EHLO ' . gethostname(), '250');
        }

        if ($username !== '') {
            self::command($socket, 'AUTH LOGIN', '334');
            self::command($socket, base64_encode($username), '334');
            self::command($socket, base64_encode($password), '235');
        }

        self::command($socket, "MAIL FROM:<{$fromEmail}>", '250');
        self::command($socket, "RCPT TO:<{$to}>", '250');
        self::command($socket, 'DATA', '354');

        $boundary = 'glee-' . bin2hex(random_bytes(8));
        $text     = $textBody ?? strip_tags($htmlBody);

        $headers = [
            'From: ' . self::encodeHeader($fromName) . " <{$fromEmail}>",
            "To: <{$to}>",
            'Subject: ' . self::encodeHeader($subject),
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            'Date: ' . date('r'),
        ];

        $message  = implode("\r\n", $headers) . "\r\n\r\n";
        $message .= "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$text}\r\n";
        $message .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$htmlBody}\r\n";
        $message .= "--{$boundary}--\r\n.";

        // Escape lines that start with a lone "." per RFC 5321.
        $message = preg_replace('/^\./m', '..', $message);

        self::command($socket, $message, '250');
        self::command($socket, 'QUIT', '221');

        fclose($socket);
    }

    private static function command($socket, string $line, string $expectedCode): string
    {
        fwrite($socket, $line . "\r\n");
        return self::expect($socket, $expectedCode);
    }

    private static function expect($socket, string $expectedCode): string
    {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            // Multi-line SMTP responses use "code-" until the final "code ".
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            throw new RuntimeException('No response from SMTP server.');
        }

        if (!str_starts_with($response, $expectedCode)) {
            throw new RuntimeException("Unexpected SMTP response: {$response}");
        }

        return $response;
    }

    private static function encodeHeader(string $value): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $value)) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
