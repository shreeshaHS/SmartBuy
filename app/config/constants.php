<?php
require_once __DIR__ . '/env.php';

define('APP_NAME', env('APP_NAME', 'SmartBuy'));

/**
 * ✅ FIX: Set APP_URL from env first (stable BASE_URL)
 * Put this in env.php:  env('APP_URL') => 'http://localhost/smartbuy2.O/public'
 */


function base_url_detect(): string {
  // ✅ Always prefer APP_URL (prevents /admin/ or /seller/ base bug)
  if (defined('APP_URL') && APP_URL !== '') return APP_URL . '/';

  // fallback (only if APP_URL not set)
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
  $scheme = $https ? 'https://' : 'http://';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

  // script should be /smartbuy2.O/public/index.php
  $script = $_SERVER['SCRIPT_NAME'] ?? '/public/index.php';
  $dir = rtrim(str_replace('\\','/', dirname($script)), '/');
  return $scheme . $host . $dir . '/';
}

function base_path_detect(): string {
  $script = $_SERVER['SCRIPT_NAME'] ?? '/public/index.php';
  return rtrim(str_replace('\\','/', dirname($script)), '/');
}

define('BASE_URL', base_url_detect());
define('BASE_PATH', base_path_detect());

// --------------------
// Delivery Settings
// --------------------
if (!defined('DELIVERY_BASE_FEE')) define('DELIVERY_BASE_FEE', 49);
if (!defined('DELIVERY_FREE_RULE')) define('DELIVERY_FREE_RULE', 'FREE_999'); // FREE_999 | FREE_499 | FIRST_ORDER
if (!defined('DELIVERY_FREE_ABOVE_999')) define('DELIVERY_FREE_ABOVE_999', 999);
if (!defined('DELIVERY_FREE_ABOVE_499')) define('DELIVERY_FREE_ABOVE_499', 499);

// --------------------
// Payments
// --------------------
define('RAZORPAY_KEY_ID', env('RAZORPAY_KEY_ID', '')); // you only have KEY_ID

define('PAYMENT_MODE', 'UPI_QR'); // UPI_QR | RAZORPAY (razorpay needs SECRET)

// UPI QR settings (works without secret)
define('UPI_VPA', env('UPI_VPA', 'smartbuy07@upi'));
define('UPI_PAYEE_NAME', env('UPI_PAYEE_NAME', 'SmartBuy Store'));
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'smarbuy32@gmail.com');