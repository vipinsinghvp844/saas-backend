<?php
require_once "../../config/cors.php";
require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['gym_admin','super_admin']);

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("
  SELECT
    id, name, email, phone, address, city, state, country,
    logo, primary_color, secondary_color, timezone, currency
  FROM gyms
  WHERE id = :gym_id
  LIMIT 1
"); 

$stmt->execute([
  ":gym_id" => $auth['gym_id']
]);

$gym = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
  "status" => true,
  "data" => $gym
]);
