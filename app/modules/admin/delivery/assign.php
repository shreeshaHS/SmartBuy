<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
global $conn;

requireLogin();
requireRole('admin');

// CSRF protection
if (isPost()) { require_csrf(); }

$title = 'Assign Delivery - ' . APP_NAME;
require __DIR__ . '/../../../views/layout/header.php';

$err = null; 
$ok  = null;

// ✅ Delivery accounts
$deliveryUsers = $conn->query("
  SELECT email, COALESCE(name,email) AS name 
  FROM users 
  WHERE role='delivery' AND status=1 
  ORDER BY email
")->fetch_all(MYSQLI_ASSOC);

// ✅ Assign delivery boy
if (isPost()) {
  $orderId = (int)($_POST['order_id'] ?? 0);
  $deliveryEmail = trim($_POST['delivery_email'] ?? '');

  if ($orderId <= 0 || $deliveryEmail === '') {
    $err = 'Select order + delivery boy.';
  } else {

    // ensure delivery email is valid + active delivery account
    $chk = $conn->prepare("SELECT 1 FROM users WHERE email=? AND role='delivery' AND status=1 LIMIT 1");
    $chk->bind_param("s", $deliveryEmail);
    $chk->execute();
    $exists = (bool)$chk->get_result()->fetch_row();
    $chk->close();

    if (!$exists) {
      $err = "Invalid delivery account.";
    } else {

      // ✅ assign + keep status correct
      // If order is placed, make it packed first (your old logic)
      $stmt = $conn->prepare("
        UPDATE orders
        SET delivery_email=?,
            order_status=CASE 
              WHEN order_status='placed' THEN 'packed'
              ELSE order_status
            END
        WHERE id=?
      ");
      $stmt->bind_param('si', $deliveryEmail, $orderId);
      $stmt->execute();
      $stmt->close();

      $ok = 'Assigned ✅';
    }
  }
}

// ✅ check column exists safely (uses $conn not $mysqli)
$hasDeliveryEmail = false;
$check = $conn->query("SHOW COLUMNS FROM orders LIKE 'delivery_email'");
if ($check && $check->num_rows > 0) $hasDeliveryEmail = true;

// ✅ Load orders ready to assign (packed)
if ($hasDeliveryEmail) {
  $orders = $conn->query("
    SELECT id, order_no, user_email, order_status, grand_total, delivery_email
    FROM orders
    WHERE order_status='packed'
    ORDER BY id DESC
    LIMIT 200
  ")->fetch_all(MYSQLI_ASSOC);
} else {
  $orders = $conn->query("
    SELECT id, order_no, user_email, order_status, grand_total, NULL AS delivery_email
    FROM orders
    WHERE order_status='packed'
    ORDER BY id DESC
    LIMIT 200
  ")->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="role-shell">
  <aside class="role-nav">
    <div class="role-badge">👑 Admin</div>
    <a class="role-link" href="<?= BASE_URL ?>admin/dashboard">Dashboard</a>
    <a class="role-link" href="<?= BASE_URL ?>admin/sellers/requests">Seller Requests</a>
    <a class="role-link" href="<?= BASE_URL ?>admin/products/pending">Pending Products</a>
    <a class="role-link active" href="<?= BASE_URL ?>admin/delivery/assign">Delivery Assign</a>
    <div class="role-sep"></div>
    <a class="role-link" href="<?= BASE_URL ?>admin/catalog/categories">Catalog</a>
  </aside>

  <section class="role-main">

    <div class="page-title">Assign Delivery</div>

    <?php if($err): ?><div class="alert error"><?= safe($err) ?></div><?php endif; ?>
    <?php if($ok): ?><div class="alert ok"><?= safe($ok) ?></div><?php endif; ?>

    <div class="card">
      <div class="muted">Assign a delivery boy to packed orders. Assigned orders appear in Delivery dashboard.</div>
      <div class="divider"></div>

      <form method="post" class="grid" style="grid-template-columns:1.2fr 1fr auto;gap:10px;align-items:end">
        <?= csrf_field() ?>

        <div class="f has-value">
          <select name="order_id" required>
            <option value="">Select Order</option>
            <?php foreach($orders as $o): ?>
              <option value="<?= (int)$o['id'] ?>">
                <?= safe($o['order_no']) ?> (<?= safe($o['order_status']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <label>Order</label>
        </div>

        <div class="f has-value">
          <select name="delivery_email" required>
            <option value="">Select Delivery Boy</option>
            <?php foreach($deliveryUsers as $d): ?>
              <option value="<?= safe($d['email']) ?>"><?= safe($d['name']) ?> (<?= safe($d['email']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <label>Delivery</label>
        </div>

        <button class="btn small" type="submit">Assign</button>
      </form>
    </div>

    <div class="card" style="margin-top:14px">
      <div class="page-title">Packed Orders (Ready)</div>

      <?php if(empty($orders)): ?>
        <div class="muted">No packed orders to assign.</div>
      <?php else: ?>
        <table class="table" width="100%">
          <thead>
            <tr>
              <th>Order</th>
              <th>User</th>
              <th>Status</th>
              <th>Total</th>
              <th>Delivery</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($orders as $o): ?>
            <tr>
              <td><b><?= safe($o['order_no']) ?></b></td>
              <td><?= safe($o['user_email']) ?></td>
              <td><?= safe($o['order_status']) ?></td>
              <td>₹<?= number_format((float)$o['grand_total'],2) ?></td>
              <td class="muted"><?= safe($o['delivery_email'] ?: '-') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  </section>
</div>

<?php require __DIR__ . '/../../../views/layout/footer.php'; ?>