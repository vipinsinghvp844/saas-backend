<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

try {

    /* ==========================
       AUTH
    ========================== */
    $auth = authenticate();
    requireRole(['gym_admin']);

    $gymId = (int)$auth['gym_id'];

    $db = new Database();
    $conn = $db->connect();

    /* ==========================
       CLASS LIST
    ========================== */
    $stmt = $conn->prepare("
        SELECT
            c.id,
            c.name,
            c.schedule_text,
            c.duration_minutes,
            c.capacity,
            c.status,

            CONCAT(u.first_name,' ',u.last_name) AS trainer_name,

            COUNT(cm.id) AS enrolled_count

        FROM gym_classes c

        LEFT JOIN trainers t
            ON t.id = c.trainer_id

        LEFT JOIN users u
            ON u.id = t.user_id

        LEFT JOIN class_members cm
            ON cm.class_id = c.id

        WHERE c.gym_id = :gym_id

        GROUP BY c.id
        ORDER BY c.id DESC
    ");

    $stmt->execute([
        ":gym_id" => $gymId
    ]);

    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ==========================
       STATS CALCULATION
    ========================== */
    $totalClasses = count($classes);
    $activeClasses = 0;
    $totalEnrollment = 0;
    $capacityPercentSum = 0;

    foreach ($classes as &$c) {

        $enrolled = (int)$c['enrolled_count'];
        $capacity = (int)$c['capacity'];

        if ($c['status'] === 'active') {
            $activeClasses++;
        }

        $totalEnrollment += $enrolled;

        $percent = $capacity > 0
            ? ($enrolled / $capacity) * 100
            : 0;

        $capacityPercentSum += $percent;

        /* format for frontend */
        $c['trainer']  = $c['trainer_name'] ?: "Unassigned";
        $c['schedule'] = $c['schedule_text'];
        $c['duration'] = (int)$c['duration_minutes'];
        $c['enrolled'] = $enrolled;

        unset($c['trainer_name']);
        unset($c['schedule_text']);
        unset($c['duration_minutes']);
        unset($c['enrolled_count']);
    }

    $avgCapacity = $totalClasses > 0
        ? round($capacityPercentSum / $totalClasses)
        : 0;

    echo json_encode([
        "status" => true,
        "stats" => [
            "total_classes"    => $totalClasses,
            "active_classes"   => $activeClasses,
            "total_enrollment" => $totalEnrollment,
            "avg_capacity"     => $avgCapacity
        ],
        "data" => $classes
    ]);

    exit;

} catch (Exception $e) {

    http_response_code(400);

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
