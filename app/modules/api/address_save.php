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

$user = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
if ($user === '') {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'Unauthorized']);
  exit;
}

$name  = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$line1 = trim($_POST['line1'] ?? '');
$city  = trim($_POST['city'] ?? '');
$state = trim($_POST['state'] ?? '');
$pin   = trim($_POST['pin'] ?? '');

if ($name==='' || $phone==='' || $line1==='' || $city==='' || $state==='' || $pin==='') {
  echo json_encode(['ok'=>false,'msg'=>'Fill all fields']);
  exit;
}
if (!preg_match('/^[0-9]{10}$/', $phone)) {
  echo json_encode(['ok'=>false,'msg'=>'Phone must be 10 digits']);
  exit;
}
if (!preg_match('/^[0-9]{6}$/', $pin)) {
  echo json_encode(['ok'=>false,'msg'=>'Pincode must be 6 digits']);
  exit;
}

// Optional: validate pincode exists + serviceable
// If your pincodes table exists with is_serviceable
$st = $conn->prepare("SELECT is_serviceable FROM pincodes WHERE pincode=? LIMIT 1");
if ($st) {
  $st->bind_param("s", $pin);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();
  if (!$row) {
    echo json_encode(['ok'=>false,'msg'=>'Pincode not found']);
    exit;
  }
  if (isset($row['is_serviceable']) && (int)$row['is_serviceable'] !== 1) {
    echo json_encode(['ok'=>false,'msg'=>'Sorry, delivery not available to this pincode']);
    exit;
  }
}

// If first address, make it default
$st = $conn->prepare("SELECT COUNT(*) c FROM user_addresses WHERE user_email=?");
$st->bind_param("s", $user);
$st->execute();
$c = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
$st->close();

$isDefault = ($c === 0) ? 1 : 0;

if ($isDefault === 1) {
  $conn->prepare("UPDATE user_addresses SET is_default=0 WHERE user_email=?")
       ->bind_param("s", $user);
}

$stmt = $conn->prepare("
  INSERT INTO user_addresses(user_email,name,phone,line1,city,state,pincode,is_default)
  VALUES (?,?,?,?,?,?,?,?)
");
$stmt->bind_param("sssssssi", $user, $name, $phone, $line1, $city, $state, $pin, $isDefault);
$stmt->execute();
$stmt->close();

echo json_encode(['ok'=>true]);