<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

try {

  /* ✅ AUTH */
  $auth = authenticate();
  $GLOBALS['auth_user'] = $auth;
  requireRole(['gym_admin']);

  $gym_id = (int)($auth['gym_id'] ?? 0);

  if (!$gym_id) {
    http_response_code(400);
    echo json_encode([
      "status" => false,
      "message" => "Gym id missing in token"
    ]);
    exit;
  }

  $db = new Database();
  $conn = $db->connect();

  /* ✅ get latest UNPAID invoice for this gym */
  $stmt = $conn->prepare("
    SELECT
      i.id,
      i.gym_id,
      i.plan_id,
      i.invoice_number,
      i.amount,
      i.currency,
      i.status,
      i.due_date,
      i.notes,
      i.issued_at,
      i.paid_at,
      i.created_at,

      mp.name AS plan_name,
      mp.slug AS plan_slug,
      mp.price AS plan_price,
      mp.billing_cycle

    FROM invoices i
    LEFT JOIN membership_plans mp ON mp.id = i.plan_id

    WHERE i.gym_id = :gym_id
      AND LOWER(i.status) = 'unpaid'

    ORDER BY
      CASE WHEN i.due_date IS NULL THEN 1 ELSE 0 END ASC,
      i.due_date ASC,
      i.id DESC

    LIMIT 1
  ");
  $stmt->execute([":gym_id" => $gym_id]);

  $inv = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$inv) {
    echo json_encode([
      "status" => true,
      "data" => null,
      "message" => "No unpaid invoice found"
    ]);
    exit;
  }

  echo json_encode([
    "status" => true,
    "data" => $inv
  ]);
  exit;

} catch (Exception $e) {

  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load latest unpaid invoice",
    "error" => $e->getMessage()
  ]);
  exit;
}
