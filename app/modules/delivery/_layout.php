<?php
// app/modules/delivery/_layout.php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
requireRole('delivery');
global $conn;

$me = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
if ($me === '') redirect('login');

$active = trim($_GET['page'] ?? '');
if ($active === '') {
  // path routing: /public/delivery/orders etc
  $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
  $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
  $active = trim(preg_replace('#^' . preg_quote($base, '#') . '#', '', $path), '/');
}
function dl_active($route, $active){
  return ($active === $route) ? 'active' : '';
}

// Small counters for sidebar
$cntAssigned = 0; $cntOFD = 0; $cntPickup = 0;
try{
  $stmt = $conn->prepare("SELECT
      SUM(CASE WHEN delivery_email=? AND order_status IN('packed','shipped') THEN 1 ELSE 0 END) AS pickup,
      SUM(CASE WHEN delivery_email=? AND order_status='out_for_delivery' THEN 1 ELSE 0 END) AS ofd,
      SUM(CASE WHEN delivery_email=? AND order_status NOT IN('delivered','cancelled','failed') THEN 1 ELSE 0 END) AS assigned
    FROM orders
  ");
  $stmt->bind_param("sss", $me, $me, $me);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc() ?: [];
  $stmt->close();
  $cntPickup = (int)($r['pickup'] ?? 0);
  $cntOFD = (int)($r['ofd'] ?? 0);
  $cntAssigned = (int)($r['assigned'] ?? 0);
}catch(Throwable $e){ /* ignore */ }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= safe(APP_NAME) ?> • Delivery</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>

<div class="dash">
  <aside class="side">
    <div class="brand">
      <div class="logo">🚚</div>
      <div>
        <div class="btitle">Delivery Panel</div>
        <div class="muted" style="font-size:12px"><?= safe($me) ?></div>
      </div>
    </div>

    <nav class="menu">
      <a class="mitem <?= dl_active('delivery/dashboard',$active) ?>" href="<?= BASE_URL ?>delivery/dashboard">
        <span>🏠</span> Dashboard
      </a>
      <a class="mitem <?= dl_active('delivery/orders',$active) ?>" href="<?= BASE_URL ?>delivery/orders">
        <span>📦</span> Orders
        <span class="pill"><?= (int)$cntAssigned ?></span>
      </a>
      <a class="mitem <?= dl_active('delivery/orders',$active) ?>" href="<?= BASE_URL ?>delivery/orders?status=pickup">
        <span>✅</span> Pickup
        <span class="pill"><?= (int)$cntPickup ?></span>
      </a>
      <a class="mitem <?= dl_active('delivery/orders',$active) ?>" href="<?= BASE_URL ?>delivery/orders?status=out_for_delivery">
        <span>🛵</span> Out for delivery
        <span class="pill"><?= (int)$cntOFD ?></span>
      </a>
      <a class="mitem <?= dl_active('delivery/return_pickups',$active) ?>" href="<?= BASE_URL ?>delivery/return_pickups">
        <span>↩️</span> Return pickups
      </a>

      <div class="divider"></div>

      <a class="mitem" href="<?= BASE_URL ?>logout"><span>🚪</span> Logout</a>
    </nav>
  </aside>

  <main class="main">
    <div class="topbar">
      <div class="tleft">
        <div class="page-title" style="margin:0"><?= safe(APP_NAME) ?> Delivery</div>
        <div class="muted" style="font-size:12px">Track pickups, delivery OTP, and returns</div>
      </div>
      <div class="tright">
        <span class="badge">CSRF: Enabled</span>
      </div>
    </div>

    <div class="content">
