<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
requireRole('seller');
global $conn;

$seller = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
if ($seller === '') redirect('login');

$orderNo = trim($_GET['no'] ?? '');
if ($orderNo === '') redirect('seller/orders');

// Fallback timeline helpers (prevents "undefined function render_order_timeline()")
if (!function_exists('order_status_steps')) {
  function order_status_steps(): array {
    return [
      'placed' => 'Placed',
      'packed' => 'Packed',
      'shipped' => 'Shipped',
      'out_for_delivery' => 'Out for delivery',
      'delivered' => 'Delivered',
    ];
  }
}
if (!function_exists('order_step_index')) {
  function order_step_index(string $status): int {
    $status = strtolower(trim($status));
    $keys = array_keys(order_status_steps());
    $i = array_search($status, $keys, true);
    return ($i === false) ? 0 : (int)$i;
  }
}
if (!function_exists('render_order_timeline')) {
  function render_order_timeline(string $status, ?string $etaDate = null): string {
    $steps = order_status_steps();
    $idx = order_step_index($status);

    $etaHtml = '';
    if (!empty($etaDate)) {
      $etaHtml = '<div class="muted" style="margin-top:8px">Estimated delivery: <b>'.safe($etaDate).'</b></div>';
    }

    $html = '<div class="timeline">';
    $i = 0;
    foreach ($steps as $key => $label) {
      $state = ($i < $idx) ? 'done' : (($i === $idx) ? 'active' : 'todo');
      $html .= '
        <div class="tstep '.$state.'">
          <div class="dot"></div>
          <div class="lbl">'.safe($label).'</div>
        </div>
      ';
      $i++;
    }
    $html .= '</div>'.$etaHtml;
    return $html;
  }
}

// Load order (must contain seller products)
$stmt = $conn->prepare("
  SELECT o.*
  FROM orders o
  WHERE o.order_no=?
  LIMIT 1
");
$stmt->bind_param("s", $orderNo);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
  $title = "Order - " . APP_NAME;
  require __DIR__ . '/../../views/layout/header.php';
  echo '<div class="container"><div class="card"><div class="page-title">Order not found</div><a class="btn" href="'.BASE_URL.'seller/orders">Back</a></div></div>';
  require __DIR__ . '/../../views/layout/footer.php';
  exit;
}

// Seller items for this order
$stmt = $conn->prepare("
  SELECT
    oi.product_id, oi.qty, oi.price, oi.subtotal,
    p.name, p.slug, p.seller_email
  FROM order_items oi
  JOIN products p ON p.id = oi.product_id
  WHERE oi.order_id=? AND p.seller_email=?
  ORDER BY oi.id ASC
");
$oid = (int)$order['id'];
$stmt->bind_param("is", $oid, $seller);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!$items) {
  $title = "Order - " . APP_NAME;
  require __DIR__ . '/../../views/layout/header.php';
  echo '<div class="container"><div class="card"><div class="page-title">Not allowed</div><div class="muted">This order does not contain your products.</div><a class="btn" href="'.BASE_URL.'seller/orders">Back</a></div></div>';
  require __DIR__ . '/../../views/layout/footer.php';
  exit;
}

$sellerTotal = 0.0;
foreach($items as $it) $sellerTotal += (float)$it['subtotal'];

