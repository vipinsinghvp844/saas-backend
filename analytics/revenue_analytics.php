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

  // ✅ date range start
  $startDate = date("Y-m-01", strtotime("-" . ($months - 1) . " months"));
  $currentYM = date("Y-m");

  /* ==========================
      ✅ TOTAL PAID REVENUE
  ========================== */
  $stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) AS total
    FROM payments
    WHERE status='paid'
      AND currency=:currency
      AND paid_at >= :startDate
  ");
  $stmt->execute([
    ":currency" => $currency,
    ":startDate" => $startDate
  ]);
  $totalRevenue = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  /* ==========================
      ✅ CURRENT MONTH MRR
      (paid revenue of current month)
  ========================== */
  $stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) AS total
    FROM payments
    WHERE status='paid'
      AND currency=:currency
      AND DATE_FORMAT(paid_at,'%Y-%m') = :ym
  ");
  $stmt->execute([
    ":currency" => $currency,
    ":ym" => $currentYM
  ]);
  $mrr = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  /* ==========================
      ✅ ACTIVE SUBSCRIPTIONS (for ARPU)
  ========================== */
  $stmt = $conn->query("
    SELECT COUNT(*) as total
    FROM gym_subscriptions
    WHERE status='active'
  ");
  $activeSubs = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  /* ==========================
      ✅ ARPU (MRR / active subs)
  ========================== */
  $arpu = 0;
  if ($activeSubs > 0) {
    $arpu = $mrr / $activeSubs;
  }

  /* ==========================
      ✅ ARR (run rate)
  ========================== */
  $arr = $mrr * 12;

  /* ==========================
      ✅ REVENUE CHART (months list)
  ========================== */
  $stmt = $conn->prepare("
    SELECT 
      DATE_FORMAT(paid_at,'%Y-%m') as ym,
      COALESCE(SUM(amount),0) as revenue
    FROM payments
    WHERE status='paid'
      AND currency=:currency
      AND paid_at >= :startDate
    GROUP BY ym
    ORDER BY ym ASC
  ");
  $stmt->execute([
    ":currency" => $currency,
    ":startDate" => $startDate
  ]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $revMap = [];
  foreach ($rows as $r) {
    $revMap[$r['ym']] = (float)$r['revenue'];
  }

  $revenueChart = [];
  for ($i = $months - 1; $i >= 0; $i--) {
    $ym = date("Y-m", strtotime("-$i months"));
    $revenueChart[] = [
      "ym" => $ym,
      "month" => date("M", strtotime($ym . "-01")),
      "year" => date("Y", strtotime($ym . "-01")),
      "revenue" => $revMap[$ym] ?? 0
    ];
  }

  /* ==========================
      ✅ COMPARISON: last month vs current month
  ========================== */
  $lastYM = date("Y-m", strtotime("-1 month"));

  $stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) AS total
    FROM payments
    WHERE status='paid'
      AND currency=:currency
      AND DATE_FORMAT(paid_at,'%Y-%m') = :ym
  ");
  $stmt->execute([":currency" => $currency, ":ym" => $lastYM]);
  $lastMonthRevenue = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  $mrrChangePercent = 0;
  if ($lastMonthRevenue > 0) {
    $mrrChangePercent = (($mrr - $lastMonthRevenue) / $lastMonthRevenue) * 100;
  } else {
    $mrrChangePercent = $mrr > 0 ? 100 : 0;
  }

  /* ==========================
      ✅ TOP PLANS BY REVENUE (optional)
  ========================== */
  $topPlansStmt = $conn->prepare("
    SELECT
      COALESCE(mp.name,'Unknown') AS plan_name,
      COUNT(DISTINCT p.gym_id) AS gyms,
      COALESCE(SUM(p.amount),0) AS revenue
    FROM payments p
    LEFT JOIN gym_subscriptions gs ON gs.gym_id = p.gym_id AND gs.status='active'
    LEFT JOIN membership_plans mp ON mp.id = gs.plan_id
    WHERE p.status='paid'
      AND p.currency=:currency
      AND p.paid_at >= :startDate
    GROUP BY plan_name
    ORDER BY revenue DESC
    LIMIT 6
  ");
  $topPlansStmt->execute([
    ":currency" => $currency,
    ":startDate" => $startDate
  ]);
  $topPlans = $topPlansStmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    "status" => true,
    "data" => [
      "currency" => $currency,
      "months" => $months,
      "start_date" => $startDate,

      "total_revenue" => $totalRevenue,
      "mrr" => $mrr,
      "arr" => $arr,
      "arpu" => round($arpu, 2),

      "active_subscriptions" => $activeSubs,

      "last_month_revenue" => $lastMonthRevenue,
      "mrr_change_percent" => round($mrrChangePercent, 2),

      "revenue_chart" => $revenueChart,
      "top_plans" => $topPlans
    ]
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load revenue analytics",
    "error" => $e->getMessage()
  ]);
  exit;
}
