<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

try {

    /* ==========================
       🔐 AUTH
    ========================== */
    $auth = authenticate();
    $GLOBALS['auth_user'] = $auth;
    requireRole(['gym_admin']);

    $gymId = (int)$auth['gym_id'];

    $db = new Database();
    $conn = $db->connect();

    /* ==========================
       📊 TOP STATS
    ========================== */

    // Total Members
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM members m
        INNER JOIN users u ON u.id = m.user_id
        WHERE m.gym_id = :gym_id
          AND u.role = 'member'
    ");
    $stmt->execute([":gym_id" => $gymId]);
    $totalMembers = (int)$stmt->fetchColumn();

    // Active Members
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM members m
        INNER JOIN users u ON u.id = m.user_id
        WHERE m.gym_id = :gym_id
          AND u.role = 'member'
          AND m.status = 'active'
    ");
    $stmt->execute([":gym_id" => $gymId]);
    $activeMembers = (int)$stmt->fetchColumn();

    // Trainers
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE gym_id = :gym_id
          AND role = 'trainer'
          AND status = 'active'
    ");
    $stmt->execute([":gym_id" => $gymId]);
    $totalTrainers = (int)$stmt->fetchColumn();

    // Staff
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE gym_id = :gym_id
          AND role = 'staff'
          AND status = 'active'
    ");
    $stmt->execute([":gym_id" => $gymId]);
    $totalStaff = (int)$stmt->fetchColumn();

    // Gym Pages
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM pages
        WHERE site_type = 'gym'
          AND gym_id = :gym_id
    ");
    $stmt->execute([":gym_id" => $gymId]);
    $totalPages = (int)$stmt->fetchColumn();

    /* ==========================
       💰 REVENUE (GYM SIDE)
       (Members payments only)
    ========================== */
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(p.amount), 0)
        FROM payments p
        WHERE p.gym_id = :gym_id
          AND p.status = 'paid'
          AND MONTH(p.created_at) = MONTH(CURRENT_DATE())
          AND YEAR(p.created_at) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([":gym_id" => $gymId]);
    $monthlyRevenue = (float)$stmt->fetchColumn();

    /* ==========================
       👤 RECENT MEMBERS
    ========================== */
    $stmt = $conn->prepare("
        SELECT
            u.first_name,
            u.last_name,
            u.email,
            m.status,
            m.created_at
        FROM members m
        INNER JOIN users u ON u.id = m.user_id
        WHERE m.gym_id = :gym_id
          AND u.role = 'member'
        ORDER BY m.id DESC
        LIMIT 5
    ");
    $stmt->execute([":gym_id" => $gymId]);
    $recentMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ==========================
   📊 ATTENDANCE CHART (LAST 6 MONTHS)
========================== */
$attendanceChart = [];

$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(attendance_date, '%b') AS month,
        COUNT(*) AS total
    FROM member_attendance
    WHERE gym_id = :gym_id
      AND status = 'present'
      AND attendance_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(attendance_date), MONTH(attendance_date)
    ORDER BY attendance_date ASC
");
$stmt->execute([":gym_id" => $gymId]);

$attendanceChart = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================
   📈 MEMBERSHIP GROWTH (LAST 6 MONTHS)
========================== */
$memberGrowth = [];

$stmt = $conn->prepare("
    SELECT
        DATE_FORMAT(m.created_at, '%b') AS month,
        COUNT(*) AS total
    FROM members m
    INNER JOIN users u ON u.id = m.user_id
    WHERE m.gym_id = :gym_id
      AND u.role = 'member'
      AND m.created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(m.created_at), MONTH(m.created_at)
    ORDER BY m.created_at ASC
");
$stmt->execute([":gym_id" => $gymId]);

$memberGrowth = $stmt->fetchAll(PDO::FETCH_ASSOC);



    /* ==========================
       📦 RESPONSE
    ========================== */
    echo json_encode([
    "status" => true,
    "data" => [
        "stats" => [
            "total_members"   => $totalMembers,
            "active_members"  => $activeMembers,
            "total_trainers"  => $totalTrainers,
            "total_staff"     => $totalStaff,
            "total_pages"     => $totalPages,
            "monthly_revenue" => $monthlyRevenue
        ],

        "charts" => [
            "attendance"      => $attendanceChart,
            "member_growth"   => $memberGrowth   // ✅ NEW
        ],

        "analysis" => [
            "income_this_month"  => $monthlyRevenue,
            "expense_this_month" => 0
        ],

        "recent_members" => $recentMembers
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
