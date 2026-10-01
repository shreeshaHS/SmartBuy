<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
requireLogin(); requireRole('admin');
global $conn;

$msg = '';
if(isPost()){
  require_csrf();
  $action = $_POST['action'] ?? '';
  if($action==='create'){
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $type = ($_POST['type'] ?? 'percent')==='fixed' ? 'fixed' : 'percent';
    $value = (float)($_POST['value'] ?? 0);
    $min = (float)($_POST['min_cart'] ?? 0);
    $maxd = (float)($_POST['max_discount'] ?? 0);
    $start = trim($_POST['start_at'] ?? '');
    $end = trim($_POST['end_at'] ?? '');
    $limit = (int)($_POST['usage_limit'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;

    if($code==='' || $value<=0){ $msg = "Enter valid coupon code and value."; }
    else {
      $stmt = $conn->prepare("INSERT INTO coupons(code,type,value,min_cart,max_discount,start_at,end_at,usage_limit,used_count,active,created_at)
                              VALUES (?,?,?,?,?,?,?,?,0,?,NOW())
                              ON DUPLICATE KEY UPDATE type=VALUES(type), value=VALUES(value), min_cart=VALUES(min_cart), max_discount=VALUES(max_discount),
                                start_at=VALUES(start_at), end_at=VALUES(end_at), usage_limit=VALUES(usage_limit), active=VALUES(active)");
      $stmt->bind_param("sssddssii", $code, $type, $value, $min, $maxd, $start, $end, $limit, $active);
      $stmt->execute();
      $stmt->close();
      $msg = "Saved coupon: $code";
    }
  }
  if($action==='delete'){
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $stmt = $conn->prepare("DELETE FROM coupons WHERE code=?");
    $stmt->bind_param("s",$code);
    $stmt->execute();
    $stmt->close();
    $msg = "Deleted: $code";
  }
}

$list = $conn->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

$title = "Coupons - Admin";
include __DIR__ . '/../../../views/layout/header.php';
?>
<div class="container">
  <div class="page-title">Coupons</div>
  <?php if($msg): ?><div class="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="card">
    <h3>Create / Update Coupon</h3>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="grid grid-3">
        <div>
          <label>Code</label>
          <input name="code" placeholder="SAVE10" required>
        </div>
        <div>
          <label>Type</label>
          <select name="type">
            <option value="percent">Percent</option>
            <option value="fixed">Fixed</option>
          </select>
        </div>
        <div>
          <label>Value</label>
          <input name="value" type="number" step="0.01" required>
        </div>
        <div>
          <label>Min Cart</label>
          <input name="min_cart" type="number" step="0.01">
        </div>
        <div>
          <label>Max Discount (for percent)</label>
          <input name="max_discount" type="number" step="0.01">
        </div>
        <div>
          <label>Usage Limit</label>
          <input name="usage_limit" type="number">
        </div>
        <div>
          <label>Start At (YYYY-MM-DD HH:MM:SS)</label>
          <input name="start_at" placeholder="2026-02-21 00:00:00">
        </div>
        <div>
          <label>End At (YYYY-MM-DD HH:MM:SS)</label>
          <input name="end_at" placeholder="2026-12-31 23:59:59">
        </div>
        <div style="display:flex;align-items:end;gap:10px">
          <label style="display:flex;gap:6px;align-items:center">
            <input type="checkbox" name="active" checked> Active
          </label>
          <button class="btn" type="submit">Save</button>
        </div>
      </div>
    </form>
  </div>

  <div class="card">
    <h3>All Coupons</h3>
    <div style="overflow:auto">
      <table class="table">
        <thead>
          <tr>
            <th>Code</th><th>Type</th><th>Value</th><th>Min</th><th>Max</th><th>Used</th><th>Limit</th><th>Active</th><th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($list as $c): ?>
          <tr>
            <td><?= htmlspecialchars($c['code']) ?></td>
            <td><?= htmlspecialchars($c['type']) ?></td>
            <td><?= htmlspecialchars($c['value']) ?></td>
            <td><?= htmlspecialchars($c['min_cart']) ?></td>
            <td><?= htmlspecialchars($c['max_discount']) ?></td>
            <td><?= htmlspecialchars($c['used_count']) ?></td>
            <td><?= htmlspecialchars($c['usage_limit']) ?></td>
            <td><?= (int)$c['active'] ? 'Yes' : 'No' ?></td>
            <td>
              <form method="post" style="margin:0" onsubmit="return confirm('Delete coupon?')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="code" value="<?= htmlspecialchars($c['code']) ?>">
                <button class="btn btn-danger" type="submit">Delete</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../../views/layout/footer.php'; ?>
