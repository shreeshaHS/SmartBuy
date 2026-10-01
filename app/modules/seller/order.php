<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
requireRole('seller');
global $conn;

$seller = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
if ($seller === '') redirect('login');

// Filters
$status = trim($_GET['status'] ?? '');
$q      = trim($_GET['q'] ?? '');

$w = " WHERE p.seller_email=? ";
$params = [$seller];
$types  = "s";

if ($status !== '') {
  $w .= " AND o.order_status=? ";
  $params[] = $status;
  $types .= "s";
}
if ($q !== '') {
  $w .= " AND (o.order_no LIKE ? OR o.user_email LIKE ?) ";
  $like = "%{$q}%";
  $params[] = $like; $types .= "s";
  $params[] = $like; $types .= "s";
}

// Seller orders list (only orders containing seller products)
$sql = "
  SELECT
    o.id, o.order_no, o.user_email, o.order_status, o.payment_status, o.created_at,
    o.delivery_eta, o.delivery_charge, o.discount_amount, o.coupon_code, o.grand_total,
    COUNT(DISTINCT oi.id) AS items_count,
    SUM(oi.subtotal) AS seller_items_total
  FROM orders o
  JOIN order_items oi ON oi.order_id = o.id
  JOIN products p ON p.id = oi.product_id
  $w
  GROUP BY o.id
  ORDER BY o.id DESC
  LIMIT 200
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$title = "Seller Orders - " . APP_NAME;
require __DIR__ . '/../../views/layout/header.php';
?>

<div class="container">
  <div class="card">
    <div class="row" style="justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
      <div>
        <div class="page-title">Seller Orders</div>
        <div class="muted">Only orders that contain <b>your products</b>.</div>
      </div>
      <a class="btn ghost" href="<?= BASE_URL ?>seller/dashboard">← Dashboard</a>
    </div>

    <div class="divider"></div>

    <form class="row" style="gap:10px;flex-wrap:wrap" method="get" action="<?= BASE_URL ?>seller/orders">
      <input class="in" name="q" placeholder="Search by order no / customer email" value="<?= safe($q) ?>" style="min-width:260px">
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
      <a class="btn ghost" href="<?= BASE_URL ?>seller/orders">Reset</a>
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
              <th>Items</th>
              <th>Your Total</th>
              <th>ETA</th>
              <th>Created</th>
              <th style="width:120px"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($rows as $r): ?>
              <tr>
                <td><b><?= safe($r['order_no']) ?></b></td>
                <td class="muted"><?= safe($r['user_email']) ?></td>
                <td><span class="badge"><?= safe($r['order_status']) ?></span></td>
                <td class="muted"><?= safe($r['payment_status']) ?></td>
                <td><?= (int)$r['items_count'] ?></td>
                <td><b>₹<?= number_format((float)$r['seller_items_total'],2) ?></b></td>
                <td class="muted"><?= $r['delivery_eta'] ? safe($r['delivery_eta']) : '-' ?></td>
                <td class="muted"><?= safe($r['created_at']) ?></td>
                <td>
                  <a class="btn small" href="<?= BASE_URL ?>seller/order-view?no=<?= urlencode($r['order_no']) ?>">View</a>
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