<?php
require_once __DIR__ . '/../../config/bootstrap.php';
global $conn;

$cart = cart_items();
$products = [];
$total = 0;

if(!empty($cart)){
  $ids = implode(',', array_keys($cart));
  $rows = $conn->query("
    SELECT id,name,slug,price,mrp,images_json,stock 
    FROM products WHERE id IN ($ids)
  ")->fetch_all(MYSQLI_ASSOC);

  foreach($rows as $p){
    $qty = $cart[$p['id']] ?? 1;
    $p['qty'] = $qty;
    $p['subtotal'] = $p['price'] * $qty;
    $products[] = $p;
    $total += $p['subtotal'];
  }
}

function firstImg($json){
  if(!$json) return null;
  $arr = json_decode($json,true);
  return is_array($arr) ? ($arr[0] ?? null) : null;
}

require __DIR__ . '/../../views/layout/header.php';
?>

<div class="container">
  <div class="page-title">Your Cart</div>

  <?php if(empty($products)): ?>
    <div class="card" style="text-align:center;padding:40px">
      <h3>🛒 Cart is empty</h3>
      <p class="muted">Add products to continue shopping</p>
      <a class="btn" href="<?= BASE_URL ?>products">Browse Products</a>
    </div>
  <?php else: ?>

  <div class="grid" style="grid-template-columns:2fr 1fr;gap:14px">

    <!-- Cart items -->
    <div class="card">
      <?php foreach($products as $p): ?>
        <?php $img = firstImg($p['images_json']); ?>

        <div class="row cart-row" data-id="<?= $p['id'] ?>" style="margin-bottom:16px;align-items:center">
          
          <div style="width:90px;height:90px;background:#0001;border-radius:12px;display:flex;align-items:center;justify-content:center">
            <?php if($img): ?><img src="<?= safe($img) ?>" style="max-height:80px"><?php endif; ?>
          </div>

          <div style="flex:1">
            <div style="font-weight:900"><?= safe($p['name']) ?></div>
            <div class="muted">₹<?= number_format($p['price'],2) ?></div>

            <div class="row" style="margin-top:8px">
              <button class="qty-btn" data-act="dec">−</button>
              <span class="qty"><?= $p['qty'] ?></span>
              <button class="qty-btn" data-act="inc">+</button>

              <button class="btn ghost small remove" style="margin-left:12px">Remove</button>
            </div>
          </div>

          <div style="font-weight:900">
            ₹<span class="sub"><?= number_format($p['subtotal'],2) ?></span>
          </div>

        </div>
        <div class="divider"></div>
      <?php endforeach; ?>
    </div>

    <!-- Summary -->
    <div class="card">
      <h3>Order Summary</h3>
      <div class="divider"></div>

      <div class="row">
        <span>Items</span>
        <span id="itemCount"><?= cart_count() ?></span>
      </div>

      <div class="row">
        <span>Subtotal</span>
        <span id="subTotal">₹<?= number_format($total,2) ?></span>
      </div>

      <div class="row">
        <span>Delivery</span>
        <span class="muted">Calculated at checkout</span>
      </div>

      <div class="divider"></div>

      <div class="row" style="font-weight:900;font-size:18px">
        <span>Total</span>
        <span id="grandTotal">₹<?= number_format($total,2) ?></span>
      </div>

      <a class="btn" style="margin-top:12px;width:100%;text-align:center"
         href="<?= BASE_URL ?>checkout">
         Proceed to Checkout →
      </a>
    </div>

  </div>
  <?php endif; ?>
</div>

<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';

document.querySelectorAll('.cart-row').forEach(row=>{
  const id = row.dataset.id;
  const qtyEl = row.querySelector('.qty');
  const subEl = row.querySelector('.sub');

  row.querySelectorAll('.qty-btn').forEach(btn=>{
    btn.onclick = async ()=>{
      const act = btn.dataset.act;
      let qty = parseInt(qtyEl.textContent);

      qty = act==='inc' ? qty+1 : Math.max(1,qty-1);

      await updateQty(id, qty);
    };
  });

  row.querySelector('.remove').onclick = async ()=>{
    await updateQty(id, 0);
    row.remove();
  };
});

async function updateQty(id, qty){
  const res = await fetch('<?= BASE_URL ?>api/cart-update',{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-TOKEN':CSRF_TOKEN},
    body:`id=${id}&qty=${qty}`
  });
  const data = await res.json();
  location.reload(); // simple refresh
}
</script>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
