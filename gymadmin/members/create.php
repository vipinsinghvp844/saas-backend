<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";
require_once "../../helpers/auditLog.php";

function generatePassword($len = 8) {
    $chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    return substr(str_shuffle($chars), 0, $len);
}

try {

    /* ==========================
       AUTH
    ========================== */
    $auth = authenticate();
    $GLOBALS['auth_user'] = $auth;
    requireRole(['gym_admin']);

    $gymId = (int)$auth['gym_id'];

    /* ==========================
       INPUT
    ========================== */
    $data = json_decode(file_get_contents("php://input"), true);

    $firstName = trim($data['first_name'] ?? '');
    $lastName  = trim($data['last_name'] ?? '');
    $email     = trim($data['email'] ?? '');
    $phone     = trim($data['phone'] ?? '');
    $gender    = $data['gender'] ?? null;
    $dob       = $data['dob'] ?? null;

    $planId    = (int)($data['plan_id'] ?? 0);
    $startDate = $data['start_date'] ?? date('Y-m-d');

    $paymentAmount = $data['payment_amount'] ?? null;
    $paymentMethod = $data['payment_method'] ?? null;

    if ($firstName === '' || $phone === '' || $planId === 0) {
        throw new Exception("Required fields missing");
    }

    /* ==========================
       DB
    ========================== */
    $db = new Database();
    $conn = $db->connect();
    $conn->beginTransaction();

    /* ==========================
       DUPLICATE CHECKS
    ========================== */

    // Phone unique per gym
    $stmt = $conn->prepare("
        SELECT id FROM users
        WHERE gym_id = :gym_id AND phone = :phone
        LIMIT 1
    ");
    $stmt->execute([
        ":gym_id" => $gymId,
        ":phone"  => $phone
    ]);
    if ($stmt->fetch()) {
        throw new Exception("Member with this phone already exists");
    }

    // Email unique (only if provided)
    if ($email !== '') {
        $stmt = $conn->prepare("
            SELECT id FROM users
            WHERE gym_id = :gym_id AND email = :email
            LIMIT 1
        ");
        $stmt->execute([
            ":gym_id" => $gymId,
            ":email"  => $email
        ]);
        if ($stmt->fetch()) {
            throw new Exception("Member with this email already exists");
        }
    }

    /* ==========================
       USER (MEMBER LOGIN)
    ========================== */
    $plainPassword = generatePassword();

    $stmt = $conn->prepare("
        INSERT INTO users (
            gym_id,
            first_name,
            last_name,
            email,
            phone,
            password,
            role,
            status,
            force_password_change,
            created_at
        ) VALUES (
            :gym_id,
            :first_name,
            :last_name,
            :email,
            :phone,
            :password,
            'member',
            'active',
            1,
            NOW()
        )
    ");
    $stmt->execute([
        ":gym_id"     => $gymId,
        ":first_name" => $firstName,
        ":last_name"  => $lastName,
        ":email"      => $email ?: null,
        ":phone"      => $phone,
        ":password"   => password_hash($plainPassword, PASSWORD_DEFAULT),
    ]);

    $userId = $conn->lastInsertId();

    /* ==========================
       MEMBER PROFILE
    ========================== */
    $stmt = $conn->prepare("
        INSERT INTO members (
            gym_id,
            user_id,
            status,
            join_date,
            gender,
            created_at
        ) VALUES (
            :gym_id,
            :user_id,
            'active',
            CURRENT_DATE(),
            :gender,
            NOW()
        )
    ");
    $stmt->execute([
        ":gym_id" => $gymId,
        ":user_id"=> $userId,
        ":gender" => $gender,
    ]);

    $memberId = $conn->lastInsertId();

    /* ==========================
       MEMBERSHIP PLAN
    ========================== */
    $stmt = $conn->prepare("
        SELECT duration_days
        FROM gym_membership_plans
        WHERE id = :id AND gym_id = :gym_id
        LIMIT 1
    ");
    $stmt->execute([
        ":id"     => $planId,
        ":gym_id"=> $gymId
    ]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        throw new Exception("Invalid membership plan");
    }

    $endDate = date('Y-m-d', strtotime($startDate . " +{$plan['duration_days']} days"));

    $stmt = $conn->prepare("
        INSERT INTO member_subscriptions (
            gym_id,
            member_id,
            plan_id,
            start_date,
            end_date,
            status,
            created_at
        ) VALUES (
            :gym_id,
            :member_id,
            :plan_id,
            :start_date,
            :end_date,
            'active',
            NOW()
        )
    ");
    $stmt->execute([
        ":gym_id"    => $gymId,
        ":member_id" => $memberId,
        ":plan_id"   => $planId,
        ":start_date"=> $startDate,
        ":end_date"  => $endDate
    ]);

    /* ==========================
       PAYMENT (OPTIONAL)
    ========================== */
    if ($paymentAmount && $paymentAmount > 0) {

        $allowedMethods = ['cash','upi','card','bank','stripe','razorpay'];
        if ($paymentMethod && !in_array($paymentMethod, $allowedMethods)) {
            throw new Exception("Invalid payment method");
        }

        $stmt = $conn->prepare("
            INSERT INTO member_payments (
                gym_id,
                member_id,
                user_id,
                payment_for,
                amount,
                payment_method,
                status,
                paid_at,
                created_at
            ) VALUES (
                :gym_id,
                :member_id,
                :user_id,
                'membership',
                :amount,
                :method,
                'paid',
                NOW(),
                NOW()
            )
        ");
        $stmt->execute([
            ":gym_id"    => $gymId,
            ":member_id" => $memberId,
            ":user_id"   => $userId,
            ":amount"    => $paymentAmount,
            ":method"    => $paymentMethod
        ]);
    }

    /* ==========================
       AUDIT LOG
    ========================== */
    logAudit([
        "action"      => "created",
        "module"      => "member",
        "target_type" => "member",
        "target_id"   => $memberId,
        "description" => "Member added by gym admin"
    ]);

    $conn->commit();

    echo json_encode([
        "status" => true,
        "message" => "Member added successfully",
        "data" => [
            "member_id" => $memberId,
            "user_id"   => $userId,
            "temp_password" => $plainPassword // optional: remove later
        ]
    ]);
    exit;

} catch (Exception $e) {

    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    http_response_code(400);
    echo json_encode([
        "status"  => false,
        "message" => $e->getMessage()
    ]);
    exit;
}
