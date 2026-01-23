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

  /* Filters */
  $status = strtolower(trim($_GET['status'] ?? 'all'));
  $search = trim($_GET['search'] ?? '');

  /* Pagination */
  $page  = (int)($_GET['page'] ?? 1);
  $limit = (int)($_GET['limit'] ?? 10);

  if ($page < 1) $page = 1;
  if ($limit < 1) $limit = 10;
  if ($limit > 100) $limit = 100;

  $offset = ($page - 1) * $limit;

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

  /* ✅ COUNT total */
  $countSql = "
    SELECT COUNT(*) as total
    FROM invoices i
    LEFT JOIN gyms g ON g.id = i.gym_id
    $whereSql
  ";
  $countStmt = $conn->prepare($countSql);
  $countStmt->execute($params);
  $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  $totalPages = (int)ceil($total / $limit);

  /* ✅ MAIN DATA */
  $sql = "
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
    LIMIT $limit OFFSET $offset
  ";

  $stmt = $conn->prepare($sql);
  $stmt->execute($params);

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    "status" => true,
    "data" => $rows,
    "pagination" => [
      "page" => $page,
      "limit" => $limit,
      "total" => $total,
      "totalPages" => $totalPages
    ]
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
