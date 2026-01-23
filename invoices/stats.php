<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

try {

  $auth = authenticate();
  $GLOBALS['auth_user'] = $auth;
  requireRole(['super_admin']);

  $db = new Database();
  $conn = $db->connect();

  /*
    ✅ Stats:
      - total invoices
      - paid invoices
      - unpaid invoices
      - overdue invoices (unpaid + due_date < now)
  */

  $stmt = $conn->prepare("
    SELECT
      COUNT(*) AS total,
      SUM(LOWER(status) = 'paid') AS paid,
      SUM(LOWER(status) = 'unpaid') AS unpaid,
      SUM(LOWER(status) = 'unpaid' AND due_date IS NOT NULL AND due_date < NOW()) AS overdue
    FROM invoices
  ");

  $stmt->execute();
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  echo json_encode([
    "status" => true,
    "data" => [
      "total" => (int)($row["total"] ?? 0),
      "paid" => (int)($row["paid"] ?? 0),
      "unpaid" => (int)($row["unpaid"] ?? 0),
      "overdue" => (int)($row["overdue"] ?? 0),
    ]
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load invoice stats",
    "error" => $e->getMessage()
  ]);
  exit;
}
