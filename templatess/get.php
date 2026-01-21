<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['super_admin']);

$id = $_GET['id'] ?? null;

if (empty($id)) {
  http_response_code(400);
  echo json_encode(["status" => false, "message" => "Template id required"]);
  exit;
}

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("SELECT id, type, name, structure_json, page_data_json FROM templates WHERE id=:id LIMIT 1");
$stmt->execute([":id" => $id]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
  http_response_code(404);
  echo json_encode(["status" => false, "message" => "Template not found"]);
  exit;
}

echo json_encode([
  "status" => true,
  "data" => $template
]);
