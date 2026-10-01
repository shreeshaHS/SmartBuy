<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
requireLogin();
requireRole('admin');
global $conn;
$id = (int)($_POST['id'] ?? 0);
if ($id<=0) { http_response_code(400); exit('Bad request'); }

$conn->begin_transaction();

try {
  // lock request row
  $stmt = $conn->prepare("SELECT user_email,status FROM seller_requests WHERE id=? FOR UPDATE");
  $stmt->bind_param("i",$id);
  $stmt->execute();
  $req = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$req) throw new Exception("Request not found");
  if ($req['status'] !== 'pending') throw new Exception("Already processed");

  $email = $req['user_email'];

  // update request
  $stmt = $conn->prepare("UPDATE seller_requests SET status='approved', admin_note=NULL WHERE id=?");
  $stmt->bind_param("i",$id);
  $stmt->execute();
  $stmt->close();

  // promote user to seller
  $stmt = $conn->prepare("UPDATE users SET role='seller' WHERE email=?");
  $stmt->bind_param("s",$email);
  $stmt->execute();
  $stmt->close();

  $conn->commit();
if (function_exists('sendSellerApprovedMail')) {
  @sendSellerApprovedMail($sellerEmail, $sellerEmail, $storeName);
}
  header("Location: ".BASE_URL."admin/sellers/view?id=".$id);
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo "Error: ".htmlspecialchars($e->getMessage());
}
