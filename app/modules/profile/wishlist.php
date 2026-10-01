<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
global $conn;

$email = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
$title = "Wishlist - " . APP_NAME;

$stmt = $conn->prepare("
  SELECT p.id, p.name, p.slug, p.brand, p.price, p.mrp, p.images_json
  FROM wishlists w
  JOIN products p ON p.id = w.product_id
  WHERE w.user_email=?
  ORDER BY w.id DESC
");
$stmt->bind_param("s", $email);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require __DIR__ . '/../layout/header.php';
?>

<div class="card">
  <div class="page-title">My Wishlist</div>

  <?php if(!$rows): ?>
    <div class="muted">No items in wishlist.</div>
    <a class="btn" href="<?= BASE_URL ?>products">Shop Now</a>
  <?php else: ?>
    <div class="grid">
      <?php foreach($rows as $p): ?>
        <?php
          $imgs = [];
          if (!empty($p['images_json'])) {
            $t = json_decode($p['images_json'], true);
            if (is_array($t)) $imgs = $t;
          }
          $img = $imgs[0] ?? '';
          $mrp = (float)($p['mrp'] ?? 0);
          $price = (float)($p['price'] ?? 0);
          $off = 0;
          if ($mrp > 0 && $price > 0 && $mrp > $price) $off = (int)round((($mrp-$price)/$mrp)*100);
        ?>

        <div class="fk-card">
          <a class="fk-img" href="<?= BASE_URL ?>product?slug=<?= urlencode($p['slug']) ?>">
            <?php if($img): ?>
              <img src="<?= safe($img) ?>" alt="">
            <?php else: ?>
              <div class="fk-img-ph">No image</div>
            <?php endif; ?>
          </a>

          <div class="fk-meta">
            <a class="fk-title line-clamp" href="<?= BASE_URL ?>product?slug=<?= urlencode($p['slug']) ?>"><?= safe($p['name']) ?></a>
            <div class="muted" style="font-size:12px"><?= safe($p['brand'] ?? '') ?></div>

            <div class="fk-price-row">
              <div class="fk-price">₹<?= number_format($price,2) ?></div>
              <?php if($mrp>0 && $mrp>$price): ?>
                <div class="fk-mrp">₹<?= number_format($mrp,2) ?></div>
                <div class="fk-off"><?= $off ?>% off</div>
              <?php endif; ?>
            </div>

            <div class="fk-actions">
              <a class="btn small" href="<?= BASE_URL ?>product?slug=<?= urlencode($p['slug']) ?>">View</a>
              <button class="btn small ghost btnWish" data-id="<?= (int)$p['id'] ?>">Remove</button>
            </div>
          </div>
        </div>

      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
const BASE_URL = "<?= BASE_URL ?>";
const CSRF = "<?= csrf_token() ?>";

document.querySelectorAll('.btnWish').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const id = btn.dataset.id;

    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('id', id);

    const res = await fetch(BASE_URL + "?page=api/wishlist_toggle", {
      method:"POST",
      body: fd
    });

    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch { data = {ok:false,msg:text}; }

    if(!data.ok){
      alert(data.msg || "Failed");
      return;
    }
    // reload page after remove
    location.reload();
  });
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>