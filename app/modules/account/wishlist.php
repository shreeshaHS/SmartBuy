<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
global $conn;

$email = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
if ($email === '') { http_response_code(401); exit('Unauthorized'); }

$title = "Wishlist - ".APP_NAME;

// Column exists helper
if (!function_exists('col_exists')) {
  function col_exists(mysqli $conn, string $table, string $col): bool {
    $sql = "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
    $st = $conn->prepare($sql);
    $st->bind_param("ss", $table, $col);
    $st->execute();
    $ok = (bool)$st->get_result()->fetch_row();
    $st->close();
    return $ok;
  }
}

$hasTitle = col_exists($conn,'products','title');

$sql = "
  SELECT
    w.id AS wid,
    w.product_id,
    ".($hasTitle ? "COALESCE(p.title,p.name)" : "p.name")." AS name,
    p.slug,
    p.price,
    p.mrp,
    p.status
  FROM wishlists w
  JOIN products p ON p.id = w.product_id
  WHERE w.user_email=?
  ORDER BY w.id DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require __DIR__ . '/../../views/layout/header.php';
?>

<div class="card">
  <div class="row" style="justify-content:space-between;align-items:center;flex-wrap:wrap">
    <div>
      <div class="page-title">Wishlist</div>
      <div class="muted">Saved items in your account</div>
    </div>
    <a class="btn ghost" href="<?= BASE_URL ?>products">Continue shopping</a>
  </div>

  <div class="divider"></div>

  <?php if(!$rows): ?>
    <div class="muted">No items in wishlist.</div>
  <?php else: ?>
    <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">
      <?php foreach($rows as $p): ?>
        <?php
          $imgUrl = product_primary_image_url($conn, (int)$p['product_id']);
          $mrp = (float)($p['mrp'] ?? 0);
          $price = (float)($p['price'] ?? 0);
          $off = 0;
          if ($mrp>0 && $price>0 && $mrp>$price) $off = (int)round((($mrp-$price)/$mrp)*100);
        ?>
        <div class="fk-card">
          <a class="fk-img" href="<?= BASE_URL ?>product/<?= urlencode($p['slug']) ?>">
            <img src="<?= safe($imgUrl) ?>" alt="">
          </a>

          <div class="fk-meta">
            <a class="fk-title line-clamp" href="<?= BASE_URL ?>product/<?= urlencode($p['slug']) ?>"><?= safe($p['name']) ?></a>
            <div class="fk-price-row">
              <div class="fk-price">₹<?= number_format($price,2) ?></div>
              <?php if($mrp>0 && $mrp>$price): ?>
                <div class="fk-mrp">₹<?= number_format($mrp,2) ?></div>
                <div class="fk-off"><?= $off ?>% off</div>
              <?php endif; ?>
            </div>

            <?php if(($p['status'] ?? '') !== 'live'): ?>
              <div class="badge" style="margin-top:8px">Not available</div>
            <?php endif; ?>

            <form method="post" action="<?= BASE_URL ?>?page=api/wishlist-toggle" style="margin-top:10px">
              <?= csrf_field() ?>
              <input type="hidden" name="product_id" value="<?= (int)$p['product_id'] ?>">
              <button class="btn small ghost" type="submit">Remove</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
