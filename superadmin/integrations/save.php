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

    $slug   = trim($data['slug'] ?? '');
    $config = $data['config'] ?? null;

    if ($slug === '' || !is_array($config)) {
        http_response_code(400);
        echo json_encode([
            "status"  => false,
            "message" => "Invalid integration data"
        ]);
        exit;
    }

    /* ==========================
       🗄 DB
    ========================== */
    $db = new Database();
    $conn = $db->connect();

    /* ==========================
       🔎 CHECK INTEGRATION
    ========================== */
    $stmt = $conn->prepare("
        SELECT id, name
        FROM integrations
        WHERE slug = :slug
        LIMIT 1
    ");
    $stmt->execute([":slug" => $slug]);
    $integration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$integration) {
        http_response_code(404);
        echo json_encode([
            "status"  => false,
            "message" => "Integration not found"
        ]);
        exit;
    }

    /* ==========================
       🧪 VALIDATION (PER INTEGRATION)
    ========================== */

    /* 🔹 STRIPE */
    if ($slug === 'stripe') {

        // support both names (future-proof)
        $publishableKey =
            trim($config['publishable_key'] ?? $config['public_key'] ?? '');
        $secretKey = trim($config['secret_key'] ?? '');

        if ($publishableKey === '' || $secretKey === '') {
            http_response_code(400);
            echo json_encode([
                "status"  => false,
                "message" => "Stripe publishable & secret keys required"
            ]);
            exit;
        }
    }

    /* 🔹 SMTP (example – optional, safe) */
    if ($slug === 'smtp') {
        if (
            empty($config['host']) ||
            empty($config['port']) ||
            empty($config['username']) ||
            empty($config['password'])
        ) {
            http_response_code(400);
            echo json_encode([
                "status"  => false,
                "message" => "SMTP host, port, username and password required"
            ]);
            exit;
        }
    }

    /* ==========================
       💾 SAVE CONFIG
    ========================== */
    $stmt = $conn->prepare("
        UPDATE integrations
        SET
            config_json   = :config,
            status        = 'connected',
            is_enabled    = 1,
            error_message = NULL,
            updated_at    = NOW()
        WHERE slug = :slug
    ");

    $stmt->execute([
        ":config" => json_encode($config, JSON_UNESCAPED_UNICODE),
        ":slug"   => $slug
    ]);

    /* ==========================
       🧾 AUDIT LOG
    ========================== */
    logAudit([
        "action"      => "updated",
        "module"      => "integration",
        "target_type" => "integration",
        "target_id"   => (int)$integration['id'],
        "description" => "Integration configured: {$integration['name']}"
    ]);

    echo json_encode([
        "status"  => true,
        "message" => "{$integration['name']} connected successfully"
    ]);
    exit;

} catch (Exception $e) {

    http_response_code(500);
    echo json_encode([
        "status"  => false,
        "message" => "Failed to save integration",
        "error"   => $e->getMessage()
    ]);
    exit;
}
