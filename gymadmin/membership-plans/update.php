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

    $id           = (int)($data['id'] ?? 0);
    $name         = trim($data['name'] ?? '');
    $description  = trim($data['description'] ?? '');
    $price        = (float)($data['price'] ?? 0);
    $durationDays = (int)($data['duration_days'] ?? 0);
    $isActive     = (int)($data['is_active'] ?? 1);

    if ($id <= 0) {
        throw new Exception("Invalid plan ID");
    }

    $db = new Database();
    $conn = $db->connect();

    $stmt = $conn->prepare("
        UPDATE gym_membership_plans
        SET
            name = :name,
            description = :description,
            price = :price,
            duration_days = :duration_days,
            is_active = :is_active,
            updated_at = NOW()
        WHERE id = :id AND gym_id = :gym_id
    ");

    $stmt->execute([
        ":id"            => $id,
        ":gym_id"        => $gymId,
        ":name"          => $name,
        ":description"   => $description,
        ":price"         => $price,
        ":duration_days" => $durationDays,
        ":is_active"     => $isActive
    ]);

    echo json_encode([
        "status" => true,
        "message" => "Plan updated"
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
