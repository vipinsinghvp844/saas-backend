<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['super_admin']);

$data = json_decode(file_get_contents("php://input"), true);

$id      = $data['id'] ?? null;
$title   = trim($data['title'] ?? '');
$message = trim($data['message'] ?? '');
$status  = $data['status'] ?? 'draft';
$startAt = $data['start_at'] ?? null;
$endAt   = $data['end_at'] ?? null;

if (!$id || $title === '' || $message === '') {
  http_response_code(400);
  echo json_encode(["status"=>false,"message"=>"Invalid data"]);
  exit;
}

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("
  UPDATE announcements SET
    title = :title,
    message = :message,
    status = :status,
    start_at = :start_at,
    end_at = :end_at
  WHERE id = :id AND is_deleted = 0
");

$stmt->execute([
  ":title"    => $title,
  ":message"  => $message,
  ":status"   => $status,
  ":start_at" => $startAt,
  ":end_at"   => $endAt,
  ":id"       => $id
]);

echo json_encode([
  "status" => true,
  "message" => "Announcement updated"
]);
