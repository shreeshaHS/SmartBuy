<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
requireLogin();
requireRole('admin');
global $conn;

if (!isPost()) { http_response_code(405); exit('Method not allowed'); }
require_csrf();

$id = (int)($_POST['id'] ?? 0);
$note = trim((string)($_POST['admin_note'] ?? ''));

if ($id <= 0) {
  $_SESSION['flash_admin'] = "Invalid request.";
  redirect('admin/sellers/requests?status=pending');
}

/**
 * Load request row (need email + names for mail)
 */
$stmt = $conn->prepare("
  SELECT id, user_email, owner_name, store_name, status
  FROM seller_requests
  WHERE id=?
  LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$r) {
  $_SESSION['flash_admin'] = "Request not found.";
  redirect('admin/sellers/requests?status=pending');
}

$status = strtolower((string)($r['status'] ?? ''));

if ($status !== 'pending') {
  $_SESSION['flash_admin'] = "This request is already {$status}.";
  redirect('admin/sellers/requests?status='.$status);
}

/**
 * Update status
 */
$stmt = $conn->prepare("
  UPDATE seller_requests
  SET status='rejected',
      admin_note=?,
      updated_at=NOW()
  WHERE id=? AND status='pending'
");
$stmt->bind_param("si", $note, $id);
$stmt->execute();
$stmt->close();

/**
 * Send email (SAFE: never pass null)
 */
$email = trim((string)($r['user_email'] ?? ''));
$name  = trim((string)($r['owner_name'] ?? ''));
$store = trim((string)($r['store_name'] ?? ''));

if ($name === '')  $name = 'Seller';
if ($store === '') $store = 'Your store';

if ($email !== '' && function_exists('sendSellerRejectedMail')) {
  // sendSellerRejectedMail(email, name, businessName, reason)
  @sendSellerRejectedMail($email, $name, $store, $note);
}

$_SESSION['flash_admin'] = "Rejected ✅";
redirect('admin/sellers/requests?status=pending');