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
  $name = trim($data['name'] ?? '');
  $subject = trim($data['subject'] ?? '');
  $body_html = trim($data['body_html'] ?? '');
  $status = strtolower(trim($data['status'] ?? 'active'));

  $variables_json = $data['variables_json'] ?? [];

  if ($slug === '' || $name === '' || $subject === '' || $body_html === '') {
    http_response_code(400);
    echo json_encode(["status"=>false,"message"=>"slug, name, subject, body_html required"]);
    exit;
  }

  if (!preg_match('/^[a-z0-9\-_]+$/', $slug)) {
    http_response_code(400);
    echo json_encode(["status"=>false,"message"=>"Invalid slug. Use letters, numbers, dash, underscore"]);
    exit;
  }

  if ($status !== 'active' && $status !== 'inactive') $status = 'active';

  $db = new Database();
  $conn = $db->connect();

  $check = $conn->prepare("SELECT id FROM email_templates WHERE slug=:slug LIMIT 1");
  $check->execute([":slug"=>$slug]);
  if ($check->fetch()) {
    http_response_code(409);
    echo json_encode(["status"=>false,"message"=>"Slug already exists"]);
    exit;
  }

  $stmt = $conn->prepare("
    INSERT INTO email_templates (slug, name, subject, body_html, variables_json, status)
    VALUES (:slug, :name, :subject, :body_html, :variables_json, :status)
  ");

  $stmt->execute([
    ":slug" => $slug,
    ":name" => $name,
    ":subject" => $subject,
    ":body_html" => $body_html,
    ":variables_json" => json_encode(is_array($variables_json) ? $variables_json : []),
    ":status" => $status
  ]);

  echo json_encode([
    "status"=>true,
    "message"=>"Email template created ✅",
    "data"=>["id"=>$conn->lastInsertId()]
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status"=>false,
    "message"=>"Failed to create template",
    "error"=>$e->getMessage()
  ]);
  exit;
}
