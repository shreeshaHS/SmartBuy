<?php $title="Product - ".APP_NAME; require __DIR__.'/../layout/header.php'; ?>

<div class="product-wrap">
  <div class="card">
    <div class="big-img"></div>
  </div>

  <div class="card">
    <h2><?= safe($slug ?? 'product') ?></h2>
    <div class="muted">Brand • 4.4★ • 10,235 ratings</div>

    <div class="price-row" style="margin-top:10px">
      <div class="price">₹19,999</div>
      <div class="badge">-35%</div>
      <div class="muted"><s>₹30,999</s></div>
    </div>

    <div class="row" style="margin-top:12px">
      <button class="btn">Add to Cart</button>
      <button class="btn ghost">Buy Now</button>
    </div>

    <div class="divider"></div>
    <h3>Offers</h3>
    <ul class="muted">
      <li>Bank Offer: Extra 10% off</li>
      <li>Free delivery on first order (later rule)</li>
      <li>Coupon rewards after ₹3000 (later rule)</li>
    </ul>
  </div>
</div>

<?php require __DIR__.'/../layout/footer.php'; ?>
