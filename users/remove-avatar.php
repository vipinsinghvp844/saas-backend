<?php
require_once "../config/cors.php";
require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

$auth = authenticate();

$db = new Database();
$conn = $db->connect();

/* =========================
   GET CURRENT AVATAR
========================= */
$stmt = $conn->prepare("
  SELECT avatar FROM users WHERE id = :id
");
$stmt->execute([":id" => $auth['id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   DELETE FILE (IF EXISTS)
========================= */
if ($user && $user['avatar']) {
  $filePath = __DIR__ . "/.." . $user['avatar'];
  if (file_exists($filePath)) {
    unlink($filePath);
  }
}

/* =========================
   UPDATE DB
========================= */
$stmt = $conn->prepare("
  UPDATE users SET avatar = NULL WHERE id = :id
");
$stmt->execute([":id" => $auth['id']]);

echo json_encode([
  "status" => true,
  "message" => "Avatar removed"
]);
