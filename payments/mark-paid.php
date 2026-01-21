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

  $payment_id = (int)($data['payment_id'] ?? 0);

  if (!$payment_id) {
    http_response_code(400);
    echo json_encode(["status"=>false,"message"=>"payment_id required"]);
    exit;
  }

  $db = new Database();
  $conn = $db->connect();

  // ✅ fetch payment
  $stmt = $conn->prepare("SELECT * FROM payments WHERE id=:id LIMIT 1");
  $stmt->execute([":id" => $payment_id]);
  $payment = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$payment) {
    http_response_code(404);
    echo json_encode(["status"=>false,"message"=>"Payment not found"]);
    exit;
  }

  // ✅ already paid
  if (strtolower($payment['status']) === "paid") {
    echo json_encode(["status"=>true,"message"=>"Payment already paid"]);
    exit;
  }

  $conn->beginTransaction();

  // ✅ update payment status
  $upd = $conn->prepare("
    UPDATE payments
    SET status='paid',
        paid_at=NOW()
    WHERE id=:id
  ");
  $upd->execute([":id" => $payment_id]);

  // ✅ if invoice exists mark invoice paid too
  if (!empty($payment['invoice_id'])) {
    $inv = $conn->prepare("
      UPDATE invoices
      SET status='paid',
          paid_at=NOW()
      WHERE id=:invoice_id
    ");
    $inv->execute([":invoice_id" => $payment['invoice_id']]);
  }

  $conn->commit();

  echo json_encode([
    "status"=>true,
    "message"=>"Payment marked as paid ✅"
  ]);
  exit;

} catch (Exception $e) {

  if (isset($conn)) $conn->rollBack();

  http_response_code(500);
  echo json_encode([
    "status"=>false,
    "message"=>"Failed to mark payment paid",
    "error"=>$e->getMessage()
  ]);
  exit;
}
