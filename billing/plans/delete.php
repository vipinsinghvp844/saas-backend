<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['super_admin']);

$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'] ?? null;

if (!$id) {
  http_response_code(400);
  echo json_encode(["status"=>false,"message"=>"Plan id required"]);
  exit;
}

$db = new Database();
$conn = $db->connect();

try {
  $stmt = $conn->prepare("DELETE FROM membership_plans WHERE id=:id");
  $stmt->execute([":id"=>$id]);

  echo json_encode(["status"=>true,"message"=>"Plan deleted"]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["status"=>false,"message"=>"Failed to delete plan","error"=>$e->getMessage()]);
}
