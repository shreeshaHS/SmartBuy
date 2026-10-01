<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
global $conn;

$user = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';

$stmt = $conn->prepare("
  SELECT order_no, grand_total, order_status, delivery_eta, created_at
  FROM orders
  WHERE user_email=?
  ORDER BY id DESC
");
$stmt->bind_param("s", $user);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$title = "My Orders - ".APP_NAME;
require __DIR__ . '/../../views/layout/header.php';
?>

<style>
.timeline{display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap;margin-top:10px}
.tstep{display:flex;align-items:center;gap:8px;min-width:150px;padding:8px 10px;border-radius:14px;border:1px solid rgba(0,0,0,.08)}
.tstep .dot{width:10px;height:10px;border-radius:999px;background:#bbb}
.tstep.done{background:rgba(34,197,94,.08);border-color:rgba(34,197,94,.25)}
.tstep.done .dot{background:#22c55e}
.tstep.active{background:rgba(124,58,237,.08);border-color:rgba(124,58,237,.25)}
.tstep.active .dot{background:#7c3aed}
.tstep.todo{opacity:.75}
.tstep .lbl{font-weight:700;font-size:13px}
</style>

<div class="container">
  <div class="page-title">My Orders</div>

  <?php if(!$orders): ?>
    <div class="card">No orders yet.</div>
  <?php else: ?>
    <?php foreach($orders as $o): ?>
      <div class="card" style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
          <div>
            <div style="font-weight:900">Order: <?= safe($o['order_no']) ?></div>
            <div class="muted">Placed: <?= safe($o['created_at']) ?></div>
            <div class="muted">ETA: <b><?= safe($o['delivery_eta'] ?? '-') ?></b></div>
          </div>
          <div style="text-align:right">
            <div style="font-weight:900">₹<?= number_format((float)$o['grand_total'],2) ?></div>
            <div class="muted">Status: <b><?= safe($o['order_status']) ?></b></div>
          </div>
        </div>

        <?= render_order_timeline($o['order_status'] ?? 'placed', $o['delivery_eta'] ?? null) ?>

        <div style="margin-top:10px">
          <a class="btn small" href="<?= BASE_URL ?>?page=order-success&no=<?= urlencode($o['order_no']) ?>">View</a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>