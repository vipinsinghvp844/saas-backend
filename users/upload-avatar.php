<?php
require_once "../config/cors.php";
require_once "../config/db.php";
require_once "../middleware/auth.php";

$auth = authenticate();

/* =========================
   VALIDATION
========================= */
if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
  http_response_code(400);
  echo json_encode([
    "status" => false,
    "message" => "Image required"
  ]);
  exit;
}

/* =========================
   UPLOAD DIRECTORY
========================= */
$dir = __DIR__ . "/../storage/uploads/users/";
if (!is_dir($dir)) {
  mkdir($dir, 0777, true);
}

/* =========================
   FORCE EXTENSION (IMPORTANT)
   Because cropped image = Blob
========================= */
$filename = "user_" . $auth['id'] . "_" . time() . ".jpg";
$path = $dir . $filename;

/* =========================
   MOVE FILE
========================= */
if (!move_uploaded_file($_FILES['image']['tmp_name'], $path)) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to upload image"
  ]);
  exit;
}

/* =========================
   SAVE TO DB
========================= */
$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("
  UPDATE users
  SET avatar = :avatar
  WHERE id = :id
");

$stmt->execute([
  ":avatar" => "/storage/uploads/users/" . $filename,
  ":id" => $auth['id']
]);

/* =========================
   RESPONSE
========================= */
echo json_encode([
  "status" => true,
  "image" => "/storage/uploads/users/" . $filename
]);
