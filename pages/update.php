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
    echo json_encode(["status" => false, "message" => "Page id required"]);
    exit;
  }

  // ✅ validate page_data_json must be array (json object)
  if ($page_data_json === null || !is_array($page_data_json)) {
    http_response_code(400);
    echo json_encode([
      "status" => false,
      "message" => "page_data_json must be a valid JSON object"
    ]);
    exit;
  }

  $db = new Database();
  $conn = $db->connect();

  // ✅ check page exists
  $check = $conn->prepare("SELECT id FROM pages WHERE id=:id LIMIT 1");
  $check->execute([":id" => $id]);
  if (!$check->fetch(PDO::FETCH_ASSOC)) {
    http_response_code(404);
    echo json_encode(["status" => false, "message" => "Page not found"]);
    exit;
  }

  $encoded = json_encode($page_data_json);
  if ($encoded === false) {
    http_response_code(400);
    echo json_encode([
      "status" => false,
      "message" => "Invalid page_data_json (JSON encode failed)"
    ]);
    exit;
  }

  $stmt = $conn->prepare("
    UPDATE pages
    SET page_data_json = :page_data_json,
        updated_at = NOW()
    WHERE id = :id
  ");

  $stmt->execute([
    ":page_data_json" => $encoded,
    ":id" => $id
  ]);

  echo json_encode([
    "status" => true,
    "message" => "Page updated ✅"
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to update page",
    "error" => $e->getMessage()
  ]);
  exit;
}
