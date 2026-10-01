<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
requireRole('admin');
global $conn;

// Filters
$status = trim($_GET['status'] ?? '');
$q      = trim($_GET['q'] ?? '');

$w = " WHERE 1=1 ";
$params = [];
$types  = "";

if ($status !== '') {
  $w .= " AND o.order_status=? ";
  $params[] = $status; $types .= "s";
}
if ($q !== '') {
  $w .= " AND (o.order_no LIKE ? OR o.user_email LIKE ?) ";
  $like = "%{$q}%";
  $params[] = $like; $types .= "s";
  $params[] = $like; $types .= "s";
}

// Support both column names (some old code uses delivery_email)
$deliveryCol = null;
$chk = $conn->query("SHOW COLUMNS FROM orders LIKE 'assigned_delivery_email'");
if ($chk && $chk->num_rows > 0) $deliveryCol = "assigned_delivery_email";
else {
  $chk2 = $conn->query("SHOW COLUMNS FROM orders LIKE 'delivery_email'");
  if ($chk2 && $chk2->num_rows > 0) $deliveryCol = "delivery_email";
}

$selectDelivery = $deliveryCol ? "o.`$deliveryCol` AS delivery_assignee" : "NULL AS delivery_assignee";

$sql = "
  SELECT
    o.id, o.order_no, o.user_email, o.order_status, o.payment_status,
    o.grand_total, o.delivery_eta, o.created_at,
    $selectDelivery
  FROM orders o
  $w
  ORDER BY o.id DESC
  LIMIT 300
";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$title = "Admin Orders - " . APP_NAME;
require __DIR__ . '/../../views/layout/header.php';
?>

<div class="container">
  <div class="card">
    <div class="row" style="justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
      <div>
        <div class="page-title">Admin Orders</div>
        <div class="muted">Manage orders and assign delivery.</div>
      </div>
      <a class="btn ghost" href="<?= BASE_URL ?>admin/dashboard">← Dashboard</a>
    </div>

    <div class="divider"></div>

    <form class="row" style="gap:10px;flex-wrap:wrap" method="get" action="<?= BASE_URL ?>admin/orders">
      <input class="in" name="q" placeholder="Search order no / customer email" value="<?= safe($q) ?>" style="min-width:260px">
      <select class="in" name="status">
        <option value="">All status</option>
        <?php
          $opts = ['placed','packed','shipped','out_for_delivery','delivered','cancelled','failed','returned'];
          foreach($opts as $op){
            $sel = ($status===$op) ? 'selected' : '';
            echo "<option value='".safe($op)."' $sel>".safe(ucwords(str_replace('_',' ',$op)))."</option>";
          }
        ?>
      </select>
      <button class="btn" type="submit">Apply</button>
      <a class="btn ghost" href="<?= BASE_URL ?>admin/orders">Reset</a>
    </form>

    <div class="divider"></div>

    <?php if(empty($rows)): ?>
      <div class="muted">No orders found.</div>
    <?php else: ?>
      <div style="overflow:auto">
        <table class="table" width="100%">
          <thead>
            <tr>
              <th>Order</th>
              <th>Customer</th>
              <th>Status</th>
              <th>Payment</th>
              <th>Total</th>
              <th>ETA</th>
              <th>Assigned Delivery</th>
              <th>Created</th>
              <th style="width:160px"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($rows as $r): ?>
              <tr>
                <td><b><?= safe($r['order_no']) ?></b></td>
                <td class="muted"><?= safe($r['user_email']) ?></td>
                <td><span class="badge"><?= safe($r['order_status']) ?></span></td>
                <td class="muted"><?= safe($r['payment_status']) ?></td>
                <td><b>₹<?= number_format((float)$r['grand_total'],2) ?></b></td>
                <td class="muted"><?= $r['delivery_eta'] ? safe($r['delivery_eta']) : '-' ?></td>
                <td class="muted"><?= $r['delivery_assignee'] ? safe($r['delivery_assignee']) : '-' ?></td>
                <td class="muted"><?= safe($r['created_at']) ?></td>
                <td class="row" style="gap:8px">
                  <a class="btn small" href="<?= BASE_URL ?>admin/assign-delivery?no=<?= urlencode($r['order_no']) ?>">Assign</a>
                  <a class="btn small ghost" href="<?= BASE_URL ?>admin/order-view?id=<?= (int)$r['id'] ?>">View</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>