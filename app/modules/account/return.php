<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
global $conn;

$title="Return Request - ".APP_NAME;
$email = $_SESSION['user'] ?? ($_SESSION['email'] ?? '');
$orderId = (int)($_GET['order'] ?? 0);
if($orderId<=0){ header("Location: ".BASE_URL."?page=account/orders"); exit; }

$stmt=$conn->prepare("SELECT * FROM orders WHERE id=? AND user_email=? LIMIT 1");
$stmt->bind_param("is",$orderId,$email);
$stmt->execute();
$order=$stmt->get_result()->fetch_assoc();
if(!$order){ http_response_code(404); echo "Order not found"; exit; }

$msg='';
if(isPost()){
  require_csrf();
  $reason=trim($_POST['reason'] ?? '');
  if($reason===''){ $msg='Please enter reason.'; }
  else{
    $stmtR=$conn->prepare("INSERT INTO return_requests(order_id,user_email,reason,status,created_at,updated_at) VALUES (?,?,?,'pending',NOW(),NOW())");
    $stmtR->bind_param("iss",$orderId,$email,$reason);
    $stmtR->execute();
    $stmtR->close();
    $msg='Return request submitted. Admin will review.';
  }
}

include __DIR__ . '/../../views/layout/header.php';
?>
<div class="container">
  <div class="page-title">Return Request</div>
  <?php if($msg): ?><div class="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <div class="card">
    <div><b>Order:</b> <?= htmlspecialchars($order['order_no']) ?></div>
    <div class="muted">Delivered orders can be returned as per policy.</div>
    <form method="post" style="margin-top:12px">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <label>Reason</label>
      <textarea name="reason" rows="4" required></textarea>
      <button class="btn" type="submit">Submit Return</button>
      <a class="btn" href="<?= htmlspecialchars(BASE_URL) ?>?page=account/orders">Back</a>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
