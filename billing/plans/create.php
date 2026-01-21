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

if ($name === '') {
  http_response_code(400);
  echo json_encode(["status" => false, "message" => "Plan name required"]);
  exit;
}

if ($slug === '') {
  http_response_code(400);
  echo json_encode(["status" => false, "message" => "Slug required"]);
  exit;
}

if (!in_array($billing_cycle, ['monthly','yearly','lifetime'])) {
  http_response_code(400);
  echo json_encode(["status" => false, "message" => "Invalid billing_cycle"]);
  exit;
}

if (!in_array($status, ['active','inactive'])) {
  http_response_code(400);
  echo json_encode(["status" => false, "message" => "Invalid status"]);
  exit;
}

if (!is_array($features)) $features = [];

$db = new Database();
$conn = $db->connect();

try {

  // ✅ Unique slug check
  $check = $conn->prepare("SELECT id FROM membership_plans WHERE slug=:slug LIMIT 1");
  $check->execute([":slug" => $slug]);

  if ($check->fetch(PDO::FETCH_ASSOC)) {
    http_response_code(409);
    echo json_encode(["status" => false, "message" => "Slug already exists"]);
    exit;
  }

  $stmt = $conn->prepare("
    INSERT INTO membership_plans
      (name, slug, description, price, currency, billing_cycle, duration_days, status, sort_order, features_json)
    VALUES
      (:name, :slug, :description, :price, :currency, :billing_cycle, :duration_days, :status, :sort_order, :features_json)
  ");

  $stmt->execute([
    ":name" => $name,
    ":slug" => $slug,
    ":description" => $description,
    ":price" => $price,
    ":currency" => $currency,
    ":billing_cycle" => $billing_cycle,
    ":duration_days" => $duration_days,
    ":status" => $status,
    ":sort_order" => $sort_order,
    ":features_json" => json_encode($features)
  ]);

  echo json_encode([
    "status" => true,
    "message" => "Plan created",
    "id" => $conn->lastInsertId()
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to create plan",
    "error" => $e->getMessage()
  ]);
  exit;
}
