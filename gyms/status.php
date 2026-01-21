<?php
require_once "../config/cors.php";
require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

/* =========================
   AUTH & ROLE CHECK
========================= */
$auth = authenticate();
requireRole(['super_admin']); // Only Super Admin

/* =========================
   INPUT VALIDATION
========================= */
$data = json_decode(file_get_contents("php://input"), true);

if (
  empty($data['gym_id']) ||
  empty($data['status']) ||
  !in_array($data['status'], ['active', 'inactive'])
) {
  http_response_code(400);
  echo json_encode([
    "status" => false,
    "message" => "Invalid request data"
  ]);
  exit;
}

$gym_id = (int) $data['gym_id'];
$status = $data['status'];

/* =========================
   DB UPDATE
========================= */
$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("
  UPDATE gyms
  SET status = :status
  WHERE id = :id
");

$success = $stmt->execute([
  ":status" => $status,
  ":id" => $gym_id
]);

/* =========================
   RESPONSE
========================= */
if ($success && $stmt->rowCount() > 0) {
  echo json_encode([
    "status" => true,
    "message" => "Gym status updated successfully"
  ]);
} else {
  http_response_code(400);
  echo json_encode([
    "status" => false,
    "message" => "Failed to update gym status"
  ]);
}
