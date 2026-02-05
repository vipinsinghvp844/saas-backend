<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

function generatePassword($len = 8) {
    $chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    return substr(str_shuffle($chars), 0, $len);
}

try {

    /* ==========================
       🔐 AUTH
    ========================== */
    $auth = authenticate();
    requireRole(['gym_admin']);

    $gymId = (int)$auth['gym_id'];

    /* ==========================
       📥 INPUT
    ========================== */
    $data = json_decode(file_get_contents("php://input"), true);

    $name        = trim($data['name'] ?? '');
    $email       = trim($data['email'] ?? '');
    $phone       = trim($data['phone'] ?? '');
    $specialty   = trim($data['specialty'] ?? '');
    $status      = $data['status'] ?? 'active';

    $allowLogin  = (bool)($data['allowLogin'] ?? false);
    $passwordType = $data['passwordType'] ?? 'auto';
    $password    = $data['password'] ?? '';

    if ($name === '' || $phone === '') {
        throw new Exception("Name and phone are required");
    }

    /* ==========================
       🗄 DB
    ========================== */
    $db = new Database();
    $conn = $db->connect();
    $conn->beginTransaction();

    $userId = null;
    $plainPassword = null;

    /* ==========================
       👤 CREATE LOGIN (OPTIONAL)
    ========================== */
    if ($allowLogin) {

        if ($email === '') {
            throw new Exception("Email is required for login access");
        }

        // duplicate email check
        $stmt = $conn->prepare("
            SELECT id FROM users
            WHERE email = :email AND gym_id = :gym_id
            LIMIT 1
        ");
        $stmt->execute([
            ":email" => $email,
            ":gym_id" => $gymId
        ]);
        if ($stmt->fetch()) {
            throw new Exception("Email already exists");
        }

        if ($passwordType === 'manual') {
            if (strlen($password) < 6) {
                throw new Exception("Password must be at least 6 characters");
            }
            $plainPassword = $password;
        } else {
            $plainPassword = generatePassword();
        }

        $stmt = $conn->prepare("
            INSERT INTO users (
                gym_id,
                first_name,
                email,
                phone,
                password,
                role,
                status,
                force_password_change,
                created_at
            ) VALUES (
                :gym_id,
                :name,
                :email,
                :phone,
                :password,
                'trainer',
                'active',
                1,
                NOW()
            )
        ");
        $stmt->execute([
            ":gym_id"   => $gymId,
            ":name"     => $name,
            ":email"    => $email,
            ":phone"    => $phone,
            ":password" => password_hash($plainPassword, PASSWORD_DEFAULT),
        ]);

        $userId = $conn->lastInsertId();
    }

    /* ==========================
       🏋️ TRAINER PROFILE
    ========================== */
    $stmt = $conn->prepare("
        INSERT INTO trainers (
            gym_id,
            user_id,
            name,
            email,
            phone,
            specialty,
            status,
            created_at
        ) VALUES (
            :gym_id,
            :user_id,
            :name,
            :email,
            :phone,
            :specialty,
            :status,
            NOW()
        )
    ");
    $stmt->execute([
        ":gym_id"    => $gymId,
        ":user_id"   => $userId,
        ":name"      => $name,
        ":email"     => $email ?: null,
        ":phone"     => $phone,
        ":specialty" => $specialty,
        ":status"    => $status
    ]);

    $trainerId = $conn->lastInsertId();

    $conn->commit();

    echo json_encode([
        "status" => true,
        "message" => "Trainer added successfully",
        "data" => [
            "trainer_id" => (int)$trainerId,
            "user_id"    => $userId ? (int)$userId : null,
            "password"   => $plainPassword // 🔴 remove in production / email instead
        ]
    ]);
    exit;

} catch (Exception $e) {

    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
    exit;
}
