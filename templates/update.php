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

  $id = isset($data['id']) ? (int)$data['id'] : 0;
  $name = trim($data['name'] ?? '');
  $type = trim($data['type'] ?? '');
  $structure_json = $data['structure_json'] ?? null;
  $page_data_json = $data['page_data_json'] ?? null;

  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Template id required"]);
    exit;
  }

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

  // ✅ Validate structure_json
  if (!is_array($structure_json) || empty($structure_json['sections']) || !is_array($structure_json['sections'])) {
    http_response_code(400);
    echo json_encode([
      "status" => false,
      "message" => "Template structure_json required (sections array required)"
    ]);
    exit;
  }

  // ✅ Validate page_data_json (optional but should be array)
  if ($page_data_json !== null && !is_array($page_data_json)) {
    http_response_code(400);
    echo json_encode([
      "status" => false,
      "message" => "Invalid page_data_json format"
    ]);
    exit;
  }

  $db = new Database();
  $conn = $db->connect();

  // ✅ check template exists
  $check = $conn->prepare("SELECT id FROM templates WHERE id=:id LIMIT 1");
  $check->execute([":id" => $id]);

  if (!$check->fetch(PDO::FETCH_ASSOC)) {
    http_response_code(404);
    echo json_encode(["status" => false, "message" => "Template not found"]);
    exit;
  }

  // ✅ prevent duplicate name in same type (exclude current id)
  $dup = $conn->prepare("
    SELECT id 
    FROM templates 
    WHERE name=:name AND type=:type AND id!=:id
    LIMIT 1
  ");
  $dup->execute([
    ":name" => $name,
    ":type" => $type,
    ":id" => $id
  ]);

  if ($dup->fetch(PDO::FETCH_ASSOC)) {
    http_response_code(409);
    echo json_encode([
      "status" => false,
      "message" => "Template name already exists for this type"
    ]);
    exit;
  }

  $stmt = $conn->prepare("
    UPDATE templates
    SET name=:name,
        type=:type,
        structure_json=:structure_json,
        page_data_json=:page_data_json,
        updated_at=NOW()
    WHERE id=:id
  ");

  $stmt->execute([
    ":name" => $name,
    ":type" => $type,
    ":structure_json" => json_encode($structure_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ":page_data_json" => json_encode($page_data_json ?? new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ":id" => $id
  ]);

  echo json_encode([
    "status" => true,
    "message" => "Template updated successfully"
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to update template",
    "error" => $e->getMessage()
  ]);
  exit;
}
