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
    $where[] = "LOWER(i.status)=:status";
    $params[":status"] = $status;
  }

  if ($search !== '') {
    $where[] = "(i.invoice_number LIKE :q OR g.name LIKE :q)";
    $params[":q"] = "%" . $search . "%";
  }

  $whereSql = "";
  if (!empty($where)) {
    $whereSql = "WHERE " . implode(" AND ", $where);
  }

  $stmt = $conn->prepare("
    SELECT
      i.id,
      i.invoice_number,
      i.gym_id,
      g.name AS gym_name,
      i.plan_id,
      mp.name AS plan_name,
      i.amount,
      i.currency,
      i.status,
      i.issued_at,
      i.due_date,
      i.paid_at,
      i.created_at
    FROM invoices i
    LEFT JOIN gyms g ON g.id = i.gym_id
    LEFT JOIN membership_plans mp ON mp.id = i.plan_id
    $whereSql
    ORDER BY i.id DESC
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
    "message" => "Failed to load invoices",
    "error" => $e->getMessage()
  ]);
  exit;
}
