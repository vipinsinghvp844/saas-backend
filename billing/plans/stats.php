<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

try {
  $auth = authenticate();
  $GLOBALS['auth_user'] = $auth;
  requireRole(['super_admin']);

  $db = new Database();
  $conn = $db->connect();

  /* ✅ TOTAL REVENUE (all time) */
  $revStmt = $conn->query("
    SELECT COALESCE(SUM(amount),0) AS total_revenue
    FROM payments
    WHERE status='paid'
  ");
  $totalRevenue = (float)$revStmt->fetch(PDO::FETCH_ASSOC)['total_revenue'];

  /* ✅ MRR (current month paid revenue) */
  $mrrStmt = $conn->query("
    SELECT COALESCE(SUM(amount),0) AS mrr
    FROM payments
    WHERE status='paid'
      AND DATE_FORMAT(paid_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
  ");
  $mrr = (float)$mrrStmt->fetch(PDO::FETCH_ASSOC)['mrr'];

  /* ✅ Plan wise subscriptions summary */
  $subStmt = $conn->query("
    SELECT
      plan_id,
      COUNT(*) AS total,
      SUM(status='active') AS active,
      SUM(status='trial') AS trial
    FROM gym_subscriptions
    WHERE plan_id IS NOT NULL
    GROUP BY plan_id
  ");

  $plansSummary = [];
  while ($r = $subStmt->fetch(PDO::FETCH_ASSOC)) {
    $planId = (string)$r['plan_id'];
    $plansSummary[$planId] = [
      "total" => (int)$r['total'],
      "active" => (int)$r['active'],
      "trial" => (int)$r['trial'],
    ];
  }

  echo json_encode([
    "status" => true,
    "data" => [
      "total_revenue" => $totalRevenue,
      "mrr" => $mrr,
      "plans_summary" => $plansSummary
    ]
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load plan stats",
    "error" => $e->getMessage()
  ]);
  exit;
}
