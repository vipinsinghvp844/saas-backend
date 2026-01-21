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
  $id = (int)($data['id'] ?? 0);

  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["status"=>false,"message"=>"Page id required"]);
    exit;
  }

  $db = new Database();
  $conn = $db->connect();

  $stmt = $conn->prepare("DELETE FROM pages WHERE id=:id LIMIT 1");
  $stmt->execute([":id"=>$id]);

  echo json_encode(["status"=>true,"message"=>"Page deleted ✅"]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status"=>false,
    "message"=>"Delete failed",
    "error"=>$e->getMessage()
  ]);
  exit;
}
