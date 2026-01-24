<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";
require_once "../mailer/send-template.php";

try {
  $auth = authenticate();
  $GLOBALS['auth_user'] = $auth;
  requireRole(['super_admin']);

  $data = json_decode(file_get_contents("php://input"), true);

  $to = trim($data['to'] ?? '');
  $slug = trim($data['slug'] ?? '');
  $vars = $data['vars'] ?? [];

  if ($to === '' || $slug === '') {
    http_response_code(400);
    echo json_encode(["status"=>false,"message"=>"to and slug required"]);
    exit;
  }

  if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status"=>false,"message"=>"Invalid email"]);
    exit;
  }

  if (!is_array($vars)) $vars = [];

  sendTemplateMail($to, $slug, $vars);

  echo json_encode(["status"=>true,"message"=>"Email sent ✅"]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status"=>false,
    "message"=>"Failed to send template email",
    "error"=>$e->getMessage()
  ]);
  exit;
}
