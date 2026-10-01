<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
global $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isPost()) { http_response_code(405); echo json_encode(['ok'=>false,'msg'=>'Method not allowed']); exit; }
require_csrf_json();

$user = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
if ($user === '') { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'Unauthorized']); exit; }

$orderId = (int)($_POST['order_id'] ?? 0);
$utr = strtoupper(trim((string)($_POST['utr'] ?? '')));

if ($orderId <= 0) { echo json_encode(['ok'=>false,'msg'=>'Order missing']); exit; }
if (strlen($utr) < 6) { echo json_encode(['ok'=>false,'msg'=>'Enter valid UTR']); exit; }

try {
  // only allow for user's own order and pending status
  $st = $conn->prepare("SELECT id, order_no, payment_status FROM orders WHERE id=? AND user_email=? LIMIT 1");
  $st->bind_param("is", $orderId, $user);
  $st->execute();
  $o = $st->get_result()->fetch_assoc();
  $st->close();

  if (!$o) { echo json_encode(['ok'=>false,'msg'=>'Order not found']); exit; }

  $ps = (string)($o['payment_status'] ?? '');
  if ($ps === 'paid') {
    echo json_encode(['ok'=>true,'msg'=>'Already paid', 'order_no'=>$o['order_no']]);
    exit;
  }

  // save UTR and mark as submitted (NOT paid)
  $up = $conn->prepare("
    UPDATE orders
    SET payment_status='submitted',
        payment_method='UPI_QR',
        payment_ref=?,
        payment_submitted_at=NOW()
    WHERE id=? AND user_email=?
  ");
  $up->bind_param("sis", $utr, $orderId, $user);
  $up->execute();
  $up->close();

  // clear cart since order is now finalized (awaiting admin verification)
  cart_clear();

  echo json_encode([
    'ok'=>true,
    'order_no'=>$o['order_no'],
    'msg'=>'Payment submitted. Waiting for verification.'
  ]);
} catch(Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}