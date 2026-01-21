<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";

$gymSlug  = $_GET['gym']  ?? null;
$pageSlug = $_GET['page'] ?? 'home';

if (!$gymSlug) {
    http_response_code(400);
    echo json_encode(["message"=>"Gym slug required"]);
    exit;
}

$db = new Database();
$conn = $db->connect();

/* 1️⃣ Find gym */
$stmt = $conn->prepare("
  SELECT id, name
  FROM gyms
  WHERE slug = :slug
  AND status = 'active'
");
$stmt->execute([":slug"=>$gymSlug]);
$gym = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$gym) {
    http_response_code(404);
    echo json_encode(["message"=>"Gym not found"]);
    exit;
}

/* 2️⃣ Find page */
$stmt = $conn->prepare("
  SELECT
    p.slug,
    p.page_data_json,
    t.structure_json
  FROM pages p
  JOIN templates t ON t.id = p.template_id
  WHERE p.site_type = 'gym'
    AND p.gym_id = :gym_id
    AND p.slug = :slug
");
$stmt->execute([
  ":gym_id"=>$gym['id'],
  ":slug"=>$pageSlug
]);

$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    http_response_code(404);
    echo json_encode(["message"=>"Page not found"]);
    exit;
}

echo json_encode([
  "status" => true,
  "gym" => $gym,
  "page" => $page
]);
