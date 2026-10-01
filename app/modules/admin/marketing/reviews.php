<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
requireLogin(); requireRole('admin');
global $conn;

$msg='';
if(isPost()){
  require_csrf();
  $id=(int)($_POST['id'] ?? 0);
  $act=$_POST['act'] ?? '';
  if($id>0 && in_array($act,['approve','reject'],true)){
    $status = $act==='approve' ? 'approved' : 'rejected';
    $stmt=$conn->prepare("UPDATE reviews SET status=? WHERE id=?");
    $stmt->bind_param("si",$status,$id);
    $stmt->execute(); $stmt->close();
    $msg="Updated review #$id";
  }
}

$list=$conn->query("SELECT r.*, p.name product_name FROM reviews r JOIN products p ON p.id=r.product_id ORDER BY r.id DESC")->fetch_all(MYSQLI_ASSOC);

$title="Reviews - Admin";
include __DIR__ . '/../../../views/layout/header.php';
?>
<div class="container">
  <div class="page-title">Product Reviews</div>
  <?php if($msg): ?><div class="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <div class="card" style="overflow:auto">
    <table class="table">
      <thead><tr><th>ID</th><th>Product</th><th>User</th><th>Rating</th><th>Title</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach($list as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= htmlspecialchars($r['product_name']) ?></td>
          <td><?= htmlspecialchars($r['user_email']) ?></td>
          <td><?= (int)$r['rating'] ?>/5</td>
          <td><?= htmlspecialchars($r['title']) ?></td>
          <td><?= htmlspecialchars($r['status']) ?></td>
          <td>
            <?php if($r['status']==='pending'): ?>
              <form method="post" style="margin:0;display:flex;gap:8px">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn" name="act" value="approve">Approve</button>
                <button class="btn btn-danger" name="act" value="reject">Reject</button>
              </form>
            <?php else: ?><span class="muted">-</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../../../views/layout/footer.php'; ?>
