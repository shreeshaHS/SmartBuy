<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
requireRole('delivery');
global $conn;

$title = "Delivery Dashboard - " . APP_NAME;

$me = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
if ($me === '') { http_response_code(401); exit('Unauthorized'); }

$today = date('Y-m-d');

// Stats
$stats = ['assigned'=>0,'pickup'=>0,'ofd'=>0,'delivered_today'=>0,'failed_today'=>0];

try{
  $stmt = $conn->prepare("
    SELECT
      SUM(CASE WHEN (delivery_email=? OR delivery_boy_email=?) AND order_status NOT IN('delivered','cancelled','failed') THEN 1 ELSE 0 END) AS assigned,
      SUM(CASE WHEN (delivery_email=? OR delivery_boy_email=?) AND order_status IN('packed','shipped') THEN 1 ELSE 0 END) AS pickup,
      SUM(CASE WHEN (delivery_email=? OR delivery_boy_email=?) AND order_status='out_for_delivery' THEN 1 ELSE 0 END) AS ofd,
      SUM(CASE WHEN (delivery_email=? OR delivery_boy_email=?) AND order_status='delivered' AND DATE(updated_at)=? THEN 1 ELSE 0 END) AS delivered_today,
      SUM(CASE WHEN (delivery_email=? OR delivery_boy_email=?) AND order_status IN('failed','cancelled') AND DATE(updated_at)=? THEN 1 ELSE 0 END) AS failed_today
    FROM orders
  ");
  // 12 placeholders + 1 today? Actually: assigned(2) pickup(2) ofd(2) delivered_today(2+today) failed_today(2+today) => 2+2+2+3+3 = 12
  $stmt->bind_param("ssssssssssss",
    $me,$me, $me,$me, $me,$me, $me,$me,$today, $me,$me,$today
  );
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc() ?: [];
  $stmt->close();
  foreach($stats as $k=>$v) $stats[$k] = (int)($r[$k] ?? 0);
}catch(Throwable $e){}

// Recent assigned orders
$orders = [];
try{
  $stmt = $conn->prepare("
    SELECT id, order_no, user_email, grand_total, order_status, delivery_eta, created_at
    FROM orders
    WHERE (delivery_email=? OR delivery_boy_email=?)
    ORDER BY id DESC
    LIMIT 8
  ");
  $stmt->bind_param("ss",$me,$me);
  $stmt->execute();
  $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}catch(Throwable $e){}

$navActive = 'dashboard';
require __DIR__ . '/../../views/layout/header.php';
?>

<div class="role-shell">
  <aside class="role-nav">
  <div class="role-badge">🚚 Delivery</div>
  <a class="role-link <?= ($navActive==='dashboard'?'active':'') ?>" href="<?= BASE_URL ?>delivery/dashboard">Dashboard</a>
  <a class="role-link <?= ($navActive==='orders'?'active':'') ?>" href="<?= BASE_URL ?>delivery/orders">Orders</a>
  <a class="role-link <?= ($navActive==='returns'?'active':'') ?>" href="<?= BASE_URL ?>delivery/return_pickups">Return Pickups</a>
  <div class="role-sep"></div>
  <a class="role-link" href="<?= BASE_URL ?>products">Open Store</a>
</aside>
  <section class="role-main">
    <div class="page-title">Delivery Dashboard</div>
    <div class="muted" style="margin-top:-6px;margin-bottom:12px">Logged in as <b><?= safe($me) ?></b></div>

    <div class="dash-grid" style="grid-template-columns:repeat(5,minmax(0,1fr))">
      <div class="dash-card"><div class="dash-k">Assigned</div><div class="dash-v"><?= (int)$stats['assigned'] ?></div><div class="dash-s muted">Active orders</div></div>
      <div class="dash-card"><div class="dash-k">Pickup pending</div><div class="dash-v"><?= (int)$stats['pickup'] ?></div><div class="dash-s muted">Packed / shipped</div></div>
      <div class="dash-card"><div class="dash-k">Out for delivery</div><div class="dash-v"><?= (int)$stats['ofd'] ?></div><div class="dash-s muted">Deliver today</div></div>
      <div class="dash-card"><div class="dash-k">Delivered today</div><div class="dash-v"><?= (int)$stats['delivered_today'] ?></div><div class="dash-s muted"><?= safe($today) ?></div></div>
      <div class="dash-card"><div class="dash-k">Failed today</div><div class="dash-v"><?= (int)$stats['failed_today'] ?></div><div class="dash-s muted">Cancelled / failed</div></div>
    </div>

    <div class="card" style="margin-top:14px">
      <div class="row" style="justify-content:space-between;align-items:center">
        <div>
          <div style="font-weight:950;font-size:16px">Recent assigned orders</div>
          <div class="muted" style="font-size:12px">Tap an order to open tracking + actions</div>
        </div>
        <a class="btn" href="<?= BASE_URL ?>delivery/orders">View all</a>
      </div>

      <div class="divider"></div>

      <?php if(empty($orders)): ?>
        <div class="muted">No assigned orders yet.</div>
      <?php else: ?>
        <div style="overflow:auto">
          <table class="table" width="100%">
            <thead><tr><th>Order</th><th>User</th><th>Status</th><th>ETA</th><th>Total</th><th></th></tr></thead>
            <tbody>
              <?php foreach($orders as $o): ?>
                <tr>
                  <td><b>#<?= safe($o['order_no']) ?></b><br><span class="muted"><?= safe($o['created_at']) ?></span></td>
                  <td><?= safe($o['user_email']) ?></td>
                  <td><span class="badge"><?= safe($o['order_status']) ?></span></td>
                  <td><?= !empty($o['delivery_eta']) ? safe($o['delivery_eta']) : '<span class="muted">—</span>' ?></td>
                  <td>₹<?= number_format((float)$o['grand_total'],2) ?></td>
                  <td><a class="btn small ghost" href="<?= BASE_URL ?>delivery/view?id=<?= (int)$o['id'] ?>">Open</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
