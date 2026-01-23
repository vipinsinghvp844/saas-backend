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

  /* ✅ EXCLUDE SYSTEM GYM */
  $systemGymEmail = "admin@platform.com";

  /* ✅ Filters */
  $search = trim($_GET['search'] ?? '');
  $status = strtolower(trim($_GET['status'] ?? 'all'));   // active/trial/cancelled
  $plan   = strtolower(trim($_GET['plan'] ?? 'all'));     // free/basic/pro/enterprise

  /* ✅ Pagination */
  $page  = (int)($_GET['page'] ?? 1);
  $limit = (int)($_GET['limit'] ?? 10);

  if ($page < 1) $page = 1;
  if ($limit < 1) $limit = 10;
  if ($limit > 100) $limit = 100;

  $offset = ($page - 1) * $limit;

  $where = [];
  $params = [];

  /* ✅ exclude system gym */
  $where[] = "g.email != :sysEmail";
  $params[":sysEmail"] = $systemGymEmail;

  /* ✅ Search filter */
  if ($search !== '') {
    $where[] = "(
      g.name LIKE :q
      OR g.email LIKE :q
      OR mp.name LIKE :q
      OR mp.slug LIKE :q
      OR u.email LIKE :q
    )";
    $params[":q"] = "%" . $search . "%";
  }

  /* ✅ Status filter (subscription status) */
  $allowedStatus = ["active", "trial", "cancelled"];
  if ($status !== "all" && in_array($status, $allowedStatus, true)) {
    $where[] = "LOWER(gs.status) = :status";
    $params[":status"] = $status;
  }

  /* ✅ Plan filter */
  $allowedPlan = ["free", "basic", "pro", "enterprise"];
  if ($plan !== "all" && in_array($plan, $allowedPlan, true)) {
    $where[] = "LOWER(mp.slug) = :plan";
    $params[":plan"] = $plan;
  }

  $whereSql = "WHERE " . implode(" AND ", $where);

  /* ======================================================
     ✅ COUNT QUERY (for pagination)
  ====================================================== */
  $countSql = "
    SELECT COUNT(*) as total
    FROM gym_subscriptions gs
    LEFT JOIN gyms g ON g.id = gs.gym_id
    LEFT JOIN membership_plans mp ON mp.id = gs.plan_id
    LEFT JOIN users u 
      ON u.gym_id = g.id AND u.role='gym_admin'
    $whereSql
  ";

  $countStmt = $conn->prepare($countSql);
  $countStmt->execute($params);
  $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
  $totalPages = (int)ceil($total / $limit);

  /* ======================================================
     ✅ MAIN LIST QUERY
  ====================================================== */
  $sql = "
    SELECT
      gs.id,
      gs.gym_id,
      gs.plan_id,
      LOWER(gs.status) AS status,
      gs.start_date,
      gs.end_date,
      gs.trial_days,
      gs.trial_ends_at,
      gs.created_at,

      g.name AS gym_name,
      g.email AS gym_email,

      CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS owner_name,
      u.email AS owner_email,

      mp.name AS plan_name,
      mp.slug AS plan_slug,
      mp.billing_cycle AS plan_cycle,
      mp.price AS amount,
      mp.currency AS currency

    FROM gym_subscriptions gs
    LEFT JOIN gyms g ON g.id = gs.gym_id
    LEFT JOIN membership_plans mp ON mp.id = gs.plan_id
    LEFT JOIN users u 
      ON u.gym_id = g.id AND u.role='gym_admin'

    $whereSql

    ORDER BY gs.id DESC
    LIMIT $limit OFFSET $offset
  ";

  $stmt = $conn->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  /* ✅ next billing date logic */
  foreach ($rows as &$r) {
    $st = strtolower(trim((string)$r['status']));

    // ✅ next billing:
    // - trial => trial_ends_at
    // - active => end_date (if exists)
    // - cancelled => null
    if ($st === "trial") {
      $r["next_billing_at"] = $r["trial_ends_at"] ?? null;
    } else if ($st === "active") {
      $r["next_billing_at"] = $r["end_date"] ?? null;
    } else {
      $r["next_billing_at"] = null;
    }

    // ✅ fallback currency
    if (empty($r["currency"])) $r["currency"] = "INR";
  }

  /* ======================================================
     ✅ STATS (total/active/trial/cancelled)
     Note: same filter applied except pagination
  ====================================================== */
  $statsSql = "
    SELECT
      COUNT(*) AS total,
      SUM(LOWER(gs.status)='active') AS active,
      SUM(LOWER(gs.status)='trial') AS trial,
      SUM(LOWER(gs.status)='cancelled') AS cancelled
    FROM gym_subscriptions gs
    LEFT JOIN gyms g ON g.id = gs.gym_id
    LEFT JOIN membership_plans mp ON mp.id = gs.plan_id
    LEFT JOIN users u 
      ON u.gym_id = g.id AND u.role='gym_admin'
    $whereSql
  ";

  $statsStmt = $conn->prepare($statsSql);
  $statsStmt->execute($params);
  $statsRow = $statsStmt->fetch(PDO::FETCH_ASSOC);

  $stats = [
    "total" => (int)($statsRow["total"] ?? 0),
    "active" => (int)($statsRow["active"] ?? 0),
    "trial" => (int)($statsRow["trial"] ?? 0),
    "cancelled" => (int)($statsRow["cancelled"] ?? 0),
  ];

  echo json_encode([
    "status" => true,
    "data" => $rows,
    "pagination" => [
      "page" => $page,
      "limit" => $limit,
      "total" => $total,
      "totalPages" => $totalPages
    ],
    "stats" => $stats
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load subscriptions",
    "error" => $e->getMessage()
  ]);
  exit;
}
