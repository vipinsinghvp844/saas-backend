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

  // ✅ Currency filter (optional)
  $currency = strtoupper(trim($_GET['currency'] ?? 'INR'));

  // ✅ Months (default 12)
  $months = (int)($_GET['months'] ?? 12);
  if ($months < 3) $months = 3;
  if ($months > 24) $months = 24;

  // ✅ Start month
  $startYm = date("Y-m-01", strtotime("-" . ($months - 1) . " months"));

  /* ===========================
     ✅ Total Revenue (all time)
  =========================== */
  $totalStmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) AS total
    FROM payments
    WHERE status='paid'
      AND currency = :currency
  ");
  $totalStmt->execute([":currency" => $currency]);
  $totalRevenue = (float)($totalStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  /* ===========================
     ✅ Current Month Revenue (MRR base)
  =========================== */
  $mrrStmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) AS revenue
    FROM payments
    WHERE status='paid'
      AND currency = :currency
      AND DATE_FORMAT(paid_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
  ");
  $mrrStmt->execute([":currency" => $currency]);
  $mrr = (float)($mrrStmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0);

  /* ===========================
     ✅ Previous Month Revenue
  =========================== */
  $prevStmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) AS revenue
    FROM payments
    WHERE status='paid'
      AND currency = :currency
      AND DATE_FORMAT(paid_at, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m')
  ");
  $prevStmt->execute([":currency" => $currency]);
  $prevMonthRevenue = (float)($prevStmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0);

  $mrrGrowthPct = 0;
  if ($prevMonthRevenue > 0) {
    $mrrGrowthPct = (($mrr - $prevMonthRevenue) / $prevMonthRevenue) * 100;
  } else if ($mrr > 0) {
    $mrrGrowthPct = 100;
  }

  /* ===========================
     ✅ ARR = MRR * 12
  =========================== */
  $arr = $mrr * 12;

  /* ===========================
     ✅ Active Gyms Count (Paid subscriptions)
     using gym_subscriptions status=active
  =========================== */
  $activeGymsStmt = $conn->prepare("
    SELECT COUNT(DISTINCT gym_id) AS total
    FROM gym_subscriptions
    WHERE status='active'
  ");
  $activeGymsStmt->execute();
  $activeGyms = (int)($activeGymsStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  /* ===========================
     ✅ ARPU = MRR / Active Gyms
  =========================== */
  $arpu = 0;
  if ($activeGyms > 0) {
    $arpu = $mrr / $activeGyms;
  }

  /* ===========================
     ✅ Revenue Chart (Last N months)
  =========================== */
  $revStmt = $conn->prepare("
    SELECT 
      DATE_FORMAT(paid_at, '%Y-%m') as ym,
      COALESCE(SUM(amount),0) as revenue
    FROM payments
    WHERE status='paid'
      AND currency = :currency
      AND paid_at >= :startDate
    GROUP BY ym
    ORDER BY ym ASC
  ");
  $revStmt->execute([
    ":currency" => $currency,
    ":startDate" => $startYm
  ]);
  $revRows = $revStmt->fetchAll(PDO::FETCH_ASSOC);

  $revMap = [];
  foreach ($revRows as $r) {
    $revMap[$r['ym']] = (float)$r['revenue'];
  }

  $revenue_chart = [];
  for ($i = $months - 1; $i >= 0; $i--) {
    $ym = date("Y-m", strtotime("-$i months"));
    $revenue_chart[] = [
      "ym" => $ym,
      "month" => date("M", strtotime($ym . "-01")),
      "year" => date("Y", strtotime($ym . "-01")),
      "revenue" => $revMap[$ym] ?? 0,
    ];
  }

  echo json_encode([
    "status" => true,
    "data" => [
      "currency" => $currency,
      "total_revenue" => $totalRevenue,
      "mrr" => $mrr,
      "arr" => $arr,
      "arpu" => $arpu,
      "active_gyms" => $activeGyms,
      "prev_month_revenue" => $prevMonthRevenue,
      "mrr_growth_pct" => round($mrrGrowthPct, 2),
      "revenue_chart" => $revenue_chart,
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
