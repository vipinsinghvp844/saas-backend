<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['super_admin']);

$data = json_decode(file_get_contents("php://input"), true);

$id = (int)($data['id'] ?? 0);
$status = strtolower(trim($data['status'] ?? ''));

$allowed = ['open','in_progress','resolved','closed'];

if ($id<=0 || !in_array($status,$allowed)) {
  http_response_code(400);
  echo json_encode(["status"=>false,"message"=>"Invalid input"]);
  exit;
}

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("
  UPDATE support_tickets
  SET status=:status
  WHERE id=:id
");
$stmt->execute([
  ":status"=>$status,
  ":id"=>$id
]);

echo json_encode(["status"=>true,"message"=>"Status updated"]);
