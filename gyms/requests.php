<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;

requireRole(['super_admin']);

$db = new Database();
$conn = $db->connect();

/*
 Optional filters:
  /gyms/requests.php?status=pending
  /gyms/requests.php?search=gmail
*/

$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM gym_requests WHERE 1=1";
$params = [];

if (!empty($status)) {
    $sql .= " AND status = :status";
    $params[":status"] = $status;
}

if (!empty($search)) {
    $sql .= " AND (gym_name LIKE :search OR owner_email LIKE :search OR owner_name LIKE :search)";
    $params[":search"] = "%$search%";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);

echo json_encode([
    "status" => true,
    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);
