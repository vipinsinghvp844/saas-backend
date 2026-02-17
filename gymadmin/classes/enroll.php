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
    requireRole(['gym_admin']);

    $gymId = (int)$auth['gym_id'];

    /* ==========================
       📥 INPUT
    ========================== */
    $data = json_decode(file_get_contents("php://input"), true);

    $classId  = (int)($data['class_id'] ?? 0);
    $memberId = (int)($data['member_id'] ?? 0);

    if (!$classId || !$memberId) {
        throw new Exception("Class and Member required");
    }

    /* ==========================
       DB
    ========================== */
    $db = new Database();
    $conn = $db->connect();
    $conn->beginTransaction();

    /* ==========================
       ✅ CHECK CLASS EXISTS
    ========================== */
    $stmt = $conn->prepare("
        SELECT id, capacity
        FROM gym_classes
        WHERE id = :class_id
          AND gym_id = :gym_id
          AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([
        ":class_id" => $classId,
        ":gym_id"   => $gymId
    ]);

    $class = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$class) {
        throw new Exception("Class not found");
    }

    /* ==========================
       ✅ MEMBER EXISTS
    ========================== */
    $stmt = $conn->prepare("
        SELECT id FROM members
        WHERE id = :member_id
          AND gym_id = :gym_id
        LIMIT 1
    ");
    $stmt->execute([
        ":member_id" => $memberId,
        ":gym_id"    => $gymId
    ]);

    if (!$stmt->fetch()) {
        throw new Exception("Member not found");
    }

    /* ==========================
       🚫 DUPLICATE CHECK
    ========================== */
    $stmt = $conn->prepare("
        SELECT id FROM class_members
        WHERE class_id = :class_id
          AND member_id = :member_id
          AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([
        ":class_id"  => $classId,
        ":member_id" => $memberId
    ]);

    if ($stmt->fetch()) {
        throw new Exception("Member already enrolled");
    }

    /* ==========================
       📊 CAPACITY CHECK
    ========================== */
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM class_members
        WHERE class_id = :class_id
          AND status = 'active'
    ");
    $stmt->execute([
        ":class_id" => $classId
    ]);

    $enrolled = (int)$stmt->fetchColumn();

    if ($enrolled >= (int)$class['capacity']) {
        throw new Exception("Class capacity full");
    }

    /* ==========================
       ✅ INSERT ENROLLMENT
    ========================== */
    $stmt = $conn->prepare("
        INSERT INTO class_members (
            gym_id,
            class_id,
            member_id,
            joined_at,
            status
        ) VALUES (
            :gym_id,
            :class_id,
            :member_id,
            NOW(),
            'active'
        )
    ");

    $stmt->execute([
        ":gym_id"    => $gymId,
        ":class_id"  => $classId,
        ":member_id" => $memberId
    ]);

    $conn->commit();

    echo json_encode([
        "status" => true,
        "message" => "Member enrolled successfully"
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
}
