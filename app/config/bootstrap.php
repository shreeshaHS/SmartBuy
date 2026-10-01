<?php
require __DIR__ . '/constants.php';
require __DIR__ . '/db.php';
require __DIR__ . '/mail.php';
require_once __DIR__ . '/../helpers/invoice.php';
require_once __DIR__ . '/../helpers/cart.php';
require_once __DIR__ . '/../helpers/delivery.php';

date_default_timezone_set('Asia/Kolkata');
global $conn;

// Start session BEFORE csrf.php
if (session_status() !== PHP_SESSION_ACTIVE) {
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);

  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);

  session_start();
}

// Load CSRF once
require_once __DIR__ . '/csrf.php';

// Basic security headers (only if headers not sent)
if (!headers_sent()) {
  header('X-Frame-Options: SAMEORIGIN');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: strict-origin-when-cross-origin');
}

// -----------------------
// Logging
// -----------------------
if (!function_exists('app_log')) {
  function app_log(string $level, string $message, array $context=[]): void {
    $root = realpath(__DIR__ . '/../../');
    if ($root === false) $root = __DIR__ . '/../../';
    $dir = rtrim($root, DIRECTORY_SEPARATOR) . '/storage/logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $file = $dir . '/app.log';
    $row = [
      'ts' => date('c'),
      'level' => strtoupper($level),
      'msg' => $message,
      'ctx' => $context,
      'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
      'route' => $_SERVER['REQUEST_URI'] ?? null,
    ];
    @file_put_contents($file, json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
  }
}

set_error_handler(function($severity,$message,$file,$line){
  if (!(error_reporting() & $severity)) return false;
  app_log('error', 'PHP error: '.$message, ['file'=>$file,'line'=>$line,'sev'=>$severity]);
  if (defined('APP_DEBUG') && APP_DEBUG === '1') return false;
  http_response_code(500);
  echo "Something went wrong.";
  return true;
});

set_exception_handler(function($e){
  app_log('error', 'Unhandled exception', ['err'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]);
  http_response_code(500);
  if (defined('APP_DEBUG') && APP_DEBUG === '1') {
    echo "<pre>".htmlspecialchars((string)$e)."</pre>";
  } else {
    echo "Server error.";
  }
});

// -----------------------
// Helpers
// -----------------------
if (!function_exists('safe')) {
  function safe($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('redirect')) {
  function redirect($path){
    header("Location: " . BASE_URL . ltrim($path,'/'));
    exit;
  }
}

if (!function_exists('view')) {
  function view($file, $data=[]){
    extract($data);
    require __DIR__ . '/../views/' . $file . '.php';
  }
}

if (!function_exists('isPost')) {
  function isPost(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
}
if (!function_exists('now')) {
  function now(): string { return date('Y-m-d H:i:s'); }
}
if (!function_exists('addMinutes')) {
  function addMinutes(int $m): string { return date('Y-m-d H:i:s', time() + ($m*60)); }
}
if (!function_exists('otp6')) {
  function otp6(): string { return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT); }
}

// -----------------------
// Auth Helpers
// -----------------------
if (!function_exists('requireLogin')) {
  function requireLogin(): void {
    if (empty($_SESSION['user']) && empty($_SESSION['user_email'])) {
      redirect('login');
    }
  }
}

if (!function_exists('requireRole')) {
  function requireRole(string $role): void {
    if (empty($_SESSION['role']) || $_SESSION['role'] !== $role) {
      http_response_code(403);
      echo "403 Forbidden";
      exit;
    }
  }
}
if (!function_exists('profileComplete')) {
  function profileComplete(string $email): bool {
    global $conn;

    // If table doesn't exist, don't block checkout
    $t = $conn->query("SHOW TABLES LIKE 'user_profiles'");
    if (!$t || $t->num_rows === 0) return true;

    $stmt = $conn->prepare("SELECT is_profile_complete FROM user_profiles WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // If row missing, don't block checkout
    if (!$row) return true;

    return ((int)$row['is_profile_complete'] === 1);
  }
}
if (!function_exists('requireProfileForCheckout')) {
  function requireProfileForCheckout(): void {
    requireLogin();
    global $conn;

    $email = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
    if ($email === '') {
      redirect('login');
    }

    // ✅ IMPORTANT: Only enforce address, not profileComplete (profile table may be missing)
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM user_addresses WHERE user_email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();

    if ($c <= 0) {
      $_SESSION['flash_checkout'] = "Add a delivery address to place order.";
      redirect('profile/address');
    }
  }
}
// -----------------------
// Product Image Helper
// -----------------------
if (!function_exists('product_primary_image_url')) {
  function product_primary_image_url(mysqli $conn, int $productId): string {
    $stmt = $conn->prepare("
      SELECT image_path
      FROM product_images
      WHERE product_id=? AND is_primary=1
      ORDER BY sort_order ASC, id ASC
      LIMIT 1
    ");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!empty($row['image_path'])) return rtrim(BASE_URL,'/') . '/' . ltrim($row['image_path'],'/');
    return rtrim(BASE_URL,'/') . "/assets/img/no-image.png";
  }
}

// -----------------------
// Delivery helpers
// -----------------------
if (!function_exists('get_delivery_days')) {
  function get_delivery_days(mysqli $conn, string $pincode): int {
    $pincode = preg_replace('/\D+/', '', $pincode);
    if (strlen($pincode) !== 6) return 5;

    $stmt = $conn->prepare("SELECT delivery_days, is_serviceable FROM pincodes WHERE pincode=? LIMIT 1");
    $stmt->bind_param("s", $pincode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return 5;
    if (isset($row['is_serviceable']) && (int)$row['is_serviceable'] === 0) return 0;

    $days = (int)($row['delivery_days'] ?? 5);
    if ($days <= 0) $days = 5;
    return $days;
  }
}

if (!function_exists('calc_delivery_eta')) {
  function calc_delivery_eta(mysqli $conn, string $pincode): ?string {
    $days = get_delivery_days($conn, $pincode);
    if ($days <= 0) return null;
    return date('Y-m-d', strtotime("+$days days"));
  }
}

if (!function_exists('eta_human')) {
  function eta_human(?string $eta): string {
    if (!$eta) return "Not serviceable";
    return date('D, d M', strtotime($eta));
  }
}

if (!function_exists('delivery_badge_for_product')) {
  function delivery_badge_for_product(mysqli $conn, float $netCartTotal, string $userEmail): array {
    $pin = $_SESSION['ship_pin'] ?? '';

    if ($pin === '' && $userEmail !== '') {
      $st = $conn->prepare("SELECT pincode FROM user_addresses WHERE user_email=? ORDER BY is_default DESC, id DESC LIMIT 1");
      $st->bind_param("s", $userEmail);
      $st->execute();
      $r = $st->get_result()->fetch_assoc();
      $st->close();
      $pin = (string)($r['pincode'] ?? '');
    }

    if (!preg_match('/^\d{6}$/', $pin)) {
      return ['ok'=>false, 'msg'=>'Enter pincode to check delivery'];
    }

    $q = delivery_quote($conn, $pin, $netCartTotal, $userEmail);
    if ($q === null) return ['ok'=>false, 'msg'=>'Not deliverable'];

    $days = (int)$q['days'];
    $eta  = (string)$q['eta'];

    $min = max(1, $days - 1);
    $max = $days + 1;

    return [
      'ok'=>true,
      'pin'=>$pin,
      'days'=>$days,
      'eta'=>$eta,
      'label'=>"Arrives in {$min}–{$max} days (by ".date('D, d M', strtotime($eta)).")",
      'fee'=>(float)$q['fee'],
      'fee_msg'=>(string)$q['msg'],
    ];
  }
}