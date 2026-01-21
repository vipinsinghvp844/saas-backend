<?php
require_once "../../config/cors.php";
require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['gym_admin', 'super_admin']);

$data = json_decode(file_get_contents("php://input"), true);

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("
  UPDATE gyms SET
    name = :name,
    phone = :phone,
    address = :address,
    city = :city,
    state = :state,
    country = :country,
    primary_color = :primary_color,
    secondary_color = :secondary_color,
    timezone = :timezone,
    currency = :currency
  WHERE id = :gym_id
");

$stmt->execute([
  ":name" => $data['name'] ?? '',
  ":phone" => $data['phone'] ?? '',
  ":address" => $data['address'] ?? '',
  ":city" => $data['city'] ?? '',
  ":state" => $data['state'] ?? '',
  ":country" => $data['country'] ?? '',
  ":primary_color" => $data['primary_color'] ?? '#111827',
  ":secondary_color" => $data['secondary_color'] ?? '#3b82f6',
  ":timezone" => $data['timezone'] ?? 'Asia/Kolkata',
  ":currency" => $data['currency'] ?? 'INR',
  ":gym_id" => $auth['gym_id']
]);

echo json_encode([
  "status" => true,
  "message" => "Gym profile updated"
]);
