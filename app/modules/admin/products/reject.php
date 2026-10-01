<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
global $conn;

requireLogin();
requireRole('admin');

if (isPost()) { require_csrf(); }

// Accept id from BOTH GET and POST (fix)
$id = (int)($_POST['id'] ?? $_GET['id'] ?? $_GET['pid'] ?? $_GET['product_id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit("Invalid product"); }

$reason = trim($_POST['reason'] ?? $_GET['reason'] ?? '');

// Exists?
$stmt = $conn->prepare("SELECT id FROM products WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) { http_response_code(404); exit("Invalid product"); }

// Detect enum allowed values + optional columns
$statusType = '';
$cols = [];
$r = $conn->query("SHOW COLUMNS FROM products");
while($c = $r->fetch_assoc()){
  $cols[] = $c['Field'];
  if ($c['Field'] === 'status') $statusType = (string)$c['Type'];
}

$allowed = [];
if (stripos($statusType, "enum(") === 0 && preg_match("/^enum\((.*)\)$/i", $statusType, $m)) {
  preg_match_all("/'((?:\\\\'|[^'])*)'/", $m[1], $mm);
  $allowed = array_map(fn($x)=>str_replace("\\'","'",$x), $mm[1] ?? []);
}

$rejectCandidates = ['rejected','blocked','inactive','pending','draft'];
$rejectStatus = 'pending';
foreach ($rejectCandidates as $c) {
  if (empty($allowed) || in_array($c, $allowed, true)) { $rejectStatus = $c; break; }
}

$hasRejectedAt   = in_array('rejected_at', $cols, true);
$hasRejectReason = in_array('reject_reason', $cols, true);

// Update safely
if ($hasRejectedAt && $hasRejectReason) {
  $st = $conn->prepare("UPDATE products SET status=?, rejected_at=NOW(), reject_reason=? WHERE id=?");
  $st->bind_param("ssi", $rejectStatus, $reason, $id);
} elseif ($hasRejectedAt) {
  $st = $conn->prepare("UPDATE products SET status=?, rejected_at=NOW() WHERE id=?");
  $st->bind_param("si", $rejectStatus, $id);
} elseif ($hasRejectReason) {
  $st = $conn->prepare("UPDATE products SET status=?, reject_reason=? WHERE id=?");
  $st->bind_param("ssi", $rejectStatus, $reason, $id);
} else {
  $st = $conn->prepare("UPDATE products SET status=? WHERE id=?");
  $st->bind_param("si", $rejectStatus, $id);
}

$st->execute();
$st->close();
if (function_exists('sendProductRejectedMail')) {
  @sendProductRejectedMail($sellerEmail, $sellerEmail, $productTitle, $reason ?? '');
}
header("Location: " . BASE_URL . "admin/products?tab=pending&rejected=1");
exit;