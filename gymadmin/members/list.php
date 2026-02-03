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
    $GLOBALS['auth_user'] = $auth;
    requireRole(['gym_admin']);

    $gymId = (int)$auth['gym_id'];

    /* ==========================
       📥 INPUTS
    ========================== */
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = min(50, max(10, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');

    $db = new Database();
    $conn = $db->connect();

    /* ==========================
       🔎 WHERE CLAUSE
    ========================== */
    $where  = "m.gym_id = :gym_id";
    $params = [":gym_id" => $gymId];

    if ($status !== '') {
        $where .= " AND m.status = :status";
        $params[":status"] = $status;
    }

    if ($search !== '') {
        $where .= " AND (
            u.first_name LIKE :search OR
            u.last_name  LIKE :search OR
            u.email      LIKE :search OR
            m.phone      LIKE :search
        )";
        $params[":search"] = "%{$search}%";
    }

    /* ==========================
       📊 TOTAL COUNT
    ========================== */
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM members m
        INNER JOIN users u ON u.id = m.user_id
        WHERE $where
    ");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    /* ==========================
       📋 MEMBERS LIST
    ========================== */
    $stmt = $conn->prepare("
        SELECT
            m.id,
            m.status,
            m.phone,
            m.created_at AS joined_at,
            u.first_name,
            u.last_name,
            u.email
        FROM members m
        INNER JOIN users u ON u.id = m.user_id
        WHERE $where
        ORDER BY m.id DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);

    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => true,
        "data" => $members,
        "pagination" => [
            "page"        => $page,
            "limit"       => $limit,
            "total"       => $total,
            "total_pages" => (int)ceil($total / $limit)
        ]
    ]);
    exit;

} catch (Exception $e) {

    http_response_code(500);
    echo json_encode([
        "status"  => false,
        "message" => "Failed to load members",
        "error"   => $e->getMessage()
    ]);
    exit;
}
