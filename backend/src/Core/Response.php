<?php

namespace App\Core;

class Response
{
    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['success' => $status < 400, 'data' => $data]);
        exit;
    }

    public static function error(string $message, int $status = 400, $details = null): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message, 'details' => $details]);
        exit;
    }

    public static function roleDenied(): void
    {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access for this role', 'data' => null]);
        exit;
    }
}
