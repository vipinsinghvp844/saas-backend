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
       PAGINATION + FILTERS
    ========================== */
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = min(50, max(10, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');

    $where  = "t.gym_id = :gym_id";
    $params = [":gym_id" => $gymId];

    if ($status !== '') {
        $where .= " AND t.status = :status";
        $params[":status"] = $status;
    }

    if ($search !== '') {
        $where .= " AND (
            t.name LIKE :search OR
            t.email LIKE :search OR
            t.specialty LIKE :search
        )";
        $params[":search"] = "%{$search}%";
    }

    $db = new Database();
    $conn = $db->connect();

    /* ==========================
       TOTAL COUNT
    ========================== */
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM trainers t
        WHERE $where
    ");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    /* ==========================
       LIST DATA
    ========================== */
    $stmt = $conn->prepare("
        SELECT
            t.id,
            t.name,
            t.email,
            t.phone,
            t.specialty,
            t.status,
            t.created_at,

            u.id AS user_id
        FROM trainers t
        LEFT JOIN users u ON u.id = t.user_id
        WHERE $where
        ORDER BY t.id DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);

    $stmt->execute();
    $trainers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => true,
        "data" => $trainers,
        "pagination" => [
            "page" => $page,
            "limit" => $limit,
            "total" => $total,
            "total_pages" => (int)ceil($total / $limit)
        ]
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Failed to load trainers",
        "error" => $e->getMessage()
    ]);
    exit;
}
