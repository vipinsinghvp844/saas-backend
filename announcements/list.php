<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['super_admin']);

$db = new Database();
$conn = $db->connect();

/*
|--------------------------------------------------------------------------
| Inputs (Query Params)
|--------------------------------------------------------------------------
*/
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = max(1, intval($_GET['limit'] ?? 10));
$offset = ($page - 1) * $limit;

$status = trim($_GET['status'] ?? 'all');
$search = trim($_GET['search'] ?? '');

/*
|--------------------------------------------------------------------------
| Build WHERE clause
|--------------------------------------------------------------------------
*/
$where = ["is_deleted = 0"];
$params = [];

if ($status !== 'all') {
  $where[] = "status = :status";
  $params[':status'] = $status;
}

if ($search !== '') {
  $where[] = "(title LIKE :search OR message LIKE :search)";
  $params[':search'] = "%{$search}%";
}

$whereSql = "WHERE " . implode(" AND ", $where);

/*
|--------------------------------------------------------------------------
| Total Count
|--------------------------------------------------------------------------
*/
$countStmt = $conn->prepare("
  SELECT COUNT(*) 
  FROM announcements
  $whereSql
");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$totalPages = max(1, ceil($total / $limit));

/*
|--------------------------------------------------------------------------
| Data Query
|--------------------------------------------------------------------------
*/
$dataStmt = $conn->prepare("
  SELECT
    id,
    title,
    message,
    status,
    start_at,
    end_at,
    created_at,
    updated_at
  FROM announcements
  $whereSql
  ORDER BY id DESC
  LIMIT :limit OFFSET :offset
");

/* bind dynamic params */
foreach ($params as $key => $val) {
  $dataStmt->bindValue($key, $val);
}

/* bind pagination params */
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
