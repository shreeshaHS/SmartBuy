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

$addrId  = (int)($_POST['addr'] ?? 0);
$payment = 'COD';

$cart = cart_items();
if (empty($cart)) {
  echo json_encode(['ok'=>false,'msg'=>'Cart empty']);
  exit;
}

if ($addrId <= 0) {
  echo json_encode(['ok'=>false,'msg'=>'Select address']);
  exit;
}

// Address must belong to user
$stmt = $conn->prepare("SELECT * FROM user_addresses WHERE id=? AND user_email=? LIMIT 1");
$stmt->bind_param("is", $addrId, $user);
$stmt->execute();
$addr = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$addr) {
  echo json_encode(['ok'=>false,'msg'=>'Select valid address']);
  exit;
}

$pincode  = (string)($addr['pincode'] ?? '');
$addrJson = json_encode($addr, JSON_UNESCAPED_UNICODE);

// Load products in one query
$productIds = array_values(array_filter(array_map('intval', array_keys($cart))));
if (!$productIds) {
  echo json_encode(['ok'=>false,'msg'=>'Cart invalid']);
  exit;
}

$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$types = str_repeat('i', count($productIds));

$stmtP = $conn->prepare("
  SELECT id, name, price, stock, status, seller_email
  FROM products
  WHERE id IN ($placeholders)
");
$stmtP->bind_param($types, ...$productIds);
$stmtP->execute();
$products = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtP->close();

$map = [];
foreach ($products as $p) $map[(int)$p['id']] = $p;

// Compute total + validate
$total = 0.0;
foreach ($cart as $pid => $qty) {
  $pid=(int)$pid; $qty=(int)$qty;
  if ($qty<=0) continue;

  if (!isset($map[$pid])) {
    echo json_encode(['ok'=>false,'msg'=>"Product not found (ID $pid)"]);
    exit;
  }
  if (($map[$pid]['status'] ?? 'live') !== 'live') {
    echo json_encode(['ok'=>false,'msg'=>"Product not available (ID $pid)"]);
    exit;
  }
  if ((int)$map[$pid]['stock'] < $qty) {
    echo json_encode(['ok'=>false,'msg'=>"Low stock for product ID $pid"]);
    exit;
  }
  $total += (float)$map[$pid]['price'] * $qty;
}

// Coupon (optional)
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
    $now = date('Y-m-d H:i:s');
    $startOk = (empty($c['start_at']) || $now >= $c['start_at']);
    $endOk   = (empty($c['end_at'])   || $now <= $c['end_at']);
    $minOk   = ((float)$c['min_cart'] <= 0 || $total >= (float)$c['min_cart']);
    $limitOk = ((int)$c['usage_limit'] <= 0 || (int)$c['used_count'] < (int)$c['usage_limit']);

    if ($startOk && $endOk && $minOk && $limitOk) {
      if (($c['type'] ?? '') === 'percent') {
        $discount = $total * ((float)$c['value'] / 100.0);
        if ((float)$c['max_discount'] > 0) $discount = min($discount, (float)$c['max_discount']);
      } else {
        $discount = (float)$c['value'];
      }
      $discount = max(0.0, min($discount, $total));
    } else {
      $couponCode = null;
      $discount = 0.0;
      unset($_SESSION['coupon']);
    }
  } else {
    $couponCode = null;
    unset($_SESSION['coupon']);
  }
}

$net = max(0.0, $total - $discount);

// Delivery quote (FREE_999/FREE_499/FIRST_ORDER)
$q = delivery_quote($conn, $pincode, $net, $user);
if ($q === null) {
  echo json_encode(['ok'=>false,'msg'=>'Sorry, delivery not available to this pincode']);
  exit;
}

$delivery = (float)$q['fee'];
$etaDate  = (string)$q['eta'];

$grand = $net + $delivery;
$orderNo = 'SB' . date('YmdHis') . random_int(100, 999);

// ✅ FIX: avoid NULL in bind
$couponCodeDb = $couponCode ?? '';

