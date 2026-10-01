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
    $stmt=$conn->prepare("UPDATE return_requests SET status=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("si",$status,$id);
    $stmt->execute();
    $stmt->close();
    $msg="Updated return request #$id";
  }
  if($id>0 && $act==='refund_wallet'){
    // refund to wallet
    $r = $conn->query("SELECT rr.*, o.grand_total FROM return_requests rr JOIN orders o ON o.id=rr.order_id WHERE rr.id=$id")->fetch_assoc();
    if($r && in_array($r['status'],['picked','approved'],true)){
      $amount = (float)$r['grand_total'];
      $email = $conn->real_escape_string($r['user_email']);
      // ensure wallet row exists
      $conn->query("INSERT IGNORE INTO wallets(email,balance) VALUES ('$email',0)");
      $stmtW=$conn->prepare("UPDATE wallets SET balance = balance + ? WHERE email=?");
      $stmtW->bind_param("ds",$amount,$r['user_email']);
      $stmtW->execute(); $stmtW->close();
      $stmtT=$conn->prepare("INSERT INTO wallet_transactions(email,txn_type,amount,ref_type,ref_id,note) VALUES (?, 'credit', ?, 'refund', ?, 'Return refund')");
      $refId = (string)$r['order_id'];
      $stmtT->bind_param("sds",$r['user_email'],$amount,$refId);
      $stmtT->execute(); $stmtT->close();
      $stmtS=$conn->prepare("UPDATE return_requests SET status='refunded', updated_at=NOW() WHERE id=?");
      $stmtS->bind_param("i",$id);
      $stmtS->execute(); $stmtS->close();
      $msg="Refunded ₹".number_format($amount,2)." to wallet.";
    } else {
      $msg="Return must be approved/picked before refund.";
    }
  }
}

$list=$conn->query("SELECT rr.*, o.order_no, o.order_status FROM return_requests rr JOIN orders o ON o.id=rr.order_id ORDER BY rr.id DESC")->fetch_all(MYSQLI_ASSOC);

$title="Returns - Admin";
include __DIR__ . '/../../../views/layout/header.php';
?>
<div class="container">
  <div class="page-title">Return Requests</div>
  <?php if($msg): ?><div class="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="card" style="overflow:auto">
    <table class="table">
      <thead><tr>
        <th>ID</th><th>Order</th><th>User</th><th>Status</th><th>Reason</th><th>Action</th>
      </tr></thead>
      <tbody>
        <?php foreach($list as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['order_no']) ?></td>
            <td><?= htmlspecialchars($r['user_email']) ?></td>
            <td><?= htmlspecialchars($r['status']) ?></td>
            <td><?= htmlspecialchars($r['reason']) ?></td>
            <td style="display:flex;gap:8px;flex-wrap:wrap">
              <?php if($r['status']==='pending'): ?>
                <form method="post" style="margin:0">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn" name="act" value="approve">Approve</button>
                  <button class="btn btn-danger" name="act" value="reject">Reject</button>
                </form>
              <?php elseif(in_array($r['status'],['approved','picked'],true)): ?>
                <form method="post" style="margin:0" onsubmit="return confirm('Refund to wallet?')">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn" name="act" value="refund_wallet">Refund Wallet</button>
                </form>
              <?php else: ?>
                <span class="muted">-</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../../../views/layout/footer.php'; ?>
