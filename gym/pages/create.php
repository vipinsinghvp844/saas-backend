<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['gym_admin']);

$data = json_decode(file_get_contents("php://input"), true);

if (
    empty($data['slug']) ||
    empty($data['template_id'])
) {
    http_response_code(400);
    echo json_encode(["message" => "Slug & template required"]);
    exit;
}

$db = new Database();
$conn = $db->connect();

// 🔐 prevent duplicate slug PER GYM
$stmt = $conn->prepare(
  "SELECT id FROM pages
   WHERE slug = :slug
   AND site_type = 'gym'
   AND gym_id = :gym_id"
);
$stmt->execute([
  ":slug" => $data['slug'],
  ":gym_id" => $auth['gym_id']
]);

if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(["message" => "Slug already exists"]);
    exit;
}

// ✅ create gym page
$stmt = $conn->prepare(
  "INSERT INTO pages
   (site_type, gym_id, slug, template_id, page_data_json)
   VALUES ('gym', :gym_id, :slug, :template, :data)"
);

$stmt->execute([
  ":gym_id" => $auth['gym_id'],
  ":slug" => $data['slug'],
  ":template" => $data['template_id'],
  ":data" => json_encode($data['page_data'] ?? [])
]);

echo json_encode([
  "status" => true,
  "message" => "Gym page created"
]);
