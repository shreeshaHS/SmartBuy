<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
global $conn;

requireLogin();
// requireRole('admin');

$q = $conn->query("
  SELECT 
    p.id, p.title, p.price, p.stock, p.seller_email, p.approved_at,
    c.name AS category_name,
    s.name AS subcategory_name
  FROM products p
  LEFT JOIN categories c ON c.id=p.category_id
  LEFT JOIN subcategories s ON s.id=p.subcategory_id
  WHERE p.status='live'
  ORDER BY p.id DESC
");
$rows = $q->fetch_all(MYSQLI_ASSOC);

require __DIR__ . '/../../../views/layout/header.php';
?>

<div class="container">
  <div class="card">
    <div class="row" style="justify-content:space-between;align-items:flex-end">
      <div>
        <div class="page-title">Admin: Live Products</div>
        <div class="muted">These are visible to customers.</div>
      </div>
      <a class="btn small ghost" href="<?= BASE_URL ?>admin/products/pending">← Pending</a>
    </div>

    <div class="divider"></div>

    <?php if(empty($rows)): ?>
      <div class="muted">No live products yet.</div>
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
              <th>Approved</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($rows as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><b><?= safe($r['title']) ?></b></td>
                <td><?= safe($r['seller_email']) ?></td>
                <td><?= safe($r['category_name'] ?? '-') ?> <div class="muted" style="font-size:12px"><?= safe($r['subcategory_name'] ?? '-') ?></div></td>
                <td>₹<?= number_format((float)$r['price'],2) ?></td>
                <td><?= (int)$r['stock'] ?></td>
                <td class="muted"><?= safe($r['approved_at'] ?? '-') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../../../views/layout/footer.php'; ?>
