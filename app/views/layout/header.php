<?php
$role = $_SESSION['role'] ?? 'guest';
$userEmail = $_SESSION['user_email'] ?? ($_SESSION['user'] ?? '');
?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= safe($title ?? APP_NAME) ?></title>

  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <script defer src="<?= BASE_URL ?>assets/js/app.js"></script>

  <!-- ✅ Razorpay checkout script (safe to include even if you use UPI QR) -->
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>

<body data-role="<?= safe($role) ?>">
<div class="page-wrap">

<header class="topbar fk-topbar">
  <a class="brand fk-brand" href="<?= BASE_URL ?>?page=home">
    <span class="logo">🛍️</span>
    <span><?= safe(APP_NAME) ?></span>
  </a>

  <form class="fk-search" action="<?= BASE_URL ?>" method="get">
    <input type="hidden" name="page" value="products">
    <span class="fk-search-ic">🔍</span>
    <input name="q" type="search" autocomplete="off"
           placeholder="Search for products, brands and more"
           value="<?= safe($_GET['q'] ?? '') ?>">
    <button type="submit" class="fk-search-btn">Search</button>
  </form>

  <nav class="fk-actions">

    <?php if(empty($userEmail)): ?>
      <a class="fk-pill" href="<?= BASE_URL ?>?page=login">Login</a>
    <?php else: ?>
      <div class="fk-dd">
        <button type="button" class="fk-pill fk-dd-btn">👤 Account ▾</button>
        <div class="fk-dd-menu">
          <?php if($role==='admin'): ?>
            <a href="<?= BASE_URL ?>?page=admin/dashboard">Admin Dashboard</a>
          <?php elseif($role==='seller'): ?>
            <a href="<?= BASE_URL ?>?page=seller/dashboard">Seller Dashboard</a>
            <a href="<?= BASE_URL ?>?page=seller/inventory">Inventory</a>
            <a href="<?= BASE_URL ?>?page=seller/add-product">Add Product</a>
          <?php elseif($role==='delivery'): ?>
            <a href="<?= BASE_URL ?>?page=delivery/dashboard">Delivery Dashboard</a>
            <a href="<?= BASE_URL ?>?page=delivery/orders">Orders</a>
          <?php else: ?>
            <a href="<?= BASE_URL ?>?page=profile">My Account</a>
            <a href="<?= BASE_URL ?>?page=profile/address">Addresses</a>
            <a href="<?= BASE_URL ?>?page=orders">My Orders</a>
            <a href="<?= BASE_URL ?>?page=wishlist">Wishlist</a>
            <div class="fk-dd-sep"></div>
            <a href="<?= BASE_URL ?>?page=profile/become-seller">Become a Seller</a>
          <?php endif; ?>
          <div class="fk-dd-sep"></div>
          <a href="<?= BASE_URL ?>?page=logout">Logout</a>
        </div>
      </div>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>?page=cart" class="fk-cart" title="Cart">
      🛒
      <span id="cartCount" class="fk-badge"><?= (int)cart_count() ?></span>
    </a>

    <button class="icon-btn" id="themeToggle" title="Toggle theme">🌓</button>
  </nav>
</header>

<main class="container">