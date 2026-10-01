<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
requireRole('seller');
global $conn;

$seller = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
$esc = $conn->real_escape_string($seller);

$title = "Inventory - ".APP_NAME;

$rows = $conn->query("
  SELECT id, name, slug, price, stock, status
  FROM products
  WHERE seller_email='$esc'
  ORDER BY status='live' DESC, id DESC
")->fetch_all(MYSQLI_ASSOC);

require __DIR__ . '/../../views/layout/header.php';
?>

<div class="role-shell">
  <aside class="role-nav">
    <div class="role-badge">🧾 Seller</div>
    <a class="role-link" href="<?= BASE_URL ?>seller/dashboard">Dashboard</a>
    <a class="role-link" href="<?= BASE_URL ?>seller/add-product">Add Product</a>
    <a class="role-link" href="<?= BASE_URL ?>seller/orders">Orders</a>
    <a class="role-link active" href="<?= BASE_URL ?>seller/inventory">Inventory</a>
    <div class="role-sep"></div>
    <a class="role-link" href="<?= BASE_URL ?>products">Open Store</a>
  </aside>

  <section class="role-main">
    <div class="row" style="justify-content:space-between;align-items:center;flex-wrap:wrap">
      <div>
        <div class="page-title">Inventory</div>
        <div class="muted">Track stock and value.</div>
      </div>
      <a class="btn" href="<?= BASE_URL ?>seller/add-product">+ Add Product</a>
    </div>

    <div class="divider"></div>

    <?php if(!$rows): ?>
      <div class="card"><div class="muted">No products yet.</div></div>
    <?php else: ?>
      <div class="card" style="overflow:auto">
        <table class="table" width="100%">
          <thead>
            <tr>
              <th>Product</th>
              <th>Status</th>
              <th>Price</th>
              <th>Stock</th>
              <th>Stock Value</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($rows as $p): ?>
            <?php
              $pid=(int)$p['id'];
              $price=(float)$p['price'];
              $stock=(int)$p['stock'];
              $value=$price*$stock;
              $img = product_primary_image_url($conn, $pid);
            ?>
            <tr>
              <td>
                <div class="row" style="gap:10px;align-items:center">
                  <img src="<?= safe($img) ?>" style="height:44px;width:64px;object-fit:contain;border-radius:10px;border:1px solid var(--stroke);background:rgba(255,255,255,.02)">
                  <div>
                    <b><?= safe($p['name']) ?></b>
                    <div class="muted"><?= safe($p['slug']) ?></div>
                  </div>
                </div>
              </td>
              <td><?= safe($p['status']) ?></td>
              <td>₹<?= number_format($price,2) ?></td>
              <td>
                <?php if($stock<=5): ?>
                  <span class="badge">Low: <?= $stock ?></span>
                <?php else: ?>
                  <?= $stock ?>
                <?php endif; ?>
              </td>
              <td>₹<?= number_format($value,2) ?></td>
              <td><a class="btn small ghost" href="<?= BASE_URL ?>product/<?= urlencode($p['slug']) ?>" target="_blank">View</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
