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

$addrId = (int)($_POST['addr'] ?? 0);
if ($addrId <= 0) { echo json_encode(['ok'=>false,'msg'=>'Address required']); exit; }

$cart = cart_items();
if (empty($cart)) { echo json_encode(['ok'=>false,'msg'=>'Cart empty']); exit; }

// Address
$stmt = $conn->prepare("SELECT * FROM user_addresses WHERE id=? AND user_email=? LIMIT 1");
$stmt->bind_param("is", $addrId, $user);
$stmt->execute();
$addr = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$addr) { echo json_encode(['ok'=>false,'msg'=>'Invalid address']); exit; }

$pincode = (string)($addr['pincode'] ?? '');
$addrJson = json_encode($addr, JSON_UNESCAPED_UNICODE);

// Load products
$productIds = array_values(array_filter(array_map('intval', array_keys($cart))));
if (!$productIds) { echo json_encode(['ok'=>false,'msg'=>'Cart empty']); exit; }

$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$types = str_repeat('i', count($productIds));

$stmtP = $conn->prepare("SELECT id, price, stock, status FROM products WHERE id IN ($placeholders)");
$stmtP->bind_param($types, ...$productIds);
$stmtP->execute();
$products = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtP->close();

$map = [];
foreach ($products as $p) $map[(int)$p['id']] = $p;

// Totals
$total = 0.0;
foreach ($cart as $pid=>$qty) {
  $pid=(int)$pid; $qty=(int)$qty;
  if ($qty<=0) continue;

  if (!isset($map[$pid])) { echo json_encode(['ok'=>false,'msg'=>"Product not found (ID $pid)"]); exit; }
  if (($map[$pid]['status'] ?? '') !== 'live') { echo json_encode(['ok'=>false,'msg'=>'Product not available']); exit; }
  if ((int)$map[$pid]['stock'] < $qty) { echo json_encode(['ok'=>false,'msg'=>'Low stock']); exit; }

  $total += (float)$map[$pid]['price'] * $qty;
}

// Coupon
$couponCode = null;
$discount = 0.0;

if (!empty($_SESSION['coupon']['code'])) {
  $couponCode = strtoupper((string)$_SESSION['coupon']['code']);

  $stmtC = $conn->prepare("SELECT * FROM coupons WHERE code=? AND active=1 LIMIT 1");
  $stmtC->bind_param("s", $couponCode);
  $stmtC->execute();
  $c = $stmtC->get_result()->fetch_assoc();
  $stmtC->close();

  if ($c) {
    if (($c['type'] ?? '') === 'percent') {
      $discount = $total * ((float)$c['value'] / 100.0);
      if ((float)$c['max_discount'] > 0) $discount = min($discount, (float)$c['max_discount']);
    } else {
      $discount = (float)$c['value'];
    }
    $discount = max(0.0, min($discount, $total));
  } else {
    $couponCode = null;
    unset($_SESSION['coupon']);
  }
}

$net = max(0.0, $total - $discount);

// Delivery (returns eta as YYYY-MM-DD)
$q = delivery_quote($conn, $pincode, $net, $user);
if ($q === null) { echo json_encode(['ok'=>false,'msg'=>'Delivery not available to this pincode']); exit; }

$delivery = (float)$q['fee'];
$etaDate  = (string)$q['eta'];      // ✅ must be string "YYYY-MM-DD"
$grand    = $net + $delivery;

$orderNo = 'SB' . date('YmdHis') . random_int(100, 999);

// UPI URI (works with only UPI ID)
$vpa  = defined('UPI_VPA') ? UPI_VPA : '';
$name = defined('UPI_PAYEE_NAME') ? UPI_PAYEE_NAME : 'SmartBuy Store';
if ($vpa === '') { echo json_encode(['ok'=>false,'msg'=>'UPI_VPA not configured in constants.php']); exit; }

$upiUri = "upi://pay?pa=".rawurlencode($vpa)
  ."&pn=".rawurlencode($name)
  ."&am=".rawurlencode(number_format($grand, 2, '.', ''))
  ."&cu=INR"
  ."&tn=".rawurlencode("Order ".$orderNo);

// QR image via free QR server (no library needed)
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=" . rawurlencode($upiUri);

try {
  $conn->begin_transaction();

  // ✅ IMPORTANT: bind types must match params
  // order_no(s), user(s), total(d), discount(d), coupon(s), delivery(d), eta(s), grand(d), payMethod(s), addrJson(s)
  $payMethod = 'UPI_QR';

  $stmtO = $conn->prepare("
    INSERT INTO orders
      (order_no,user_email,total,discount_amount,coupon_code,delivery_charge,delivery_eta,grand_total,
       payment_method,payment_status,order_status,address_json,created_at)
    VALUES
      (?,?,?,?,?,?,?,?,?,'pending','placed',?,NOW())
  ");

  // ✅ FIXED TYPES STRING: ssddsdsdss
  $stmtO->bind_param(
    "ssddsdsdss",
    $orderNo, $user, $total, $discount, $couponCode,
    $delivery, $etaDate, $grand, $payMethod, $addrJson
  );

  $stmtO->execute();
  $orderId = (int)$stmtO->insert_id;
  $stmtO->close();

  if ($orderId <= 0) throw new Exception("Order create failed");

  // Insert items (no stock reduce until admin verifies / or you can reduce later)
  $ins = $conn->prepare("INSERT INTO order_items(order_id,product_id,qty,price,subtotal) VALUES (?,?,?,?,?)");
  foreach ($cart as $pid=>$qty) {
    $pid=(int)$pid; $qty=(int)$qty;
    if ($qty<=0) continue;
    $price = (float)$map[$pid]['price'];
    $sub   = $price * $qty;

    $ins->bind_param("iiidd", $orderId, $pid, $qty, $price, $sub);
    $ins->execute();
  }
  $ins->close();

  // Optional payments record
  if ($conn->query("SHOW TABLES LIKE 'payments'")->num_rows > 0) {
    $stmtPay = $conn->prepare("
      INSERT INTO payments(order_id,provider,provider_order_id,status,amount)
      VALUES (?, 'upi', '', 'pending', ?)
    ");
    $stmtPay->bind_param("id", $orderId, $grand);
    $stmtPay->execute();
    $stmtPay->close();
  }

  $conn->commit();

  echo json_encode([
    'ok'=>true,
    'order_id'=>$orderId,
    'order_no'=>$orderNo,
    'upi_uri'=>$upiUri,
    'qr_png'=>$qrUrl,
    'amount'=>$grand,
    'delivery_fee'=>$delivery,
    'delivery_eta'=>$etaDate,
  ]);
} catch(Throwable $e){
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}