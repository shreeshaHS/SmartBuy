<?php
require_once __DIR__ . '/../../config/bootstrap.php';
global $conn;

if(!isPost()) json_response(['ok'=>false,'error'=>'POST required'],405);
require_csrf_json();
requireLogin();

$product_id=(int)($_POST['product_id'] ?? 0);
$rating=(int)($_POST['rating'] ?? 0);
$title=trim($_POST['title'] ?? '');
$body=trim($_POST['body'] ?? '');

if($product_id<=0 || $rating<1 || $rating>5) json_response(['ok'=>false,'error'=>'Invalid data'],422);

$email = $_SESSION['user'] ?? ($_SESSION['email'] ?? '');

# ensure user purchased delivered
$stmt=$conn->prepare("SELECT 1 FROM orders o JOIN order_items oi ON oi.order_id=o.id WHERE o.user_email=? AND oi.product_id=? AND o.order_status='delivered' LIMIT 1");
$stmt->bind_param("si",$email,$product_id);
$stmt->execute();
$ok=$stmt->get_result()->fetch_row();
if(!$ok) json_response(['ok'=>false,'error'=>'Only delivered buyers can review'],403);

$stmtI=$conn->prepare("INSERT INTO reviews(product_id,user_email,rating,title,body,status,created_at) VALUES (?,?,?,?,?,'pending',NOW())");
$stmtI->bind_param("isiss",$product_id,$email,$rating,$title,$body);
$stmtI->execute();
$stmtI->close();

json_response(['ok'=>true,'message'=>'Review submitted for approval']);
