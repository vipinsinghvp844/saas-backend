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

  $status = strtolower(trim($_GET['status'] ?? 'all'));
  $search = trim($_GET['search'] ?? '');

  $where = [];
  $params = [];

  if ($status !== 'all') {
    $where[] = "LOWER(p.status)=:status";
    $params[":status"] = $status;
  }

  if ($search !== '') {
    $where[] = "(g.name LIKE :q OR p.transaction_id LIKE :q OR p.payment_ref LIKE :q)";
    $params[":q"] = "%" . $search . "%";
  }

  $whereSql = "";
  if (!empty($where)) $whereSql = "WHERE " . implode(" AND ", $where);

  $stmt = $conn->prepare("
    SELECT
      p.id,
      p.invoice_id,
      i.invoice_number,
      p.gym_id,
      g.name AS gym_name,
      p.amount,
      p.currency,
      p.payment_method,
      p.payment_for,
      p.status,
      p.transaction_id,
      p.payment_ref,
      p.paid_at,
      p.created_at
    FROM payments p
    LEFT JOIN gyms g ON g.id = p.gym_id
    LEFT JOIN invoices i ON i.id = p.invoice_id
    $whereSql
    ORDER BY p.id DESC
  ");

  $stmt->execute($params);

  echo json_encode([
    "status" => true,
    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load payments",
    "error" => $e->getMessage()
  ]);
  exit;
}
