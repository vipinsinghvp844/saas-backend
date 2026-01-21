<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['super_admin']);

$id = $_GET['id'] ?? null;

if (!$id) {
  http_response_code(400);
  echo json_encode(["status"=>false,"message"=>"Plan id required"]);
  exit;
}

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("
  SELECT id, name, slug, description, price, currency, billing_cycle, duration_days,
         features_json, sort_order, status, created_at, updated_at
  FROM membership_plans
  WHERE id=:id LIMIT 1
");
$stmt->execute([":id"=>$id]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  http_response_code(404);
  echo json_encode(["status"=>false,"message"=>"Plan not found"]);
  exit;
}

$row['features_json'] = $row['features_json'] ? json_decode($row['features_json'], true) : [];

echo json_encode(["status"=>true,"data"=>$row]);
