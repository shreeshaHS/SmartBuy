<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
global $conn;

requireLogin();
requireRole('admin');

if (isPost()) { require_csrf(); }

// Accept id from BOTH GET and POST (fix)
$id = (int)($_POST['id'] ?? $_GET['id'] ?? $_GET['pid'] ?? $_GET['product_id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit("Invalid product"); }

// Exists?
$stmt = $conn->prepare("SELECT id FROM products WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) { http_response_code(404); exit("Invalid product"); }

// Detect enum allowed values
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

$approveCandidates = ['live','approved','active'];
$approveStatus = 'live';
foreach ($approveCandidates as $c) {
  if (empty($allowed) || in_array($c, $allowed, true)) { $approveStatus = $c; break; }
}

$hasApprovedAt = in_array('approved_at', $cols, true);

if ($hasApprovedAt) {
  $st = $conn->prepare("UPDATE products SET status=?, approved_at=NOW() WHERE id=?");
  $st->bind_param("si", $approveStatus, $id);
} else {
  $st = $conn->prepare("UPDATE products SET status=? WHERE id=?");
  $st->bind_param("si", $approveStatus, $id);
}

$st->execute();
$st->close();
if (function_exists('sendProductApprovedMail')) {
  @sendProductApprovedMail($sellerEmail, $sellerEmail, $productTitle);
}
header("Location: " . BASE_URL . "admin/products?tab=pending&approved=1");
exit;