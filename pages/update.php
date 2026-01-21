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
  $page_data_json = $data['page_data_json'] ?? null;

  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["status"=>false,"message"=>"Page id required"]);
    exit;
  }

  if (!$page_data_json || !is_array($page_data_json)) {
    http_response_code(400);
    echo json_encode(["status"=>false,"message"=>"page_data_json must be an object"]);
    exit;
  }

  $db = new Database();
  $conn = $db->connect();

  $stmt = $conn->prepare("
    UPDATE pages
    SET page_data_json = :page_data_json,
        updated_at = NOW()
    WHERE id = :id
    LIMIT 1
  ");

  $stmt->execute([
    ":page_data_json" => json_encode($page_data_json),
    ":id" => $id
  ]);

  echo json_encode(["status"=>true,"message"=>"Page updated ✅"]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status"=>false,
    "message"=>"Failed to update page",
    "error"=>$e->getMessage()
  ]);
  exit;
}
