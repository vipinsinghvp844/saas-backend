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
    $slug = trim($data['slug'] ?? '');

    if ($slug === '') {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Integration slug required"
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
        SELECT id, name, config_json
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

    if (empty($integration['config_json'])) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Integration not configured"
        ]);
        exit;
    }

    $config = json_decode($integration['config_json'], true);

    /* ==========================
       🧪 TEST LOGIC
    ========================== */
    $testResult = [
        "success" => false,
        "message" => "Unknown integration"
    ];

    /* 🔹 STRIPE TEST */
    if ($slug === 'stripe') {

        if (empty($config['secret_key'])) {
            throw new Exception("Stripe secret key missing");
        }

        // Minimal test: hit Stripe API (no charge)
        $ch = curl_init("https://api.stripe.com/v1/balance");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $config['secret_key'] . ":",
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $testResult = [
                "success" => true,
                "message" => "Stripe connection successful"
            ];
        } else {
            throw new Exception("Stripe API authentication failed");
        }
    }

    /* ==========================
       🗄 UPDATE STATUS
    ========================== */
    if ($testResult['success']) {

        $stmt = $conn->prepare("
            UPDATE integrations
            SET
                status = 'connected',
                error_message = NULL,
                last_tested_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([":id" => $integration['id']]);

        logAudit([
            "action"      => "tested",
            "module"      => "integration",
            "target_type" => "integration",
            "target_id"   => $integration['id'],
            "description" => "Integration test successful: {$integration['name']}"
        ]);

        echo json_encode([
            "status"  => true,
            "message" => $testResult['message']
        ]);
        exit;
    }

} catch (Exception $e) {

    /* ==========================
       ❌ TEST FAILED
    ========================== */
    if (isset($integration['id'])) {
        $stmt = $conn->prepare("
            UPDATE integrations
            SET
                status = 'error',
                error_message = :error,
                last_tested_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ":error" => $e->getMessage(),
            ":id"    => $integration['id']
        ]);

        logAudit([
            "action"      => "test_failed",
            "module"      => "integration",
            "target_type" => "integration",
            "target_id"   => $integration['id'],
            "description" => "Integration test failed: {$e->getMessage()}"
        ]);
    }

    http_response_code(400);
    echo json_encode([
        "status"  => false,
        "message" => "Integration test failed",
        "error"   => $e->getMessage()
    ]);
    exit;
}
