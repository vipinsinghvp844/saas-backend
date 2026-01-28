<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;

$data = json_decode(file_get_contents("php://input"), true);

$ticket_id = (int)($data['ticket_id'] ?? 0);
$message   = trim($data['message'] ?? '');

if ($ticket_id <= 0 || $message === '') {
  http_response_code(400);
  echo json_encode(["status"=>false,"message"=>"Invalid data"]);
  exit;
}

$db = new Database();
$conn = $db->connect();

/* fetch ticket */
$t = $conn->prepare("SELECT * FROM support_tickets WHERE id=:id");
$t->execute([":id"=>$ticket_id]);
$ticket = $t->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
  http_response_code(404);
  echo json_encode(["status"=>false,"message"=>"Ticket not found"]);
  exit;
}

/* gym isolation */
if ($auth['role']==='gym_admin' && $ticket['gym_id'] != $auth['gym_id']) {
  http_response_code(403);
  echo json_encode(["status"=>false,"message"=>"Access denied"]);
  exit;
}

$sender = $auth['role']==='super_admin' ? 'super_admin' : 'gym_admin';

/* insert reply */
$stmt = $conn->prepare("
  INSERT INTO support_ticket_replies
    (ticket_id, sender, message, created_at)
  VALUES
    (:ticket_id, :sender, :message, NOW())
");
$stmt->execute([
  ":ticket_id"=>$ticket_id,
  ":sender"=>$sender,
  ":message"=>$message
]);

/* auto status change */
$newStatus = $sender === 'super_admin' ? 'in_progress' : 'open';

$u = $conn->prepare("
  UPDATE support_tickets
  SET status=:status
  WHERE id=:id
");
$u->execute([
  ":status"=>$newStatus,
  ":id"=>$ticket_id
]);

echo json_encode(["status"=>true,"message"=>"Reply sent"]);
