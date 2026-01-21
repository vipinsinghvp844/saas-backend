<?php
// middleware/auth.php

require_once __DIR__ . '/../config/jwt.php';

function authenticate() {

    // Allow preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


    $headers = getallheaders();

    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode([
            "status" => false,
            "message" => "Authorization header missing"
        ]);
        exit;
    }

    // Expect: Bearer TOKEN
    $authHeader = $headers['Authorization'];
    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode([
            "status" => false,
            "message" => "Invalid authorization format"
        ]);
        exit;
    }

    $token = $matches[1];

    $decoded = verifyJWT($token);

    if (!$decoded) {
        http_response_code(401);
        echo json_encode([
            "status" => false,
            "message" => "Invalid or expired token"
        ]);
        exit;
    }

    // 🔴 IMPORTANT FIX
    $GLOBALS['auth_user'] = $decoded;

    return $decoded;
}
