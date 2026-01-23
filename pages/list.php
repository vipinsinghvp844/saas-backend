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

  /* =========================
     Filters
  ========================= */
  $site_type = strtolower(trim($_GET['site_type'] ?? 'all')); // all | platform | gym
  $search = trim($_GET['search'] ?? '');

  /* =========================
     Pagination
  ========================= */
  $page  = (int)($_GET['page'] ?? 1);
  $limit = (int)($_GET['limit'] ?? 10);

  if ($page < 1) $page = 1;
  if ($limit < 1) $limit = 10;
  if ($limit > 100) $limit = 100;

  $offset = ($page - 1) * $limit;

  $where = [];
  $params = [];

  // ✅ filter site_type (optional)
  if ($site_type !== 'all') {
    if ($site_type !== 'platform' && $site_type !== 'gym') {
      http_response_code(400);
      echo json_encode([
        "status" => false,
        "message" => "Invalid site_type filter"
      ]);
      exit;
    }

    $where[] = "p.site_type = :site_type";
    $params[":site_type"] = $site_type;
  }

  // ✅ search by slug or template name
  if ($search !== '') {
    $where[] = "(
      p.slug LIKE :q
      OR t.name LIKE :q
      OR t.type LIKE :q
    )";
    $params[":q"] = "%" . $search . "%";
  }

  $whereSql = "";
  if (!empty($where)) {
    $whereSql = "WHERE " . implode(" AND ", $where);
  }

  /* =========================
     COUNT (Total)
  ========================= */
  $countSql = "
    SELECT COUNT(*) AS total
    FROM pages p
    LEFT JOIN templates t ON t.id = p.template_id
    $whereSql
  ";

  $countStmt = $conn->prepare($countSql);
  $countStmt->execute($params);
  $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  $totalPages = (int)ceil($total / $limit);
  if ($totalPages < 1) $totalPages = 1;

  /* =========================
     DATA Query
  ========================= */
  $sql = "
    SELECT
      p.id,
      p.site_type,
      p.gym_id,
      p.slug,
      p.template_id,
      t.name AS template_name,
      t.type AS template_type,
      p.created_at,
      p.updated_at
    FROM pages p
    LEFT JOIN templates t ON t.id = p.template_id
    $whereSql
    ORDER BY p.id DESC
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
    "message" => "Failed to load pages",
    "error" => $e->getMessage()
  ]);
  exit;
}
