<?php
require_once __DIR__ . '/../../config/bootstrap.php';
global $conn;

$title = "Home - " . APP_NAME;

// Show real products
$stmt = $conn->prepare("SELECT id,name,slug,brand,price,mrp,images_json FROM products WHERE status='live' ORDER BY id DESC LIMIT 12");
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require __DIR__ . '/../layout/header.php';
?>

<div class="home-hero card">
  <div>
    <div class="home-title">Welcome to <?= safe(APP_NAME) ?></div>
    <div class="muted">Search, filter and buy like a real marketplace.</div>
  </div>
  <a class="btn" href="<?= BASE_URL ?>products">Shop Now</a>
</div>

<div class="divider"></div>

<div class="page-title">Latest Deals</div>

<?php if(empty($rows)): ?>
  <div class="card">
    <div class="muted">No live products yet. Once admin approves products, they will appear here.</div>
  </div>
<?php else: ?>
  <div class="grid">
    <?php foreach($rows as $p): ?>
      <?php
        // ✅ Always use helper (reads product_images table first, then fallback)
        $img = product_primary_image_url($conn, (int)$p['id']);

        $mrp = (float)($p['mrp'] ?? 0);
        $price = (float)($p['price'] ?? 0);
        $off = 0;
        if ($mrp > 0 && $price > 0 && $mrp > $price) $off = (int)round((($mrp-$price)/$mrp)*100);
      ?>

      <div class="fk-card">
        <a class="fk-img" href="<?= BASE_URL ?>product?slug=<?= urlencode($p['slug']) ?>">
          <img src="<?= safe($img) ?>" alt="<?= safe($p['name']) ?>">
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
            <button class="btn small add-cart" data-id="<?= (int)$p['id'] ?>">🛒 Add</button>
            <a class="btn small ghost" href="<?= BASE_URL ?>product?slug=<?= urlencode($p['slug']) ?>">View</a>
          </div>
        </div>
      </div>

    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';

document.querySelectorAll('.add-cart').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const id = btn.dataset.id;
    const old = btn.textContent;
    btn.textContent = 'Adding…';
    const res = await fetch('<?= BASE_URL ?>api/cart-add', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-TOKEN':CSRF_TOKEN},
      body:`id=${encodeURIComponent(id)}&qty=1`
    });
    const data = await res.json().catch(()=>({ok:false}));
    if(data.ok){
      btn.textContent = '✔ Added';
      const el = document.getElementById('cartCount');
      if(el) el.textContent = data.count;
    } else {
      btn.textContent = 'Error';
      alert(data.msg || 'Add to cart failed');
    }
    setTimeout(()=>btn.textContent = old, 1200);
  });
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>