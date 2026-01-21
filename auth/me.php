<?php
require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";

try {

  $auth = authenticate();

  $db = new Database();
  $conn = $db->connect();

  // ✅ user info
  $stmt = $conn->prepare("
    SELECT 
      id,
      gym_id,
      first_name,
      last_name,
      email,
      role,
      avatar
    FROM users
    WHERE id = :id
    LIMIT 1
  ");
  $stmt->execute([":id" => $auth['id']]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    http_response_code(404);
    echo json_encode(["status" => false, "message" => "User not found"]);
    exit;
  }

  $billing_required = 0;
  $billing_reason = null;
  $gym_status = null;

  // ✅ only check billing for gym_admin
  if ($user["role"] === "gym_admin" && !empty($user["gym_id"])) {

    // ✅ gym status
    $gstmt = $conn->prepare("SELECT id, status FROM gyms WHERE id=:id LIMIT 1");
    $gstmt->execute([":id" => $user["gym_id"]]);
    $gym = $gstmt->fetch(PDO::FETCH_ASSOC);

    if ($gym) {
      $gym_status = strtolower($gym["status"]);

      // ✅ if gym blocked
      if ($gym_status === "inactive" || $gym_status === "suspended") {
        $billing_required = 1;
        $billing_reason = "gym_inactive";
      }
    } else {
      $billing_required = 1;
      $billing_reason = "gym_not_found";
    }

    // ✅ invoice check only if not already locked
    if ($billing_required === 0) {
      $invStmt = $conn->prepare("
        SELECT id, status, due_date
        FROM invoices
        WHERE gym_id = :gym_id
        ORDER BY id DESC
        LIMIT 1
      ");
      $invStmt->execute([":gym_id" => $user["gym_id"]]);
      $inv = $invStmt->fetch(PDO::FETCH_ASSOC);

      if ($inv) {
        $invStatus = strtolower($inv["status"]);
        $due = $inv["due_date"];

        if ($invStatus === "unpaid" && !empty($due) && strtotime($due) < time()) {
          $billing_required = 1;
          $billing_reason = "invoice_overdue";
        }
      }
    }
  }

  echo json_encode([
    "status" => true,
    "data" => [
      "id" => (int)$user["id"],
      "gym_id" => $user["gym_id"] ? (int)$user["gym_id"] : null,
      "role" => $user["role"],
      "email" => $user["email"],
      "name" => trim(($user["first_name"] ?? "") . " " . ($user["last_name"] ?? "")),
      "avatar" => $user["avatar"],

      // ✅ billing fields
      "gym_status" => $gym_status,
      "billing_required" => (int)$billing_required,
      "billing_reason" => $billing_reason,
    ]
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to load profile",
    "error" => $e->getMessage()
  ]);
  exit;
}
