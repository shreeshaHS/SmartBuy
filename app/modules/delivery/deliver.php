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

// Load order
$stmt = $conn->prepare("SELECT id, order_no, user_email, order_status FROM orders WHERE id=? AND delivery_email=? LIMIT 1");
$stmt->bind_param("is", $id, $me);
$stmt->execute();
$o = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$o) redirect('delivery/orders');

// Only allow OTP when out_for_delivery
if (!in_array($o['order_status'], ['out_for_delivery','delivery_otp_sent'], true)) {
  redirect('delivery/view?id='.$id);
}

// Generate OTP
$otp = otp6();
$expires = addMinutes(15); // 15 minutes validity

$stmt = $conn->prepare("UPDATE orders SET delivery_otp=?, delivery_otp_expires=?, order_status='delivery_otp_sent', updated_at=NOW() WHERE id=? AND delivery_email=?");
$stmt->bind_param("ssis", $otp, $expires, $id, $me);
$stmt->execute();
$stmt->close();

// TODO: send OTP via SMS provider. For now, email (if mail.php supports it)
if (function_exists('sendDeliveryOtpMail')) {
  // sendMail($to,$subject,$html)
  sendDeliveryOtpMail($o['user_email'], "Delivery OTP - ".$o['order_no'], "Your delivery OTP is <b>{$otp}</b>. It is valid for 15 minutes.");
}

$_SESSION['flash_delivery'] = "OTP sent to customer (valid 15 minutes).";
redirect('delivery/verify_otp?id='.$id);
