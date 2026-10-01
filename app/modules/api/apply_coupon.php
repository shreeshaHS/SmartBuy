<?php
require_once __DIR__ . '/../../config/bootstrap.php';
global $conn;

if(!isPost()) json_response(['ok'=>false,'error'=>'POST required'],405);
require_csrf_json();
requireLogin();

$code = strtoupper(trim($_POST['code'] ?? ''));
if($code==='') json_response(['ok'=>false,'error'=>'Enter coupon code'],422);

// compute cart total
$cart = $_SESSION['cart'] ?? [];
$total = 0;
foreach($cart as $pid=>$qty){
  $pid=(int)$pid; $qty=(int)$qty;
  $p = $conn->query("SELECT price FROM products WHERE id=$pid")->fetch_assoc();
  if(!$p) continue;
  $total += (float)$p['price'] * $qty;
}
if($total<=0) json_response(['ok'=>false,'error'=>'Cart is empty'],422);

$stmt = $conn->prepare("SELECT * FROM coupons WHERE code=? AND active=1 LIMIT 1");
$stmt->bind_param("s",$code);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();
if(!$c) json_response(['ok'=>false,'error'=>'Invalid coupon'],404);

$now = date('Y-m-d H:i:s');
if($c['start_at'] && $now < $c['start_at']) json_response(['ok'=>false,'error'=>'Coupon not started'],422);
if($c['end_at'] && $now > $c['end_at']) json_response(['ok'=>false,'error'=>'Coupon expired'],422);
if((float)$c['min_cart']>0 && $total < (float)$c['min_cart']) json_response(['ok'=>false,'error'=>'Minimum cart ₹'.(float)$c['min_cart']],422);
if((int)$c['usage_limit']>0 && (int)$c['used_count'] >= (int)$c['usage_limit']) json_response(['ok'=>false,'error'=>'Coupon limit reached'],422);

// Calculate discount preview
$discount = 0;
if($c['type']==='percent'){
  $discount = $total * ((float)$c['value']/100.0);
  if((float)$c['max_discount']>0) $discount = min($discount,(float)$c['max_discount']);
} else {
  $discount = (float)$c['value'];
}
$discount = max(0, min($discount, $total));

$_SESSION['coupon'] = ['code'=>$code,'discount'=>$discount];
json_response(['ok'=>true,'code'=>$code,'discount'=>round($discount,2)]);
