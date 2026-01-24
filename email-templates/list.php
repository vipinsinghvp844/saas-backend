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

  $page  = (int)($_GET['page'] ?? 1);
  $limit = (int)($_GET['limit'] ?? 10);

  if ($page < 1) $page = 1;
  if ($limit < 5) $limit = 10;
  if ($limit > 50) $limit = 50;

  $offset = ($page - 1) * $limit;

  $where = [];
  $params = [];

  if ($status !== 'all') {
    $where[] = "LOWER(status)=:status";
    $params[":status"] = $status;
  }

  if ($search !== '') {
    $where[] = "(slug LIKE :q OR name LIKE :q OR subject LIKE :q)";
    $params[":q"] = "%" . $search . "%";
  }

  $whereSql = "";
  if (!empty($where)) {
    $whereSql = "WHERE " . implode(" AND ", $where);
  }

  // ✅ total count
  $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM email_templates $whereSql");
  $countStmt->execute($params);
  $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  // ✅ list
  $stmt = $conn->prepare("
    SELECT
      id, slug, name, subject, status, variables_json, created_at, updated_at
    FROM email_templates
    $whereSql
    ORDER BY id DESC
    LIMIT $limit OFFSET $offset
  ");
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    "status" => true,
    "data" => [
      "items" => $rows,
      "pagination" => [
        "page" => $page,
        "limit" => $limit,
        "total" => $total,
        "pages" => $limit > 0 ? (int)ceil($total / $limit) : 1
      ]
    ]
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load email templates",
    "error" => $e->getMessage()
  ]);
  exit;
}
