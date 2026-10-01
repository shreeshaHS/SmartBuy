<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
global $conn;

requireLogin();
// requireRole('admin'); // enable after admin login final

// Fetch pending products
$q = $conn->query("
  SELECT 
    p.id, p.title, p.brand, p.price, p.mrp, p.stock, p.status, p.seller_email,
    c.name AS category_name,
    s.name AS subcategory_name
  FROM products p
  LEFT JOIN categories c ON c.id = p.category_id
  LEFT JOIN subcategories s ON s.id = p.subcategory_id
  WHERE p.status='pending'
  ORDER BY p.id DESC
");
$rows = $q->fetch_all(MYSQLI_ASSOC);

require __DIR__ . '/../../../views/layout/header.php';
?>

<div class="container">
  <div class="card">
    <div class="row" style="justify-content:space-between;align-items:flex-end">
      <div>
        <div class="page-title">Admin: Pending Product Approvals</div>
        <div class="muted">Approve products to make them LIVE. Reject with reason.</div>
      </div>
      <div class="row">
        <a class="btn small ghost" href="<?= BASE_URL ?>admin/products/live">Live Products →</a>
      </div>
    </div>

    <div class="divider"></div>

    <?php if(empty($rows)): ?>
      <div class="muted">No pending products ✅</div>
    <?php else: ?>

      <div style="border:1px solid var(--stroke);border-radius:16px;overflow:hidden">
        <table width="100%" class="table" style="margin:0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Product</th>
              <th>Seller</th>
              <th>Category</th>
              <th>Price</th>
              <th>Stock</th>
              <th style="width:220px">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($rows as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td>
                  <b><?= safe($r['title']) ?></b>
                  <?php if($r['brand']): ?><div class="muted" style="font-size:12px"><?= safe($r['brand']) ?></div><?php endif; ?>
                </td>
                <td><?= safe($r['seller_email']) ?></td>
                <td>
                  <?= safe($r['category_name'] ?? '-') ?>
                  <div class="muted" style="font-size:12px"><?= safe($r['subcategory_name'] ?? '-') ?></div>
                </td>
                <td>
                  <b>₹<?= number_format((float)$r['price'],2) ?></b>
                  <?php if(!is_null($r['mrp'])): ?><div class="muted" style="font-size:12px">MRP: ₹<?= number_format((float)$r['mrp'],2) ?></div><?php endif; ?>
                </td>
                <td><?= (int)$r['stock'] ?></td>
                <td class="row" style="gap:8px;flex-wrap:wrap">
                  <form method="post" action="<?= BASE_URL ?>admin/products/approve" onsubmit="return confirm('Approve and make LIVE?')">
    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn small" type="submit">Approve</button>
                  </form>

                  <a class="btn small ghost" href="<?= BASE_URL ?>admin/products/reject?id=<?= (int)$r['id'] ?>">Reject</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../../../views/layout/footer.php'; ?>
