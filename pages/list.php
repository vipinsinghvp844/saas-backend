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

  $db = new Database();
  $conn = $db->connect();

  $stmt = $conn->query("
    SELECT
      p.id,
      p.site_type,
      p.gym_id,
      p.slug,
      p.template_id,
      t.name AS template_name,
      t.type AS template_type,
      p.created_at,
      p.updated_at
    FROM pages p
    LEFT JOIN templates t ON t.id = p.template_id
    WHERE p.site_type = 'platform'
    ORDER BY p.id DESC
  ");

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    "status" => true,
    "data" => $rows
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load pages",
    "error" => $e->getMessage()
  ]);
  exit;
}
