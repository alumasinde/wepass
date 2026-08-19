<?php

namespace App\Core;

class Request
{
    private static ?array $jsonBody   = null;
    private static bool   $jsonParsed = false;

    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

   public function uri(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Remove index.php if present
    if ($uri === '/index.php') {
        $uri = '/';
    }

    // Remove script directory if app is in subfolder
    $scriptName = dirname($_SERVER['SCRIPT_NAME']);

    if ($scriptName !== '/' && str_starts_with($uri, $scriptName)) {
        $uri = substr($uri, strlen($scriptName));
    }

    return rtrim($uri, '/') ?: '/';
}

    /**
     * Lazily parses a JSON request body (cached — php://input is only
     * read once). A handful of views in this app submit
     * Content-Type: application/json via fetch() instead of a normal
     * form post, which means $_POST is empty for them (PHP only
     * auto-populates $_POST for application/x-www-form-urlencoded or
     * multipart/form-data). Before this, input()/all() had no way to
     * see those fields at all — including csrf_token, which meant
     * the global CSRF check added to every non-GET request silently
     * broke every JSON-submitting form the moment it was introduced.
     */
    private function jsonBody(): array
    {
        if (self::$jsonParsed) {
            return self::$jsonBody ?? [];
        }

        self::$jsonParsed = true;
        self::$jsonBody   = [];

        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw     = file_get_contents('php://input');
            $decoded = json_decode((string) $raw, true);

            if (is_array($decoded)) {
                self::$jsonBody = $decoded;
            }
        }

        return self::$jsonBody;
    }

    public function input(string $key, $default = null)
    {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }
        if (array_key_exists($key, $_GET)) {
            return $_GET[$key];
        }

        $json = $this->jsonBody();
        if (array_key_exists($key, $json)) {
            return $json[$key];
        }

        return $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST, $this->jsonBody());
    }

    public function header(string $key)
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$key] ?? null;
    }

    /**
     * True when the client explicitly asked for JSON (fetch() calls
     * in this app always send Accept: application/json). Used to let
     * a handler serve BOTH a plain form POST (full page redirect,
     * works with JS disabled) and an AJAX-enhanced version (JSON
     * response, no page reload) from the same route, without a
     * second endpoint to maintain.
     */
    public function wantsJson(): bool
    {
        $accept = $this->header('Accept') ?? '';
        return str_contains($accept, 'application/json');
    }

}
