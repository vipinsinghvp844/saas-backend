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

if (empty($data['page_id']) || !isset($data['page_data'])) {
    http_response_code(400);
    echo json_encode(["message"=>"Page ID & content required"]);
    exit;
}

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("
  UPDATE pages
  SET page_data_json = :data
  WHERE id = :id
    AND site_type = 'gym'
    AND gym_id = :gym_id
");

$stmt->execute([
  ":data" => json_encode($data['page_data']),
  ":id" => $data['page_id'],
  ":gym_id" => $auth['gym_id']
]);

echo json_encode([
  "status" => true,
  "message" => "Page content updated"
]);
