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

  /* ==========================================
     ✅ EXCLUDE SYSTEM / SUPER ADMIN GYM
     (your system gym record)
  ========================================== */
  $systemGymEmail = "admin@platform.com";

  /* ============================
     ✅ TOTAL GYMS (exclude system)
  ============================ */
  $stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM gyms
    WHERE email != :sysEmail
  ");
  $stmt->execute([":sysEmail" => $systemGymEmail]);
  $totalGyms = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  /* ============================
     ✅ ACTIVE GYMS (RUNNING GYMS)
     ✅ Production meaning:
        - Gym status must be active
        - And billing can be trial OR paid
     So trial gyms are included ✅
  ============================ */
  $stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM gyms
    WHERE email != :sysEmail
      AND LOWER(status) = 'active'
      AND LOWER(COALESCE(billing_status,'trial')) IN ('trial','paid')
  ");
  $stmt->execute([":sysEmail" => $systemGymEmail]);
  $activeGyms = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  /* ============================
     ✅ TOTAL GYM ADMINS
  ============================ */
  $stmt = $conn->query("
    SELECT COUNT(*) as total
    FROM users
    WHERE role='gym_admin'
  ");
  $totalUsers = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  /* ============================
     ✅ Latest Gyms
     WITH PLAN + SUB STATUS (active/trial)
     exclude system gym
     
     IMPORTANT FIX:
     - If multiple subs exist, take latest one
     - So we use a subquery to pick latest subscription row
  ============================ */
  $latestStmt = $conn->prepare("
    SELECT
      g.id,
      g.name,
      g.slug,
      LOWER(g.status) as status,
      g.created_at,
      g.email AS gymEmail,
      LOWER(COALESCE(g.billing_status,'trial')) AS billing_status,

      CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS ownerName,
      u.email AS ownerEmail,

      mp.id   AS plan_id,
      mp.name AS plan_name,
      mp.slug AS plan_slug,
      mp.price AS plan_price,

      gs.status AS subscription_status,
      gs.trial_ends_at,
      gs.start_date,
      gs.end_date

    FROM gyms g

    LEFT JOIN users u
      ON u.gym_id = g.id
      AND u.role = 'gym_admin'

    /* ✅ Latest subscription row for that gym */
    LEFT JOIN gym_subscriptions gs
      ON gs.id = (
        SELECT id
        FROM gym_subscriptions
        WHERE gym_id = g.id
        ORDER BY id DESC
        LIMIT 1
      )

    LEFT JOIN membership_plans mp
      ON mp.id = gs.plan_id

    WHERE g.email != :sysEmail

    ORDER BY g.id DESC
    LIMIT 5
  ");
  $latestStmt->execute([":sysEmail" => $systemGymEmail]);
  $latestGyms = $latestStmt->fetchAll(PDO::FETCH_ASSOC);

  /* ======================================
     ✅ Gym Status Chart (REAL + CORRECT)
     ✅ This is for PIE CHART

     Rules:
      - suspended -> Suspended
      - inactive -> Inactive
      - active + trial billing -> Trial
      - active + paid billing -> Active
  ====================================== */
  $statusStmt = $conn->prepare("
    SELECT 
      CASE
        WHEN LOWER(g.status) = 'suspended' THEN 'Suspended'
        WHEN LOWER(g.status) = 'inactive' THEN 'Inactive'
        WHEN LOWER(g.status) = 'active' AND LOWER(COALESCE(g.billing_status,'trial')) = 'trial' THEN 'Trial'
        WHEN LOWER(g.status) = 'active' AND LOWER(g.billing_status) = 'paid' THEN 'Active'
        ELSE 'Inactive'
      END AS label,
      COUNT(*) as total
    FROM gyms g
    WHERE g.email != :sysEmail
    GROUP BY label
  ");
  $statusStmt->execute([":sysEmail" => $systemGymEmail]);
  $statusRows = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

  $statusMap = [
    "Active" => 0,
    "Trial" => 0,
    "Suspended" => 0,
    "Inactive" => 0,
  ];

  foreach ($statusRows as $row) {
    $label = $row['label'] ?? '';
    if (isset($statusMap[$label])) {
      $statusMap[$label] = (int)($row['total'] ?? 0);
    }
  }

  $gym_status_chart = [
    ["name" => "Active", "value" => $statusMap["Active"]],
    ["name" => "Trial", "value" => $statusMap["Trial"]],
    ["name" => "Suspended", "value" => $statusMap["Suspended"]],
    ["name" => "Inactive", "value" => $statusMap["Inactive"]],
  ];

  /* ======================================
     ✅ Revenue Chart (Last 12 months)
     Based on PAYMENTS (status=paid)
  ====================================== */
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

  /* ======================================
     ✅ MRR (Current month paid revenue)
  ====================================== */
  $mrrStmt = $conn->query("
    SELECT SUM(amount) as revenue
    FROM payments
    WHERE status='paid'
      AND DATE_FORMAT(paid_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
  ");
  $mrr = (float)($mrrStmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0);

  /* ======================================
     ✅ Recent Payments (Latest 8)
  ====================================== */
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

  echo json_encode([
    "status" => true,
    "data" => [
      "total_gyms" => $totalGyms,

      // ✅ includes paid + trial gyms (running gyms)
      "active_gyms" => $activeGyms,

      "total_users" => $totalUsers,
      "latest_gyms" => $latestGyms,

      "mrr" => $mrr,
      "revenue_chart" => $chart,

      // ✅ Trial/Active/Suspended/Inactive properly separate
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
