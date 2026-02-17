<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

try {

    /* ======================
       AUTH
    ====================== */
    $auth = authenticate();
    requireRole(['gym_admin']);

    $gymId = (int)$auth['gym_id'];

    $page  = max(1,(int)($_GET['page'] ?? 1));
    $limit = min(50,max(10,(int)($_GET['limit'] ?? 10)));
    $offset = ($page-1)*$limit;

    $search = trim($_GET['search'] ?? '');

    $db = new Database();
    $conn = $db->connect();

    /* ======================
       WHERE
    ====================== */
    $where = "u.gym_id = :gym_id AND u.role='staff'";
    $params = [":gym_id"=>$gymId];

    if($search !== ''){
        $where .= " AND (
            u.first_name LIKE :search OR
            u.last_name LIKE :search OR
            u.email LIKE :search OR
            sp.role_title LIKE :search
        )";
        $params[":search"] = "%$search%";
    }

    /* ======================
       STATS
    ====================== */
    $stats = [];

    // total staff
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE gym_id=:gym_id AND role='staff'
    ");
    $stmt->execute([":gym_id"=>$gymId]);
    $stats['total_staff'] = (int)$stmt->fetchColumn();

    // active staff
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE gym_id=:gym_id
        AND role='staff'
        AND status='active'
    ");
    $stmt->execute([":gym_id"=>$gymId]);
    $stats['active_staff'] = (int)$stmt->fetchColumn();

    // front desk count
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM staff_profiles
        WHERE gym_id=:gym_id
        AND role_title='Front Desk'
    ");
    $stmt->execute([":gym_id"=>$gymId]);
    $stats['front_desk'] = (int)$stmt->fetchColumn();

    // maintenance count
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM staff_profiles
        WHERE gym_id=:gym_id
        AND role_title='Maintenance'
    ");
    $stmt->execute([":gym_id"=>$gymId]);
    $stats['maintenance'] = (int)$stmt->fetchColumn();

    /* ======================
       TOTAL COUNT
    ====================== */
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM users u
        LEFT JOIN staff_profiles sp ON sp.user_id=u.id
        WHERE $where
    ");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    /* ======================
       LIST
    ====================== */
    $stmt = $conn->prepare("
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.status,
            sp.role_title,
            sp.shift
        FROM users u
        LEFT JOIN staff_profiles sp ON sp.user_id=u.id
        WHERE $where
        ORDER BY u.id DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach($params as $k=>$v){
        $stmt->bindValue($k,$v);
    }

    $stmt->bindValue(":limit",$limit,PDO::PARAM_INT);
    $stmt->bindValue(":offset",$offset,PDO::PARAM_INT);

    $stmt->execute();
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status"=>true,
        "stats"=>$stats,
        "data"=>$staff,
        "pagination"=>[
            "page"=>$page,
            "limit"=>$limit,
            "total"=>$total,
            "total_pages"=>ceil($total/$limit)
        ]
    ]);

} catch(Exception $e){
    http_response_code(500);
    echo json_encode([
        "status"=>false,
        "message"=>$e->getMessage()
    ]);
}
