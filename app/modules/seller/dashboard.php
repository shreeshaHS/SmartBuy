<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
requireRole('seller');
global $conn;

$seller = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';

/**
 * Stats
 */
$esc = $conn->real_escape_string($seller);

$stats = [
  'pending'  => (int)($conn->query("SELECT COUNT(*) c FROM products WHERE seller_email='$esc' AND status='pending'")->fetch_assoc()['c'] ?? 0),
  'live'     => (int)($conn->query("SELECT COUNT(*) c FROM products WHERE seller_email='$esc' AND status='live'")->fetch_assoc()['c'] ?? 0),
  'rejected' => (int)($conn->query("SELECT COUNT(*) c FROM products WHERE seller_email='$esc' AND status='rejected'")->fetch_assoc()['c'] ?? 0),
];

$invRow = $conn->query("
  SELECT
    COALESCE(SUM(stock),0) as units,
    COALESCE(SUM(stock * price),0) as value,
    COALESCE(SUM(CASE WHEN stock<=5 THEN 1 ELSE 0 END),0) as low_count
  FROM products
  WHERE seller_email='$esc' AND status IN ('pending','live')
")->fetch_assoc();

$ordersRow = $conn->query("
  SELECT
    COALESCE(COUNT(DISTINCT o.id),0) as open_orders
  FROM orders o
  JOIN order_items oi ON oi.order_id=o.id
  JOIN products p ON p.id=oi.product_id
  WHERE p.seller_email='$esc'
    AND o.order_status IN ('placed','packed','shipped','out_for_delivery')
")->fetch_assoc();

$title = "Seller Dashboard - ".APP_NAME;
require __DIR__ . '/../../views/layout/header.php';
?>

<div class="role-shell">
  <aside class="role-nav">
    <div class="role-badge">🧾 Seller</div>
    <a class="role-link active" href="<?= BASE_URL ?>seller/dashboard">Dashboard</a>
    <a class="role-link" href="<?= BASE_URL ?>seller/add-product">Add Product</a>
    <a class="role-link" href="<?= BASE_URL ?>seller/orders">Orders</a>
    <a class="role-link" href="<?= BASE_URL ?>seller/inventory">Inventory</a>
    <div class="role-sep"></div>
    <a class="role-link" href="<?= BASE_URL ?>products">Open Store</a>
  </aside>

  <section class="role-main">
    <div class="page-title">Seller Dashboard</div>
    <div class="muted">Manage your products, stock, and orders.</div>

    <div class="dash-grid" style="margin-top:12px">
      <div class="dash-card">
        <div class="dash-k">Pending Products</div>
        <div class="dash-v"><?= (int)$stats['pending'] ?></div>
        <div class="dash-s muted">Waiting for admin approval</div>
      </div>
      <div class="dash-card">
        <div class="dash-k">Live Products</div>
        <div class="dash-v"><?= (int)$stats['live'] ?></div>
        <div class="dash-s muted">Visible to customers</div>
      </div>
      <div class="dash-card">
        <div class="dash-k">Rejected</div>
        <div class="dash-v"><?= (int)$stats['rejected'] ?></div>
        <div class="dash-s muted">Fix & resubmit</div>
      </div>

      <div class="dash-card">
        <div class="dash-k">Open Orders</div>
        <div class="dash-v"><?= (int)($ordersRow['open_orders'] ?? 0) ?></div>
        <div class="dash-s muted">Need packing / shipping</div>
      </div>

      <div class="dash-card">
        <div class="dash-k">Stock Units</div>
        <div class="dash-v"><?= (int)($invRow['units'] ?? 0) ?></div>
        <div class="dash-s muted">Total units in stock</div>
      </div>

      <div class="dash-card">
        <div class="dash-k">Stock Value</div>
        <div class="dash-v">₹<?= number_format((float)($invRow['value'] ?? 0), 0) ?></div>
        <div class="dash-s muted">Approx. at sell price</div>
      </div>
    </div>

    <div class="card" style="margin-top:14px">
      <div class="row" style="justify-content:space-between;align-items:center;flex-wrap:wrap">
        <div>
          <div style="font-weight:950;font-size:16px">Quick Actions</div>
          <div class="muted">Keep inventory updated and pack orders quickly.</div>
        </div>
        <div class="row" style="gap:10px;flex-wrap:wrap">
          <a class="btn" href="<?= BASE_URL ?>seller/add-product">+ Add Product</a>
          <a class="btn ghost" href="<?= BASE_URL ?>seller/orders">View Orders</a>
          <a class="btn ghost" href="<?= BASE_URL ?>seller/inventory">Inventory</a>
        </div>
      </div>

      <?php if((int)($invRow['low_count'] ?? 0) > 0): ?>
        <div class="divider"></div>
        <div class="alert" style="margin:0">
          ⚠ Low stock on <b><?= (int)$invRow['low_count'] ?></b> product(s). Please restock.
        </div>
      <?php endif; ?>
    </div>

  </section>
</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
