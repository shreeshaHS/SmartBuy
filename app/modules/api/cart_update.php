<?php
require_once __DIR__ . '/../../config/bootstrap.php';



// CSRF
if (isPost()) { require_csrf_json(); }
$id = (int)($_POST['id'] ?? 0);
$qty = (int)($_POST['qty'] ?? 1);

if($id <= 0){
  echo json_encode(['ok'=>false]); exit;
}

if($qty <= 0){
  cart_remove($id);
}else{
  cart_update($id,$qty);
}

echo json_encode(['ok'=>true,'count'=>cart_count()]);
