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

  $data = json_decode(file_get_contents("php://input"), true);

  $gym_id = (int)($data['gym_id'] ?? 0);
  $plan_id = (int)($data['plan_id'] ?? 0);
  $amount = (float)($data['amount'] ?? 0);
  $currency = trim($data['currency'] ?? "INR");
  $due_date = $data['due_date'] ?? null; // optional
  $notes = trim($data['notes'] ?? '');

  if (!$gym_id) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "gym_id required"]);
    exit;
  }

  if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "amount must be > 0"]);
    exit;
  }

  $db = new Database();
  $conn = $db->connect();

  // ✅ Generate invoice number: INV-YYYYMM-0001
  $prefix = "INV-" . date("Ym") . "-";

  $stmt = $conn->prepare("
    SELECT invoice_number
    FROM invoices
    WHERE invoice_number LIKE :prefix
    ORDER BY id DESC
    LIMIT 1
  ");
  $stmt->execute([":prefix" => $prefix . "%"]);
  $last = $stmt->fetchColumn();

  $next = 1;
  if ($last) {
    $parts = explode("-", $last); // INV / YYYYMM / 0001
    $lastSeq = (int)end($parts);
    $next = $lastSeq + 1;
  }

  $invoice_number = $prefix . str_pad((string)$next, 4, "0", STR_PAD_LEFT);

  $ins = $conn->prepare("
    INSERT INTO invoices
      (invoice_number, gym_id, plan_id, amount, currency, status, issued_at, due_date, notes)
    VALUES
      (:invoice_number, :gym_id, :plan_id, :amount, :currency, 'unpaid', NOW(), :due_date, :notes)
  ");

  $ins->execute([
    ":invoice_number" => $invoice_number,
    ":gym_id" => $gym_id,
    ":plan_id" => $plan_id ?: null,
    ":amount" => $amount,
    ":currency" => $currency,
    ":due_date" => $due_date,
    ":notes" => $notes
  ]);

  echo json_encode([
    "status" => true,
    "message" => "Invoice created ✅",
    "data" => [
      "id" => $conn->lastInsertId(),
      "invoice_number" => $invoice_number
    ]
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to create invoice",
    "error" => $e->getMessage()
  ]);
  exit;
}
