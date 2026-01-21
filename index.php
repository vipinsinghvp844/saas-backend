<?php
require __DIR__ . '/config/cors.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ✅ IMPORT ROUTE
if ($path === '/import') {
    require __DIR__ . '/import.php';
    exit;
}

// existing routes
if ($path === '/login') {
    require __DIR__ . '/api/login.php';
    exit;
}

echo json_encode(["status" => "API Running"]);
