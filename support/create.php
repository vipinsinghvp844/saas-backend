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

$subject  = trim($data['subject'] ?? '');
$message  = trim($data['message'] ?? '');
$priority = strtolower($data['priority'] ?? 'medium');

if ($subject === '' || $message === '') {
  http_response_code(400);
  echo json_encode(["status"=>false,"message"=>"Subject and message required"]);
  exit;
}

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("
  INSERT INTO support_tickets
    (gym_id, user_id, subject, message, priority, status, created_at)
  VALUES
    (:gym_id, :user_id, :subject, :message, :priority, 'open', NOW())
");

$stmt->execute([
  ":gym_id"  => $auth['gym_id'],
  ":user_id" => $auth['id'],
  ":subject" => $subject,
  ":message" => $message,
  ":priority"=> $priority
]);

echo json_encode([
  "status" => true,
  "message" => "Ticket created"
]);
