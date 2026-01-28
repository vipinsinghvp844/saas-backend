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
  SELECT `key`, `value`, `type`, `group`
  FROM platform_settings
");

$data = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $val = $row['value'];

  if ($row['type'] === 'boolean') $val = $val === '1';
  if ($row['type'] === 'number')  $val = (int)$val;
  if ($row['type'] === 'json')    $val = json_decode($val, true);

  $data[$row['group']][$row['key']] = $val;
}

echo json_encode([
  "status" => true,
  "data" => $data
]);
