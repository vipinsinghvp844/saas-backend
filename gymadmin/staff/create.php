<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

try {

    /* ==========================
       AUTH
    ========================== */
    $auth = authenticate();
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

    $roleTitle = trim($data['role_title'] ?? '');
    $shift     = $data['shift'] ?? 'full_day';
    $salary    = $data['salary'] ?? null;
    $joining   = $data['joining_date'] ?? null;

    $status    = $data['status'] ?? 'active';

    if ($firstName === '' || $phone === '') {
        throw new Exception("Name and phone required");
    }

    /* ==========================
       DB
    ========================== */
    $db = new Database();
    $conn = $db->connect();
    $conn->beginTransaction();

    /* ==========================
       DUPLICATE CHECK
    ========================== */
    $stmt = $conn->prepare("
        SELECT id FROM users
        WHERE gym_id=:gym_id AND phone=:phone
        LIMIT 1
    ");
    $stmt->execute([
        ":gym_id"=>$gymId,
        ":phone"=>$phone
    ]);

    if($stmt->fetch()){
        throw new Exception("Phone already exists");
    }

    /* ==========================
       USERS TABLE
    ========================== */
    $stmt = $conn->prepare("
        INSERT INTO users(
            gym_id,
            first_name,
            last_name,
            email,
            phone,
            role,
            status,
            created_at
        ) VALUES(
            :gym_id,
            :first_name,
            :last_name,
            :email,
            :phone,
            'staff',
            :status,
            NOW()
        )
    ");

    $stmt->execute([
        ":gym_id"=>$gymId,
        ":first_name"=>$firstName,
        ":last_name"=>$lastName,
        ":email"=>$email ?: null,
        ":phone"=>$phone,
        ":status"=>$status
    ]);

    $userId = $conn->lastInsertId();

    /* ==========================
       STAFF PROFILE
    ========================== */
    $stmt = $conn->prepare("
        INSERT INTO staff_profiles(
            gym_id,
            user_id,
            role_title,
            shift,
            salary,
            joining_date,
            created_at
        ) VALUES(
            :gym_id,
            :user_id,
            :role_title,
            :shift,
            :salary,
            :joining_date,
            NOW()
        )
    ");

    $stmt->execute([
        ":gym_id"=>$gymId,
        ":user_id"=>$userId,
        ":role_title"=>$roleTitle ?: null,
        ":shift"=>$shift,
        ":salary"=>$salary,
        ":joining_date"=>$joining
    ]);

    $staffId = $conn->lastInsertId();

    $conn->commit();

    echo json_encode([
        "status"=>true,
        "message"=>"Staff created successfully",
        "data"=>[
            "staff_id"=>$staffId,
            "user_id"=>$userId
        ]
    ]);

} catch(Exception $e){

    if(isset($conn) && $conn->inTransaction()){
        $conn->rollBack();
    }

    http_response_code(400);
    echo json_encode([
        "status"=>false,
        "message"=>$e->getMessage()
    ]);
}
