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
  requireRole(['super_admin']);

  $db = new Database();
  $conn = $db->connect();

  /* ✅ EXCLUDE SYSTEM / SUPER ADMIN GYM */
  $systemGymEmail = "admin@platform.com";

  /* ✅ Filters */
  $status = strtolower(trim($_GET['status'] ?? 'all')); // paid/pending/failed/refunded/all
  $search = trim($_GET['search'] ?? '');

  /* ✅ Pagination */
  $page  = (int)($_GET['page'] ?? 1);
  $limit = (int)($_GET['limit'] ?? 10);

  if ($page < 1) $page = 1;
  if ($limit < 1) $limit = 10;
  if ($limit > 100) $limit = 100;

  $offset = ($page - 1) * $limit;

  $where = [];
  $params = [];

  /* ✅ exclude system gym */
  $where[] = "g.email != :sysEmail";
  $params[":sysEmail"] = $systemGymEmail;

  /* ✅ Status filter */
  $allowedStatus = ["paid", "pending", "failed", "refunded", "completed"];
  if ($status !== "all" && in_array($status, $allowedStatus, true)) {
    $where[] = "LOWER(p.status) = :status";
    $params[":status"] = $status;
  }

  /* ✅ Search filter */
  if ($search !== '') {
    $where[] = "(
      g.name LIKE :q
      OR i.invoice_number LIKE :q
      OR p.transaction_id LIKE :q
      OR p.payment_ref LIKE :q
      OR p.payment_method LIKE :q
    )";
    $params[":q"] = "%" . $search . "%";
  }

  $whereSql = "WHERE " . implode(" AND ", $where);

  /* ✅ COUNT for pagination */
  $countSql = "
    SELECT COUNT(*) as total
    FROM payments p
    LEFT JOIN gyms g ON g.id = p.gym_id
    LEFT JOIN invoices i ON i.id = p.invoice_id
    $whereSql
  ";
  $countStmt = $conn->prepare($countSql);
  $countStmt->execute($params);
  $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  $totalPages = (int)ceil($total / $limit);

  /* ✅ MAIN LIST */
  $sql = "
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
    LIMIT $limit OFFSET $offset
  ";

  $stmt = $conn->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  /* ✅ STATS (same filters except pagination) */
  $statsSql = "
    SELECT
      COUNT(*) AS total,
      SUM(LOWER(p.status)='paid') AS paid,
      SUM(LOWER(p.status)='pending') AS pending,
      SUM(LOWER(p.status)='failed') AS failed,
      SUM(LOWER(p.status)='refunded') AS refunded,
      SUM(CASE WHEN LOWER(p.status)='paid' THEN p.amount ELSE 0 END) AS total_paid_amount
    FROM payments p
    LEFT JOIN gyms g ON g.id = p.gym_id
    LEFT JOIN invoices i ON i.id = p.invoice_id
    $whereSql
  ";

  $statsStmt = $conn->prepare($statsSql);
  $statsStmt->execute($params);
  $s = $statsStmt->fetch(PDO::FETCH_ASSOC);

  echo json_encode([
    "status" => true,
    "data" => $rows,
    "pagination" => [
      "page" => $page,
      "limit" => $limit,
      "total" => $total,
      "totalPages" => $totalPages
    ],
    "stats" => [
      "total" => (int)($s["total"] ?? 0),
      "paid" => (int)($s["paid"] ?? 0),
      "pending" => (int)($s["pending"] ?? 0),
      "failed" => (int)($s["failed"] ?? 0),
      "refunded" => (int)($s["refunded"] ?? 0),
      "total_paid_amount" => (float)($s["total_paid_amount"] ?? 0),
    ]
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
