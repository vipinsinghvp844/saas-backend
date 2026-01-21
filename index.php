<?php
require __DIR__ . '/config/cors.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/login') {
    require __DIR__ . '/api/login.php';
    exit;
}

echo json_encode(["status" => "API Running"]);
