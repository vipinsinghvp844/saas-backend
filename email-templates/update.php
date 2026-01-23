<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";
require_once "./helpers.php";

try {
  $auth = authenticate();
  $GLOBALS['auth_user'] = $auth;
  requireRole(['super_admin']);

  $data = json_decode(file_get_contents("php://input"), true);

  $id = (int)($data["id"] ?? 0);
  $name = trim($data["name"] ?? "");
  $slug = trim($data["slug"] ?? "");
  $subject = trim($data["subject"] ?? "");
  $html_body = (string)($data["html_body"] ?? "");
  $status = strtolower(trim($data["status"] ?? "active"));

  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Template id required"]);
    exit;
  }

  if ($name === "") {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Template name required"]);
    exit;
  }

  if ($slug === "") $slug = slugify($name);

  if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Invalid slug format"]);
    exit;
  }

  if ($subject === "") {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Subject required"]);
    exit;
  }

  if ($html_body === "") {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "HTML body required"]);
    exit;
  }

  if ($status !== "active" && $status !== "inactive") {
    $status = "active";
  }

  $db = new Database();
  $conn = $db->connect();

  // ✅ ensure exists
  $check = $conn->prepare("SELECT id FROM email_templates WHERE id=:id LIMIT 1");
  $check->execute([":id" => $id]);
  if (!$check->fetch(PDO::FETCH_ASSOC)) {
    http_response_code(404);
    echo json_encode(["status" => false, "message" => "Template not found"]);
    exit;
  }

  // ✅ duplicate slug check excluding self
  $dup = $conn->prepare("SELECT id FROM email_templates WHERE slug=:slug AND id!=:id LIMIT 1");
  $dup->execute([":slug" => $slug, ":id" => $id]);
  if ($dup->fetch(PDO::FETCH_ASSOC)) {
    http_response_code(409);
    echo json_encode(["status" => false, "message" => "Slug already exists"]);
    exit;
  }

  $stmt = $conn->prepare("
    UPDATE email_templates
    SET name=:name,
        slug=:slug,
        subject=:subject,
        html_body=:html_body,
        status=:status,
        updated_at = NOW()
    WHERE id=:id
    LIMIT 1
  ");

  $stmt->execute([
    ":name" => $name,
    ":slug" => $slug,
    ":subject" => $subject,
    ":html_body" => $html_body,
    ":status" => $status,
    ":id" => $id
  ]);

  echo json_encode([
    "status" => true,
    "message" => "Email template updated ✅"
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to update email template",
    "error" => $e->getMessage()
  ]);
  exit;
}
