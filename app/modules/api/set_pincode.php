<?php
require_once __DIR__ . '/../../config/bootstrap.php';
global $conn;

if (isPost()) { require_csrf(); }

$pincode = preg_replace('/\D+/', '', (string)($_POST['pincode'] ?? $_GET['pincode'] ?? ''));
$slug = trim((string)($_GET['slug'] ?? ''));

// validate
if (!preg_match('/^\d{6}$/', $pincode)) {
  $_SESSION['flash'] = "Invalid pincode";
  if ($slug !== '') redirect("product/$slug");
  redirect("products");
}

// check if exists in DB
$st = $conn->prepare("SELECT is_serviceable FROM pincodes WHERE pincode=? LIMIT 1");
$st->bind_param("s", $pincode);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row) {
  $_SESSION['flash'] = "Pincode not found in database.";
  if ($slug !== '') redirect("product/$slug");
  redirect("products");
}

if (isset($row['is_serviceable']) && (int)$row['is_serviceable'] === 0) {
  $_SESSION['flash'] = "Delivery not available to $pincode";
  if ($slug !== '') redirect("product/$slug");
  redirect("products");
}

$_SESSION['ship_pin'] = $pincode;
$_SESSION['flash'] = "Pincode saved ✅ ($pincode)";

if ($slug !== '') redirect("product/$slug");
redirect("products");