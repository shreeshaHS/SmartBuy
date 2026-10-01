<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
requireRole('delivery');
global $conn;

$title = "Return Pickups - " . APP_NAME;
$me = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';

function addr_short($json){
  $a = json_decode((string)$json,true);
  if(!is_array($a)) return '';
  $line1 = $a['address_line1'] ?? ($a['line1'] ?? '');
  $s = trim($line1.' '.($a['city'] ?? '').' '.($a['state'] ?? '').' '.($a['pincode'] ?? ''));
  return $s;
}

// Mark picked
if (isPost()) {
  require_csrf();
  $rid = (int)($_POST['rid'] ?? 0);
  $action = trim((string)($_POST['action'] ?? ''));
  if ($rid > 0 && $action === 'pick') {
    $stmt = $conn->prepare("UPDATE return_requests SET status='picked' WHERE id=? AND status IN('approved','assigned')");
    $stmt->bind_param("i", $rid);
    $stmt->execute();
    $stmt->close();
  }
  redirect('delivery/return_pickups');
}

// List returns (basic)
$rows = [];
try{
  $rows = $conn->query("
    SELECT rr.id, rr.order_id, rr.product_id, rr.reason, rr.status, rr.created_at,
           o.order_no, o.user_email, o.address_json
    FROM return_requests rr
    LEFT JOIN orders o ON o.id = rr.order_id
    WHERE rr.status IN('approved','assigned','picked')
    ORDER BY rr.id DESC
    LIMIT 200
  ")->fetch_all(MYSQLI_ASSOC);
}catch(Throwable $e){}

$navActive = 'returns';
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
    <div class="row" style="justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
      <div>
        <div class="page-title">Return Pickups</div>
        <div class="muted" style="font-size:12px">Approved returns that need pickup from customer</div>
      </div>
    </div>

    <div class="divider"></div>

    <div class="card">
      <?php if(empty($rows)): ?>
        <div class="muted">No return pickups right now.</div>
      <?php else: ?>
        <div style="overflow:auto">
          <table class="table" width="100%">
            <thead>
              <tr>
                <th>Return</th>
                <th>Order</th>
                <th>User</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Address</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($rows as $r): ?>
              <?php
                $short = addr_short($r['address_json'] ?? '');
                $mapUrl = $short ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($short) : '';
              ?>
              <tr>
                <td><b>#<?= (int)$r['id'] ?></b><br><span class="muted"><?= safe($r['created_at'] ?? '') ?></span></td>
                <td><?= safe($r['order_no'] ?? '') ?></td>
                <td><?= safe($r['user_email'] ?? '') ?></td>
                <td class="muted"><?= safe($r['reason'] ?? '') ?></td>
                <td><span class="badge"><?= safe($r['status'] ?? '') ?></span></td>
                <td class="muted">
                  <?= safe($short) ?>
                  <?php if($mapUrl): ?><br><a target="_blank" rel="noreferrer" href="<?= safe($mapUrl) ?>">Open map</a><?php endif; ?>
                </td>
                <td>
                  <?php if(($r['status'] ?? '') !== 'picked'): ?>
                    <form method="post" style="margin:0">
                      <?= csrf_field() ?>
                      <input type="hidden" name="rid" value="<?= (int)$r['id'] ?>">
                      <input type="hidden" name="action" value="pick">
                      <button class="btn small" type="submit">Mark picked</button>
                    </form>
                  <?php else: ?>
                    <span class="muted">Picked</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
