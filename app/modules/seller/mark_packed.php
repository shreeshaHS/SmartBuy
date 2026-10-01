<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
requireRole('seller');
global $conn;

if (!isPost()) { http_response_code(405); exit("Method not allowed"); }
require_csrf();

$seller  = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
$orderNo = trim($_POST['order_no'] ?? '');

if ($seller === '' || $orderNo === '') redirect('seller/orders');

// Load order
$stmt = $conn->prepare("SELECT id, order_status FROM orders WHERE order_no=? LIMIT 1");
$stmt->bind_param("s", $orderNo);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$order) redirect('seller/orders');

$oid = (int)$order['id'];

// Verify this order is single-seller AND belongs to this seller items
$stmt = $conn->prepare("
  SELECT COUNT(DISTINCT p.seller_email) AS sellers_count,
         SUM(CASE WHEN p.seller_email=? THEN 1 ELSE 0 END) AS seller_item_rows
  FROM order_items oi
  JOIN products p ON p.id=oi.product_id
  WHERE oi.order_id=?
");
$stmt->bind_param("si", $seller, $oid);
$stmt->execute();
$chk = $stmt->get_result()->fetch_assoc();
$stmt->close();

$sellersCount = (int)($chk['sellers_count'] ?? 0);
$sellerRows   = (int)($chk['seller_item_rows'] ?? 0);

if ($sellerRows <= 0) {
  redirect('seller/orders');
}
if ($sellersCount > 1) {
  // multi-vendor: avoid seller changing global status
  $_SESSION['flash_seller'] = "This is multi-seller order. Only admin can update packing status.";
  redirect('seller/order-view?no=' . urlencode($orderNo));
}

if (($order['order_status'] ?? '') !== 'placed') {
  $_SESSION['flash_seller'] = "Order is not in PLACED status.";
  redirect('seller/order-view?no=' . urlencode($orderNo));
}

try {
  $conn->begin_transaction();

  // Update order status
  $st = $conn->prepare("UPDATE orders SET order_status='packed', updated_at=NOW() WHERE id=?");
  $st->bind_param("i", $oid);
  $st->execute();
  $st->close();

  // Optional history table (only if exists)
  // Safe insert: check table exists
  $exists = $conn->query("SHOW TABLES LIKE 'order_status_history'")->num_rows > 0;
  if ($exists) {
    $note = "Seller packed the order";
    $ins = $conn->prepare("INSERT INTO order_status_history(order_id,status,note,created_at) VALUES (?,?,?,NOW())");
    $status = 'packed';
    $ins->bind_param("iss", $oid, $status, $note);
    $ins->execute();
    $ins->close();
  }

  $conn->commit();
  redirect('seller/order-view?no=' . urlencode($orderNo));
} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo "Error: " . safe($e->getMessage());
}