<?php

require_once "../config/cors.php";
require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

/* AUTH */
$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['super_admin']);

$db = new Database();
$conn = $db->connect();

/*
  - Stats Query
  - Count gyms by status
  - DB me status lowercase hai (active, trial, suspended, inactive)
*/
$sql = "
SELECT
    COUNT(*) AS total,
    SUM(status = 'active') AS active,
    SUM(status = 'trial') AS trial,
    SUM(status = 'suspended') AS suspended
FROM gyms
";

$stmt = $conn->prepare($sql);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    "status" => true,
    "data" => [
        "total"     => (int)$stats['total'],
        "active"    => (int)$stats['active'],
        "trial"     => (int)$stats['trial'],
        "suspended" => (int)$stats['suspended'],
    ]
]);
