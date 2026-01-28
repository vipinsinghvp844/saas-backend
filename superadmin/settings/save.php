<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['super_admin']);

$payload = json_decode(file_get_contents("php://input"), true);
if (!is_array($payload)) {
  http_response_code(400);
  echo json_encode(["status"=>false,"message"=>"Invalid payload"]);
  exit;
}

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("
  INSERT INTO platform_settings (`key`,`value`,`type`,`group`)
  VALUES (:key,:value,:type,:group)
  ON DUPLICATE KEY UPDATE
    value = VALUES(value),
    type = VALUES(type),
    `group` = VALUES(`group`)
");

foreach ($payload as $group => $settings) {
  foreach ($settings as $key => $value) {
    $type = is_bool($value) ? 'boolean' : (is_array($value) ? 'json' : 'string');
    $val  = $type === 'json' ? json_encode($value) : (string)$value;

    $stmt->execute([
      ":key"   => $key,
      ":value" => $val,
      ":type"  => $type,
      ":group" => $group
    ]);
  }
}

echo json_encode([
  "status" => true,
  "message" => "Settings saved successfully"
]);
