<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;

// Gym admin & trainer allowed
requireRole(['gym_admin','trainer']);

$data = json_decode(file_get_contents("php://input"), true);

if (
    empty($data['name']) ||
    empty($data['email']) ||
    empty($data['password'])
) {
    http_response_code(400);
    echo json_encode(["message" => "Required fields missing"]);
    exit;
}

$db = new Database();
$conn = $db->connect();

// check duplicate email
$stmt = $conn->prepare("SELECT id FROM users WHERE email=:email");
$stmt->execute([":email"=>$data['email']]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(["message"=>"Email already exists"]);
    exit;
}

// create user (member)
$stmt = $conn->prepare(
  "INSERT INTO users (gym_id,name,email,password,role,status)
   VALUES (:gym,:name,:email,:password,'member','active')"
);

$stmt->execute([
  ":gym"=>$auth['gym_id'],
  ":name"=>$data['name'],
  ":email"=>$data['email'],
  ":password"=>password_hash($data['password'],PASSWORD_DEFAULT)
]);

$user_id = $conn->lastInsertId();

// create member profile
$stmt = $conn->prepare(
  "INSERT INTO members (gym_id,user_id,phone,gender,join_date)
   VALUES (:gym,:user,:phone,:gender,CURDATE())"
);

$stmt->execute([
  ":gym"=>$auth['gym_id'],
  ":user"=>$user_id,
  ":phone"=>$data['phone'] ?? null,
  ":gender"=>$data['gender'] ?? null
]);

echo json_encode([
  "status"=>true,
  "message"=>"Member created successfully"
]);
