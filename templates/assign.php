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

  // ✅ Filters
  $type   = strtolower(trim($_GET['type'] ?? 'all'));      // all | platform | gym
  $search = trim($_GET['search'] ?? '');                   // keyword search

  // ✅ Pagination
  $page  = (int)($_GET['page'] ?? 1);
  $limit = (int)($_GET['limit'] ?? 10);

  if ($page < 1) $page = 1;
  if ($limit < 1) $limit = 10;
  if ($limit > 100) $limit = 100;

  $offset = ($page - 1) * $limit;

  $where = [];
  $params = [];

  // ✅ type filter
  if ($type !== "all" && in_array($type, ["platform", "gym"], true)) {
    $where[] = "LOWER(t.type) = :type";
    $params[":type"] = $type;
  }

  // ✅ search filter
  if ($search !== "") {
    $where[] = "(t.name LIKE :q OR t.type LIKE :q)";
    $params[":q"] = "%" . $search . "%";
  }

  $whereSql = "";
  if (!empty($where)) {
    $whereSql = "WHERE " . implode(" AND ", $where);
  }

  // ✅ Count query
  $countStmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM templates t
    $whereSql
  ");
  $countStmt->execute($params);
  $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  $totalPages = (int)ceil($total / $limit);

  // ✅ Data query
  $stmt = $conn->prepare("
    SELECT
      t.id,
      t.type,
      t.name,
      t.structure_json,
      t.updated_at
    FROM templates t
    $whereSql
    ORDER BY t.id DESC
    LIMIT $limit OFFSET $offset
  ");
  $stmt->execute($params);

  echo json_encode([
    "status" => true,
    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC),
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
    "message" => "Failed to load templates",
    "error" => $e->getMessage()
  ]);
  exit;
}
