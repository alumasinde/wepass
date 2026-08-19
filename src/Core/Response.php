<?php

namespace App\Core;

class Response
{
    /**
     * Send JSON response and terminate.
     */
    public static function json(mixed $data, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($data, JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * Redirect and terminate.
     */
    public static function redirect(string $url, int $status = 302): never
    {
        if (!headers_sent()) {
            header("Location: {$url}", true, $status);
        }

        exit;
    }

    /**
     * Abort execution with proper error rendering.
     */
    public static function abort(int $code, string $message = ''): never
    {
        // Normalize invalid codes
        if ($code < 400 || $code > 599) {
            $code = 500;
        }

        $message = $message ?: self::defaultMessage($code);

        // API request? Return JSON.
        if (self::wantsJson()) {
            self::json([
                'error'   => true,
                'code'    => $code,
                'message' => $message,
            ], $code);
        }

        if (!headers_sent()) {
            http_response_code($code);
        }

        // Make available to error view
        $errorMessage = $message;

        $viewPath = self::resolveErrorView($code);

        if (is_file($viewPath)) {
            require $viewPath;
        } else {
            // Minimal safe fallback
            echo "<h1>{$code}</h1>";
            echo "<p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>";
        }

        exit;
    }

    /**
     * Resolve correct error view.
     */
    private static function resolveErrorView(int $code): string
    {
        // Uses your helper from bootstrap/app.php
        $base = base_path('resources/views/errors/');

        $file = match ($code) {
            403 => '403.php',
            404 => '404.php',
            500 => '500.php',
            default => '500.php',
        };

        return $base . $file;
    }

    /**
     * Default HTTP messages.
     */
    private static function defaultMessage(int $code): string
    {
        return match ($code) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
            default => "Error {$code}",
        };
    }

    /**
     * Determine if request expects JSON.
     */
    private static function wantsJson(): bool
    {
        if (!isset($_SERVER['HTTP_ACCEPT'])) {
            return false;
        }

        return str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')
            || str_contains($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'XMLHttpRequest');
    }
}