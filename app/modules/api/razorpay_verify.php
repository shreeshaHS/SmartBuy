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
if ($user === '') {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'Unauthorized']);
  exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
$paymentId = trim((string)($_POST['razorpay_payment_id'] ?? ''));

if ($orderId <= 0 || $paymentId === '') {
  echo json_encode(['ok'=>false,'msg'=>'Invalid payment data']);
  exit;
}

// ensure order belongs to user
$stmt = $conn->prepare("SELECT id, payment_status FROM orders WHERE id=? AND user_email=? LIMIT 1");
$stmt->bind_param("is", $orderId, $user);
$stmt->execute();
$o = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$o) {
  echo json_encode(['ok'=>false,'msg'=>'Order not found']);
  exit;
}

try {
  $conn->begin_transaction();

  // mark order paid_unverified
  $stO = $conn->prepare("UPDATE orders SET payment_status='paid_unverified' WHERE id=?");
  $stO->bind_param("i", $orderId);
  $stO->execute();
  $stO->close();

  // update payments row
  $stP = $conn->prepare("
    UPDATE payments
    SET provider_payment_id=?, status='paid_unverified'
    WHERE order_id=? AND provider='razorpay'
    LIMIT 1
  ");
  $stP->bind_param("si", $paymentId, $orderId);
  $stP->execute();
  $stP->close();

  // OPTIONAL: reserve stock now (recommended)
  // reduce stock for each item
  $it = $conn->prepare("SELECT product_id, qty FROM order_items WHERE order_id=?");
  $it->bind_param("i", $orderId);
  $it->execute();
  $items = $it->get_result()->fetch_all(MYSQLI_ASSOC);
  $it->close();

  $upd = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id=? AND stock >= ?");
  foreach($items as $r){
    $pid = (int)$r['product_id'];
    $qty = (int)$r['qty'];
    $upd->bind_param("iii", $qty, $pid, $qty);
    $upd->execute();
    if ($upd->affected_rows <= 0) throw new Exception("Low stock for product $pid");
  }
  $upd->close();

  // clear cart
  cart_clear();

  $conn->commit();
  echo json_encode(['ok'=>true,'msg'=>'Payment stored (unverified)']);
} catch(Throwable $e){
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}