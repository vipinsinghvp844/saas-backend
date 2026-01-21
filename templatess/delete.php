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

if (empty($data['id'])) {
  http_response_code(400);
  echo json_encode(["status" => false, "message" => "Template id required"]);
  exit;
}

$db = new Database();
$conn = $db->connect();

$templateId = (int)$data['id'];

// ✅ Check if any pages are using this template
$check = $conn->prepare("SELECT COUNT(*) as total FROM pages WHERE template_id = :id");
$check->execute([":id" => $templateId]);
$row = $check->fetch(PDO::FETCH_ASSOC);

if (!empty($row) && (int)$row['total'] > 0) {
  http_response_code(409);
  echo json_encode([
    "status" => false,
    "message" => "This template is in use by pages. Please update pages first, then delete."
  ]);
  exit;
}

// ✅ Safe delete
$stmt = $conn->prepare("DELETE FROM templates WHERE id=:id");
$stmt->execute([":id" => $templateId]);

echo json_encode(["status" => true, "message" => "Template deleted"]);
