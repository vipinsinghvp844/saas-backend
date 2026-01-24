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

  $slug = strtolower(trim($_GET['slug'] ?? ''));
  if ($slug === '') {
    http_response_code(400);
    echo json_encode(["status"=>false,"message"=>"Template slug required"]);
    exit;
  }

  $db = new Database();
  $conn = $db->connect();

  $stmt = $conn->prepare("SELECT * FROM email_templates WHERE slug=:slug LIMIT 1");
  $stmt->execute([":slug" => $slug]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    http_response_code(404);
    echo json_encode(["status"=>false,"message"=>"Template not found"]);
    exit;
  }

  echo json_encode(["status"=>true,"data"=>$row]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status"=>false,
    "message"=>"Failed to load template",
    "error"=>$e->getMessage()
  ]);
  exit;
}
