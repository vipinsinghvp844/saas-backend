<?php
// middleware/roleGuard.php

function requireRole(array $allowedRoles) {

    if (!isset($GLOBALS['auth_user'])) {
        http_response_code(401);
        echo json_encode([
            "status" => false,
            "message" => "Unauthorized"
        ]);
        exit;
    }

    $userRole = $GLOBALS['auth_user']['role'];

    if (!in_array($userRole, $allowedRoles)) {
        http_response_code(403);
        echo json_encode([
            "status" => false,
            "message" => "Access denied"
        ]);
        exit;
    }
}
