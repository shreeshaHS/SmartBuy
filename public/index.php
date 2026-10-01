<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../app/config/bootstrap.php';
require_once __DIR__ . '/../routes/web.php';

/**
 * Route resolution priority:
 * 1) ?page=... (your existing system)
 * 2) Clean path: /public/<route>
 */

$route = 'home';

// 1) Query routing (?page=order-success)
if (!empty($_GET['page'])) {
  $route = trim((string)$_GET['page'], "/ \t\n\r\0\x0B");
} else {
  // 2) Clean URL routing (/public/order-success)
  $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

  // Script name like: /smartbuy2.O/public/index.php
  $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

  // Base dir: /smartbuy2.O/public
  $baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

  // Normalize
  $uriPath = rtrim(str_replace('\\', '/', $uriPath), '/');

  // Remove baseDir from URI (if present)
  if ($baseDir !== '' && $baseDir !== '/' && str_starts_with($uriPath, $baseDir)) {
    $uriPath = substr($uriPath, strlen($baseDir));
  }

  // Remove leading slash
  $uriPath = ltrim($uriPath, '/');

  if ($uriPath !== '') $route = $uriPath;
}

if ($route === '' || $route === '/') $route = 'home';

dispatch($route);