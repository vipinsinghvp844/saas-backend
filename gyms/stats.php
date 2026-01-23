<?php

require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

/* AUTH */
$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['super_admin']);

$db = new Database();
$conn = $db->connect();

/* ✅ Exclude system gym */
$systemGymEmail = "admin@platform.com";

/*
  ✅ Correct Stats Logic
  - Total gyms (excluding system gym)
  - Active gyms = status active + billing paid
  - Trial gyms  = status active + billing trial
  - Suspended gyms = status suspended
*/
$sql = "
SELECT
    COUNT(*) AS total,

    SUM(LOWER(status) = 'active' AND LOWER(billing_status) = 'paid') AS active,
    SUM(LOWER(status) = 'active' AND LOWER(billing_status) = 'trial') AS trial,

    SUM(LOWER(status) = 'suspended') AS suspended,
    SUM(LOWER(status) = 'inactive') AS inactive

FROM gyms
WHERE email != :sysEmail
";

$stmt = $conn->prepare($sql);
$stmt->execute([":sysEmail" => $systemGymEmail]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    "status" => true,
    "data" => [
        "total"     => (int)($stats['total'] ?? 0),
        "active"    => (int)($stats['active'] ?? 0),
        "trial"     => (int)($stats['trial'] ?? 0),
        "suspended" => (int)($stats['suspended'] ?? 0),
        "inactive"  => (int)($stats['inactive'] ?? 0),
    ]
]);
exit;
