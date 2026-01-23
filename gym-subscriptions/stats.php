<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

try {

  /* ✅ AUTH */
  $auth = authenticate();
  $GLOBALS['auth_user'] = $auth;
  requireRole(['super_admin']);

  $db = new Database();
  $conn = $db->connect();

  $systemGymEmail = "admin@platform.com";

  $stmt = $conn->prepare("
    SELECT
      COUNT(*) AS total,
      SUM(LOWER(gs.status)='active') AS active,
      SUM(LOWER(gs.status)='trial') AS trial,
      SUM(LOWER(gs.status)='cancelled') AS cancelled
    FROM gym_subscriptions gs
    LEFT JOIN gyms g ON g.id = gs.gym_id
    WHERE g.email != :sysEmail
  ");
  $stmt->execute([":sysEmail" => $systemGymEmail]);

  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  echo json_encode([
    "status" => true,
    "data" => [
      "total" => (int)($row["total"] ?? 0),
      "active" => (int)($row["active"] ?? 0),
      "trial" => (int)($row["trial"] ?? 0),
      "cancelled" => (int)($row["cancelled"] ?? 0),
    ]
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load subscription stats",
    "error" => $e->getMessage()
  ]);
  exit;
}
