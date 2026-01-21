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

  $slug = strtolower(trim($data['slug'] ?? ''));
  $template_id = (int)($data['template_id'] ?? 0);

  if ($slug === '' || $template_id <= 0) {
    http_response_code(400);
    echo json_encode([
      "status" => false,
      "message" => "slug and template_id are required"
    ]);
    exit;
  }

  // ✅ slug validation
  if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
    http_response_code(400);
    echo json_encode([
      "status" => false,
      "message" => "Invalid slug. Use only lowercase letters, numbers and hyphen (-)"
    ]);
    exit;
  }

  $db = new Database();
  $conn = $db->connect();

  // ✅ prevent duplicate slug for platform
  $check = $conn->prepare("
    SELECT id FROM pages
    WHERE site_type='platform' AND slug=:slug
    LIMIT 1
  ");
  $check->execute([":slug" => $slug]);

  if ($check->fetch(PDO::FETCH_ASSOC)) {
    http_response_code(409);
    echo json_encode([
      "status" => false,
      "message" => "Page slug already exists"
    ]);
    exit;
  }

  // ✅ fetch template
  $t = $conn->prepare("
    SELECT id, type, structure_json, page_data_json
    FROM templates
    WHERE id=:id
    LIMIT 1
  ");
  $t->execute([":id" => $template_id]);
  $tpl = $t->fetch(PDO::FETCH_ASSOC);

  if (!$tpl) {
    http_response_code(404);
    echo json_encode([
      "status" => false,
      "message" => "Template not found"
    ]);
    exit;
  }

  // ✅ platform page must use platform template
  if ($tpl['type'] !== 'platform') {
    http_response_code(400);
    echo json_encode([
      "status" => false,
      "message" => "Selected template is not a platform template"
    ]);
    exit;
  }

  $structure_json = $tpl['structure_json'] ?: json_encode(["sections" => []]);
  $page_data_json = $tpl['page_data_json'] ?: json_encode(new stdClass());

  // ✅ insert page
  $stmt = $conn->prepare("
    INSERT INTO pages
      (site_type, gym_id, slug, template_id, structure_json, page_data_json)
    VALUES
      ('platform', NULL, :slug, :template_id, :structure_json, :page_data_json)
  ");

  $stmt->execute([
    ":slug" => $slug,
    ":template_id" => $template_id,
    ":structure_json" => $structure_json,
    ":page_data_json" => $page_data_json
  ]);

  $pageId = $conn->lastInsertId();

  echo json_encode([
    "status" => true,
    "message" => "Page created ✅",
    "data" => [
      "id" => $pageId,
      "slug" => $slug
    ]
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to create page",
    "error" => $e->getMessage()
  ]);
  exit;
}
