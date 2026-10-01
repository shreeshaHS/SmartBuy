<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
requireRole('seller');
global $conn;

$title = "My Products - " . APP_NAME;
$seller = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
if ($seller === '') redirect('login');

if (isPost()) require_csrf();

$msg = null;
$err = null;

/**
 * Update stock
 */
if (isPost() && isset($_POST['update_stock'])) {
  $pid = (int)($_POST['id'] ?? 0);
  $stock = (int)($_POST['stock'] ?? 0);
  if ($pid <= 0) $err = "Invalid product.";
  else {
    $st = $conn->prepare("UPDATE products SET stock=? WHERE id=? AND seller_email=?");
    $st->bind_param("iis", $stock, $pid, $seller);
    $st->execute();
    $st->close();
    $msg = "Stock updated ✅";
  }
}

/**
 * Load products
 */
$rows = [];
$st = $conn->prepare("
  SELECT id,name,slug,price,stock,status,created_at
  FROM products
  WHERE seller_email=?
  ORDER BY id DESC
");
$st->bind_param("s", $seller);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

/**
 * Inventory totals
 */
$totalSku = count($rows);
$totalQty = 0;
$totalValue = 0.0;
foreach ($rows as $p) {
  $qty = (int)($p['stock'] ?? 0);
  $totalQty += $qty;
  $totalValue += $qty * (float)($p['price'] ?? 0);
}

require __DIR__ . '/../../views/layout/header.php';
?>

<div class="role-shell">
  <aside class="role-nav">
    <div class="role-badge">🧾 Seller</div>
    <a class="role-link" href="<?= BASE_URL ?>seller/dashboard">Dashboard</a>
    <a class="role-link" href="<?= BASE_URL ?>seller/add-product">Add Product</a>
    <a class="role-link active" href="<?= BASE_URL ?>seller/products">My Products</a>
    <a class="role-link" href="<?= BASE_URL ?>seller/orders">My Orders</a>
    <div class="role-sep"></div>
    <a class="role-link" href="<?= BASE_URL ?>products">Open Store</a>
  </aside>

  <section class="role-main">

    <div class="row" style="justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap">
      <div>
        <div class="page-title">Inventory</div>
        <div class="muted">Track stock, value, and update inventory quickly.</div>
      </div>
      <a class="btn" href="<?= BASE_URL ?>seller/add-product">+ Add Product</a>
    </div>

    <div class="divider"></div>

    <div class="dash-grid">
      <div class="dash-card">
        <div class="dash-k">Total SKUs</div>
        <div class="dash-v"><?= (int)$totalSku ?></div>
        <div class="dash-s muted">Products listed</div>
      </div>
      <div class="dash-card">
        <div class="dash-k">Total Stock Qty</div>
        <div class="dash-v"><?= (int)$totalQty ?></div>
        <div class="dash-s muted">Sum of all stocks</div>
      </div>
      <div class="dash-card">
        <div class="dash-k">Stock Value</div>
        <div class="dash-v">₹<?= number_format((float)$totalValue, 2) ?></div>
        <div class="dash-s muted">Stock × selling price</div>
      </div>
    </div>

    <?php if($err): ?><div class="alert error" style="margin-top:12px"><?= safe($err) ?></div><?php endif; ?>
    <?php if($msg): ?><div class="alert ok" style="margin-top:12px"><?= safe($msg) ?></div><?php endif; ?>

    <div class="card" style="margin-top:14px">
      <div style="font-weight:950;font-size:16px">My Products</div>
      <div class="muted">Only your products are shown here.</div>

      <div class="divider"></div>

      <?php if(!$rows): ?>
        <div class="muted">No products yet.</div>
      <?php else: ?>
        <div style="overflow:auto">
          <table class="table" width="100%">
            <thead>
              <tr>
                <th>Product</th>
                <th>Status</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Stock Value</th>
                <th>Update Stock</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($rows as $p): ?>
              <?php
                $qty = (int)($p['stock'] ?? 0);
                $price = (float)($p['price'] ?? 0);
                $val = $qty * $price;
              ?>
              <tr>
                <td>
                  <b><?= safe($p['name']) ?></b>
                  <div class="muted"><?= safe($p['slug']) ?></div>
                </td>
                <td><?= safe($p['status']) ?></td>
                <td>₹<?= number_format($price,2) ?></td>
                <td><b><?= $qty ?></b></td>
                <td>₹<?= number_format($val,2) ?></td>
                <td>
                  <form method="post" style="display:flex;gap:8px;align-items:center">
                    <?= csrf_field() ?>
                    <input type="hidden" name="update_stock" value="1">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <input class="input" name="stock" type="number" min="0" value="<?= $qty ?>" style="max-width:90px;padding:10px;border-radius:12px;border:1px solid var(--stroke)">
                    <button class="btn small" type="submit">Save</button>
                  </form>
                </td>
                <td>
                  <a class="btn small ghost" href="<?= BASE_URL ?>seller/edit-product?id=<?= (int)$p['id'] ?>">Edit</a>
                  <a class="btn small ghost" href="<?= BASE_URL ?>product?slug=<?= urlencode($p['slug']) ?>" target="_blank">View</a>
                </td>
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