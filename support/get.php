<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(["status"=>false,"message"=>"Ticket id required"]);
  exit;
}

$db = new Database();
$conn = $db->connect();

/* ticket */
$stmt = $conn->prepare("
  SELECT *
  FROM support_tickets
  WHERE id = :id
  LIMIT 1
");
$stmt->execute([":id"=>$id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
  http_response_code(404);
  echo json_encode(["status"=>false,"message"=>"Ticket not found"]);
  exit;
}

/* gym isolation */
if ($auth['role'] === 'gym_admin' && $ticket['gym_id'] != $auth['gym_id']) {
  http_response_code(403);
  echo json_encode(["status"=>false,"message"=>"Access denied"]);
  exit;
}

/* replies */
$r = $conn->prepare("
  SELECT sender, message, created_at
  FROM support_ticket_replies
  WHERE ticket_id = :id
  ORDER BY id ASC
");
$r->execute([":id"=>$id]);

echo json_encode([
  "status" => true,
  "data" => [
    "ticket" => $ticket,
    "replies" => $r->fetchAll(PDO::FETCH_ASSOC)
  ]
]);
