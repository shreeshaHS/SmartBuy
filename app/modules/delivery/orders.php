<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
requireRole('delivery');
global $conn;

$title = "Delivery Orders - " . APP_NAME;

$me = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
if ($me === '') { http_response_code(401); exit('Unauthorized'); }

$status = strtolower(trim((string)($_GET['status'] ?? 'active')));
$allowed = ['active','pickup','ofd','delivered','failed','all'];
if (!in_array($status, $allowed, true)) $status = 'active';

$q = trim((string)($_GET['q'] ?? ''));

$where = "WHERE (o.delivery_email=? OR o.delivery_boy_email=?)";
$params = [$me,$me];
$types = "ss";

if ($status === 'pickup') {
  $where .= " AND o.order_status IN('packed','shipped')";
} elseif ($status === 'ofd') {
  $where .= " AND o.order_status='out_for_delivery'";
} elseif ($status === 'delivered') {
  $where .= " AND o.order_status='delivered'";
} elseif ($status === 'failed') {
  $where .= " AND o.order_status IN('failed','cancelled')";
} elseif ($status === 'active') {
  $where .= " AND o.order_status NOT IN('delivered','failed','cancelled')";
}

if ($q !== '') {
  $where .= " AND (o.order_no LIKE CONCAT('%',?,'%') OR o.user_email LIKE CONCAT('%',?,'%'))";
  $types .= "ss";
  $params[] = $q;
  $params[] = $q;
}

$sql = "
  SELECT
    o.id, o.order_no, o.user_email, o.order_status, o.grand_total,
    o.delivery_eta, o.created_at, o.updated_at
  FROM orders o
  $where
  ORDER BY o.id DESC
  LIMIT 200
";

$orders = [];
try{
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}catch(Throwable $e){
  if (defined('APP_DEBUG') && (string)APP_DEBUG==='1') {
    echo "<pre>".safe($e->getMessage())."</pre>";
  }
}

$navActive = 'orders';
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
    <div class="row" style="justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
      <div>
        <div class="page-title">Orders</div>
        <div class="muted" style="margin-top:-6px">Assigned to <b><?= safe($me) ?></b></div>
      </div>

      <form method="get" action="<?= BASE_URL ?>delivery/orders" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <input type="hidden" name="status" value="<?= safe($status) ?>">
        <input class="input" name="q" placeholder="Search order no / user email" value="<?= safe($q) ?>" style="min-width:260px">
        <button class="btn" type="submit">Search</button>
      </form>
    </div>

    <div class="divider"></div>

    <div class="row" style="gap:10px;flex-wrap:wrap">
      <?php
        function tabBtn($key,$label,$cur,$q){
          $cls = ($key===$cur) ? 'btn' : 'btn ghost';
          $url = BASE_URL."delivery/orders?status={$key}".($q!==''?("&q=".urlencode($q)):'');
          echo '<a class="'.$cls.' small" href="'.safe($url).'">'.safe($label).'</a>';
        }
        tabBtn('active','Active',$status,$q);
        tabBtn('pickup','Pickup',$status,$q);
        tabBtn('ofd','Out for delivery',$status,$q);
        tabBtn('delivered','Delivered',$status,$q);
        tabBtn('failed','Failed',$status,$q);
        tabBtn('all','All',$status,$q);
      ?>
    </div>

    <div class="card" style="margin-top:12px">
      <?php if(empty($orders)): ?>
        <div class="muted">No orders found for this filter.</div>
      <?php else: ?>
        <div style="overflow:auto">
          <table class="table" width="100%">
            <thead>
              <tr>
                <th>Order</th>
                <th>User</th>
                <th>Status</th>
                <th>ETA</th>
                <th>Total</th>
                <th>Updated</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($orders as $o): ?>
                <tr>
                  <td><b>#<?= safe($o['order_no']) ?></b><br><span class="muted"><?= safe($o['created_at']) ?></span></td>
                  <td><?= safe($o['user_email']) ?></td>
                  <td><span class="badge"><?= safe($o['order_status']) ?></span></td>
                  <td><?= !empty($o['delivery_eta']) ? safe($o['delivery_eta']) : '<span class="muted">—</span>' ?></td>
                  <td>₹<?= number_format((float)$o['grand_total'],2) ?></td>
                  <td><span class="muted"><?= safe($o['updated_at'] ?? '') ?></span></td>
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
