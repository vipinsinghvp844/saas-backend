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

  $id = (int)($_GET['id'] ?? 0);

  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Page id required"]);
    exit;
  }

  $db = new Database();
  $conn = $db->connect();

  $stmt = $conn->prepare("
    SELECT
      p.id,
      p.site_type,
      p.gym_id,
      p.slug,
      p.template_id,
      p.structure_json,
      p.page_data_json,
      p.created_at,
      p.updated_at,
      t.name AS template_name,
      t.type AS template_type
    FROM pages p
    LEFT JOIN templates t ON t.id = p.template_id
    WHERE p.id = :id
    LIMIT 1
  ");
  $stmt->execute([":id" => $id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    http_response_code(404);
    echo json_encode(["status" => false, "message" => "Page not found"]);
    exit;
  }

  // ✅ decode JSON for frontend convenience (optional)
  $row['structure_json'] = json_decode($row['structure_json'] ?? "{}", true);
  $row['page_data_json'] = json_decode($row['page_data_json'] ?? "{}", true);

  echo json_encode([
    "status" => true,
    "data" => $row
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load page",
    "error" => $e->getMessage()
  ]);
  exit;
}
