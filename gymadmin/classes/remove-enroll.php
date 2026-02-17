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

    $data = json_decode(file_get_contents("php://input"), true);

    $classId  = (int)($data['class_id'] ?? 0);
    $memberId = (int)($data['member_id'] ?? 0);

    if (!$classId || !$memberId) {
        throw new Exception("Invalid request");
    }

    $db = new Database();
    $conn = $db->connect();

    /* ==========================
       CHECK EXISTING ENROLLMENT
    ========================== */
    $stmt = $conn->prepare("
        SELECT id
        FROM class_members
        WHERE gym_id = :gym_id
        AND class_id = :class_id
        AND member_id = :member_id
        AND status = 'active'
        LIMIT 1
    ");

    $stmt->execute([
        ":gym_id"=>$gymId,
        ":class_id"=>$classId,
        ":member_id"=>$memberId
    ]);

    $row = $stmt->fetch();

    if(!$row){
        throw new Exception("Member not enrolled");
    }

    /* ==========================
       SOFT REMOVE
    ========================== */
    $stmt = $conn->prepare("
        UPDATE class_members
        SET status='cancelled'
        WHERE id=:id
    ");

    $stmt->execute([
        ":id"=>$row['id']
    ]);

    echo json_encode([
        "status"=>true,
        "message"=>"Member removed from class"
    ]);
    exit;

} catch(Exception $e){

    http_response_code(400);
    echo json_encode([
        "status"=>false,
        "message"=>$e->getMessage()
    ]);
}
