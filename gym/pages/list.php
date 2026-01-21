<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['gym_admin']);

$db = new Database();
$conn = $db->connect();

/*
  Gym Admin sirf wahi pages dekhega
  jo uske gym ke liye bane hain
*/
$stmt = $conn->prepare("
  SELECT
    id,
    slug,
    template_id,
    created_at
  FROM pages
  WHERE site_type = 'gym'
    AND gym_id = :gym_id
  ORDER BY id DESC
");

$stmt->execute([
  ":gym_id" => $auth['gym_id']
]);

echo json_encode([
  "status" => true,
  "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);
