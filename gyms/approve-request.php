<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";
require_once "../utils/mail.php";
require_once "../mailer/send-template.php";

$auth = authenticate();
$GLOBALS['auth_user'] = $auth;
requireRole(['super_admin']);

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['request_id']) || empty($data['action'])) {
  http_response_code(400);
  echo json_encode(["status" => false, "message" => "Invalid request"]);
  exit;
}

$requestId = (int)$data['request_id'];
$action = trim($data['action']); // approved | rejected

$db = new Database();
$conn = $db->connect();

/* ✅ fetch request */
$stmt = $conn->prepare("SELECT * FROM gym_requests WHERE id=:id LIMIT 1");
$stmt->execute([":id" => $requestId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
  http_response_code(404);
  echo json_encode(["status" => false, "message" => "Request not found"]);
  exit;
}

/* ✅ prevent double approve */
if ($request['status'] === 'approved') {
  http_response_code(409);
  echo json_encode([
    "status" => false,
    "message" => "This request is already approved"
  ]);
  exit;
}

/* ✅ prevent approve if rejected already */
if ($request['status'] === 'rejected' && $action === 'approved') {
  http_response_code(409);
  echo json_encode([
    "status" => false,
    "message" => "Rejected request cannot be approved"
  ]);
  exit;
}

/* =========================
   REJECT
========================= */
if ($action === "rejected") {

  $reason = trim($data['reason'] ?? '');
  if ($reason === '') $reason = "Not specified";

  $upd = $conn->prepare("
    UPDATE gym_requests
    SET status='rejected',
        reason=:reason,
        rejected_at=NOW(),
        rejected_by=:rejected_by
    WHERE id=:id
  ");

  $upd->execute([
    ":reason" => $reason,
    ":rejected_by" => $auth['id'],
    ":id" => $requestId
  ]);

  sendTemplateMail(
  $request['owner_email'],
  "gym-request-rejected",
  [
    "owner_name" => $request['owner_name'] ?? "Owner",
    "gym_name"   => $request['gym_name'] ?? "Gym",
    "reason"     => $reason
  ]
);

  echo json_encode(["status" => true, "message" => "Request rejected ✅"]);
  exit;
}

/* =========================
   APPROVE
========================= */
if ($action !== "approved") {
  http_response_code(400);
  echo json_encode(["status" => false, "message" => "Invalid action"]);
  exit;
}

try {

  $conn->beginTransaction();

  /* ✅ Trial Setup */
  $trialDays = (int)($request['trial_days'] ?? 14);
  if ($trialDays <= 0) $trialDays = 14;

  $trialEndsAt = date("Y-m-d H:i:s", strtotime("+$trialDays days"));

  /* ✅ create gym */
  $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $request['gym_name']));
  $slug = trim($slug, "-");

  $gymStmt = $conn->prepare("
    INSERT INTO gyms (name, slug, email, status, billing_status, trial_ends_at)
    VALUES (:name, :slug, :email, 'active', 'trial', :trial_ends_at)
  ");

  $gymStmt->execute([
    ":name" => $request['gym_name'],
    ":slug" => $slug,
    ":email" => $request['owner_email'],
    ":trial_ends_at" => $trialEndsAt
  ]);

  $gym_id = (int)$conn->lastInsertId();

  /* ✅ generate password */
  $password = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 8);

  /* ✅ create gym admin user (first_name/last_name) */
  $ownerFullName = trim($request['owner_name'] ?? '');
  $parts = preg_split('/\s+/', $ownerFullName);

  $firstName = $parts[0] ?? $ownerFullName;
  $lastName  = count($parts) > 1 ? implode(" ", array_slice($parts, 1)) : "";

  $userStmt = $conn->prepare("
    INSERT INTO users
      (gym_id, first_name, last_name, email, password, role, status, force_password_change)
    VALUES
      (:gym_id, :first_name, :last_name, :email, :password, 'gym_admin', 'active', 1)
  ");

  $userStmt->execute([
    ":gym_id"     => $gym_id,
    ":first_name" => $firstName,
    ":last_name"  => $lastName,
    ":email"      => $request['owner_email'],
    ":password"   => password_hash($password, PASSWORD_DEFAULT),
  ]);

  $admin_user_id = (int)$conn->lastInsertId();

  /* ✅ Auto create default gym pages */
  $defaultPages = [
    'home'    => 'Gym Home',
    'about'   => 'Gym About',
    'contact' => 'Gym Contact',
  ];

  $templateStmt = $conn->prepare("
    SELECT id FROM templates
    WHERE name = :name AND type = 'gym'
    LIMIT 1
  ");

  $pageInsertStmt = $conn->prepare("
    INSERT INTO pages
      (site_type, gym_id, slug, template_id, page_data_json)
    VALUES
      ('gym', :gym_id, :slug, :template_id, '{}')
  ");

  foreach ($defaultPages as $pageSlug => $templateName) {
    $templateStmt->execute([":name" => $templateName]);
    $template = $templateStmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
      throw new Exception("Gym template not found: $templateName");
    }

    $pageInsertStmt->execute([
      ":gym_id" => $gym_id,
      ":slug" => $pageSlug,
      ":template_id" => $template['id']
    ]);
  }

  /* ✅ Subscription create (trial) + Invoice create (unpaid) */
  $planId = !empty($request['plan_id']) ? (int)$request['plan_id'] : null;
  $invoice_id = null;

  if ($planId) {

    $pstmt = $conn->prepare("
      SELECT id, name, slug, price, currency, billing_cycle, duration_days
      FROM membership_plans
      WHERE id=:id LIMIT 1
    ");
    $pstmt->execute([":id" => $planId]);
    $plan = $pstmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
      throw new Exception("Selected plan not found");
    }

    // ✅ create trial subscription
    $sub = $conn->prepare("
      INSERT INTO gym_subscriptions
        (gym_id, plan_id, plan, trial_days, trial_ends_at, start_date, status, created_at, updated_at, assigned_by)
      VALUES
        (:gym_id, :plan_id, :plan_name, :trial_days, :trial_ends_at, NOW(), 'trial', NOW(), NOW(), :assigned_by)
    ");

    $sub->execute([
      ":gym_id" => $gym_id,
      ":plan_id" => $planId,
      ":plan_name" => $plan['name'],
      ":trial_days" => $trialDays,
      ":trial_ends_at" => $trialEndsAt,
      ":assigned_by" => $auth['id'] ?? null,
    ]);

    // ✅ generate invoice_number (safe)
    $invNo = "INV-" . date("Ymd") . "-" . strtoupper(substr(md5($gym_id . time()), 0, 6));

    // ✅ create invoice unpaid
    $inv = $conn->prepare("
      INSERT INTO invoices
        (invoice_number, gym_id, plan_id, amount, currency, status, issued_at, due_date, notes, created_at, updated_at)
      VALUES
        (:invoice_number, :gym_id, :plan_id, :amount, :currency, 'unpaid', NOW(), :due_date, :notes, NOW(), NOW())
    ");

    $inv->execute([
      ":invoice_number" => $invNo,
      ":gym_id" => $gym_id,
      ":plan_id" => $planId,
      ":amount" => $plan['price'],
      ":currency" => $plan['currency'] ?? 'INR',
      ":due_date" => $trialEndsAt,
      ":notes" => "Auto invoice generated on approval (trial {$trialDays} days)."
    ]);

    $invoice_id = (int)$conn->lastInsertId();
  }

  /* ✅ update request approved (save invoice_id too) */
  $reqUpd = $conn->prepare("
    UPDATE gym_requests
    SET status='approved',
        gym_id=:gym_id,
        admin_user_id=:admin_user_id,
        invoice_id=:invoice_id,
        approved_at=NOW(),
        approved_by=:approved_by
    WHERE id=:id
  ");

  $reqUpd->execute([
    ":gym_id" => $gym_id,
    ":admin_user_id" => $admin_user_id,
    ":invoice_id" => $invoice_id,
    ":approved_by" => $auth['id'],
    ":id" => $requestId
  ]);

  $conn->commit();

  /* ✅ send approval email */
  $loginUrl = "http://localhost:5173/login";

  sendTemplateMail(
  $request['owner_email'],
  "gym-approved-login",
  [
    "owner_name"    => $request['owner_name'] ?? "Owner",
    "gym_name"      => $request['gym_name'] ?? "Gym",
    "trial_days"    => $trialDays,
    "trial_ends_at" => $trialEndsAt,
    "login_url"     => $loginUrl,
    "email"         => $request['owner_email'],
    "password"      => $password
  ]
);

  echo json_encode(["status" => true, "message" => "Gym approved ✅ Trial started ✅ Invoice created ✅"]);
  exit;

} catch (Exception $e) {

  $conn->rollBack();

  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to approve request",
    "error" => $e->getMessage()
  ]);
  exit;
}
