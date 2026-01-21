<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

try {
  $auth = authenticate();
  $GLOBALS['auth_user'] = $auth;
  requireRole(['super_admin']);

  $db = new Database();
  $conn = $db->connect();

  $stmt = $conn->query("
    SELECT
      DATE_FORMAT(paid_at, '%Y-%m') AS ym,
      SUM(amount) AS total
    FROM payments
    WHERE status='paid'
      AND paid_at IS NOT NULL
      AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY ym
    ORDER BY ym ASC
  ");

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // build last 12 months default 0
  $result = [];
  for ($i = 11; $i >= 0; $i--) {
    $ym = date("Y-m", strtotime("-$i months"));
    $label = date("M", strtotime($ym . "-01"));

    $result[$ym] = [
      "month" => $label,
      "revenue" => 0
    ];
  }

  foreach ($rows as $r) {
    $ym = $r['ym'];
    if (isset($result[$ym])) {
      $result[$ym]["revenue"] = (float)$r["total"];
    }
  }

  echo json_encode([
    "status" => true,
    "data" => array_values($result)
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load revenue",
    "error" => $e->getMessage()
  ]);
  exit;
}
