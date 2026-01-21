<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;

requireRole(['gym_admin','trainer']);

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare(
  "SELECT m.id,u.name,u.email,m.phone,m.gender,m.status
   FROM members m
   JOIN users u ON u.id=m.user_id
   WHERE m.gym_id=:gym"
);

$stmt->execute([":gym"=>$auth['gym_id']]);

echo json_encode([
  "status"=>true,
  "data"=>$stmt->fetchAll(PDO::FETCH_ASSOC)
]);
