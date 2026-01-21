<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['super_admin']);

$db = new Database();
$conn = $db->connect();

$stmt = $conn->query("
  SELECT id, name, slug, description, price, currency, billing_cycle, duration_days,
         features_json, sort_order, status, created_at, updated_at
  FROM membership_plans
  ORDER BY sort_order ASC, price ASC
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$r) {
  $r['features_json'] = $r['features_json'] ? json_decode($r['features_json'], true) : [];
}

echo json_encode(["status" => true, "data" => $rows]);
