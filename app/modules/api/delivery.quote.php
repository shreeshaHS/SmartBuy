<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
global $conn;

header('Content-Type: application/json; charset=utf-8');
if (!isPost()) { http_response_code(405); echo json_encode(['ok'=>false,'msg'=>'Method not allowed']); exit; }
require_csrf_json();

$user = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
if ($user === '') { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'Unauthorized']); exit; }

$addrId = (int)($_POST['addr'] ?? 0);
if ($addrId <= 0) { echo json_encode(['ok'=>false,'msg'=>'Address required']); exit; }

$stmt = $conn->prepare("SELECT pincode FROM user_addresses WHERE id=? AND user_email=? LIMIT 1");
$stmt->bind_param("is", $addrId, $user);
$stmt->execute();
$a = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$a) { echo json_encode(['ok'=>false,'msg'=>'Invalid address']); exit; }

$pincode = (string)$a['pincode'];

// cart total (basic)
$cart = cart_items();
if (empty($cart)) { echo json_encode(['ok'=>false,'msg'=>'Cart empty']); exit; }

// compute subtotal
$total = 0.0;
foreach ($cart as $pid=>$qty){
  $pid=(int)$pid; $qty=(int)$qty;
  $p = $conn->query("SELECT price FROM products WHERE id=$pid")->fetch_assoc();
  if(!$p) continue;
  $total += (float)$p['price'] * $qty;
}

// apply coupon discount if you want (optional) else 0
$discount = 0.0;
$net = max(0.0, $total - $discount);

// quote
$q = delivery_quote($conn, $pincode, $net, $user);
if ($q === null) {
  echo json_encode(['ok'=>false,'serviceable'=>false,'msg'=>'Delivery not available']);
  exit;
}

echo json_encode([
  'ok'=>true,
  'serviceable'=>true,
  'fee'=>$q['fee'],
  'eta'=>$q['eta'],
  'days'=>$q['days'],
  'msg'=>$q['msg']
]);