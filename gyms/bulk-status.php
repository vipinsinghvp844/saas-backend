<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

try {
    $auth = authenticate();
    $GLOBALS['auth_user'] = $auth;
    requireRole(['super_admin']);

    $data = json_decode(file_get_contents("php://input"), true);

    $ids = $data['gym_ids'] ?? [];
    $status = $data['status'] ?? '';

    if (!is_array($ids) || count($ids) === 0 || empty($status)) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "gym_ids and status required"
        ]);
        exit;
    }

    // allow only these statuses
    $allowed = ['active', 'inactive', 'suspended', 'trial'];
    if (!in_array($status, $allowed)) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Invalid status"
        ]);
        exit;
    }

    $db = new Database();
    $conn = $db->connect();

    // make placeholders (?, ?, ?)
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sql = "UPDATE gyms SET status = ? WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);

    // first param = status, then ids
    $params = array_merge([$status], $ids);

    $stmt->execute($params);

    echo json_encode([
        "status" => true,
        "message" => "Bulk status updated",
        "updated" => $stmt->rowCount()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Server error",
        "error" => $e->getMessage()
    ]);
}
