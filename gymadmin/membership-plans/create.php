<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

try {
    $auth = authenticate();
    requireRole(['gym_admin']);

    $gymId = (int)$auth['gym_id'];
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['name']) || empty($data['slug']) || empty($data['price']) || empty($data['duration_days'])) {
        throw new Exception("Required fields missing");
    }

    $db = new Database();
    $conn = $db->connect();

    // check duplicate slug per gym
    $stmt = $conn->prepare("
        SELECT id FROM gym_membership_plans
        WHERE gym_id = :gym_id AND slug = :slug
        LIMIT 1
    ");
    $stmt->execute([
        ":gym_id" => $gymId,
        ":slug"   => $data['slug']
    ]);

    if ($stmt->fetch()) {
        throw new Exception("Plan slug already exists");
    }

    $stmt = $conn->prepare("
        INSERT INTO gym_membership_plans (
            gym_id,
            name,
            slug,
            description,
            price,
            currency,
            billing_cycle,
            duration_days,
            features_json,
            is_active,
            sort_order,
            created_at
        ) VALUES (
            :gym_id,
            :name,
            :slug,
            :description,
            :price,
            :currency,
            :billing_cycle,
            :duration_days,
            :features_json,
            :is_active,
            :sort_order,
            NOW()
        )
    ");

    $stmt->execute([
        ":gym_id"        => $gymId,
        ":name"          => $data['name'],
        ":slug"          => $data['slug'],
        ":description"   => $data['description'] ?? null,
        ":price"         => $data['price'],
        ":currency"      => $data['currency'] ?? 'INR',
        ":billing_cycle" => $data['billing_cycle'] ?? 'monthly',
        ":duration_days" => $data['duration_days'],
        ":features_json" => json_encode($data['features_json'] ?? []),
        ":is_active"     => $data['is_active'] ?? 1,
        ":sort_order"    => $data['sort_order'] ?? 0,
    ]);

    echo json_encode([
        "status" => true,
        "message" => "Membership plan created"
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
    exit;
}
