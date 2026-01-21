<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";

$auth = authenticate(); // JWT verify

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['password'])) {
    http_response_code(400);
    echo json_encode(["message"=>"New password required"]);
    exit;
}

if (strlen($data['password']) < 6) {
    http_response_code(400);
    echo json_encode(["message"=>"Password must be at least 6 characters"]);
    exit;
}

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare(
  "UPDATE users
   SET password = :password,
       force_password_change = 0
   WHERE id = :id"
);

$stmt->execute([
  ":password" => password_hash($data['password'], PASSWORD_DEFAULT),
  ":id" => $auth['id']
]);

echo json_encode([
  "status" => true,
  "message" => "Password updated successfully"
]);
