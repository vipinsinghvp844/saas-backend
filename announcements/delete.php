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
$id = $data['id'] ?? null;

if (!$id) {
  http_response_code(400);
  echo json_encode(["status"=>false,"message"=>"ID required"]);
  exit;
}

$db = new Database();
$conn = $db->connect();

$conn->prepare("
  UPDATE announcements
  SET is_deleted = 1
  WHERE id = ?
")->execute([$id]);

echo json_encode([
  "status" => true,
  "message" => "Announcement deleted"
]);
