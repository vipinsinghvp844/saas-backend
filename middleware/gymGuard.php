<?php
// middleware/gymGuard.php

function requireSameGym($gym_id_from_request) {

    if (!isset($GLOBALS['auth_user'])) {
        http_response_code(401);
        echo json_encode([
            "status" => false,
            "message" => "Unauthorized"
        ]);
        exit;
    }

    $loggedInGymId = $GLOBALS['auth_user']['gym_id'];
    $role = $GLOBALS['auth_user']['role'];

    // Super admin can access any gym
    if ($role === 'super_admin') {
        return true;
    }

    // For others, gym_id must match
    if ((int)$gym_id_from_request !== (int)$loggedInGymId) {
        http_response_code(403);
        echo json_encode([
            "status" => false,
            "message" => "Gym access denied"
        ]);
        exit;
    }

    return true;
}
