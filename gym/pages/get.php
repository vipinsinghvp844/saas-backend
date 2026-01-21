<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['gym_admin']);

if (empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(["message"=>"Page ID required"]);
    exit;
}

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("
  SELECT
    p.id,
    p.slug,
    p.page_data_json,
    t.structure_json
  FROM pages p
  JOIN templates t ON t.id = p.template_id
  WHERE p.id = :id
    AND p.site_type = 'gym'
    AND p.gym_id = :gym_id
");

$stmt->execute([
  ":id" => $_GET['id'],
  ":gym_id" => $auth['gym_id']
]);

$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    http_response_code(404);
    echo json_encode(["message"=>"Page not found"]);
    exit;
}

echo json_encode([
  "status" => true,
  "data" => $page
]);
