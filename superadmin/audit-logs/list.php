<?php
require_once "../../config/cors.php";
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../middleware/auth.php";
require_once "../../middleware/roleGuard.php";

/* auth */
$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['super_admin']);

/* db */
$db = new Database();
$conn = $db->connect();

/*
|--------------------------------------------------------------------------
| Inputs
|--------------------------------------------------------------------------
*/
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = max(1, intval($_GET['limit'] ?? 20));
$offset = ($page - 1) * $limit;

$module = trim($_GET['module'] ?? 'all');
$action = trim($_GET['action'] ?? 'all');
$search = trim($_GET['search'] ?? '');

/*
|--------------------------------------------------------------------------
| WHERE clause
|--------------------------------------------------------------------------
*/
$where = [];
$params = [];

if ($module !== 'all') {
  $where[] = "module = :module";
  $params[':module'] = $module;
}

if ($action !== 'all') {
  $where[] = "action = :action";
  $params[':action'] = $action;
}

if ($search !== '') {
  $where[] = "(description LIKE :search OR user_name LIKE :search)";
  $params[':search'] = "%{$search}%";
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

/*
|--------------------------------------------------------------------------
| Total Count
|--------------------------------------------------------------------------
*/
$countStmt = $conn->prepare("
  SELECT COUNT(*)
  FROM audit_logs
  $whereSql
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $limit));

/*
|--------------------------------------------------------------------------
| Data Query
|--------------------------------------------------------------------------
*/
$dataStmt = $conn->prepare("
  SELECT
    id,
    user_id,
    user_name,
    user_role,
    action,
    module,
    description,
    ip_address,
    created_at
  FROM audit_logs
  $whereSql
  ORDER BY id DESC
  LIMIT :limit OFFSET :offset
");

/* bind filters */
foreach ($params as $k => $v) {
  $dataStmt->bindValue($k, $v);
}

/* bind pagination */
$dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$dataStmt->execute();
$data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/
echo json_encode([
  "status" => true,
  "data" => $data,
  "pagination" => [
    "page" => $page,
    "limit" => $limit,
    "total" => $total,
    "totalPages" => $totalPages
  ]
]);
