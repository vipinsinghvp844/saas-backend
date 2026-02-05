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

    $db = new Database();
    $conn = $db->connect();

    // total trainers
    $total = $conn->prepare("
        SELECT COUNT(*) FROM trainers
        WHERE gym_id = :gym_id
    ");
    $total->execute([":gym_id" => $gymId]);
    $totalTrainers = (int)$total->fetchColumn();

    // active trainers
    $active = $conn->prepare("
        SELECT COUNT(*) FROM trainers
        WHERE gym_id = :gym_id AND status = 'active'
    ");
    $active->execute([":gym_id" => $gymId]);
    $activeTrainers = (int)$active->fetchColumn();

    // avg rating
    $rating = $conn->prepare("
        SELECT ROUND(AVG(rating),1)
        FROM trainers
        WHERE gym_id = :gym_id AND rating > 0
    ");
    $rating->execute([":gym_id" => $gymId]);
    $avgRating = $rating->fetchColumn() ?: 0;

    // total classes
    $classes = $conn->prepare("
        SELECT COUNT(DISTINCT class_id)
        FROM trainer_classes
        WHERE gym_id = :gym_id
    ");
    $classes->execute([":gym_id" => $gymId]);
    $totalClasses = (int)$classes->fetchColumn();

    echo json_encode([
        "status" => true,
        "data" => [
            "total_trainers"  => $totalTrainers,
            "active_trainers" => $activeTrainers,
            "avg_rating"      => (float)$avgRating,
            "total_classes"   => $totalClasses
        ]
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Failed to load trainer stats"
    ]);
    exit;
}
