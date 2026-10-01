<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireProfileForCheckout();
global $conn;

$user = $_SESSION['user_email'] ?? ($_SESSION['user'] ?? '');
$cart = cart_items();

if (empty($cart)) redirect('cart');

// addresses
$stmtA = $conn->prepare("SELECT * FROM user_addresses WHERE user_email=? ORDER BY is_default DESC, id DESC");
$stmtA->bind_param("s", $user);
$stmtA->execute();
$addresses = $stmtA->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtA->close();

if (!$addresses) redirect('profile/address');
$defaultAddrId = (int)($addresses[0]['id'] ?? 0);

// products
$productIds = array_values(array_filter(array_map('intval', array_keys($cart))));
$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$types = str_repeat('i', count($productIds));

$stmtP = $conn->prepare("SELECT id,name,slug,price,stock,status FROM products WHERE id IN ($placeholders)");
$stmtP->bind_param($types, ...$productIds);
$stmtP->execute();
$products = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtP->close();

$map = [];
foreach ($products as $p) $map[(int)$p['id']] = $p;

// totals
$total = 0.0;
$items = [];

foreach ($cart as $pid => $qty) {
  $pid = (int)$pid; $qty = (int)$qty;
  if ($qty <= 0) continue;
  if (!isset($map[$pid])) continue;

  $p = $map[$pid];
  if (($p['status'] ?? '') !== 'live') continue;

  $price = (float)$p['price'];
  $sub = $price * $qty;
  $total += $sub;

  $items[] = [
    'id'=>$pid,
    'name'=>$p['name'],
    'slug'=>$p['slug'],
    'price'=>$price,
    'qty'=>$qty,
    'subtotal'=>$sub
  ];
}

$discount = 0.0;
$couponCode = null;

if (!empty($_SESSION['coupon']['code'])) {
  $couponCode = strtoupper((string)$_SESSION['coupon']['code']);
  $stmtC = $conn->prepare("SELECT * FROM coupons WHERE code=? AND active=1 LIMIT 1");
  $stmtC->bind_param("s", $couponCode);
  $stmtC->execute();
  $c = $stmtC->get_result()->fetch_assoc();
  $stmtC->close();

  if ($c) {
    if (($c['type'] ?? '') === 'percent') {
      $discount = $total * ((float)$c['value'] / 100.0);
      if ((float)$c['max_discount'] > 0) $discount = min($discount, (float)$c['max_discount']);
    } else {
      $discount = (float)$c['value'];
    }
    $discount = max(0.0, min($discount, $total));
  } else {
    $couponCode = null;
    unset($_SESSION['coupon']);
  }
}

$net = max(0.0, $total - $discount);

// initial delivery (default address)
$pin = (string)($addresses[0]['pincode'] ?? '');
$q = delivery_quote($conn, $pin, $net, $user);
$deliveryFee = $q ? (float)$q['fee'] : 0.0;
$etaDate = $q ? (string)$q['eta'] : null;

$grand = $net + $deliveryFee;

$title = "Checkout";
require __DIR__ . '/../../views/layout/header.php';
?>

