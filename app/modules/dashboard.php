<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$role = $_SESSION['role'] ?? 'user';

if ($role === 'admin') {
  header("Location: " . BASE_URL . "admin/catalog/categories");
  exit;
}

if ($role === 'seller') {
  header("Location: " . BASE_URL . "seller/add-product");
  exit;
}

if ($role === 'delivery') {
  header("Location: " . BASE_URL . "delivery/dashboard");
  exit;
}

// user
header("Location: " . BASE_URL . "profile");
exit;
