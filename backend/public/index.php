<?php

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/autoload.php';

use App\Core\Request;

header('Access-Control-Allow-Origin: ' . config('cors.origin'));
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$request = new Request();

try {
    require __DIR__ . '/../routes.php';
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
