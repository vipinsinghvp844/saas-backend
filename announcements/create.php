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

$title   = trim($data['title'] ?? '');
$message = trim($data['message'] ?? '');
$status  = $data['status'] ?? 'draft';
$startAt = $data['start_at'] ?? null;
$endAt   = $data['end_at'] ?? null;

if ($title === '' || $message === '') {
  http_response_code(400);
  echo json_encode([
    "status" => false,
    "message" => "Title and message are required"
  ]);
  exit;
}

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("
  INSERT INTO announcements
    (title, message, status, start_at, end_at, created_by, created_at)
  VALUES
    (:title, :message, :status, :start_at, :end_at, :created_by, NOW())
");

$stmt->execute([
  ":title"      => $title,
  ":message"    => $message,
  ":status"     => $status,
  ":start_at"   => $startAt,
  ":end_at"     => $endAt,
  ":created_by" => $auth['id']
]);

echo json_encode([
  "status" => true,
  "message" => "Announcement created"
]);
