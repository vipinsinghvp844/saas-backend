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
       📅 LAST 6 MONTHS (YYYY-MM)
    ========================== */
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $months[] = date("Y-m", strtotime("-$i months"));
    }

    /* ==========================
       👥 MEMBERS JOINED PER MONTH
    ========================== */
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') AS ym,
            COUNT(*) AS total
        FROM members
        WHERE gym_id = :gym_id
        GROUP BY ym
    ");
    $stmt->execute([":gym_id" => $gymId]);
    $memberRows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $membersChart = [];
    foreach ($months as $m) {
        $membersChart[] = [
            "month" => $m,
            "count" => (int)($memberRows[$m] ?? 0)
        ];
    }

    /* ==========================
       💰 REVENUE PER MONTH
       (member_payments)
    ========================== */
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(paid_at, '%Y-%m') AS ym,
            SUM(amount) AS total
        FROM member_payments
        WHERE gym_id = :gym_id
          AND status = 'paid'
          AND paid_at IS NOT NULL
        GROUP BY ym
    ");
    $stmt->execute([":gym_id" => $gymId]);
    $revenueRows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $revenueChart = [];
    foreach ($months as $m) {
        $revenueChart[] = [
            "month"  => $m,
            "amount" => (float)($revenueRows[$m] ?? 0)
        ];
    }

    /* ==========================
       📦 RESPONSE
    ========================== */
    echo json_encode([
        "status" => true,
        "data" => [
            "members" => $membersChart,
            "revenue" => $revenueChart
        ]
    ]);
    exit;

} catch (Exception $e) {

    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Failed to load dashboard chart",
        "error" => $e->getMessage()
    ]);
    exit;
}
