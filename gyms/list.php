<?php
// gyms/list.php

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

  /* ✅ exclude system gym */
  $systemGymEmail = "admin@platform.com";

  /* ✅ Filters */
  $search = trim($_GET['search'] ?? '');
  $status = strtolower(trim($_GET['status'] ?? 'all'));
  $plan   = strtolower(trim($_GET['plan'] ?? 'all'));

  /* ✅ Pagination */
  $page  = (int)($_GET['page'] ?? 1);
  $limit = (int)($_GET['limit'] ?? 10);

  if ($page < 1) $page = 1;
  if ($limit < 1) $limit = 10;
  if ($limit > 100) $limit = 100;

  $offset = ($page - 1) * $limit;

  $where = [];
  $params = [];

  /* ✅ always exclude system gym */
  $where[] = "g.email != :sysEmail";
  $params[":sysEmail"] = $systemGymEmail;

  /* 🔍 Search */
  if ($search !== '') {
    $where[] = "(
      g.name LIKE :search
      OR g.slug LIKE :search
      OR u.email LIKE :search
      OR CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) LIKE :search
    )";
    $params[":search"] = "%" . $search . "%";
  }

  /* ✅ Status filter (active/inactive/suspended/trial) */
  $allowedStatus = ["active", "trial", "suspended", "inactive"];
  if ($status !== "all" && in_array($status, $allowedStatus, true)) {

    if ($status === "trial") {
      $where[] = "LOWER(g.status) = 'active'
                 AND LOWER(COALESCE(g.billing_status,'trial')) = 'trial'";
    } else {
      $where[] = "LOWER(g.status) = :status";
      $params[":status"] = $status;
    }
  }

  /* ✅ Plan filter */
  $allowedPlan = ["free", "basic", "pro", "enterprise"];
  if ($plan !== "all" && in_array($plan, $allowedPlan, true)) {
    $where[] = "(LOWER(COALESCE(mp.slug, gs.plan)) = :plan)";
    $params[":plan"] = $plan;
  }

  $whereSql = "WHERE " . implode(" AND ", $where);

  /* ✅ TOTAL COUNT QUERY */
  $countSql = "
    SELECT COUNT(DISTINCT g.id) AS total
    FROM gyms g
    LEFT JOIN users u
      ON u.gym_id = g.id
      AND u.role = 'gym_admin'

    LEFT JOIN gym_subscriptions gs
      ON gs.id = (
        SELECT gs2.id
        FROM gym_subscriptions gs2
        WHERE gs2.gym_id = g.id
          AND gs2.status IN ('trial','active')
        ORDER BY gs2.id DESC
        LIMIT 1
      )

    LEFT JOIN membership_plans mp
      ON mp.id = gs.plan_id

    $whereSql
  ";

  $countStmt = $conn->prepare($countSql);
  $countStmt->execute($params);
  $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  $totalPages = (int)ceil($total / $limit);

  /* ✅ MAIN QUERY */
  $sql = "
    SELECT
      g.id,
      g.name,
      g.slug,
      g.logo,
      g.email AS gymEmail,

      LOWER(g.status) AS status,
      LOWER(COALESCE(g.billing_status,'trial')) AS billing_status,
      g.trial_ends_at,
      g.created_at,

      CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS ownerName,
      u.email AS ownerEmail,

      (
        SELECT COUNT(*)
        FROM members m
        WHERE m.gym_id = g.id
      ) AS members,

      0 AS revenue,

      gs.id AS subscription_id,
      gs.status AS subscription_status,
      gs.start_date,
      gs.end_date,
      gs.trial_days,
      gs.trial_ends_at AS subscription_trial_ends_at,

      mp.id AS current_plan_id,
      mp.slug AS current_plan_slug,
      mp.name AS current_plan_name,
      mp.price AS current_plan_price,
      mp.billing_cycle AS current_plan_cycle,
      mp.currency AS current_plan_currency

    FROM gyms g

    LEFT JOIN users u
      ON u.gym_id = g.id
      AND u.role = 'gym_admin'

    LEFT JOIN gym_subscriptions gs
      ON gs.id = (
        SELECT gs2.id
        FROM gym_subscriptions gs2
        WHERE gs2.gym_id = g.id
          AND gs2.status IN ('trial','active')
        ORDER BY gs2.id DESC
        LIMIT 1
      )

    LEFT JOIN membership_plans mp
      ON mp.id = gs.plan_id

    $whereSql
    ORDER BY g.id DESC
    LIMIT $limit OFFSET $offset
  ";

  $stmt = $conn->prepare($sql);
  $stmt->execute($params);

  $gyms = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    "status" => true,
    "data" => $gyms,
    "pagination" => [
      "page" => $page,
      "limit" => $limit,
      "total" => $total,
      "totalPages" => $totalPages
    ]
  ]);
  exit;

} catch (Exception $e) {

  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load gyms",
    "error" => $e->getMessage()
  ]);
  exit;
}