try {
  $conn->begin_transaction();

  $stmtO = $conn->prepare("
    INSERT INTO orders
      (order_no,user_email,total,discount_amount,coupon_code,delivery_charge,delivery_eta,grand_total,
       payment_method,payment_status,order_status,address_json,created_at)
    VALUES
      (?,?,?,?,?,?,?,?,?,'pending','placed',?,NOW())
  ");

  // order_no(s), user_email(s), total(d), discount(d), coupon(s), delivery(d),
  // delivery_eta(s), grand_total(d), payment(s), address_json(s)
  $stmtO->bind_param(
    "ssddsdsdss",
    $orderNo,
    $user,
    $total,
    $discount,
    $couponCodeDb,
    $delivery,
    $etaDate,
    $grand,
    $payment,
    $addrJson
  );

  $stmtO->execute();
  $orderId = (int)$stmtO->insert_id;
  $stmtO->close();

  if ($orderId <= 0) throw new Exception("Order create failed");

  // Items + reduce stock now (COD = reserve stock immediately)
  $ins = $conn->prepare("INSERT INTO order_items(order_id,product_id,qty,price,subtotal) VALUES (?,?,?,?,?)");
  $upd = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id=? AND stock >= ?");

  foreach ($cart as $pid => $qty) {
    $pid=(int)$pid; $qty=(int)$qty;
    if ($qty<=0) continue;

    $price = (float)$map[$pid]['price'];
    $sub = $price * $qty;

    $ins->bind_param("iiidd", $orderId, $pid, $qty, $price, $sub);
    $ins->execute();

    $upd->bind_param("iii", $qty, $pid, $qty);
    $upd->execute();
    if ($upd->affected_rows <= 0) throw new Exception("Stock update failed for product $pid");
  }
  $ins->close();
  $upd->close();

  // Coupon bookkeeping
  if ($couponCode && $discount > 0) {
    $stmtU = $conn->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE code=?");
    $stmtU->bind_param("s", $couponCode);
    $stmtU->execute();
    $stmtU->close();

    $stmtR = $conn->prepare("
      INSERT INTO coupon_redemptions(code,user_email,order_id,discount_amount,created_at)
      VALUES (?,?,?,?,NOW())
    ");
    $stmtR->bind_param("ssid", $couponCode, $user, $orderId, $discount);
    $stmtR->execute();
    $stmtR->close();

    unset($_SESSION['coupon']);
  }

  // Clear cart BEFORE commit is okay, but keep after commit too (safe)
  cart_clear();

  $conn->commit();

  // ===========================
  // 📧 EMAILS (Customer + Admin)
  // ===========================
  $customerName = (string)($addr['name'] ?? 'Customer');

  $mailItems = [];
  foreach ($cart as $pid => $qty) {
    $pid = (int)$pid;
    $qty = (int)$qty;
    if ($qty <= 0) continue;

    $mailItems[] = [
      'name'  => (string)($map[$pid]['name'] ?? ('Product #'.$pid)),
      'qty'   => $qty,
      'price' => (float)($map[$pid]['price'] ?? 0),
    ];
  }

  $totals = [
    'subtotal' => (float)$total,
    'discount' => (float)$discount,
    'delivery' => (float)$delivery,
    'total'    => (float)$grand,
  ];

  // 1) Customer mail (uses your template system)
  if (function_exists('sendOrderPlacedMail')) {
    sendOrderPlacedMail($user, $customerName, $orderNo, $mailItems, $totals);
  }

  // 2) Admin mail (optional)
  if (!defined('ADMIN_EMAIL')) {
    // if you didn't define admin email, skip silently
  } else if (defined('ADMIN_EMAIL') && ADMIN_EMAIL && function_exists('sendMail')) {
    $subject = "New COD Order - {$orderNo}";
    $html = "
      <h2>New Order Received</h2>
      <p><b>Order:</b> ".htmlspecialchars($orderNo)."</p>
      <p><b>Customer:</b> ".htmlspecialchars($user)."</p>
      <p><b>Total:</b> ₹".number_format((float)$grand,2)."</p>
      <p><b>ETA:</b> ".htmlspecialchars($etaDate)."</p>
    ";
    @sendMail(ADMIN_EMAIL, 'Admin', $subject, $html);
  }

  // 3) Invoice mail hook (future)
  /*
  if (function_exists('sendOrderPlacedWithInvoiceMail')) {
    @sendOrderPlacedWithInvoiceMail($conn, $orderId, $user, $customerName, $orderNo, $mailItems, $totals);
  }
  */

  echo json_encode([
    'ok'=>true,
    'order_no'=>$orderNo,
    'delivery_fee'=>$delivery,
    'delivery_eta'=>$etaDate
  ]);
} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}