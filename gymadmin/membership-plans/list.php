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
       📥 FILTERS
    ========================== */
    $status = trim($_GET['status'] ?? 'active'); // active | inactive | all

    $where = "gym_id = :gym_id";
    $params = [
        ":gym_id" => $gymId
    ];

    if ($status !== 'all') {
        $where .= " AND status = :status";
        $params[":status"] = $status;
    }

    /* ==========================
       🗄 DB
    ========================== */
    $db = new Database();
    $conn = $db->connect();

    /* ==========================
       📦 FETCH PLANS
    ========================== */
    $stmt = $conn->prepare("
        SELECT
            id,
            name,
            slug,
            description,
            price,
            currency,
            billing_cycle,
            duration_days,
            status,
            sort_order,
            features_json
        FROM gym_membership_plans
        WHERE $where
        ORDER BY sort_order ASC, id DESC
    ");

    $stmt->execute($params);
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => true,
        "data" => $plans
    ]);
    exit;

} catch (Exception $e) {

    http_response_code(500);
    echo json_encode([
        "status"  => false,
        "message" => "Failed to load membership plans",
        "error"   => $e->getMessage()
    ]);
    exit;
}
