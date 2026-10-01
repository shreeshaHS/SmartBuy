<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
requireRole('delivery');
global $conn;

$me = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
$id = (int)($_GET['id'] ?? 0);
if ($id<=0) redirect('delivery/orders');

// Load order (assigned)
$stmt = $conn->prepare("SELECT id,order_no,user_email,order_status,delivery_otp,delivery_otp_expires FROM orders WHERE id=? AND (delivery_email=? OR delivery_boy_email=?) LIMIT 1");
$stmt->bind_param("iss",$id,$me,$me);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$order){ http_response_code(404); exit('Not found'); }

$err = null;
$ok  = null;

if (isPost()) {
  require_csrf();

  $action = (string)($_POST['action'] ?? '');
if ($action === 'send') {

  $otp = otp6();
  $exp = addMinutes(15);

  $stmt = $conn->prepare("
    UPDATE orders 
    SET delivery_otp=?, delivery_otp_expires=? 
    WHERE id=? AND (delivery_email=? OR delivery_boy_email=?)
  ");
  $stmt->bind_param("ssiss", $otp, $exp, $id, $me, $me);
  $stmt->execute();
  $stmt->close();

  // =========================
  // ✅ SEND EMAIL HERE
  // =========================
  if (function_exists('sendDeliveryOtpMail')) {
    @sendDeliveryOtpMail(
      $order['user_email'],   // customer email
      $order['user_email'],   // name fallback
      $order['order_no'],     // order number
      $otp                    // OTP
    );
  }

  $ok = "OTP generated and sent to customer email.";
  
  $order['delivery_otp'] = $otp;
  $order['delivery_otp_expires'] = $exp;
}

  if ($action === 'verify') {
    $otp = trim((string)($_POST['otp'] ?? ''));

    $saved = (string)($order['delivery_otp'] ?? '');
    $expires = (string)($order['delivery_otp_expires'] ?? '');

    if ($saved === '' || $expires === '') {
      $err = "OTP not generated yet. Click Send OTP first.";
    } elseif (strtotime($expires) < time()) {
      $err = "OTP expired. Send OTP again.";
    } elseif (!hash_equals($saved, $otp)) {
      $err = "Wrong OTP.";
    } else {
      // Mark delivered + clear otp
      $stmt = $conn->prepare("UPDATE orders SET order_status='delivered', payment_status=IF(payment_method='COD','paid',payment_status), delivery_otp=NULL, delivery_otp_expires=NULL, updated_at=NOW() WHERE id=? AND (delivery_email=? OR delivery_boy_email=?)");
      $stmt->bind_param("iss", $id, $me, $me);
      $stmt->execute();
      $stmt->close();
      // ✅ EMAIL delivered
if (function_exists('sendDeliveredMail')) {
  $custName = $order['user_email'] ?? 'Customer';
  @sendDeliveredMail($order['user_email'], $custName, $order['order_no']);
}

      redirect('delivery/view?id='.$id);
    }
  }
}

$title = "Verify OTP - " . APP_NAME;
$navActive = 'orders';
require __DIR__ . '/../../views/layout/header.php';
?>

<div class="role-shell">
  <aside class="role-nav">
  <div class="role-badge">🚚 Delivery</div>
  <a class="role-link <?= ($navActive==='dashboard'?'active':'') ?>" href="<?= BASE_URL ?>delivery/dashboard">Dashboard</a>
  <a class="role-link <?= ($navActive==='orders'?'active':'') ?>" href="<?= BASE_URL ?>delivery/orders">Orders</a>
  <a class="role-link <?= ($navActive==='returns'?'active':'') ?>" href="<?= BASE_URL ?>delivery/return_pickups">Return Pickups</a>
  <div class="role-sep"></div>
  <a class="role-link" href="<?= BASE_URL ?>products">Open Store</a>
</aside>
  <section class="role-main">
    <div class="row" style="justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
      <div>
        <div class="page-title" style="margin:0">Verify Delivery OTP</div>
        <div class="muted" style="font-size:12px">Order #<?= safe($order['order_no'] ?? '') ?> • Customer: <?= safe($order['user_email'] ?? '') ?></div>
      </div>
      <a class="btn ghost" href="<?= BASE_URL ?>delivery/view?id=<?= (int)$id ?>">← Back</a>
    </div>

    <div class="divider"></div>

    <?php if($err): ?><div class="alert error"><?= safe($err) ?></div><?php endif; ?>
    <?php if($ok): ?><div class="alert ok"><?= safe($ok) ?></div><?php endif; ?>

    <div class="card" style="max-width:620px">
      <div style="font-weight:950;margin-bottom:8px">Step 1: Send OTP</div>
      <div class="muted" style="margin-bottom:10px">Generate a 6-digit OTP for this order. (In production you would SMS/email it to the customer.)</div>

      <form method="post" style="margin:0">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="send">
        <button class="btn" type="submit">Send OTP</button>
      </form>

      <div class="divider"></div>

      <div style="font-weight:950;margin-bottom:8px">Step 2: Verify OTP</div>
      <div class="muted" style="margin-bottom:10px">
        OTP expires in 15 minutes. Current: <b><?= safe($order['delivery_otp_expires'] ?? '—') ?></b>
      </div>

      <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:0">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="verify">
        <input class="input" name="otp" maxlength="6" placeholder="Enter OTP" style="max-width:220px" required>
        <button class="btn" type="submit">Mark Delivered</button>
      </form>
    </div>

  </section>
</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
