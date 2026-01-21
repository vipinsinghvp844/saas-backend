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
  $payment_method = trim($data['payment_method'] ?? 'upi'); // cash/card/upi/net_bankning/stripe etc
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

  if (($inv['status'] ?? '') === 'paid') {
    throw new Exception("Invoice already paid");
  }

  // ✅ mark invoice paid
  $upd = $conn->prepare("
    UPDATE invoices
    SET status='paid', paid_at=NOW()
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
    ":invoice_id" => $inv['id'],
    ":gym_id" => $inv['gym_id'],
    ":amount" => $inv['amount'],
    ":currency" => $inv['currency'] ?? 'INR',
    ":payment_method" => $payment_method,
    ":payment_ref" => $payment_ref ?: null,
    ":transaction_id" => $transaction_id ?: null,
  ]);

  /* ==========================================
     ✅ NEW: Activate Gym after payment success
  ========================================== */

  // ✅ set gym billing active (and activate gym status if suspended)
  $gupd = $conn->prepare("
    UPDATE gyms
    SET billing_status='active',
        status='active'
    WHERE id=:gym_id
  ");
  $gupd->execute([":gym_id" => $inv['gym_id']]);

  // ✅ activate subscription:
  // trial -> active (or inactive -> active if you use)
  $subUpd = $conn->prepare("
    UPDATE gym_subscriptions
    SET status='active'
    WHERE gym_id=:gym_id
      AND status IN ('trial','inactive')
    ORDER BY id DESC
    LIMIT 1
  ");
  $subUpd->execute([":gym_id" => $inv['gym_id']]);

  $conn->commit();

  echo json_encode([
    "status" => true,
    "message" => "Invoice paid ✅ Payment recorded ✅ Gym activated ✅ Subscription active ✅"
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
