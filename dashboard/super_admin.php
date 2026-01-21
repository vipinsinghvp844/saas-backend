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

  /* ============================
     ✅ BASIC STATS
  ============================ */

  // ✅ Total gyms
  $stmt = $conn->query("SELECT COUNT(*) as total FROM gyms");
  $totalGyms = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

  // ✅ Active gyms
  $stmt = $conn->query("SELECT COUNT(*) as total FROM gyms WHERE LOWER(status)='active'");
  $activeGyms = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

  // ✅ Total gym admins
  $stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='gym_admin'");
  $totalUsers = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

  /* ============================
     ✅ Latest Gyms (WITH PLAN)
     gyms + gym_subscriptions(active) + membership_plans
  ============================ */
  $latestStmt = $conn->query("
  SELECT
    g.id,
    g.name,
    g.slug,
    LOWER(g.status) AS status,
    g.created_at,
    g.email AS gymEmail,

    -- ✅ owner
    u.email AS ownerEmail,
    CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS ownerName,

    -- ✅ plan
    mp.id   AS plan_id,
    mp.name AS plan_name,
    mp.slug AS plan_slug,
    mp.price AS plan_price,
    gs.status AS subscription_status

  FROM gyms g

  LEFT JOIN users u
    ON u.gym_id = g.id
    AND u.role = 'gym_admin'

  LEFT JOIN gym_subscriptions gs
    ON gs.gym_id = g.id
    AND gs.status = 'active'

  LEFT JOIN membership_plans mp
    ON mp.id = gs.plan_id

  ORDER BY g.id DESC
  LIMIT 5
");

  $latestGyms = $latestStmt->fetchAll(PDO::FETCH_ASSOC);

  /* ============================
     ✅ Gym Status Chart (REAL)
  ============================ */
  $statusStmt = $conn->query("
    SELECT LOWER(status) as status, COUNT(*) as total
    FROM gyms
    GROUP BY LOWER(status)
  ");
  $statusRows = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

  $statusMap = [
    "active" => 0,
    "trial" => 0,
    "suspended" => 0,
    "inactive" => 0
  ];

  foreach ($statusRows as $row) {
    $st = $row['status'];
    if (isset($statusMap[$st])) {
      $statusMap[$st] = (int)$row['total'];
    }
  }

  $gym_status_chart = [
    ["name" => "Active", "value" => $statusMap["active"]],
    ["name" => "Trial", "value" => $statusMap["trial"]],
    ["name" => "Suspended", "value" => $statusMap["suspended"]],
    ["name" => "Inactive", "value" => $statusMap["inactive"]],
  ];

  /* ============================
     ✅ Revenue Chart (Last 12 months)
     Based on PAYMENTS (status=paid)
  ============================ */
  $revStmt = $conn->query("
    SELECT 
      DATE_FORMAT(paid_at, '%Y-%m') as ym,
      SUM(amount) as revenue
    FROM payments
    WHERE status='paid'
      AND paid_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
    GROUP BY ym
    ORDER BY ym ASC
  ");

  $revRows = $revStmt->fetchAll(PDO::FETCH_ASSOC);

  $revMap = [];
  foreach ($revRows as $r) {
    $revMap[$r['ym']] = (float)$r['revenue'];
  }

  $chart = [];
  for ($i = 11; $i >= 0; $i--) {
    $ym = date("Y-m", strtotime("-$i months"));
    $chart[] = [
      "month" => date("M", strtotime($ym . "-01")),
      "year"  => date("Y", strtotime($ym . "-01")),
      "ym"    => $ym,
      "revenue" => $revMap[$ym] ?? 0
    ];
  }

  /* ============================
     ✅ MRR (Current month revenue)
  ============================ */
  $mrrStmt = $conn->query("
    SELECT SUM(amount) as revenue
    FROM payments
    WHERE status='paid'
      AND DATE_FORMAT(paid_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
  ");
  $mrr = (float)($mrrStmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0);

  /* ============================
     ✅ Recent Payments (Latest 8)
     payments + gyms + invoices
  ============================ */
  $payStmt = $conn->query("
    SELECT
      p.id,
      g.name AS gym_name,
      p.amount,
      p.currency,
      p.status,
      p.paid_at,
      p.created_at,
      i.invoice_number

    FROM payments p
    LEFT JOIN gyms g ON g.id = p.gym_id
    LEFT JOIN invoices i ON i.id = p.invoice_id

    ORDER BY p.id DESC
    LIMIT 8
  ");

  $recentPayments = $payStmt->fetchAll(PDO::FETCH_ASSOC);

  /* ============================
     ✅ FINAL RESPONSE
  ============================ */
  echo json_encode([
    "status" => true,
    "data" => [
      "total_gyms" => $totalGyms,
      "active_gyms" => $activeGyms,
      "total_users" => $totalUsers,

      "latest_gyms" => $latestGyms,

      "mrr" => $mrr,
      "revenue_chart" => $chart,

      "gym_status_chart" => $gym_status_chart,
      "recent_payments" => $recentPayments
    ]
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load dashboard",
    "error" => $e->getMessage()
  ]);
  exit;
}
