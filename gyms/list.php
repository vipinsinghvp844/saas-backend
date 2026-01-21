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

    /* ✅ Filters (GET Params) */
    $search = trim($_GET['search'] ?? '');
    $status = strtolower(trim($_GET['status'] ?? 'all'));
    $plan   = strtolower(trim($_GET['plan'] ?? 'all'));

    $where = [];
    $params = [];

    /* 🔍 Search */
    if (!empty($search)) {
        $where[] = "(
            g.name LIKE :search
            OR g.slug LIKE :search
            OR u.email LIKE :search
            OR CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) LIKE :search
        )";
        $params[":search"] = "%" . $search . "%";
    }

    /* ✅ Status filter */
    $allowedStatus = ["active", "trial", "suspended", "inactive"];
    if ($status !== "all" && in_array($status, $allowedStatus, true)) {
        $where[] = "LOWER(g.status) = :status";
        $params[":status"] = $status;
    }

    /* ✅ Plan filter (NOW from subscriptions) */
    $allowedPlan = ["free", "basic", "pro", "enterprise"];
    if ($plan !== "all" && in_array($plan, $allowedPlan, true)) {
        // ✅ if plan_id exists -> mp.slug else fallback gs.plan
        $where[] = "(LOWER(COALESCE(mp.slug, gs.plan)) = :plan)";
        $params[":plan"] = $plan;
    }

    $whereSql = "";
    if (!empty($where)) {
        $whereSql = "WHERE " . implode(" AND ", $where);
    }

    /* ✅ MAIN QUERY */
    $sql = "
        SELECT
            g.id,
            g.name,
            g.slug,
            g.logo,
            g.email AS gymEmail,
            g.phone,
            g.address,
            g.city,
            g.state,
            g.zip,

            LOWER(g.status) AS status,

            CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS ownerName,
            u.email AS ownerEmail,

            COUNT(DISTINCT m.id) AS members,
            0 AS revenue,

            -- ✅ Current Plan from active subscription
            gs.id AS subscription_id,
            gs.status AS subscription_status,
            gs.start_date,
            gs.end_date,

            COALESCE(mp.id, gs.plan_id) AS current_plan_id,
            COALESCE(mp.slug, gs.plan) AS current_plan_slug,
            mp.name AS current_plan_name,
            mp.price AS current_plan_price,
            mp.billing_cycle AS current_plan_cycle

        FROM gyms g

        LEFT JOIN users u
            ON u.gym_id = g.id
            AND u.role = 'gym_admin'

        LEFT JOIN members m
            ON m.gym_id = g.id

        LEFT JOIN gym_subscriptions gs
            ON gs.gym_id = g.id
            AND gs.status = 'active'

        LEFT JOIN membership_plans mp
            ON mp.id = gs.plan_id

        $whereSql

        GROUP BY g.id
        ORDER BY g.id DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $gyms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => true,
        "data"   => $gyms
    ]);

} catch (Exception $e) {

    http_response_code(500);
    echo json_encode([
        "status"  => false,
        "message" => "Server error",
        "error"   => $e->getMessage() // dev only
    ]);
}
