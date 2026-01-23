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

  $currency = strtoupper(trim($_GET['currency'] ?? 'INR'));
  $months = (int)($_GET['months'] ?? 12);
  if ($months < 1) $months = 12;
  if ($months > 24) $months = 24;

  $startDate = date("Y-m-01", strtotime("-" . ($months - 1) . " months"));

  /* ✅ Total Paid Revenue */
  $totalStmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) AS total
    FROM payments
    WHERE status='paid'
      AND currency = :currency
      AND paid_at >= :startDate
  ");
  $totalStmt->execute([
    ":currency" => $currency,
    ":startDate" => $startDate
  ]);
  $totalRevenue = (float)($totalStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  /* ✅ Method-wise revenue */
  $stmt = $conn->prepare("
    SELECT 
      LOWER(COALESCE(payment_method,'unknown')) AS method,
      COUNT(*) AS total_count,
      COALESCE(SUM(amount),0) AS revenue
    FROM payments
    WHERE status='paid'
      AND currency = :currency
      AND paid_at >= :startDate
    GROUP BY method
    ORDER BY revenue DESC
  ");
  $stmt->execute([
    ":currency" => $currency,
    ":startDate" => $startDate
  ]);

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $methods = [];
  foreach ($rows as $r) {
    $methods[] = [
      "method" => $r["method"],
      "revenue" => (float)$r["revenue"],
      "count" => (int)$r["total_count"],
    ];
  }

  echo json_encode([
    "status" => true,
    "data" => [
      "currency" => $currency,
      "months" => $months,
      "start_date" => $startDate,
      "total_paid_revenue" => $totalRevenue,
      "methods" => $methods
    ]
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load revenue methods analytics",
    "error" => $e->getMessage()
  ]);
  exit;
}
