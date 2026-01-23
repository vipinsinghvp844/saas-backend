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

  $currency = strtoupper(trim($_GET['currency'] ?? 'INR'));
  $months = (int)($_GET['months'] ?? 12);
  if ($months < 1) $months = 12;
  if ($months > 24) $months = 24;

  $limit = (int)($_GET['limit'] ?? 10);
  if ($limit < 1) $limit = 10;
  if ($limit > 50) $limit = 50;

  $startDate = date("Y-m-01", strtotime("-" . ($months - 1) . " months"));

  /* ✅ Top Gyms by revenue */
  $stmt = $conn->prepare("
    SELECT
      p.gym_id,
      COALESCE(g.name, 'Unknown Gym') AS gym_name,
      COUNT(*) AS payments,
      COALESCE(SUM(p.amount),0) AS revenue
    FROM payments p
    LEFT JOIN gyms g ON g.id = p.gym_id
    WHERE p.status='paid'
      AND p.currency = :currency
      AND p.paid_at >= :startDate
    GROUP BY p.gym_id
    ORDER BY revenue DESC
    LIMIT $limit
  ");

  $stmt->execute([
    ":currency" => $currency,
    ":startDate" => $startDate
  ]);

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $topGyms = [];
  foreach ($rows as $r) {
    $topGyms[] = [
      "gym_id" => (int)$r["gym_id"],
      "gym_name" => $r["gym_name"],
      "payments" => (int)$r["payments"],
      "revenue" => (float)$r["revenue"],
    ];
  }

  echo json_encode([
    "status" => true,
    "data" => [
      "currency" => $currency,
      "months" => $months,
      "start_date" => $startDate,
      "limit" => $limit,
      "top_gyms" => $topGyms
    ]
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load top gyms analytics",
    "error" => $e->getMessage()
  ]);
  exit;
}
