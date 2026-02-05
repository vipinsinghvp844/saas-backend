<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");
require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

try {
    $auth = authenticate();
    requireRole(['gym_admin']);

    $gymId = (int)$auth['gym_id'];
    $db = new Database();
    $conn = $db->connect();

    // total
    $total = $conn->prepare("
        SELECT COUNT(*) FROM members WHERE gym_id = :gym_id
    ");
    $total->execute([":gym_id" => $gymId]);
    $totalMembers = (int)$total->fetchColumn();

    // active
    $active = $conn->prepare("
        SELECT COUNT(*) FROM members
        WHERE gym_id = :gym_id AND status = 'active'
    ");
    $active->execute([":gym_id" => $gymId]);
    $activeMembers = (int)$active->fetchColumn();

    // new this month
    $new = $conn->prepare("
        SELECT COUNT(*) FROM members
        WHERE gym_id = :gym_id
        AND MONTH(created_at) = MONTH(CURRENT_DATE())
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $new->execute([":gym_id" => $gymId]);
    $newThisMonth = (int)$new->fetchColumn();

    // expiring soon
    $expiring = $conn->prepare("
        SELECT COUNT(*)
        FROM member_subscriptions
        WHERE gym_id = :gym_id
        AND status = 'active'
        AND end_date BETWEEN CURRENT_DATE()
        AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
    ");
    $expiring->execute([":gym_id" => $gymId]);
    $expiringSoon = (int)$expiring->fetchColumn();

    echo json_encode([
        "status" => true,
        "data" => [
            "total_members" => $totalMembers,
            "active_members" => $activeMembers,
            "new_this_month" => $newThisMonth,
            "expiring_soon" => $expiringSoon
        ]
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Failed to load member stats"
    ]);
    exit;
}
