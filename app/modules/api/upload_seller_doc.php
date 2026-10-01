<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();

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

$field = (string)($_POST['field'] ?? '');
$allowedFields = ['doc_pan','doc_aadhar','doc_gstin','doc_bank','doc_shop_license','store_logo'];
if (!in_array($field, $allowedFields, true)) {
  echo json_encode(['ok'=>false,'msg'=>'Invalid field']);
  exit;
}

if (empty($_FILES['file']) || !isset($_FILES['file']['tmp_name'])) {
  echo json_encode(['ok'=>false,'msg'=>'No file']);
  exit;
}

$f = $_FILES['file'];

if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
  echo json_encode(['ok'=>false,'msg'=>'Upload error']);
  exit;
}

$max = 3 * 1024 * 1024; // 3MB
if ((int)($f['size'] ?? 0) > $max) {
  echo json_encode(['ok'=>false,'msg'=>'File too large (max 3MB)']);
  exit;
}

// Validate type
$tmp = $f['tmp_name'];
$mime = @mime_content_type($tmp) ?: '';
$allowedMime = [
  'image/jpeg' => 'jpg',
  'image/png'  => 'png',
  'image/webp' => 'webp',
  'application/pdf' => 'pdf',
];

if (!isset($allowedMime[$mime])) {
  echo json_encode(['ok'=>false,'msg'=>'Only JPG/PNG/WebP/PDF allowed']);
  exit;
}

$ext = $allowedMime[$mime];

// Public folder real path
$publicDir = realpath(__DIR__ . '/../../../public');
if ($publicDir === false) {
  echo json_encode(['ok'=>false,'msg'=>'Public folder not found']);
  exit;
}

// Safe folder by email
$safeEmail = strtolower(trim($email));
$safeEmail = preg_replace('/[^a-z0-9@\.\-\_]+/i', '_', $safeEmail);

$dir = $publicDir . '/uploads/seller/' . $safeEmail . '/';
if (!is_dir($dir)) {
  @mkdir($dir, 0777, true);
}

$prefix = strtoupper(str_replace(['doc_','store_'],'',$field));
$name = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

$dest = $dir . $name;

if (!move_uploaded_file($tmp, $dest)) {
  echo json_encode(['ok'=>false,'msg'=>'Failed to save file']);
  exit;
}

$webPath = 'uploads/seller/' . $safeEmail . '/' . $name;
echo json_encode(['ok'=>true,'path'=>$webPath]);