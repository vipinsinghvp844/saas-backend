<?php
// gyms/bulk-delete.php

require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

try {
    /* AUTH */
    $auth = authenticate();
    $GLOBALS['auth_user'] = $auth;
    requireRole(['super_admin']);

    /* INPUT */
    $data = json_decode(file_get_contents("php://input"), true);
    $ids = $data['gym_ids'] ?? [];

    /* VALIDATION */
    if (!is_array($ids) || count($ids) === 0) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "gym_ids required"
        ]);
        exit;
    }

    // ✅ clean + numeric ids only
    $ids = array_values(array_unique(array_filter($ids, function ($id) {
        return is_numeric($id) && (int)$id > 0;
    })));

    if (count($ids) === 0) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Valid gym_ids required"
        ]);
        exit;
    }

    /* DB */
    $db = new Database();
    $conn = $db->connect();

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    /* DELETE */
    $conn->beginTransaction();

    // ✅ Only delete gyms (CASCADE will auto delete users, subscriptions, settings etc.)
    $stmt = $conn->prepare("DELETE FROM gyms WHERE id IN ($placeholders)");
    $stmt->execute($ids);

    $deleted = $stmt->rowCount();

    $conn->commit();

    echo json_encode([
        "status" => true,
        "message" => "Gyms deleted successfully",
        "deleted" => $deleted
    ]);
} catch (Exception $e) {

    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Server error",
        "error" => $e->getMessage()
    ]);
}
