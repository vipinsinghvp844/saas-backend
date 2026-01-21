<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";

try {

  $slug = strtolower(trim($_GET['slug'] ?? 'home'));
  if ($slug === '') $slug = 'home';

  $db = new Database();
  $conn = $db->connect();

  // ✅ only platform pages
  $stmt = $conn->prepare("
    SELECT
      id,
      site_type,
      gym_id,
      slug,
      template_id,
      structure_json,
      page_data_json,
      created_at,
      updated_at
    FROM pages
    WHERE site_type = 'platform'
      AND slug = :slug
    LIMIT 1
  ");

  $stmt->execute([":slug" => $slug]);
  $page = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$page) {
    http_response_code(404);
    echo json_encode([
      "status" => false,
      "message" => "Page not found"
    ]);
    exit;
  }

  echo json_encode([
    "status" => true,
    "data" => $page
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Server error",
    "error" => $e->getMessage()
  ]);
  exit;
}
