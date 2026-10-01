<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
global $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isPost()) {
  http_response_code(405);
  echo json_encode(['ok'=>false,'msg'=>'Method not allowed']);
  exit;
}
require_csrf_json();

$email = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
if ($email === '') {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'Unauthorized']);
  exit;
}

$product_id = (int)($_POST['product_id'] ?? 0);
if ($product_id <= 0) {
  echo json_encode(['ok'=>false,'msg'=>'Invalid product']);
  exit;
}

/**
 * Table expected: wishlist(id,user_email,product_id,created_at)
 * Unique: (user_email, product_id)
 */
try {
  // already?
  $st = $conn->prepare("SELECT id FROM wishlist WHERE user_email=? AND product_id=? LIMIT 1");
  $st->bind_param("si", $email, $product_id);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();

  if ($row) {
    $del = $conn->prepare("DELETE FROM wishlist WHERE id=?");
    $id = (int)$row['id'];
    $del->bind_param("i", $id);
    $del->execute();
    $del->close();

    echo json_encode(['ok'=>true,'liked'=>false]);
    exit;
  }

  $ins = $conn->prepare("INSERT INTO wishlist(user_email,product_id,created_at) VALUES (?,?,NOW())");
  $ins->bind_param("si", $email, $product_id);
  $ins->execute();
  $ins->close();

  echo json_encode(['ok'=>true,'liked'=>true]);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=> (defined('APP_DEBUG') && APP_DEBUG==='1') ? $e->getMessage() : 'Server error']);
}