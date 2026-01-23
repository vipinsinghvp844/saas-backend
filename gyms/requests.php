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

  /*
    Optional filters:
      /gyms/requests.php?status=pending
      /gyms/requests.php?search=gmail
      /gyms/requests.php?page=1&limit=10
  */

  $status = strtolower(trim($_GET['status'] ?? ''));
  $search = trim($_GET['search'] ?? '');

  /* ✅ Pagination */
  $page  = (int)($_GET['page'] ?? 1);
  $limit = (int)($_GET['limit'] ?? 10);

  if ($page < 1) $page = 1;
  if ($limit < 1) $limit = 10;
  if ($limit > 100) $limit = 100;

  $offset = ($page - 1) * $limit;

  /* ✅ Filters WHERE */
  $where = [];
  $params = [];

  if ($status !== '') {
    $where[] = "LOWER(gr.status) = :status";
    $params[":status"] = $status;
  }

  if ($search !== '') {
    $where[] = "(
      gr.gym_name LIKE :q
      OR gr.owner_email LIKE :q
      OR gr.owner_name LIKE :q
      OR inv.invoice_number LIKE :q
    )";
    $params[":q"] = "%" . $search . "%";
  }

  $whereSql = "";
  if (!empty($where)) {
    $whereSql = "WHERE " . implode(" AND ", $where);
  }

  /* ✅ COUNT QUERY (Pagination Total) */
  $countSql = "
    SELECT COUNT(*) AS total
    FROM gym_requests gr
    LEFT JOIN invoices inv ON inv.id = gr.invoice_id
    $whereSql
  ";

  $countStmt = $conn->prepare($countSql);
  $countStmt->execute($params);
  $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  $totalPages = ($total > 0) ? (int)ceil($total / $limit) : 1;

  /* ✅ MAIN DATA QUERY */
  $sql = "
    SELECT
      gr.id,
      gr.gym_name,
      gr.owner_name,
      gr.owner_email,
      gr.plan_id,
      gr.trial_days,
      gr.phone,
      gr.city,
      gr.note,
      gr.plan_name,
      gr.amount,
      gr.payment_status,
      gr.payment_id,
      gr.invoice_id,
      gr.gym_id,
      gr.admin_user_id,
      gr.approved_at,
      gr.approved_by,
      gr.rejected_at,
      gr.rejected_by,
      gr.status,
      gr.created_at,
      gr.rejection_reason,

      inv.invoice_number,
      inv.status AS invoice_status,
      inv.due_date,
      inv.paid_at,

      p.status AS latest_payment_status,
      p.payment_method AS latest_payment_method,
      p.paid_at AS latest_payment_date

    FROM gym_requests gr

    LEFT JOIN invoices inv
      ON inv.id = gr.invoice_id

    LEFT JOIN payments p
      ON p.id = gr.payment_id

    $whereSql
    ORDER BY gr.id DESC
    LIMIT :limit OFFSET :offset
  ";

  $stmt = $conn->prepare($sql);

  // ✅ bind filters params
  foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
  }

  // ✅ bind limit/offset (must be int)
  $stmt->bindValue(":limit", (int)$limit, PDO::PARAM_INT);
  $stmt->bindValue(":offset", (int)$offset, PDO::PARAM_INT);

  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  /* ✅ Normalize payment_status */
  foreach ($rows as &$r) {
    $ps = strtolower(trim((string)($r['payment_status'] ?? "")));

    if ($ps === "") {
      if (strtolower((string)($r['invoice_status'] ?? "")) === "paid") {
        $r['payment_status'] = "paid";
      } else if (strtolower((string)($r['latest_payment_status'] ?? "")) === "paid") {
        $r['payment_status'] = "paid";
      } else {
        $r['payment_status'] = "pending";
      }
    }
  }

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
    "message" => "Failed to load requests",
    "error" => $e->getMessage()
  ]);
  exit;
}
