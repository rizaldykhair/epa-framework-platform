<?php

namespace App\Core;

class Request
{
    public string $method;
    public string $path;
    public array $query = [];
    public array $body = [];
    public array $headers = [];
    public ?array $user = null;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
        $path = $uri;
        if ($base !== '' && str_starts_with($uri, $base)) {
            $path = substr($uri, strlen($base));
        }
        $this->path = '/' . ltrim($path, '/');

        parse_str($_SERVER['QUERY_STRING'] ?? '', $this->query);

        $raw = file_get_contents('php://input');
        $this->body = $raw ? (json_decode($raw, true) ?? []) : [];

        $this->headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
    }

    public function bearerToken(): ?string
    {
        $auth = $this->headers['Authorization'] ?? $this->headers['authorization'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
