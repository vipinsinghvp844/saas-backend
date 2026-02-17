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

    $gymId  = (int)$auth['gym_id'];
    $classId = (int)($_GET['class_id'] ?? 0);

    if (!$classId) {
        throw new Exception("Invalid class id");
    }

    /* ==========================
       PAGINATION
    ========================== */
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = min(50, max(10, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $db   = new Database();
    $conn = $db->connect();

    /* ==========================
       ✅ CLASS VALIDATION
    ========================== */
    $stmt = $conn->prepare("
        SELECT id
        FROM gym_classes
        WHERE id = :class_id
          AND gym_id = :gym_id
        LIMIT 1
    ");
    $stmt->execute([
        ":class_id" => $classId,
        ":gym_id"   => $gymId
    ]);

    if (!$stmt->fetch()) {
        throw new Exception("Class not found");
    }

    /* ==========================
       TOTAL COUNT
    ========================== */
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM class_members cm
        WHERE cm.class_id = :class_id
          AND cm.status = 'active'
    ");
    $stmt->execute([
        ":class_id" => $classId
    ]);

    $total = (int)$stmt->fetchColumn();

    /* ==========================
       FETCH MEMBERS
    ========================== */
    $stmt = $conn->prepare("
        SELECT
            cm.id AS enrollment_id,
            cm.joined_at,

            m.id AS member_id,
            m.status AS member_status,

            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.avatar

        FROM class_members cm
        INNER JOIN members m ON m.id = cm.member_id
        INNER JOIN users u   ON u.id = m.user_id

        WHERE cm.class_id = :class_id
          AND cm.status = 'active'

        ORDER BY cm.id DESC
        LIMIT :limit OFFSET :offset
    ");

    $stmt->bindValue(":class_id", $classId, PDO::PARAM_INT);
    $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);

    $stmt->execute();

    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ==========================
       RESPONSE
    ========================== */
    echo json_encode([
        "status" => true,
        "data" => $members,
        "pagination" => [
            "page" => $page,
            "limit" => $limit,
            "total" => $total,
            "total_pages" => (int)ceil($total / $limit)
        ]
    ]);

} catch (Exception $e) {

    http_response_code(400);

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
