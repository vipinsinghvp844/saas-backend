<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$required = ['gym_name', 'owner_name', 'owner_email'];

foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(["status" => false, "message" => "$field is required"]);
        exit;
    }
}

$db = new Database();
$conn = $db->connect();

// ✅ OPTIONAL fields (safe)
$phone = $data['phone'] ?? null;
$plan_id = $data['plan_id'] ?? null;
$plan_name = $data['plan_name'] ?? null;

// payment optional
$payment_id = $data['payment_id'] ?? null;
$payment_status = $data['payment_status'] ?? 'pending';
$amount = $data['amount'] ?? 0;

try {
    // ✅ Prevent duplicate pending requests for same email
    $stmt = $conn->prepare("
      SELECT id FROM gym_requests
      WHERE owner_email = :email AND status='pending'
      LIMIT 1
    ");
    $stmt->execute([":email" => $data['owner_email']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        http_response_code(409);
        echo json_encode([
            "status" => false,
            "message" => "A pending request already exists for this email."
        ]);
        exit;
    }

    $stmt = $conn->prepare("
      INSERT INTO gym_requests
      (gym_name, owner_name, owner_email, phone, plan_id, plan_name, payment_id, payment_status, amount, status)
      VALUES
      (:gym_name, :owner_name, :owner_email, :phone, :plan_id, :plan_name, :payment_id, :payment_status, :amount, 'pending')
    ");

    $stmt->execute([
        ":gym_name"       => $data['gym_name'],
        ":owner_name"     => $data['owner_name'],
        ":owner_email"    => $data['owner_email'],
        ":phone"          => $phone,
        ":plan_id"        => $plan_id,
        ":plan_name"      => $plan_name,
        ":payment_id"     => $payment_id,
        ":payment_status" => $payment_status,
        ":amount"         => $amount
    ]);

    echo json_encode([
        "status" => true,
        "message" => "Gym request submitted successfully",
        "request_id" => $conn->lastInsertId()
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Failed to submit request", "error" => $e->getMessage()]);
    exit;
}
