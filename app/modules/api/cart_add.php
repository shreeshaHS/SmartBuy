<?php
require_once __DIR__ . '/../../config/bootstrap.php';


// CSRF
if (isPost()) { require_csrf_json(); }
global $conn;

$id = (int)($_POST['id'] ?? 0);
$qty = (int)($_POST['qty'] ?? 1);

if($id <= 0){
  echo json_encode(['ok'=>false]);
  exit;
}

// Check product exists + stock
$stmt = $conn->prepare("SELECT stock FROM products WHERE id=? AND status='live'");
$stmt->bind_param("i",$id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$res){
  echo json_encode(['ok'=>false]);
  exit;
}

if((int)$res['stock'] <= 0){
  echo json_encode(['ok'=>false,'oos'=>true]);
  exit;
}

cart_add($id,$qty);

echo json_encode([
  'ok'=>true,
  'count'=>cart_count()
]);
