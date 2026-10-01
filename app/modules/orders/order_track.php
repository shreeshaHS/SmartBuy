<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
global $conn;

$orderNo = trim($_GET['no'] ?? '');
if ($orderNo === '') redirect('home');

$stmt = $conn->prepare("SELECT * FROM orders WHERE order_no=? AND user_email=? LIMIT 1");
$stmt->bind_param("ss", $orderNo, $_SESSION['user']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) { http_response_code(404); echo "Order not found"; exit; }

$status = $order['order_status'];

$steps = [
  'placed' => 'Order Placed',
  'packed' => 'Packed',
  'shipped' => 'Shipped',
  'out_for_delivery' => 'Out for Delivery',
  'delivered' => 'Delivered',
];

function step_done(string $current, string $step): bool {
  $rank = ['placed'=>1,'packed'=>2,'shipped'=>3,'out_for_delivery'=>4,'delivered'=>5];
  return ($rank[$current] ?? 1) >= ($rank[$step] ?? 1);
}

$title = "Track Order - ".APP_NAME;
require __DIR__ . '/../../views/layout/header.php';
?>

<div class="container">
  <div class="card">
    <div class="page-title">Track Order</div>
    <div class="muted">Order No: <b><?= safe($order['order_no']) ?></b></div>
    <div style="margin-top:8px">
      Estimated Delivery: <b><?= safe(eta_human($order['delivery_eta'] ?? null)) ?></b>
    </div>

    <div class="divider"></div>

    <div style="display:flex;flex-direction:column;gap:10px">
      <?php foreach($steps as $k=>$label): ?>
        <div class="card" style="display:flex;align-items:center;gap:12px">
          <div style="width:18px;height:18px;border-radius:50%;
              background:<?= step_done($status,$k) ? '#22c55e' : 'rgba(255,255,255,.15)' ?>;">
          </div>
          <div style="font-weight:800"><?= safe($label) ?></div>
          <?php if($k === $status): ?>
            <div class="muted" style="margin-left:auto">Current</div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="divider"></div>
    <a class="btn" href="<?= BASE_URL ?>?page=account/orders">Back to Orders</a>
  </div>
</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>