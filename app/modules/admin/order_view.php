<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
requireRole('admin');
global $conn;

$id = (int)($_GET['id'] ?? 0);
if ($id<=0) { redirect('admin/orders'); }

// Load order (no restriction for admin)
$stmt = $conn->prepare("
  SELECT *
  FROM orders
  WHERE id=?
  LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$order){ http_response_code(404); echo "Not found"; exit; }

$title = "Order #".($order['order_no'] ?? $id)." - Admin";

$addr = [];
if (!empty($order['address_json'])) {
  $t = json_decode((string)$order['address_json'], true);
  if (is_array($t)) $addr = $t;
}

// Load items (with product name)
$items = [];
try{
  $stmtI = $conn->prepare("
    SELECT oi.product_id, oi.qty, oi.price, oi.subtotal, p.name
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id=?
    ORDER BY oi.id ASC
  ");
  $stmtI->bind_param("i", $id);
  $stmtI->execute();
  $items = $stmtI->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmtI->close();
}catch(Throwable $e){}

require __DIR__ . '/../../views/layout/header.php';

$status = (string)($order['order_status'] ?? '');
$eta = (string)($order['delivery_eta'] ?? '');
?>

<div class="container">
  <div class="card">
    <div class="row" style="justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
      <div>
        <div class="page-title">Order #<?= safe($order['order_no'] ?? '') ?></div>
        <div class="muted">
          Status: <b><?= safe($status) ?></b>
          <?php if($eta): ?> • ETA: <b><?= safe($eta) ?></b><?php endif; ?>
        </div>
      </div>
      <a class="btn ghost" href="<?= BASE_URL ?>admin/orders">← Back to orders</a>
    </div>

    <div class="divider"></div>

    <?= render_order_timeline((string)$status, $eta ?: null) ?>

    <div class="grid" style="grid-template-columns:1fr 1fr;gap:12px;margin-top:20px">
      <div class="card" style="background:#f9f9f9;border:1px solid #eee">
        <div style="font-weight:950;margin-bottom:8px">Customer</div>
        <div><b>Email:</b> <?= safe($order['user_email'] ?? '') ?></div>
        <div class="muted" style="margin-top:8px">Payment: <b><?= safe($order['payment_method'] ?? '') ?></b> • <b><?= safe($order['payment_status'] ?? '') ?></b></div>
        <?php if(!empty($order['payment_ref'])): ?>
            <div class="muted">Payment Ref (UTR): <b><?= safe($order['payment_ref']) ?></b></div>
        <?php endif; ?>
        <div class="muted" style="margin-top:6px">Placed: <?= safe($order['created_at'] ?? '') ?></div>
      </div>

      <div class="card" style="background:#f9f9f9;border:1px solid #eee">
        <div style="font-weight:950;margin-bottom:8px">Delivery Address</div>
        <?php if(!$addr): ?>
          <div class="muted">No address data.</div>
        <?php else: ?>
          <div><b><?= safe($addr['name'] ?? ($addr['full_name'] ?? '')) ?></b> <?= safe($addr['phone'] ?? '') ?></div>
          <div class="muted" style="margin-top:6px;line-height:1.6">
            <?= safe($addr['address_line1'] ?? ($addr['line1'] ?? '')) ?><br>
            <?= safe($addr['city'] ?? '') ?>, <?= safe($addr['state'] ?? '') ?> - <b><?= safe($addr['pincode'] ?? '') ?></b>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card" style="margin-top:12px;border:1px solid #eee;box-shadow:none">
      <div style="font-weight:950;margin-bottom:8px">Items</div>
      <?php if(empty($items)): ?>
        <div class="muted">No items found.</div>
      <?php else: ?>
        <div style="overflow:auto">
          <table class="table" width="100%">
            <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
            <tbody>
              <?php foreach($items as $it): ?>
                <tr>
                  <td><?= safe($it['name'] ?? ('#'.$it['product_id'])) ?></td>
                  <td><?= (int)$it['qty'] ?></td>
                  <td>₹<?= number_format((float)$it['price'],2) ?></td>
                  <td>₹<?= number_format((float)$it['subtotal'],2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <div class="divider"></div>

      <div class="row" style="justify-content:space-between;align-items:center">
        <div class="muted">Grand total</div>
        <div style="font-weight:950;font-size:18px">₹<?= number_format((float)($order['grand_total'] ?? 0),2) ?></div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
