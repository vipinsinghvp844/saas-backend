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

  $systemEmail = "admin@platform.com";

  /* ===============================
      ✅ SUMMARY
  =============================== */

  // ✅ Total gyms (exclude system gym)
  $stmt = $conn->prepare("SELECT COUNT(*) as total FROM gyms WHERE email != :sysEmail");
  $stmt->execute([":sysEmail" => $systemEmail]);
  $totalGyms = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  // ✅ Total members
  $stmt = $conn->query("SELECT COUNT(*) as total FROM members");
  $totalMembers = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  // ✅ Paid payments count
  $stmt = $conn->query("SELECT COUNT(*) as total FROM payments WHERE status='paid'");
  $totalPaidPayments = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  // ✅ Total paid revenue
  $stmt = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM payments WHERE status='paid'");
  $totalRevenue = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  // ✅ revenue 30d
  $stmt = $conn->query("
    SELECT COALESCE(SUM(amount),0) as total
    FROM payments
    WHERE status='paid'
      AND paid_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
  ");
  $revenue30d = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  // ✅ new gyms today / 7d / 30d
  $stmt = $conn->prepare("
    SELECT
      SUM(DATE(created_at)=CURDATE()) as today,
      SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as last7,
      SUM(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as last30
    FROM gyms
    WHERE email != :sysEmail
  ");
  $stmt->execute([":sysEmail" => $systemEmail]);
  $gymNew = $stmt->fetch(PDO::FETCH_ASSOC);

  // ✅ new members today / 7d / 30d
  $stmt = $conn->query("
    SELECT
      SUM(DATE(created_at)=CURDATE()) as today,
      SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as last7,
      SUM(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as last30
    FROM members
  ");
  $memberNew = $stmt->fetch(PDO::FETCH_ASSOC);

  /* ===============================
      ✅ DAILY CHART (Last 14 Days)
  =============================== */

  $days = 14;
  $startDate = date("Y-m-d", strtotime("-" . ($days - 1) . " days"));

  // ✅ gyms daily
  $stmt = $conn->prepare("
    SELECT DATE(created_at) as d, COUNT(*) as total
    FROM gyms
    WHERE email != :sysEmail
      AND created_at >= :startDate
    GROUP BY d
    ORDER BY d ASC
  ");
  $stmt->execute([
    ":sysEmail" => $systemEmail,
    ":startDate" => $startDate
  ]);
  $gymRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $gymMap = [];
  foreach ($gymRows as $r) {
    $gymMap[$r['d']] = (int)$r['total'];
  }

  // ✅ members daily
  $stmt = $conn->prepare("
    SELECT DATE(created_at) as d, COUNT(*) as total
    FROM members
    WHERE created_at >= :startDate
    GROUP BY d
    ORDER BY d ASC
  ");
  $stmt->execute([":startDate" => $startDate]);
  $memberRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $memberMap = [];
  foreach ($memberRows as $r) {
    $memberMap[$r['d']] = (int)$r['total'];
  }

  // ✅ merge for frontend chart
  $dailyChart = [];
  for ($i = $days - 1; $i >= 0; $i--) {
    $d = date("Y-m-d", strtotime("-$i days"));
    $dailyChart[] = [
      "date" => date("d M", strtotime($d)),
      "raw" => $d,
      "newGyms" => $gymMap[$d] ?? 0,
      "newMembers" => $memberMap[$d] ?? 0,
    ];
  }

  echo json_encode([
    "status" => true,
    "data" => [
      "summary" => [
        "total_gyms" => $totalGyms,
        "total_members" => $totalMembers,
        "total_paid_payments" => $totalPaidPayments,
        "total_revenue" => $totalRevenue,
        "revenue_30d" => $revenue30d,

        "new_gyms_today" => (int)($gymNew['today'] ?? 0),
        "new_gyms_7d" => (int)($gymNew['last7'] ?? 0),
        "new_gyms_30d" => (int)($gymNew['last30'] ?? 0),

        "new_members_today" => (int)($memberNew['today'] ?? 0),
        "new_members_7d" => (int)($memberNew['last7'] ?? 0),
        "new_members_30d" => (int)($memberNew['last30'] ?? 0),
      ],
      "daily_chart" => $dailyChart
    ]
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load usage reports",
    "error" => $e->getMessage()
  ]);
  exit;
}
