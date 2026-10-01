<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
global $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isPost()) {
  http_response_code(405);
  echo json_encode(['ok'=>false,'msg'=>'Method not allowed']);
  exit;
}
require_csrf_json();

$user = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
if ($user === '') { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'Unauthorized']); exit; }

$orderId = (int)($_POST['order_id'] ?? 0);
$payId   = trim((string)($_POST['razorpay_payment_id'] ?? ''));

if ($orderId <= 0 || $payId === '') {
  echo json_encode(['ok'=>false,'msg'=>'Invalid payment data']);
  exit;
}

// ✅ ensure order belongs to logged-in user
$st = $conn->prepare("SELECT id,payment_status FROM orders WHERE id=? AND user_email=? LIMIT 1");
$st->bind_param("is", $orderId, $user);
$st->execute();
$o = $st->get_result()->fetch_assoc();
$st->close();

if (!$o) { echo json_encode(['ok'=>false,'msg'=>'Order not found']); exit; }

try {
  $conn->begin_transaction();

  // save payment record
  $provider = 'razorpay';
  $status = 'paid';
  $stP = $conn->prepare("
    INSERT INTO payments(order_id,provider,provider_payment_id,status,created_at)
    VALUES (?,?,?,?,NOW())
  ");
  $stP->bind_param("isss", $orderId, $provider, $payId, $status);
  $stP->execute();
  $stP->close();

  // mark order paid
  $stU = $conn->prepare("UPDATE orders SET payment_status='paid' WHERE id=? AND user_email=?");
  $stU->bind_param("is", $orderId, $user);
  $stU->execute();
  $stU->close();

  $conn->commit();

  // clear cart
  cart_clear();

  echo json_encode(['ok'=>true,'msg'=>'Payment saved']);
} catch(Throwable $e){
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}