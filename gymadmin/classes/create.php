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

    $name      = trim($data['name'] ?? '');
    $trainerId = (int)($data['trainer_id'] ?? 0);
    $schedule  = trim($data['schedule_text'] ?? '');
    $duration  = (int)($data['duration_minutes'] ?? 60);
    $capacity  = (int)($data['capacity'] ?? 10);
    $status    = $data['status'] ?? 'active';

    if ($name === '') {
        throw new Exception("Class name is required");
    }

    /* ==========================
       DB
    ========================== */
    $db = new Database();
    $conn = $db->connect();

    /* ==========================
       TRAINER VALIDATION
    ========================== */
    if ($trainerId > 0) {
        $stmt = $conn->prepare("
            SELECT t.id
            FROM trainers t
            WHERE t.id = :trainer_id
            AND t.gym_id = :gym_id
            LIMIT 1
        ");
        $stmt->execute([
            ":trainer_id" => $trainerId,
            ":gym_id"     => $gymId
        ]);

        if (!$stmt->fetch()) {
            throw new Exception("Invalid trainer selected");
        }
    }

    /* ==========================
       INSERT CLASS
    ========================== */
    $stmt = $conn->prepare("
        INSERT INTO gym_classes (
            gym_id,
            name,
            trainer_id,
            schedule_text,
            duration_minutes,
            capacity,
            status,
            created_at
        ) VALUES (
            :gym_id,
            :name,
            :trainer_id,
            :schedule,
            :duration,
            :capacity,
            :status,
            NOW()
        )
    ");

    $stmt->execute([
        ":gym_id"     => $gymId,
        ":name"       => $name,
        ":trainer_id" => $trainerId ?: null,
        ":schedule"   => $schedule ?: null,
        ":duration"   => $duration,
        ":capacity"   => $capacity,
        ":status"     => $status
    ]);

    $classId = $conn->lastInsertId();

    echo json_encode([
        "status" => true,
        "message" => "Class created successfully",
        "data" => [
            "class_id" => (int)$classId
        ]
    ]);
    exit;

} catch (Exception $e) {

    http_response_code(400);

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
    exit;
}
