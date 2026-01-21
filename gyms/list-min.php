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

$stmt = $conn->query("
  SELECT id, name, email, status
  FROM gyms
  ORDER BY id DESC
");

echo json_encode([
  "status" => true,
  "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);
