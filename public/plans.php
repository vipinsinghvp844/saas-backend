<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";

$db = new Database();
$conn = $db->connect();

$stmt = $conn->query("
  SELECT id, name, slug, description, price, currency, billing_cycle, duration_days, features_json
  FROM membership_plans
  WHERE status='active'
  ORDER BY sort_order ASC, price ASC
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$r) {
  $r['features_json'] = $r['features_json'] ? json_decode($r['features_json'], true) : [];
}

echo json_encode([
  "status" => true,
  "data" => $rows
]);
