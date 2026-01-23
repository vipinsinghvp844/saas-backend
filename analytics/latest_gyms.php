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

  $systemEmail = "admin@platform.com";

  $limit = (int)($_GET['limit'] ?? 8);
  if ($limit < 1) $limit = 8;
  if ($limit > 50) $limit = 50;

  $stmt = $conn->prepare("
    SELECT
      g.id,
      g.name,
      g.slug,
      g.email,
      LOWER(g.status) AS status,
      g.created_at,

      -- latest subscription
      gs.status AS subscription_status,
      gs.start_date,
      gs.trial_ends_at,

      mp.name AS plan_name,
      mp.slug AS plan_slug,
      mp.price AS plan_price,
      mp.billing_cycle AS plan_cycle

    FROM gyms g

    LEFT JOIN gym_subscriptions gs
      ON gs.id = (
        SELECT gs2.id
        FROM gym_subscriptions gs2
        WHERE gs2.gym_id = g.id
        ORDER BY gs2.id DESC
        LIMIT 1
      )

    LEFT JOIN membership_plans mp
      ON mp.id = gs.plan_id

    WHERE g.email != :sysEmail
    ORDER BY g.id DESC
    LIMIT $limit
  ");

  $stmt->execute([
    ":sysEmail" => $systemEmail
  ]);

  echo json_encode([
    "status" => true,
    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load latest gyms",
    "error" => $e->getMessage()
  ]);
  exit;
}
