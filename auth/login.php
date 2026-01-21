<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../config/jwt.php"; // createJWT()

try {

    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['email']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Email and password required"
        ]);
        exit;
    }

    $email = trim($data['email']);
    $password = $data['password'];

    $db = new Database();
    $conn = $db->connect();

    /* ==========================
       ✅ GET USER
    ========================== */
    $stmt = $conn->prepare("
        SELECT 
            id,
            gym_id,
            first_name,
            last_name,
            email,
            phone,
            password,
            role,
            status,
            force_password_change
        FROM users
        WHERE email = :email
        LIMIT 1
    ");
    $stmt->execute([":email" => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    /* ✅ INVALID CREDENTIALS */
    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode([
            "status" => false,
            "message" => "Invalid credentials"
        ]);
        exit;
    } 

    /* ✅ USER STATUS CHECK */
    if (strtolower($user['status']) !== 'active') {
        http_response_code(403);
        echo json_encode([
            "status" => false,
            "message" => "Account blocked"
        ]);
        exit;
    }

    $gymData = null;

    /* ==========================
       🏢 GYM CHECK (ONLY FOR gym_admin)
    ========================== */
    if ($user['role'] === 'gym_admin') {

        $gymStmt = $conn->prepare("
            SELECT 
                id,
                name,
                slug,
                status,
                billing_status,
                trial_ends_at
            FROM gyms
            WHERE id = :gym_id
            LIMIT 1
        ");
        $gymStmt->execute([":gym_id" => (int)$user['gym_id']]);
        $gym = $gymStmt->fetch(PDO::FETCH_ASSOC);

        if (!$gym) {
            http_response_code(403);
            echo json_encode([
                "status" => false,
                "message" => "Gym not found"
            ]);
            exit;
        }

        // ✅ PRODUCTION READY: gyms.status must be active
        $gymStatus = strtolower(trim($gym['status'] ?? 'inactive'));
        if ($gymStatus !== 'active') {
            http_response_code(403);
            echo json_encode([
                "status" => false,
                "message" => "Your gym is inactive. Please contact support."
            ]);
            exit;
        }

        // ✅ Billing status logic
        $billingStatus = strtolower(trim($gym['billing_status'] ?? 'trial'));

        $trialExpired = false;
        if (!empty($gym['trial_ends_at'])) {
            $trialEndsTs = strtotime($gym['trial_ends_at']);
            if ($trialEndsTs && $trialEndsTs < time()) {
                $trialExpired = true;
            }
        }

        // ✅ If trial expired & not paid => BLOCK + send billing required
        if ($billingStatus !== 'paid' && $trialExpired) {
            http_response_code(403);
            echo json_encode([
                "status" => false,
                "message" => "Billing required. Please complete payment to continue.",
                "code" => "BILLING_REQUIRED",
                "gym" => [
                    "id" => (int)$gym['id'],
                    "name" => $gym['name'],
                    "slug" => $gym['slug'],
                    "status" => $gym['status'],
                    "billing_status" => $gym['billing_status'],
                    "trial_ends_at" => $gym['trial_ends_at']
                ]
            ]);
            exit;
        }

        // ✅ optional subscription info for frontend usage
        $subStmt = $conn->prepare("
            SELECT 
                gs.id,
                gs.status,
                gs.plan_id,
                gs.plan,
                gs.trial_days,
                gs.trial_ends_at,
                gs.start_date,
                gs.end_date,
                mp.name AS plan_name,
                mp.slug AS plan_slug,
                mp.price AS plan_price,
                mp.currency AS plan_currency,
                mp.billing_cycle AS plan_billing_cycle
            FROM gym_subscriptions gs
            LEFT JOIN membership_plans mp ON mp.id = gs.plan_id
            WHERE gs.gym_id = :gym_id
            ORDER BY gs.id DESC
            LIMIT 1
        ");
        $subStmt->execute([":gym_id" => (int)$user['gym_id']]);
        $sub = $subStmt->fetch(PDO::FETCH_ASSOC);

        $gymData = [
            "id" => (int)$gym['id'],
            "name" => $gym['name'],
            "slug" => $gym['slug'],
            "status" => $gym['status'],
            "billing_status" => $gym['billing_status'],
            "trial_ends_at" => $gym['trial_ends_at'],
            "subscription" => $sub ?: null
        ];
    }

    /* ==========================
       ✅ JWT CREATE
    ========================== */
    $token = createJWT([
        "id"     => (int)$user['id'],
        "gym_id" => (int)$user['gym_id'],
        "role"   => $user['role']
    ]);

    echo json_encode([
        "status" => true,
        "token"  => $token,
        "user"   => [
            "id" => (int)$user['id'],
            "gym_id" => (int)$user['gym_id'],
            "first_name" => $user['first_name'],
            "last_name" => $user['last_name'],
            "email" => $user['email'],
            "role" => $user['role'],
            "force_password_change" => (int)$user['force_password_change']
        ],
        "gym" => $gymData
    ]);
    exit;

} catch (Exception $e) {

    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Login failed",
        "error" => $e->getMessage()
    ]);
    exit;
}
