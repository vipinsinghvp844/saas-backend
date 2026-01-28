<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['gym_admin']);

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("
  SELECT id, subject, priority, status, created_at
  FROM support_tickets
  WHERE gym_id = :gym_id
  ORDER BY id DESC
");
$stmt->execute([
  ":gym_id" => $auth['gym_id']
]);

echo json_encode([
  "status" => true,
  "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);
