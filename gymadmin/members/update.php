<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";
require_once "../../helpers/auditLog.php";

try {
    $auth = authenticate();
    requireRole(['gym_admin']);

    $gymId = (int)$auth['gym_id'];

    $data = json_decode(file_get_contents("php://input"), true);

    $memberId  = (int)($data['id'] ?? 0);
    $firstName = trim($data['first_name'] ?? '');
    $lastName  = trim($data['last_name'] ?? '');
    $email     = trim($data['email'] ?? '');
    $phone     = trim($data['phone'] ?? '');
    $gender    = $data['gender'] ?? null;
    $dob       = $data['dob'] ?? null;
    $status    = $data['status'] ?? 'active';

    if ($memberId <= 0 || $firstName === '' || $phone === '') {
        throw new Exception("Required fields missing");
    }

    $db = new Database();
    $conn = $db->connect();
    $conn->beginTransaction();

    // user_id fetch
    $stmt = $conn->prepare("
        SELECT user_id FROM members
        WHERE id = :id AND gym_id = :gym_id
        LIMIT 1
    ");
    $stmt->execute([
        ":id" => $memberId,
        ":gym_id" => $gymId
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception("Member not found");
    }

    $userId = (int)$row['user_id'];

    // update users
    $stmt = $conn->prepare("
        UPDATE users SET
            first_name = :first_name,
            last_name  = :last_name,
            email      = :email,
            phone      = :phone,
            dob        = :dob
        WHERE id = :user_id
          AND gym_id = :gym_id
    ");
    $stmt->execute([
        ":first_name" => $firstName,
        ":last_name" => $lastName,
        ":email" => $email ?: null,
        ":phone" => $phone,
        ":dob" => $dob,
        ":user_id" => $userId,
        ":gym_id" => $gymId
    ]);

    // update members
    $stmt = $conn->prepare("
        UPDATE members SET
            gender = :gender,
            status = :status
        WHERE id = :id AND gym_id = :gym_id
    ");
    $stmt->execute([
        ":gender" => $gender,
        ":status" => $status,
        ":id" => $memberId,
        ":gym_id" => $gymId
    ]);

    logAudit([
        "action" => "updated",
        "module" => "member",
        "target_id" => $memberId,
        "description" => "Member profile updated"
    ]);

    $conn->commit();

    echo json_encode([
        "status" => true,
        "message" => "Member updated successfully"
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
