<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['super_admin']);

$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'] ?? null;
$name = trim($data['name'] ?? '');
$slug = trim($data['slug'] ?? '');
$description = trim($data['description'] ?? '');
$price = $data['price'] ?? 0;
$currency = trim($data['currency'] ?? 'INR');
$billing_cycle = trim($data['billing_cycle'] ?? 'monthly');
$duration_days = $data['duration_days'] ?? null;
$status = trim($data['status'] ?? 'active');
$sort_order = intval($data['sort_order'] ?? 0);
$features = $data['features_json'] ?? [];

if (!$id) {
  http_response_code(400);
  echo json_encode(["status"=>false,"message"=>"Plan id required"]);
  exit;
}
if ($name === '') {
  http_response_code(400);
  echo json_encode(["status"=>false,"message"=>"Plan name required"]);
  exit;
}
if ($slug === '') {
  http_response_code(400);
  echo json_encode(["status"=>false,"message"=>"Slug required"]);
  exit;
}
if (!in_array($billing_cycle, ['monthly','yearly','lifetime'])) {
  http_response_code(400);
  echo json_encode(["status"=>false,"message"=>"Invalid billing_cycle"]);
  exit;
}
if (!in_array($status, ['active','inactive'])) {
  http_response_code(400);
  echo json_encode(["status"=>false,"message"=>"Invalid status"]);
  exit;
}
if (!is_array($features)) $features = [];

$db = new Database();
$conn = $db->connect();

try {
  // exist
  $chk = $conn->prepare("SELECT id FROM membership_plans WHERE id=:id LIMIT 1");
  $chk->execute([":id"=>$id]);
  if (!$chk->fetch(PDO::FETCH_ASSOC)) {
    http_response_code(404);
    echo json_encode(["status"=>false,"message"=>"Plan not found"]);
    exit;
  }

  // unique slug excluding self
  $dup = $conn->prepare("SELECT id FROM membership_plans WHERE slug=:slug AND id!=:id LIMIT 1");
  $dup->execute([":slug"=>$slug,":id"=>$id]);
  if ($dup->fetch(PDO::FETCH_ASSOC)) {
    http_response_code(409);
    echo json_encode(["status"=>false,"message"=>"Slug already exists"]);
    exit;
  }

  $stmt = $conn->prepare("
    UPDATE membership_plans
    SET name=:name,
        slug=:slug,
        description=:description,
        price=:price,
        currency=:currency,
        billing_cycle=:billing_cycle,
        duration_days=:duration_days,
        status=:status,
        sort_order=:sort_order,
        features_json=:features_json
    WHERE id=:id
  ");

  $stmt->execute([
    ":name"=>$name,
    ":slug"=>$slug,
    ":description"=>$description,
    ":price"=>$price,
    ":currency"=>$currency,
    ":billing_cycle"=>$billing_cycle,
    ":duration_days"=>$duration_days,
    ":status"=>$status,
    ":sort_order"=>$sort_order,
    ":features_json"=>json_encode($features),
    ":id"=>$id
  ]);

  echo json_encode(["status"=>true,"message"=>"Plan updated"]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["status"=>false,"message"=>"Failed to update plan","error"=>$e->getMessage()]);
}
