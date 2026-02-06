<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

/* ==========================
   HELPERS
========================== */
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

    $firstName  = trim($data['first_name'] ?? '');
    $lastName   = trim($data['last_name'] ?? '');
    $email      = trim($data['email'] ?? '');
    $phone      = trim($data['phone'] ?? '');
    $gender     = $data['gender'] ?? null;
    $dob        = $data['dob'] ?? null;

    $specialty  = trim($data['specialty'] ?? '');
    $experience = isset($data['experience_years']) ? (int)$data['experience_years'] : null;
    $bio        = trim($data['bio'] ?? '');

    $allowLogin = (bool)($data['allow_login'] ?? false);
    $password   = trim($data['password'] ?? '');

    $status     = $data['status'] ?? 'active';

    if ($firstName === '' || $phone === '') {
        throw new Exception("First name and phone are required");
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
            throw new Exception("Email already exists");
        }
    }

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
        throw new Exception("Phone number already exists");
    }

    /* ==========================
       USER ACCOUNT (OPTIONAL LOGIN)
    ========================== */
    $hashedPassword = null;
    $plainPassword  = null;

    if ($allowLogin) {
        if ($password === '') {
            $plainPassword = generatePassword();
            $password = $plainPassword;
        }
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    }

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
            created_at
        ) VALUES (
            :gym_id,
            :first_name,
            :last_name,
            :email,
            :phone,
            :password,
            'trainer',
            :status,
            NOW()
        )
    ");
    $stmt->execute([
        ":gym_id"     => $gymId,
        ":first_name" => $firstName,
        ":last_name"  => $lastName,
        ":email"      => $email ?: null,
        ":phone"      => $phone,
        ":password"   => $hashedPassword,
        ":status"     => $status
    ]);

    $userId = $conn->lastInsertId();

    /* ==========================
       TRAINER PROFILE
    ========================== */
    $trainerName = trim($firstName . ' ' . $lastName);
    $stmt = $conn->prepare("
        INSERT INTO trainers (
            gym_id,
            user_id,
            name,
            email,
            phone,
            specialty,
            experience_years,
            bio,
            gender,
            dob,
            status,
            created_at
        ) VALUES (
            :gym_id,
            :user_id,
            :name,
            :email,
            :phone,
            :specialty,
            :experience,
            :bio,
            :gender,
            :dob,
            :status,
            NOW()
        )
    ");
    $stmt->execute([
        ":gym_id"     => $gymId,
        ":user_id"    => $userId,
        ":name"       => $trainerName,
        ":email"      => $email ?: null,
        ":phone"      => $phone,
        ":specialty"  => $specialty ?: null,
        ":experience" => $experience,
        ":bio"        => $bio ?: null,
        ":gender"     => $gender,
        ":dob"        => $dob,
        ":status"     => $status,
    ]);

    $trainerId = $conn->lastInsertId();

    $conn->commit();

    echo json_encode([
        "status" => true,
        "message" => "Trainer created successfully",
        "data" => [
            "trainer_id" => (int)$trainerId,
            "user_id"    => (int)$userId,
            "temp_password" => $plainPassword // show only if auto-generated
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