// Determine if this is a single-seller order (so seller can mark packed safely)
$stmt = $conn->prepare("
  SELECT COUNT(DISTINCT p.seller_email) AS c
  FROM order_items oi
  JOIN products p ON p.id=oi.product_id
  WHERE oi.order_id=?
");
$stmt->bind_param("i", $oid);
$stmt->execute();
$cnt = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$singleSeller = ($cnt <= 1);

// Address snapshot
$addr = [];
if (!empty($order['address_json'])) {
  $tmp = json_decode($order['address_json'], true);
  if (is_array($tmp)) $addr = $tmp;
}

$title = "Order " . $orderNo . " - " . APP_NAME;
require __DIR__ . '/../../views/layout/header.php';
?>

<style>
.timeline{display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;margin-top:12px}
.tstep{display:flex;align-items:center;gap:10px;min-width:160px;padding:10px 12px;border-radius:14px;border:1px solid rgba(0,0,0,.08)}
.tstep .dot{width:12px;height:12px;border-radius:999px;background:#bbb;flex:0 0 auto}
.tstep.done{background:rgba(34,197,94,.08);border-color:rgba(34,197,94,.25)}
.tstep.done .dot{background:#22c55e}
.tstep.active{background:rgba(124,58,237,.08);border-color:rgba(124,58,237,.25)}
.tstep.active .dot{background:#7c3aed}
.tstep.todo{opacity:.75}
.tstep .lbl{font-weight:700}
</style>

<div class="container">
  <div class="card">
    <div class="row" style="justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
      <div>
        <div class="page-title">Order: <?= safe($order['order_no']) ?></div>
        <div class="muted">Status: <b><?= safe($order['order_status']) ?></b> • Payment: <b><?= safe($order['payment_status']) ?></b></div>
      </div>
      <a class="btn ghost" href="<?= BASE_URL ?>seller/orders">← Back</a>
    </div>

    <div class="divider"></div>

    <div style="font-weight:900;margin-bottom:6px">Tracking</div>
    <?= render_order_timeline($order['order_status'] ?? 'placed', $order['delivery_eta'] ?? null) ?>

    <div class="divider"></div>

    <div class="grid" style="grid-template-columns:1.2fr .8fr;gap:14px">
      <div class="card">
        <div style="font-weight:900;margin-bottom:8px">Your Items</div>

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
                    <span class="muted">ID: <?= (int)$it['product_id'] ?></span>
                  </td>
                  <td><?= (int)$it['qty'] ?></td>
                  <td>₹<?= number_format((float)$it['price'],2) ?></td>
                  <td><b>₹<?= number_format((float)$it['subtotal'],2) ?></b></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="divider"></div>

        <div class="row" style="justify-content:space-between">
          <span class="muted">Your total</span>
          <b>₹<?= number_format($sellerTotal,2) ?></b>
        </div>

        <div class="divider"></div>

        <?php
          $canPack = ($singleSeller && ($order['order_status'] ?? '') === 'placed');
        ?>
        <?php if($canPack): ?>
          <form method="post" action="<?= BASE_URL ?>seller/mark-packed" onsubmit="return confirm('Mark this order as PACKED?')">
            <?= csrf_field() ?>
            <input type="hidden" name="order_no" value="<?= safe($orderNo) ?>">
            <button class="btn" type="submit">✅ Mark Packed</button>
          </form>
          <div class="muted" style="font-size:12px;margin-top:8px">
            Packing updates order to <b>packed</b> (single-seller orders only).
          </div>
        <?php else: ?>
          <div class="muted" style="font-size:12px">
            Packing button is available only for <b>single-seller</b> orders and when status is <b>placed</b>.
          </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <div style="font-weight:900;margin-bottom:8px">Delivery Address</div>
        <?php if(!$addr): ?>
          <div class="muted">No address snapshot.</div>
        <?php else: ?>
          <div><b><?= safe($addr['name'] ?? '') ?></b> <span class="muted">(<?= safe($addr['phone'] ?? '') ?>)</span></div>
          <div class="muted" style="margin-top:6px;line-height:1.6">
            <?= safe($addr['line1'] ?? '') ?>
            <?php if(!empty($addr['line2'])): ?>, <?= safe($addr['line2']) ?><?php endif; ?>
            <?php if(!empty($addr['landmark'])): ?>, <?= safe($addr['landmark']) ?><?php endif; ?><br>
            <?= safe($addr['city'] ?? '') ?>, <?= safe($addr['state'] ?? '') ?> - <b><?= safe($addr['pincode'] ?? '') ?></b>
          </div>
        <?php endif; ?>

        <div class="divider"></div>

        <div style="font-weight:900;margin-bottom:8px">Order Summary</div>
        <div class="row" style="justify-content:space-between"><span class="muted">Grand total</span><b>₹<?= number_format((float)$order['grand_total'],2) ?></b></div>
        <div class="row" style="justify-content:space-between"><span class="muted">Delivery charge</span><span>₹<?= number_format((float)$order['delivery_charge'],2) ?></span></div>
        <div class="row" style="justify-content:space-between"><span class="muted">Discount</span><span>₹<?= number_format((float)$order['discount_amount'],2) ?></span></div>
        <div class="row" style="justify-content:space-between"><span class="muted">ETA</span><span><?= $order['delivery_eta'] ? safe($order['delivery_eta']) : '-' ?></span></div>

      </div>
    </div>

  </div>
</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>