<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../helpers/auditLog.php";

try {

    /* ==========================
       🔐 AUTHENTICATE USER
    ========================== */
    $auth = authenticate(); // JWT se user nikalega
    $GLOBALS['auth_user'] = $auth;

    /* ==========================
       ✅ AUDIT LOG: LOGOUT
    ========================== */
    logAudit([
        "action"      => "logout",
        "module"      => "auth",
        "target_type" => "user",
        "target_id"   => (int)$auth['id'],
        "description" => "User logged out successfully"
    ]);

    /* ==========================
       ✅ RESPONSE
    ========================== */
    echo json_encode([
        "status"  => true,
        "message" => "Logged out successfully"
    ]);
    exit;

} catch (Exception $e) {

    http_response_code(401);
    echo json_encode([
        "status"  => false,
        "message" => "Unauthorized"
    ]);
    exit;
}
