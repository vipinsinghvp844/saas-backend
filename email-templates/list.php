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

  $status = strtolower(trim($_GET['status'] ?? "all"));
  $search = trim($_GET['search'] ?? "");

  $page  = (int)($_GET['page'] ?? 1);
  $limit = (int)($_GET['limit'] ?? 10);

  if ($page < 1) $page = 1;
  if ($limit < 1) $limit = 10;
  if ($limit > 100) $limit = 100;

  $offset = ($page - 1) * $limit;

  $where = [];
  $params = [];

  if ($status !== "all") {
    $where[] = "LOWER(et.status) = :status";
    $params[":status"] = $status;
  }

  if ($search !== "") {
    $where[] = "(et.name LIKE :q OR et.slug LIKE :q OR et.subject LIKE :q)";
    $params[":q"] = "%" . $search . "%";
  }

  $whereSql = "";
  if (!empty($where)) $whereSql = "WHERE " . implode(" AND ", $where);

  // ✅ count
  $countStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM email_templates et
    $whereSql
  ");
  $countStmt->execute($params);
  $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  $totalPages = (int)ceil($total / $limit);
  if ($totalPages < 1) $totalPages = 1;

  $stmt = $conn->prepare("
    SELECT
      et.id,
      et.name,
      et.slug,
      et.subject,
      et.status,
      et.created_at,
      et.updated_at
    FROM email_templates et
    $whereSql
    ORDER BY et.id DESC
    LIMIT $limit OFFSET $offset
  ");
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
    "message" => "Failed to load email templates",
    "error" => $e->getMessage()
  ]);
  exit;
}
