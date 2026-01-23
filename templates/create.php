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

$name = trim($data['name'] ?? '');
$type = trim($data['type'] ?? '');
$structure_json = $data['structure_json'] ?? null;
$page_data_json = $data['page_data_json'] ?? null;

if ($name === '') {
  http_response_code(400);
  echo json_encode(["status" => false, "message" => "Template name required"]);
  exit;
}

if ($type !== 'platform' && $type !== 'gym') {
  http_response_code(400);
  echo json_encode(["status" => false, "message" => "Invalid template type"]);
  exit;
}

if (!$structure_json || empty($structure_json['sections'])) {
  http_response_code(400);
  echo json_encode(["status" => false, "message" => "Template structure_json required"]);
  exit;
}

$db = new Database();
$conn = $db->connect();

try {
  // ✅ Prevent duplicate template name for same type
  $check = $conn->prepare("SELECT id FROM templates WHERE name=:name AND type=:type LIMIT 1");
  $check->execute([
    ":name" => $name,
    ":type" => $type
  ]);

  if ($check->fetch(PDO::FETCH_ASSOC)) {
    http_response_code(409);
    echo json_encode([
      "status" => false,
      "message" => "Template name already exists for this type"
    ]);
    exit;
  }

  // ✅ Insert
  $stmt = $conn->prepare("
    INSERT INTO templates (type, name, structure_json, page_data_json)
    VALUES (:type, :name, :structure_json, :page_data_json)
  ");

  $stmt->execute([
    ":type" => $type,
    ":name" => $name,
    ":structure_json" => json_encode($structure_json),
    ":page_data_json" => json_encode($page_data_json ?? new stdClass())
  ]);

  echo json_encode([
    "status" => true,
    "message" => "Template created successfully",
    "id" => $conn->lastInsertId()
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to create template",
    "error" => $e->getMessage()
  ]);
  exit;
}
