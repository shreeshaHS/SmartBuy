<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
requireRole('admin');
global $conn;

$title = "Delivery Boys - ".APP_NAME;
require __DIR__ . '/../../views/layout/header.php';

$rows = $conn->query("
  SELECT email, name, status, created_at
  FROM users
  WHERE role='delivery'
  ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="role-shell">
  <aside class="role-nav">
    <div class="role-badge">👑 Admin</div>
    <a class="role-link" href="<?= BASE_URL ?>admin/dashboard">Dashboard</a>
    <a class="role-link" href="<?= BASE_URL ?>admin/orders">Orders</a>
    <a class="role-link" href="<?= BASE_URL ?>admin/delivery/assign">Delivery Assign</a>
    <a class="role-link" href="<?= BASE_URL ?>admin/delivery-create">Create Delivery</a>
    <a class="role-link active" href="<?= BASE_URL ?>admin/delivery-list">Delivery List</a>
  </aside>

  <section class="role-main">
    <div class="page-title">Delivery Boys</div>

    <div class="card">
      <div class="row" style="justify-content:space-between;align-items:center">
        <div class="muted">All delivery accounts created by admin.</div>
        <a class="btn small" href="<?= BASE_URL ?>admin/delivery-create">+ Create</a>
      </div>

      <div class="divider"></div>

      <?php if(!$rows): ?>
        <div class="muted">No delivery boys yet.</div>
      <?php else: ?>
        <table class="table" width="100%">
          <thead>
            <tr>
              <th>Email</th>
              <th>Name</th>
              <th>Status</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><b><?= safe($r['email']) ?></b></td>
              <td><?= safe($r['name'] ?? '-') ?></td>
              <td><?= ((int)($r['status'] ?? 0)===1) ? '<span class="badge">Active</span>' : '<span class="badge danger">Disabled</span>' ?></td>
              <td class="muted"><?= safe($r['created_at'] ?? '-') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>