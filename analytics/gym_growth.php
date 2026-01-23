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

  /* ✅ Exclude system gym */
  $systemEmail = "admin@platform.com";

  /* ✅ Filter months */
  $months = (int)($_GET['months'] ?? 12);
  if ($months < 1) $months = 12;
  if ($months > 36) $months = 36;

  /* ✅ Range start date */
  $startDate = date("Y-m-01", strtotime("-" . ($months - 1) . " months"));

  /* ===================================================
      ✅ SUMMARY KPIs
      - new_gyms (created in range)
      - approved_gyms (requests approved in range)
      - trial_gyms (subscription trial started in range)
      - active_paid_gyms (current subscription active)
  =================================================== */

  // 1) new gyms
  $stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM gyms g
    WHERE g.email != :sysEmail
      AND g.created_at >= :startDate
  ");
  $stmt->execute([
    ":sysEmail" => $systemEmail,
    ":startDate" => $startDate
  ]);
  $newGyms = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  // 2) approved gyms (based on gym_requests)
  $stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM gym_requests gr
    WHERE LOWER(gr.status) = 'approved'
      AND gr.approved_at IS NOT NULL
      AND gr.approved_at >= :startDate
  ");
  $stmt->execute([
    ":startDate" => $startDate
  ]);
  $approvedGyms = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  // 3) trial gyms started (subscriptions created as trial)
  $stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM gym_subscriptions gs
    INNER JOIN gyms g ON g.id = gs.gym_id
    WHERE g.email != :sysEmail
      AND LOWER(gs.status) = 'trial'
      AND gs.start_date >= :startDate
  ");
  $stmt->execute([
    ":sysEmail" => $systemEmail,
    ":startDate" => $startDate
  ]);
  $trialGyms = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  // 4) active paid gyms (subscriptions status active)
  $stmt = $conn->prepare("
    SELECT COUNT(DISTINCT gs.gym_id) as total
    FROM gym_subscriptions gs
    INNER JOIN gyms g ON g.id = gs.gym_id
    WHERE g.email != :sysEmail
      AND LOWER(gs.status) = 'active'
  ");
  $stmt->execute([
    ":sysEmail" => $systemEmail
  ]);
  $activePaidGyms = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  /* ===================================================
      ✅ Monthly Chart (gyms registrations)
      group by year-month
  =================================================== */
  $stmt = $conn->prepare("
    SELECT
      DATE_FORMAT(g.created_at, '%Y-%m') AS ym,
      COUNT(*) AS total
    FROM gyms g
    WHERE g.email != :sysEmail
      AND g.created_at >= :startDate
    GROUP BY ym
    ORDER BY ym ASC
  ");
  $stmt->execute([
    ":sysEmail" => $systemEmail,
    ":startDate" => $startDate
  ]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $map = [];
  foreach ($rows as $r) {
    $map[$r['ym']] = (int)$r['total'];
  }

  $monthly_chart = [];
  for ($i = $months - 1; $i >= 0; $i--) {
    $ym = date("Y-m", strtotime("-$i months"));
    $monthly_chart[] = [
      "month" => date("M Y", strtotime($ym . "-01")),
      "ym" => $ym,
      "gyms" => $map[$ym] ?? 0
    ];
  }

  /* ===================================================
      ✅ Status Chart (trial / active / inactive / suspended)
      based on gym + latest subscription
  =================================================== */

  $stmt = $conn->prepare("
    SELECT
      CASE
        WHEN LOWER(g.status)='suspended' THEN 'Suspended'
        WHEN latest_sub.status='trial' THEN 'Trial'
        WHEN latest_sub.status='active' THEN 'Active'
        ELSE 'Inactive'
      END AS label,
      COUNT(*) AS total
    FROM gyms g
    LEFT JOIN (
      SELECT gs1.gym_id, LOWER(gs1.status) AS status
      FROM gym_subscriptions gs1
      INNER JOIN (
        SELECT gym_id, MAX(id) AS max_id
        FROM gym_subscriptions
        GROUP BY gym_id
      ) x ON x.gym_id = gs1.gym_id AND x.max_id = gs1.id
    ) latest_sub ON latest_sub.gym_id = g.id
    WHERE g.email != :sysEmail
    GROUP BY label
  ");
  $stmt->execute([":sysEmail" => $systemEmail]);
  $statusRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $statusMap = [
    "Active" => 0,
    "Trial" => 0,
    "Suspended" => 0,
    "Inactive" => 0,
  ];

  foreach ($statusRows as $row) {
    $label = $row['label'];
    if (isset($statusMap[$label])) {
      $statusMap[$label] = (int)$row['total'];
    }
  }

  $status_chart = [
    ["name" => "Active", "value" => $statusMap["Active"]],
    ["name" => "Trial", "value" => $statusMap["Trial"]],
    ["name" => "Suspended", "value" => $statusMap["Suspended"]],
    ["name" => "Inactive", "value" => $statusMap["Inactive"]],
  ];

  echo json_encode([
    "status" => true,
    "data" => [
      "summary" => [
        "new_gyms" => $newGyms,
        "approved_gyms" => $approvedGyms,
        "trial_gyms" => $trialGyms,
        "active_paid_gyms" => $activePaidGyms,
      ],
      "monthly_chart" => $monthly_chart,
      "status_chart" => $status_chart,
    ]
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load gym growth analytics",
    "error" => $e->getMessage()
  ]);
  exit;
}
