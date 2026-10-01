<?php
require_once __DIR__ . '/../../config/bootstrap.php';

// ✅ safest include (no path confusion)
require_once dirname(__DIR__, 2) . '/helpers/delivery.php';

requireLogin();
global $conn;

$orderNo = trim($_GET['no'] ?? '');
if ($orderNo === '') redirect('home');

$user = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
if ($user === '') redirect('login');

$stmt = $conn->prepare("
  SELECT order_no, grand_total, order_status, delivery_eta, created_at
  FROM orders
  WHERE order_no=? AND user_email=?
  LIMIT 1
");
$stmt->bind_param("ss", $orderNo, $user);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

$title = "Order Success - " . APP_NAME;
require __DIR__ . '/../../views/layout/header.php';
?>

<style>
.timeline{display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;margin-top:14px}
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
    <?php if(!$order): ?>
      <div class="page-title">Order not found</div>
      <div class="muted">Invalid order number.</div>
      <div class="divider"></div>
      <a class="btn" href="<?= BASE_URL ?>">Go Home</a>
    <?php else: ?>
      <div class="page-title">Order Placed ✅</div>
      <div class="muted">Order No: <b><?= safe($order['order_no']) ?></b></div>

      <div style="margin-top:10px">
        Total: <b>₹<?= number_format((float)$order['grand_total'], 2) ?></b>
      </div>

      <div class="divider"></div>

      <div style="font-weight:900;margin-bottom:6px">Tracking</div>
      <?= render_order_timeline(
            (string)($order['order_status'] ?? 'placed'),
            !empty($order['delivery_eta']) ? date('D, d M', strtotime($order['delivery_eta'])) : null
          ) ?>

      <div class="divider"></div>

      <a class="btn" href="<?= BASE_URL ?>?page=account/orders">My Orders</a>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>