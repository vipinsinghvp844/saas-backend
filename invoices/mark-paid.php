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

  $invoice_id = (int)($data['invoice_id'] ?? 0);
  $payment_method = trim($data['payment_method'] ?? 'upi');
  $payment_ref = trim($data['payment_ref'] ?? '');
  $transaction_id = trim($data['transaction_id'] ?? '');

  if (!$invoice_id) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "invoice_id required"]);
    exit;
  }

  $db = new Database();
  $conn = $db->connect();

  $conn->beginTransaction();

  // ✅ fetch invoice
  $stmt = $conn->prepare("SELECT * FROM invoices WHERE id=:id LIMIT 1");
  $stmt->execute([":id" => $invoice_id]);
  $inv = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$inv) {
    throw new Exception("Invoice not found");
  }

  if (strtolower($inv['status'] ?? '') === 'paid') {
    throw new Exception("Invoice already paid");
  }

  // ✅ mark invoice paid
  $upd = $conn->prepare("
    UPDATE invoices
    SET status='paid', paid_at=NOW(), updated_at=NOW()
    WHERE id=:id
  ");
  $upd->execute([":id" => $invoice_id]);

  // ✅ insert payment record
  $pay = $conn->prepare("
    INSERT INTO payments
      (invoice_id, gym_id, amount, currency, payment_method, payment_for, status, payment_ref, transaction_id, paid_at, created_at)
    VALUES
      (:invoice_id, :gym_id, :amount, :currency, :payment_method, 'subscription', 'paid', :payment_ref, :transaction_id, NOW(), NOW())
  ");

  $pay->execute([
    ":invoice_id" => (int)$inv['id'],
    ":gym_id" => (int)$inv['gym_id'],
    ":amount" => (float)$inv['amount'],
    ":currency" => $inv['currency'] ?? 'INR',
    ":payment_method" => $payment_method,
    ":payment_ref" => $payment_ref !== "" ? $payment_ref : null,
    ":transaction_id" => $transaction_id !== "" ? $transaction_id : null,
  ]);

  $paymentId = (int)$conn->lastInsertId();

  /* ==========================================
     ✅ Update gym_requests payment status
  ========================================== */
  $reqUpd = $conn->prepare("
    UPDATE gym_requests
    SET payment_status='paid',
        payment_id=:payment_id
    WHERE invoice_id=:invoice_id
    LIMIT 1
  ");
  $reqUpd->execute([
    ":payment_id" => $paymentId,
    ":invoice_id" => (int)$inv['id']
  ]);

  /* ==========================================
     ✅ Activate Gym + Billing Paid
  ========================================== */
  $gupd = $conn->prepare("
    UPDATE gyms
    SET billing_status='paid',
        status='active'
    WHERE id=:gym_id
  ");
  $gupd->execute([":gym_id" => (int)$inv['gym_id']]);

  /* ==========================================
     ✅ Activate Latest Subscription
     trial -> active
  ========================================== */
  $subUpd = $conn->prepare("
    UPDATE gym_subscriptions
    SET status='active',
        updated_at=NOW()
    WHERE gym_id=:gym_id
      AND status IN ('trial','inactive')
    ORDER BY id DESC
    LIMIT 1
  ");
  $subUpd->execute([":gym_id" => (int)$inv['gym_id']]);

  $conn->commit();

  echo json_encode([
    "status" => true,
    "message" => "Invoice paid ✅ Payment recorded ✅ Gym activated ✅ Subscription active ✅",
    "data" => [
      "invoice_id" => (int)$inv['id'],
      "gym_id" => (int)$inv['gym_id'],
      "payment_id" => $paymentId
    ]
  ]);
  exit;

} catch (Exception $e) {
  if (isset($conn)) $conn->rollBack();

  http_response_code(500);
  echo json_encode([
    "status" => false,
    "message" => "Failed to mark invoice paid",
    "error" => $e->getMessage()
  ]);
  exit;
}
