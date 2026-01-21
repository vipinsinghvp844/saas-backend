<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";

try {

  $data = json_decode(file_get_contents("php://input"), true);

  $gym_name    = trim($data['gym_name'] ?? '');
  $owner_name  = trim($data['owner_name'] ?? '');
  $owner_email = strtolower(trim($data['owner_email'] ?? ''));
  $phone       = trim($data['phone'] ?? '');
  $plan_id     = $data['plan_id'] ?? null;

  // ✅ optional extra fields (frontend may send)
  $city = trim($data['city'] ?? '');
  $note = trim($data['note'] ?? '');

  /* ✅ validation */
  if ($gym_name === '' || $owner_name === '' || $owner_email === '' || empty($plan_id)) {
    http_response_code(400);
    echo json_encode([
      "status" => false,
      "message" => "gym_name, owner_name, owner_email and plan_id are required"
    ]);
    exit;
  }

  if (!filter_var($owner_email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
      "status" => false,
      "message" => "Invalid owner_email"
    ]);
    exit;
  }

  $db = new Database();
  $conn = $db->connect();

  /* ✅ check active plan */
  $pstmt = $conn->prepare("
    SELECT id, name, slug, price, billing_cycle, status
    FROM membership_plans
    WHERE id=:id
    LIMIT 1
  ");
  $pstmt->execute([":id" => $plan_id]);
  $plan = $pstmt->fetch(PDO::FETCH_ASSOC);

  if (!$plan) {
    http_response_code(404);
    echo json_encode(["status" => false, "message" => "Selected plan not found"]);
    exit;
  }

  if (strtolower($plan['status']) !== 'active') {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Selected plan is inactive"]);
    exit;
  }

  /* ✅ prevent duplicate pending request for same email */
  $dup = $conn->prepare("
    SELECT id
    FROM gym_requests
    WHERE LOWER(owner_email) = LOWER(:email)
      AND status = 'pending'
    LIMIT 1
  ");
  $dup->execute([":email" => $owner_email]);

  if ($dup->fetch(PDO::FETCH_ASSOC)) {
    http_response_code(409);
    echo json_encode([
      "status" => false,
      "message" => "You already have a pending request. Please wait for approval."
    ]);
    exit;
  }

  /* ✅ If user already exists, stop */
  $existsUser = $conn->prepare("SELECT id FROM users WHERE LOWER(email)=LOWER(:email) LIMIT 1");
  $existsUser->execute([":email" => $owner_email]);

  if ($existsUser->fetch(PDO::FETCH_ASSOC)) {
    http_response_code(409);
    echo json_encode([
      "status" => false,
      "message" => "This email is already registered. Please login."
    ]);
    exit;
  }

  /* ✅ insert into gym_requests */
  $stmt = $conn->prepare("
    INSERT INTO gym_requests
      (gym_name, owner_name, owner_email, plan_id, phone, plan_name, amount, payment_status, status, city, note)
    VALUES
      (:gym_name, :owner_name, :owner_email, :plan_id, :phone, :plan_name, :amount, 'unpaid', 'pending', :city, :note)
  ");

  $stmt->execute([
    ":gym_name"    => $gym_name,
    ":owner_name"  => $owner_name,
    ":owner_email" => $owner_email,
    ":plan_id"     => $plan['id'],
    ":phone"       => $phone ?: null,
    ":plan_name"   => $plan['name'],   // ✅ snapshot
    ":amount"      => $plan['price'],  // ✅ snapshot
    ":city"        => $city ?: null,
    ":note"        => $note ?: null,
  ]);

  $requestId = $conn->lastInsertId();

  echo json_encode([
    "status" => true,
    "message" => "Request submitted successfully ✅",
    "data" => [
      "request_id" => $requestId
    ]
  ]);
  exit;

} catch (Exception $e) {

  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Server error",
    "error" => $e->getMessage()
  ]);
  exit;
}
