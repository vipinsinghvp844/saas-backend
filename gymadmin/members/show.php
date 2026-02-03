<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

try {

    /* ==========================
       🔐 AUTH
    ========================== */
    $auth = authenticate();
    $GLOBALS['auth_user'] = $auth;
    requireRole(['gym_admin']);

    $gymId    = (int)$auth['gym_id'];
    $memberId = (int)($_GET['id'] ?? 0);

    if ($memberId <= 0) {
        throw new Exception("Invalid member id");
    }

    $db   = new Database();
    $conn = $db->connect();

    /* ==========================
       👤 MEMBER PROFILE
    ========================== */
    $stmt = $conn->prepare("
        SELECT
            m.id AS member_id,
            m.status,
            m.join_date,
            m.source,
            m.gender,
            m.dob,

            u.id AS user_id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone
        FROM members m
        INNER JOIN users u ON u.id = m.user_id
        WHERE m.id = :member_id
          AND m.gym_id = :gym_id
        LIMIT 1
    ");
    $stmt->execute([
        ":member_id" => $memberId,
        ":gym_id"    => $gymId
    ]);

    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        throw new Exception("Member not found");
    }

    /* ==========================
       📦 ACTIVE SUBSCRIPTION
    ========================== */
    $stmt = $conn->prepare("
        SELECT
            ms.id,
            mp.name AS plan_name,
            mp.duration_days,
            ms.start_date,
            ms.end_date,
            ms.status,
            DATEDIFF(ms.end_date, CURRENT_DATE()) AS days_left
        FROM member_subscriptions ms
        INNER JOIN membership_plans mp ON mp.id = ms.plan_id
        WHERE ms.member_id = :member_id
          AND ms.gym_id = :gym_id
          AND ms.status = 'active'
        ORDER BY ms.id DESC
        LIMIT 1
    ");
    $stmt->execute([
        ":member_id" => $memberId,
        ":gym_id"    => $gymId
    ]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    /* ==========================
       💰 PAYMENT SUMMARY
    ========================== */
    $stmt = $conn->prepare("
        SELECT
            COALESCE(SUM(amount),0) AS total_paid,
            MAX(paid_at) AS last_payment_date
        FROM member_payments
        WHERE member_id = :member_id
          AND gym_id = :gym_id
          AND status = 'paid'
    ");
    $stmt->execute([
        ":member_id" => $memberId,
        ":gym_id"    => $gymId
    ]);
    $paymentSummary = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("
        SELECT
            amount,
            payment_method,
            paid_at
        FROM member_payments
        WHERE member_id = :member_id
          AND gym_id = :gym_id
          AND status = 'paid'
        ORDER BY paid_at DESC
        LIMIT 1
    ");
    $stmt->execute([
        ":member_id" => $memberId,
        ":gym_id"    => $gymId
    ]);
    $lastPayment = $stmt->fetch(PDO::FETCH_ASSOC);

    /* ==========================
       📊 ATTENDANCE SUMMARY
    ========================== */
    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_present,
            MAX(attendance_date) AS last_visit
        FROM member_attendance
        WHERE member_id = :member_id
          AND gym_id = :gym_id
          AND status = 'present'
    ");
    $stmt->execute([
        ":member_id" => $memberId,
        ":gym_id"    => $gymId
    ]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

    /* ==========================
       📦 RESPONSE
    ========================== */
    echo json_encode([
        "status" => true,
        "data" => [
            "member" => [
                "id"         => (int)$member['member_id'],
                "name"       => trim($member['first_name'] . " " . $member['last_name']),
                "email"      => $member['email'],
                "phone"      => $member['phone'],
                "status"     => $member['status'],
                "joined_at"  => $member['join_date'],
                "source"     => $member['source'],
                "gender"     => $member['gender'],
                "dob"        => $member['dob']
            ],

            "subscription" => $subscription ? [
                "plan_name"  => $subscription['plan_name'],
                "start_date" => $subscription['start_date'],
                "end_date"   => $subscription['end_date'],
                "days_left"  => (int)$subscription['days_left'],
                "status"     => $subscription['status']
            ] : null,

            "payments" => [
                "total_paid" => (float)$paymentSummary['total_paid'],
                "last_payment" => $lastPayment ?: null
            ],

            "attendance" => [
                "total_present" => (int)$attendance['total_present'],
                "last_visit"    => $attendance['last_visit']
            ]
        ]
    ]);
    exit;

} catch (Exception $e) {

    http_response_code(400);
    echo json_encode([
        "status"  => false,
        "message" => $e->getMessage()
    ]);
    exit;
}
