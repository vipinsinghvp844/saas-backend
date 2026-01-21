<?php
require_once "../../config/cors.php";
require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

$auth = authenticate();
requireRole(['gym_admin']);

if (!isset($_FILES['logo'])) {
  http_response_code(400);
  echo json_encode(["message" => "Logo required"]);
  exit;
}

$dir = "../../storage/uploads/logos";
if (!is_dir($dir)) mkdir($dir, 0777, true);

$filename = "gym_" . $auth['gym_id'] . "_" . time() . ".png";
$path = $dir . $filename;

move_uploaded_file($_FILES['logo']['tmp_name'], $path);

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("UPDATE gyms SET logo=:logo WHERE id=:id");
$stmt->execute([
  ":logo" => "/storage/uploads/logos" . $filename,
  ":id" => $auth['gym_id']
]);

echo json_encode([
  "status" => true,
  "logo" => "/storage/uploads/logos/" . $filename
]);
