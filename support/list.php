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
  SELECT
    t.id, t.subject, t.priority, t.status, t.created_at,
    g.name AS gym_name
  FROM support_tickets t
  LEFT JOIN gyms g ON g.id = t.gym_id
  ORDER BY t.id DESC
");

echo json_encode([
  "status"=>true,
  "data"=>$stmt->fetchAll(PDO::FETCH_ASSOC)
]);
