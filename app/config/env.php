<?php
/**
 * Environment loader (no composer required)
 * - Reads .env from project root (smartbuy2.O/.env)
 * - Falls back to defaults for XAMPP
 *
 * NEVER commit real credentials. Use .env.example.
 */

/**
 * Polyfills for PHP < 8 (if needed)
 */
if (!function_exists('str_starts_with')) {
  function str_starts_with(string $haystack, string $needle): bool {
    return $needle === '' || strpos($haystack, $needle) === 0;
  }
}
if (!function_exists('str_ends_with')) {
  function str_ends_with(string $haystack, string $needle): bool {
    if ($needle === '') return true;
    return substr($haystack, -strlen($needle)) === $needle;
  }
}
if (!function_exists('str_contains')) {
  function str_contains(string $haystack, string $needle): bool {
    return $needle === '' || strpos($haystack, $needle) !== false;
  }
}

/**
 * Prevent redeclare fatals when this file is included multiple times.
 */
if (!function_exists('env_path')) {
  function env_path(): string {
    $root = realpath(__DIR__ . '/../../');
    if ($root === false) $root = __DIR__ . '/../../';
    return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
  }
}

if (!function_exists('env_load')) {
  function env_load(): array {
    static $loaded = null;
    if ($loaded !== null) return $loaded;

    $loaded = [];
    $file = env_path();
    if (!is_file($file)) return $loaded;

    $lines = file($file, FILE_IGNORE_NEW_LINES);
    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '' || str_starts_with($line, '#')) continue;

      // allow "export KEY=value"
      if (str_starts_with($line, 'export ')) {
        $line = trim(substr($line, 7));
      }

      // must contain "="
      if (!str_contains($line, '=')) continue;

      // split only on first "=" to allow values with "="
      [$k, $v] = explode('=', $line, 2);
      $k = trim($k);
      $v = trim($v);

      if ($k === '') continue;

      // strip quotes
      if (
        (str_starts_with($v, '"') && str_ends_with($v, '"')) ||
        (str_starts_with($v, "'") && str_ends_with($v, "'"))
      ) {
        $v = substr($v, 1, -1);
      }

      // allow escaping like \n \r \t
      $v = str_replace(['\n', '\r', '\t'], ["\n", "\r", "\t"], $v);

      $loaded[$k] = $v;

      // also expose to getenv / $_ENV for compatibility
      if (getenv($k) === false) {
        putenv("$k=$v");
      }
      if (!isset($_ENV[$k])) {
        $_ENV[$k] = $v;
      }
    }

    return $loaded;
  }
}

if (!function_exists('env')) {
  function env(string $key, $default = null) {
    $all = env_load();
    if (array_key_exists($key, $all)) return $all[$key];

    $sys = getenv($key);
    if ($sys !== false) return $sys;

    return $default;
  }
}

/**
 * Define constants safely (avoid "Constant already defined" errors)
 */
if (!defined('DB_HOST')) define('DB_HOST', env('DB_HOST', '127.0.0.1'));
if (!defined('DB_USER')) define('DB_USER', env('DB_USER', 'root'));
if (!defined('DB_PASS')) define('DB_PASS', env('DB_PASS', ''));
if (!defined('DB_NAME')) define('DB_NAME', env('DB_NAME', 'smartbuy'));

if (!defined('APP_ENV'))   define('APP_ENV', env('APP_ENV', 'local'));        // local | staging | production
if (!defined('APP_DEBUG')) define('APP_DEBUG', env('APP_DEBUG', '1'));        // 1/0
if (!defined('APP_URL'))   define('APP_URL', rtrim((string)env('APP_URL', ''), '/')); // optional override

// Mail (optional but very useful across the project)
if (!defined('MAIL_HOST')) define('MAIL_HOST', env('MAIL_HOST', 'smtp.gmail.com'));
if (!defined('MAIL_PORT')) define('MAIL_PORT', (int) env('MAIL_PORT', '587'));
if (!defined('MAIL_SECURE')) define('MAIL_SECURE', env('MAIL_SECURE', 'tls')); // tls|ssl
if (!defined('MAIL_USER')) define('MAIL_USER', env('MAIL_USER', 'smarbuy32@gmail.com'));
if (!defined('MAIL_PASS')) define('MAIL_PASS', env('MAIL_PASS', 'nkli urhb ymen jlci'));
if (!defined('MAIL_FROM')) define('MAIL_FROM', env('MAIL_FROM', MAIL_USER));
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'Smart Buy'));
