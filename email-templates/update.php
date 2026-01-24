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

  $id = (int)($data['id'] ?? 0);
  $slug = strtolower(trim($data['slug'] ?? ''));
  $name = trim($data['name'] ?? '');
  $subject = trim($data['subject'] ?? '');
  $body_html = trim($data['body_html'] ?? '');
  $status = strtolower(trim($data['status'] ?? 'active'));
  $variables_json = $data['variables_json'] ?? [];

  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["status"=>false,"message"=>"Template id required"]);
    exit;
  }

  if ($slug === '' || $name === '' || $subject === '' || $body_html === '') {
    http_response_code(400);
    echo json_encode(["status"=>false,"message"=>"slug, name, subject, body_html required"]);
    exit;
  }

  if (!preg_match('/^[a-z0-9\-_]+$/', $slug)) {
    http_response_code(400);
    echo json_encode(["status"=>false,"message"=>"Invalid slug"]);
    exit;
  }

  if ($status !== 'active' && $status !== 'inactive') $status = 'active';

  $db = new Database();
  $conn = $db->connect();

  $exists = $conn->prepare("SELECT id FROM email_templates WHERE id=:id LIMIT 1");
  $exists->execute([":id"=>$id]);
  if (!$exists->fetch()) {
    http_response_code(404);
    echo json_encode(["status"=>false,"message"=>"Template not found"]);
    exit;
  }

  $dup = $conn->prepare("SELECT id FROM email_templates WHERE slug=:slug AND id!=:id LIMIT 1");
  $dup->execute([":slug"=>$slug, ":id"=>$id]);
  if ($dup->fetch()) {
    http_response_code(409);
    echo json_encode(["status"=>false,"message"=>"Slug already used by another template"]);
    exit;
  }

  $stmt = $conn->prepare("
    UPDATE email_templates
    SET slug=:slug,
        name=:name,
        subject=:subject,
        body_html=:body_html,
        variables_json=:variables_json,
        status=:status,
        updated_at=NOW()
    WHERE id=:id
    LIMIT 1
  ");

  $stmt->execute([
    ":slug" => $slug,
    ":name" => $name,
    ":subject" => $subject,
    ":body_html" => $body_html,
    ":variables_json" => json_encode(is_array($variables_json) ? $variables_json : []),
    ":status" => $status,
    ":id" => $id
  ]);

  echo json_encode(["status"=>true,"message"=>"Email template updated ✅"]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status"=>false,
    "message"=>"Failed to update template",
    "error"=>$e->getMessage()
  ]);
  exit;
}
