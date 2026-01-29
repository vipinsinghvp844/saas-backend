<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";
require_once "../../helpers/auditLog.php";

try {

    /* ==========================
       🔐 AUTH
    ========================== */
    $auth = authenticate();
    $GLOBALS['auth_user'] = $auth;
    requireRole(['super_admin']);

    /* ==========================
       📥 INPUT
    ========================== */
    $data = json_decode(file_get_contents("php://input"), true);

    $slug       = trim($data['slug'] ?? '');
    $isEnabled  = isset($data['is_enabled']) ? (int)$data['is_enabled'] : null;

    if ($slug === '' || !in_array($isEnabled, [0, 1], true)) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Invalid request"
        ]);
        exit;
    }

    /* ==========================
       🗄 DB
    ========================== */
    $db = new Database();
    $conn = $db->connect();

    /* ==========================
       🔎 FETCH INTEGRATION
    ========================== */
    $stmt = $conn->prepare("
        SELECT id, name, status
        FROM integrations
        WHERE slug = :slug
        LIMIT 1
    ");
    $stmt->execute([":slug" => $slug]);
    $integration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$integration) {
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "message" => "Integration not found"
        ]);
        exit;
    }

    /* ==========================
       🚦 TOGGLE ENABLE
    ========================== */
    $stmt = $conn->prepare("
        UPDATE integrations
        SET
            is_enabled = :enabled,
            updated_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        ":enabled" => $isEnabled,
        ":id"      => $integration['id']
    ]);

    /* ==========================
       🧾 AUDIT LOG
    ========================== */
    logAudit([
        "action"      => $isEnabled ? "enabled" : "disabled",
        "module"      => "integration",
        "target_type" => "integration",
        "target_id"   => $integration['id'],
        "description" => ($isEnabled
            ? "Integration enabled"
            : "Integration disabled") . ": {$integration['name']}"
    ]);

    echo json_encode([
        "status"  => true,
        "message" => $isEnabled
            ? "{$integration['name']} enabled"
            : "{$integration['name']} disabled"
    ]);
    exit;

} catch (Exception $e) {

    http_response_code(500);
    echo json_encode([
        "status"  => false,
        "message" => "Failed to update integration",
        "error"   => $e->getMessage()
    ]);
    exit;
}
