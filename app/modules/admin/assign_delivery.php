<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
requireRole('admin');
global $conn;

// Detect column name in orders table
$deliveryCol = null;
$chk = $conn->query("SHOW COLUMNS FROM orders LIKE 'assigned_delivery_email'");
if ($chk && $chk->num_rows > 0) $deliveryCol = "assigned_delivery_email";
else {
  $chk2 = $conn->query("SHOW COLUMNS FROM orders LIKE 'delivery_email'");
  if ($chk2 && $chk2->num_rows > 0) $deliveryCol = "delivery_email";
}

$orderNo = trim($_GET['no'] ?? ($_POST['order_no'] ?? ''));
if ($orderNo === '') redirect('admin/orders');

// Load order
$stmt = $conn->prepare("SELECT id, order_no, user_email, order_status, payment_status, grand_total FROM orders WHERE order_no=? LIMIT 1");
$stmt->bind_param("s", $orderNo);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
  $title = "Assign Delivery - " . APP_NAME;
  require __DIR__ . '/../../views/layout/header.php';
  echo '<div class="container"><div class="card"><div class="page-title">Order not found</div><a class="btn" href="'.BASE_URL.'admin/orders">Back</a></div></div>';
  require __DIR__ . '/../../views/layout/footer.php';
  exit;
}

// Delivery boys list
$deliveryUsers = $conn->query("SELECT email, name FROM users WHERE role='delivery' AND status=1 ORDER BY name,email")
                     ->fetch_all(MYSQLI_ASSOC);

$err = null;
$ok  = null;

if (isPost()) {
  require_csrf();

  if (!$deliveryCol) {
    $err = "Your orders table does not have delivery assignment column (assigned_delivery_email / delivery_email).";
  } else {
    $deliveryEmail = trim($_POST['delivery_email'] ?? '');
    if ($deliveryEmail === '') {
      $err = "Select a delivery boy.";
    } else {
      try {
        $conn->begin_transaction();

        // Update assignment
        $oid = (int)$order['id'];
        $sql = "UPDATE orders SET `$deliveryCol`=?, updated_at=NOW() WHERE id=?";
        $st = $conn->prepare($sql);
        $st->bind_param("si", $deliveryEmail, $oid);
        $st->execute();
        $st->close();

        // Optional status history
        $exists = $conn->query("SHOW TABLES LIKE 'order_status_history'")->num_rows > 0;
        if ($exists) {
          $note = "Assigned to delivery: " . $deliveryEmail;
          $status = (string)($order['order_status'] ?? 'placed');
          $ins = $conn->prepare("INSERT INTO order_status_history(order_id,status,note,created_at) VALUES (?,?,?,NOW())");
          $ins->bind_param("iss", $oid, $status, $note);
          $ins->execute();
          $ins->close();
        }

        $conn->commit();
        $ok = "Assigned successfully.";
      } catch (Throwable $e) {
        $conn->rollback();
        $err = $e->getMessage();
      }
    }
  }
}

$title = "Assign Delivery - " . APP_NAME;
require __DIR__ . '/../../views/layout/header.php';
?>

<div class="container">
  <div class="card">
    <div class="row" style="justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
      <div>
        <div class="page-title">Assign Delivery</div>
        <div class="muted">Order: <b><?= safe($order['order_no']) ?></b> • Customer: <?= safe($order['user_email']) ?></div>
      </div>
      <a class="btn ghost" href="<?= BASE_URL ?>admin/orders">← Back</a>
    </div>

    <div class="divider"></div>

    <?php if($err): ?>
      <div class="alert error"><?= safe($err) ?></div>
    <?php elseif($ok): ?>
      <div class="alert success"><?= safe($ok) ?></div>
    <?php endif; ?>

    <form method="post" style="max-width:520px">
      <?= csrf_field() ?>
      <input type="hidden" name="order_no" value="<?= safe($orderNo) ?>">

      <label class="muted">Select Delivery Boy</label>
      <select class="in" name="delivery_email" required style="width:100%;margin-top:6px">
        <option value="">-- Select --</option>
        <?php foreach($deliveryUsers as $d): ?>
          <option value="<?= safe($d['email']) ?>"><?= safe(($d['name'] ?? '') ?: $d['email']) ?> (<?= safe($d['email']) ?>)</option>
        <?php endforeach; ?>
      </select>

      <div class="row" style="gap:10px;margin-top:12px">
        <button class="btn" type="submit">Assign</button>
        <a class="btn ghost" href="<?= BASE_URL ?>admin/orders">Cancel</a>
      </div>

      <div class="muted" style="font-size:12px;margin-top:10px">
        This only assigns a delivery boy. (Status change can be done separately if you want.)
      </div>
    </form>

  </div>
</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>