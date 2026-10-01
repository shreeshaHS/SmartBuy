<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
requireLogin();
requireRole('admin');
global $conn;

$status = $_GET['status'] ?? 'pending';
$allowed = ['pending','approved','rejected'];
if (!in_array($status, $allowed, true)) $status = 'pending';

$stmt = db_prepare("
  SELECT id, user_email, store_name, owner_name, phone, status, created_at
  FROM seller_requests
  WHERE status=?
  ORDER BY id DESC
");
$stmt->bind_param("s", $status);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require __DIR__ . '/../../../views/layout/header.php';
?>

<div class="container">
  <div class="card">

    <!-- HEADER -->
    <div class="row" style="justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
      <div>
        <div class="page-title">Seller Verification Requests</div>
        <div class="muted">Review uploaded documents. Approve to convert user into Seller.</div>
      </div>

      <div class="row" style="gap:8px;flex-wrap:wrap">
        <a class="btn small <?= $status==='pending'?'':'ghost' ?>" href="<?= BASE_URL ?>admin/sellers/requests?status=pending">Pending</a>
        <a class="btn small <?= $status==='approved'?'':'ghost' ?>" href="<?= BASE_URL ?>admin/sellers/requests?status=approved">Approved</a>
        <a class="btn small <?= $status==='rejected'?'':'ghost' ?>" href="<?= BASE_URL ?>admin/sellers/requests?status=rejected">Rejected</a>
      </div>
    </div>

    <div class="divider"></div>

    <div style="overflow:auto">
      <table class="table" width="100%">
        <thead>
          <tr>
            <th>ID</th>
            <th>User Email</th>
            <th>Store</th>
            <th>Owner</th>
            <th>Phone</th>
            <th>Status</th>
            <th>Created</th>
            <th style="min-width:260px">Action</th>
          </tr>
        </thead>
        <tbody>

        <?php if(empty($rows)): ?>
          <tr><td colspan="8" class="muted">No requests found.</td></tr>
        <?php else: ?>
          <?php foreach($rows as $r): ?>
            <?php $rid = (int)$r['id']; $st = strtolower((string)($r['status'] ?? '')); ?>
            <tr>
              <td><?= $rid ?></td>
              <td><?= safe($r['user_email']) ?></td>
              <td><b><?= safe($r['store_name']) ?></b></td>
              <td><?= safe($r['owner_name']) ?></td>
              <td><?= safe($r['phone']) ?></td>
              <td><span class="badge"><?= safe($r['status']) ?></span></td>
              <td><?= safe($r['created_at']) ?></td>

              <!-- ACTION BUTTONS -->
              <td>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">

                  <!-- VIEW -->
                  <a class="btn small ghost"
                     href="<?= BASE_URL ?>admin/sellers/view?id=<?= $rid ?>">
                    👁 View
                  </a>

                  <?php if($st === 'pending'): ?>

                    <!-- APPROVE -->
                    <form method="post"
                          action="<?= BASE_URL ?>admin/sellers/approve"
                          style="margin:0"
                          onsubmit="return confirm('Approve this seller request?')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= $rid ?>">
                      <button class="btn small" type="submit">
                        ✅ Approve
                      </button>
                    </form>

                    <!-- REJECT -->
                    <form method="post"
                          action="<?= BASE_URL ?>admin/sellers/reject"
                          style="margin:0"
                          onsubmit="return confirm('Reject this seller request?')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= $rid ?>">
                      <input type="hidden" name="admin_note" value="">
                      <button class="btn small ghost" type="submit">
                        ❌ Reject
                      </button>
                    </form>

                  <?php endif; ?>

                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>

        </tbody>
      </table>
    </div>

    <?php if($status==='pending' && !empty($rows)): ?>
      <div class="muted" style="margin-top:10px;font-size:12px">
        Tip: Approve/Reject only visible for <b>pending</b> requests.
      </div>
    <?php endif; ?>

  </div>
</div>

<?php require __DIR__ . '/../../../views/layout/footer.php'; ?>