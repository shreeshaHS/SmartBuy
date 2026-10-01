<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
requireRole('delivery');
global $conn;

if (!isPost()) { http_response_code(405); exit('Method not allowed'); }
require_csrf();

$me = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
$id = (int)($_GET['id'] ?? 0);
if ($id<=0) redirect('delivery/orders');

$stmt = $conn->prepare("UPDATE orders SET order_status='shipped', updated_at=NOW() WHERE id=? AND delivery_email=? AND order_status IN('packed','shipped')");
$stmt->bind_param("is", $id, $me);
$stmt->execute();
$stmt->close();

redirect('delivery/view?id='.$id);