<div class="container">
  <div class="card">
    <div class="page-title">Checkout</div>

    <div class="grid" style="grid-template-columns: 1.2fr .8fr; gap:14px">

      <div>
        <div class="card">
          <div style="font-weight:900;margin-bottom:8px">Delivery Address</div>

          <div class="muted" style="margin-bottom:10px">Select address for delivery & pincode shipping rules.</div>

          <select id="addrSelect" class="input" style="width:100%;padding:12px;border-radius:14px;border:1px solid var(--stroke)">
            <?php foreach($addresses as $a): ?>
              <option value="<?= (int)$a['id'] ?>" <?= ((int)$a['id']===$defaultAddrId?'selected':'') ?>>
                <?= safe(($a['name']??'').' - '.($a['pincode']??'')) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <div id="etaBox" class="card" style="margin-top:10px">
            <?php if($q): ?>
              <div style="font-weight:900">🚚 Arrives by <?= safe(date('D, d M', strtotime($etaDate))) ?></div>
              <div class="muted" style="margin-top:6px"><?= safe($q['msg']) ?></div>
            <?php else: ?>
              <div class="muted">Delivery not available for this pincode.</div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card" style="margin-top:12px">
          <div style="font-weight:900;margin-bottom:10px">Items</div>
          <div style="overflow:auto">
            <table class="table" width="100%">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Qty</th>
                  <th>Price</th>
                  <th>Subtotal</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach($items as $it): ?>
                <tr>
                  <td>
                    <b><?= safe($it['name']) ?></b><br>
                    <span class="muted"><?= safe($it['slug']) ?></span>
                  </td>
                  <td><?= (int)$it['qty'] ?></td>
                  <td>₹<?= number_format($it['price'],2) ?></td>
                  <td>₹<?= number_format($it['subtotal'],2) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div>
        <div class="card">
          <div style="font-weight:900;margin-bottom:10px">Order Summary</div>

          <div class="row" style="justify-content:space-between">
            <div class="muted">Items total</div>
            <div>₹<?= number_format($total,2) ?></div>
          </div>

          <div class="row" style="justify-content:space-between;margin-top:8px">
            <div class="muted">Discount</div>
            <div>- ₹<?= number_format($discount,2) ?></div>
          </div>

          <div class="row" style="justify-content:space-between;margin-top:8px">
            <div class="muted">Delivery</div>
            <div>₹<?= number_format($deliveryFee,2) ?></div>
          </div>

          <div class="divider"></div>

          <div class="row" style="justify-content:space-between;align-items:center">
            <div style="font-weight:950">Grand Total</div>
            <div style="font-size:20px;font-weight:950">₹<?= number_format($grand,2) ?></div>
          </div>

          <div class="divider"></div>

          <div style="font-weight:900;margin-bottom:8px">Payment</div>
          <div class="muted" style="margin-bottom:10px">
            UPI QR Mode.
          </div>

          <button id="btnUPI" class="btn" style="width:100%">✔ Pay with UPI QR</button>

          <div id="payBox" class="card" style="margin-top:12px;display:none">
            <div style="font-weight:900">Scan & Pay</div>
            <div class="muted" style="margin-top:6px">Scan QR using PhonePe / GPay / Paytm. Then enter UTR below.</div>

            <div style="display:flex;justify-content:center;margin-top:10px">
              <img id="qrImg" alt="UPI QR" style="max-width:220px;border-radius:12px;border:1px solid var(--stroke)">
            </div>

            <a id="upiLink" class="btn ghost" style="width:100%;margin-top:10px" href="#" target="_blank">Open UPI App</a>

            <div class="divider"></div>

            <div style="font-weight:900;margin-bottom:6px">Enter UTR / Transaction ID</div>
            <input id="utr" class="input" placeholder="e.g. 123456789012"
                   style="width:100%;padding:12px;border-radius:14px;border:1px solid var(--stroke)">

            <button id="btnMarkPaid" class="btn" style="width:100%;margin-top:10px">Submit Payment</button>
            <div id="payMsg" class="muted" style="margin-top:10px"></div>
          </div>

        </div>
      </div>

    </div>
  </div>
</div>

<script>
const BASE_URL = "<?= BASE_URL ?>";
const CSRF = <?= json_encode(csrf_token()) ?>;

const addrSelect = document.getElementById('addrSelect');
const btnUPI = document.getElementById('btnUPI');
const payBox = document.getElementById('payBox');
const qrImg = document.getElementById('qrImg');
const upiLink = document.getElementById('upiLink');
const utr = document.getElementById('utr');
const btnMarkPaid = document.getElementById('btnMarkPaid');
const payMsg = document.getElementById('payMsg');

async function postJSON(url, data){
  const form = new FormData();
  form.append('csrf_token', CSRF);
  for(const k in data) form.append(k, data[k]);
  const res = await fetch(url, {method:'POST', body: form});
  const t = await res.text();
  try { return JSON.parse(t); } catch(e){ return {ok:false, msg:t||'Server error'}; }
}

btnUPI.addEventListener('click', async () => {
  payMsg.textContent = "";
  const addr = addrSelect ? addrSelect.value : "";
  const r = await postJSON(`${BASE_URL}?page=api/upi-create`, {addr});
  if(!r.ok){ alert(r.msg || "Failed"); return; }

  qrImg.src = r.qr_png;
  upiLink.href = r.upi_uri;
  payBox.style.display = 'block';
  payBox.dataset.order_id = r.order_id;
});

btnMarkPaid.addEventListener('click', async () => {
  const order_id = payBox.dataset.order_id || '';
  const utrVal = (utr.value || '').trim();
  if(!order_id) { alert("Order missing"); return; }
  if(utrVal.length < 6) { alert("Enter valid UTR"); return; }

  const r = await postJSON(`${BASE_URL}?page=api/upi-markpaid`, {order_id, utr: utrVal});
  if(!r.ok){ payMsg.textContent = r.msg || "Failed"; return; }

  window.location.href = `${BASE_URL}?page=order-success&order_no=${encodeURIComponent(r.order_no)}`;
});
</script>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>