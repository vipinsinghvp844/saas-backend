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

  $total = (int)$conn->query("SELECT COUNT(*) AS t FROM email_templates")->fetch(PDO::FETCH_ASSOC)['t'];
  $active = (int)$conn->query("SELECT COUNT(*) AS t FROM email_templates WHERE status='active'")->fetch(PDO::FETCH_ASSOC)['t'];
  $inactive = (int)$conn->query("SELECT COUNT(*) AS t FROM email_templates WHERE status='inactive'")->fetch(PDO::FETCH_ASSOC)['t'];

  echo json_encode([
    "status" => true,
    "data" => [
      "total" => $total,
      "active" => $active,
      "inactive" => $inactive
    ]
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load email template stats",
    "error" => $e->getMessage()
  ]);
  exit;
}
