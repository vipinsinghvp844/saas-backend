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
    $memberId = (int)($_GET['id'] ?? 0);

    if ($memberId <= 0) {
        throw new Exception("Invalid member id");
    }

    $db = new Database();
    $conn = $db->connect();

    $stmt = $conn->prepare("
        SELECT
            m.id AS member_id,
            m.status,
            m.gender,

            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.dob
        FROM members m
        INNER JOIN users u ON u.id = m.user_id
        WHERE m.id = :member_id
          AND m.gym_id = :gym_id
        LIMIT 1
    ");
    $stmt->execute([
        ":member_id" => $memberId,
        ":gym_id" => $gymId
    ]);

    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        throw new Exception("Member not found");
    }

    echo json_encode([
        "status" => true,
        "data" => [
            "member" => [
                "id" => (int)$member['member_id'],
                "first_name" => $member['first_name'],
                "last_name" => $member['last_name'],
                "email" => $member['email'],
                "phone" => $member['phone'],
                "gender" => $member['gender'],
                "dob" => $member['dob'],
                "status" => $member['status'],
            ]
        ]
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
