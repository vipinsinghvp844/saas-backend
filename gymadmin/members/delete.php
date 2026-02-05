<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";
require_once "../../helpers/auditLog.php";

try {

    /* ==========================
       AUTH
    ========================== */
    $auth = authenticate();
    $GLOBALS['auth_user'] = $auth;
    requireRole(['gym_admin']);

    $gymId    = (int)$auth['gym_id'];
    $memberId = (int)($_POST['id'] ?? 0);

    if ($memberId <= 0) {
        throw new Exception("Invalid member id");
    }

    $db = new Database();
    $conn = $db->connect();
    $conn->beginTransaction();

    /* ==========================
       FETCH MEMBER
    ========================== */
    $stmt = $conn->prepare("
        SELECT m.id, m.user_id
        FROM members m
        WHERE m.id = :id
          AND m.gym_id = :gym_id
        LIMIT 1
    ");
    $stmt->execute([
        ":id"     => $memberId,
        ":gym_id"=> $gymId
    ]);

    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        throw new Exception("Member not found");
    }

    $userId = (int)$member['user_id'];

    /* ==========================
       SOFT DELETE MEMBER
    ========================== */
    $stmt = $conn->prepare("
        UPDATE members
        SET status = 'inactive'
        WHERE id = :id
    ");
    $stmt->execute([":id" => $memberId]);

    /* ==========================
       DISABLE USER LOGIN
    ========================== */
    $stmt = $conn->prepare("
        UPDATE users
        SET status = 'inactive'
        WHERE id = :user_id
    ");
    $stmt->execute([":user_id" => $userId]);

    /* ==========================
       CANCEL ACTIVE SUBSCRIPTIONS
    ========================== */
    $stmt = $conn->prepare("
        UPDATE member_subscriptions
        SET status = 'cancelled'
        WHERE member_id = :member_id
          AND gym_id = :gym_id
          AND status = 'active'
    ");
    $stmt->execute([
        ":member_id" => $memberId,
        ":gym_id"    => $gymId
    ]);

    /* ==========================
       AUDIT LOG
    ========================== */
    logAudit([
        "action"      => "deleted",
        "module"      => "member",
        "target_type" => "member",
        "target_id"   => $memberId,
        "description" => "Member soft-deleted by gym admin"
    ]);

    $conn->commit();

    echo json_encode([
        "status"  => true,
        "message" => "Member deleted successfully"
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
