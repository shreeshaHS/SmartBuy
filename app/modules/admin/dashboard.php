<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
requireRole('admin');
global $conn;

$title = 'Admin Dashboard - '.APP_NAME;

// helper: safe table exists (NO "SHOW TABLES LIKE ?" error)
function table_exists(mysqli $conn, string $table): bool {
  $db = $conn->query("SELECT DATABASE() db")->fetch_assoc()['db'] ?? '';
  if ($db === '') return false;

  $stmt = $conn->prepare("
    SELECT 1
    FROM information_schema.tables
    WHERE table_schema=? AND table_name=?
    LIMIT 1
  ");
  $stmt->bind_param("ss", $db, $table);
  $stmt->execute();
  $ok = (bool)$stmt->get_result()->fetch_row();
  $stmt->close();
  return $ok;
}

$hasOrders   = table_exists($conn, 'orders');
$hasProducts = table_exists($conn, 'products');
$hasUsers    = table_exists($conn, 'users');
$hasSellerReq= table_exists($conn, 'seller_requests');

$stats = [
  'users' => 0,
  'sellers' => 0,
  'delivery' => 0,
  'pending_products' => 0,
  'live_products' => 0,
  'orders_today' => 0,
  'orders_placed' => 0,
  'orders_packed' => 0,
  'orders_out' => 0,
  'orders_delivered' => 0,
];

if ($hasUsers) {
  $stats['users']    = (int)($conn->query("SELECT COUNT(*) c FROM users WHERE role='user'")->fetch_assoc()['c'] ?? 0);
  $stats['sellers']  = (int)($conn->query("SELECT COUNT(*) c FROM users WHERE role='seller'")->fetch_assoc()['c'] ?? 0);
  $stats['delivery'] = (int)($conn->query("SELECT COUNT(*) c FROM users WHERE role='delivery'")->fetch_assoc()['c'] ?? 0);
}

if ($hasProducts) {
  $stats['pending_products'] = (int)($conn->query("SELECT COUNT(*) c FROM products WHERE status='pending'")->fetch_assoc()['c'] ?? 0);
  $stats['live_products']    = (int)($conn->query("SELECT COUNT(*) c FROM products WHERE status='live'")->fetch_assoc()['c'] ?? 0);
}

if ($hasOrders) {
  $stats['orders_today'] = (int)($conn->query("SELECT COUNT(*) c FROM orders WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'] ?? 0);

  // Order status breakdown (works if order_status exists)
  $cols = $conn->query("SHOW COLUMNS FROM orders LIKE 'order_status'");
  if ($cols && $cols->num_rows > 0) {
    $rows = $conn->query("SELECT order_status, COUNT(*) c FROM orders GROUP BY order_status")->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $r) {
      $s = strtolower((string)$r['order_status']);
      $c = (int)$r['c'];
      if ($s === 'placed') $stats['orders_placed'] = $c;
      if ($s === 'packed') $stats['orders_packed'] = $c;
      if ($s === 'out_for_delivery') $stats['orders_out'] = $c;
      if ($s === 'delivered') $stats['orders_delivered'] = $c;
    }
  }
}

// chart: last 7 days orders count
$chartLabels = [];
$chartValues = [];
if ($hasOrders) {
  $tmp = $conn->query("
    SELECT DATE(created_at) d, COUNT(*) c
    FROM orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY d
  ")->fetch_all(MYSQLI_ASSOC);

  $map = [];
  foreach ($tmp as $t) $map[$t['d']] = (int)$t['c'];

  for ($i=6; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = $d;
    $chartValues[] = $map[$d] ?? 0;
  }
}

require __DIR__ . '/../../views/layout/header.php';
?>

<div class="role-shell">
  <aside class="role-nav">
    <div class="role-badge">👑 Admin</div>
    <a class="role-link active" href="<?= BASE_URL ?>admin/dashboard">Dashboard</a>

    <a class="role-link" href="<?= BASE_URL ?>admin/sellers/requests">Seller Requests</a>
    <a class="role-link" href="<?= BASE_URL ?>admin/products/pending">Pending Products</a>
    <a class="role-link" href="<?= BASE_URL ?>admin/delivery/assign">Delivery Assign</a>

    <div class="role-sep"></div>

    <a class="role-link" href="<?= BASE_URL ?>admin/delivery-create">Create Delivery Account</a>
    <a class="role-link" href="<?= BASE_URL ?>admin/delivery-list">Delivery Accounts</a>

    <div class="role-sep"></div>

    <a class="role-link" href="<?= BASE_URL ?>admin/catalog/categories">Catalog</a>
    <a class="role-link" href="<?= BASE_URL ?>admin/catalog/subcategories">Subcategories</a>
    <a class="role-link" href="<?= BASE_URL ?>admin/catalog/subcat-specs">Specs Mapping</a>
  </aside>

  <section class="role-main">

    <div class="page-title">Admin Dashboard</div>

    <div class="dash-grid">
      <div class="dash-card"><div class="dash-k">Users</div><div class="dash-v"><?= (int)$stats['users'] ?></div><div class="dash-s muted">Customers</div></div>
      <div class="dash-card"><div class="dash-k">Sellers</div><div class="dash-v"><?= (int)$stats['sellers'] ?></div><div class="dash-s muted">Approved sellers</div></div>
      <div class="dash-card"><div class="dash-k">Delivery</div><div class="dash-v"><?= (int)$stats['delivery'] ?></div><div class="dash-s muted">Delivery accounts</div></div>
      <div class="dash-card"><div class="dash-k">Orders Today</div><div class="dash-v"><?= (int)$stats['orders_today'] ?></div><div class="dash-s muted">New orders</div></div>

      <div class="dash-card"><div class="dash-k">Pending Products</div><div class="dash-v"><?= (int)$stats['pending_products'] ?></div><div class="dash-s muted">Need approval</div></div>
      <div class="dash-card"><div class="dash-k">Live Products</div><div class="dash-v"><?= (int)$stats['live_products'] ?></div><div class="dash-s muted">Currently listed</div></div>
      <div class="dash-card"><div class="dash-k">Placed</div><div class="dash-v"><?= (int)$stats['orders_placed'] ?></div><div class="dash-s muted">Order placed</div></div>
      <div class="dash-card"><div class="dash-k">Delivered</div><div class="dash-v"><?= (int)$stats['orders_delivered'] ?></div><div class="dash-s muted">Completed</div></div>
    </div>

    <div class="card" style="margin-top:14px">
      <div style="font-weight:950;font-size:16px">Quick Actions</div>
      <div class="divider"></div>
      <div class="row" style="flex-wrap:wrap;gap:10px">
        <a class="btn" href="<?= BASE_URL ?>admin/sellers/requests">Review Seller Requests</a>
        <a class="btn ghost" href="<?= BASE_URL ?>admin/products/pending">Approve Products</a>
        <a class="btn ghost" href="<?= BASE_URL ?>admin/delivery/assign">Assign Delivery</a>
        <a class="btn ghost" href="<?= BASE_URL ?>admin/delivery-create">Create Delivery Account</a>
      </div>
    </div>

    <div class="card" style="margin-top:14px">
      <div style="font-weight:950;font-size:16px">Orders (Last 7 days)</div>
      <div class="muted" style="margin-top:6px">Simple chart to verify everything is recording correctly.</div>
      <div class="divider"></div>
      <canvas id="ordersChart" height="90"></canvas>
    </div>

  </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = <?= json_encode($chartLabels) ?>;
const values = <?= json_encode($chartValues) ?>;

const ctx = document.getElementById('ordersChart');
new Chart(ctx, {
  type: 'line',
  data: {
    labels,
    datasets: [{
      label: 'Orders',
      data: values,
      tension: 0.3
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: true } }
  }
});
</script>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>