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

require_csrf_json(); // your project function

if (!defined('RAZORPAY_KEY_ID') || RAZORPAY_KEY_ID==='') {
  echo json_encode(['ok'=>false,'msg'=>'Razorpay KEY_ID not configured in constants.php']);
  exit;
}

$user = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
if ($user === '') {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'Unauthorized']);
  exit;
}

$addrId = (int)($_POST['addr'] ?? 0);
if ($addrId<=0) { echo json_encode(['ok'=>false,'msg'=>'Address required']); exit; }

// load address
$stmt = $conn->prepare("SELECT * FROM user_addresses WHERE id=? AND user_email=? LIMIT 1");
$stmt->bind_param("is", $addrId, $user);
$stmt->execute();
$addr = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$addr) { echo json_encode(['ok'=>false,'msg'=>'Invalid address']); exit; }

$addrJson = json_encode($addr, JSON_UNESCAPED_UNICODE);

// cart total (use your cart helper)
$cart = cart_items();
if (empty($cart)) { echo json_encode(['ok'=>false,'msg'=>'Cart empty']); exit; }

$productIds = array_values(array_filter(array_map('intval', array_keys($cart))));
$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$types = str_repeat('i', count($productIds));

$stmtP = $conn->prepare("SELECT id, price, stock, status FROM products WHERE id IN ($placeholders)");
$stmtP->bind_param($types, ...$productIds);
$stmtP->execute();
$products = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtP->close();

$map = [];
foreach($products as $p) $map[(int)$p['id']] = $p;

$total = 0.0;
foreach($cart as $pid=>$qty){
  $pid=(int)$pid; $qty=(int)$qty;
  if($qty<=0) continue;

  if(!isset($map[$pid])) { echo json_encode(['ok'=>false,'msg'=>"Product not found (ID $pid)"]); exit; }
  if(($map[$pid]['status'] ?? 'live') !== 'live') { echo json_encode(['ok'=>false,'msg'=>'Product not available']); exit; }
  if((int)$map[$pid]['stock'] < $qty) { echo json_encode(['ok'=>false,'msg'=>'Low stock']); exit; }

  $total += (float)$map[$pid]['price'] * $qty;
}

// delivery (optional) – keep 0 if you want
$delivery = 0.0;
$grand = $total + $delivery;

$orderNo = 'SB' . date('YmdHis') . random_int(100,999);

try{
  $conn->begin_transaction();

  $method = 'RAZORPAY';
  $payStatus = 'pending';
  $orderStatus = 'placed';

  $stmtO = $conn->prepare("
    INSERT INTO orders
      (order_no,user_email,total,discount_amount,coupon_code,delivery_charge,delivery_eta,grand_total,
       payment_method,payment_status,order_status,address_json,created_at)
    VALUES
      (?,?,?,0,NULL,?,NULL,?, ?, ?, ?, ?, NOW())
  ");
  $stmtO->bind_param("ssddssssss",
    $orderNo, $user, $total,
    $delivery, $grand,
    $method, $payStatus, $orderStatus,
    $addrJson
  );
  $stmtO->execute();
  $orderId = (int)$stmtO->insert_id;
  $stmtO->close();

  if($orderId<=0) throw new Exception("Order create failed");

  $ins = $conn->prepare("INSERT INTO order_items(order_id,product_id,qty,price,subtotal) VALUES (?,?,?,?,?)");
  foreach($cart as $pid=>$qty){
    $pid=(int)$pid; $qty=(int)$qty;
    if($qty<=0) continue;
    $price = (float)$map[$pid]['price'];
    $sub = $price * $qty;
    $ins->bind_param("iiidd", $orderId, $pid, $qty, $price, $sub);
    $ins->execute();
  }
  $ins->close();

  $conn->commit();

  echo json_encode([
    'ok'=>true,
    'order_id'=>$orderId,
    'order_no'=>$orderNo,
    'amount'=>(int)round($grand*100),
    'key_id'=>RAZORPAY_KEY_ID,
    'name'=>defined('APP_NAME')?APP_NAME:'SmartBuy'
  ]);

}catch(Throwable $e){
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}