<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;

requireRole(['gym_admin']);

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['member_id'])) {
    http_response_code(400);
    echo json_encode(["message"=>"Member ID required"]);
    exit;
}

$db = new Database();
$conn = $db->connect();

// soft delete (best practice)
$stmt = $conn->prepare(
  "UPDATE members SET status='inactive'
   WHERE id=:id AND gym_id=:gym"
);

$stmt->execute([
  ":id"=>$data['member_id'],
  ":gym"=>$auth['gym_id']
]);

echo json_encode([
  "status"=>true,
  "message"=>"Member deactivated"
]);
