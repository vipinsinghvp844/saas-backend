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
    requireRole(['super_admin']);

    /* ==========================
       🗄 DB
    ========================== */
    $db = new Database();
    $conn = $db->connect();

    /* ==========================
       📦 FETCH INTEGRATIONS
    ========================== */
    $stmt = $conn->query("
        SELECT
            id,
            name,
            slug,
            category,
            is_enabled,
            status,
            last_tested_at,
            error_message,
            config_json
        FROM integrations
        ORDER BY category ASC, name ASC
    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ==========================
       🧹 SANITIZE RESPONSE
       (never expose secrets)
    ========================== */
    $data = [];

    foreach ($rows as $row) {

        $config = null;
        if (!empty($row['config_json'])) {
            $decoded = json_decode($row['config_json'], true);

            if (is_array($decoded)) {
                // mask secrets
                foreach ($decoded as $k => $v) {
                    if (stripos($k, 'secret') !== false || stripos($k, 'key') !== false) {
                        $decoded[$k] = '********';
                    }
                }
            }

            $config = $decoded;
        }

        $data[] = [
            "id"             => (int)$row['id'],
            "name"           => $row['name'],
            "slug"           => $row['slug'],
            "category"       => $row['category'],
            "is_enabled"     => (bool)$row['is_enabled'],
            "status"         => $row['status'],
            "last_tested_at" => $row['last_tested_at'],
            "error_message"  => $row['error_message'],
            "config"         => $config
        ];
    }

    echo json_encode([
        "status" => true,
        "data"   => $data
    ]);
    exit;

} catch (Exception $e) {

    http_response_code(500);
    echo json_encode([
        "status"  => false,
        "message" => "Failed to load integrations",
        "error"   => $e->getMessage()
    ]);
    exit;
}
