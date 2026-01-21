<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['super_admin']);

$data = json_decode(file_get_contents("php://input"), true);

$gym_id  = isset($data['gym_id']) ? (int)$data['gym_id'] : 0;
$plan_id = isset($data['plan_id']) ? (int)$data['plan_id'] : 0;

if (!$gym_id || !$plan_id) {
  http_response_code(400);
  echo json_encode(["status"=>false,"message"=>"gym_id and plan_id required"]);
  exit;
}

$db = new Database();
$conn = $db->connect();

try {

  // ✅ Fetch plan
  $pstmt = $conn->prepare("
    SELECT id, slug, duration_days
    FROM membership_plans
    WHERE id=:id AND status='active'
    LIMIT 1
  ");
  $pstmt->execute([":id" => $plan_id]);
  $plan = $pstmt->fetch(PDO::FETCH_ASSOC);

  if (!$plan) {
    http_response_code(404);
    echo json_encode(["status"=>false,"message"=>"Plan not found or inactive"]);
    exit;
  }

  $conn->beginTransaction();

  // ✅ expire previous active subscription
  $stmt = $conn->prepare("
    UPDATE gym_subscriptions
    SET status='expired',
        end_date = NOW(),
        updated_at = NOW()
    WHERE gym_id=:gym_id AND status='active'
  ");
  $stmt->execute([":gym_id"=>$gym_id]);

  // ✅ calculate end_date if duration_days exists
  $endDate = null;
  if (!empty($plan['duration_days']) && intval($plan['duration_days']) > 0) {
    $endDate = date("Y-m-d H:i:s", strtotime("+".$plan['duration_days']." days"));
  }

  // ✅ Insert new active subscription
  $stmt = $conn->prepare("
    INSERT INTO gym_subscriptions
      (gym_id, plan, plan_id, trial_days, start_date, end_date, status, created_at, updated_at, assigned_by)
    VALUES
      (:gym_id, :plan_slug, :plan_id, 0, NOW(), :end_date, 'active', NOW(), NOW(), :assigned_by)
  ");

  $stmt->execute([
    ":gym_id"      => $gym_id,
    ":plan_slug"   => $plan['slug'],   // ✅ full slug now saves (after column fix)
    ":plan_id"     => $plan_id,
    ":end_date"    => $endDate,
    ":assigned_by" => $auth['id'] ?? null
  ]);

  $conn->commit();

  echo json_encode([
    "status"=>true,
    "message"=>"Plan assigned successfully ✅"
  ]);
  exit;

} catch (Exception $e) {
  $conn->rollBack();
  http_response_code(500);
  echo json_encode([
    "status"=>false,
    "message"=>"Failed to assign plan",
    "error"=>$e->getMessage()
  ]);
  exit;
}
