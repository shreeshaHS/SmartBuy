<?php
// app/config/csrf.php
// One CSRF system for entire project.

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (!function_exists('csrf_token')) {
  function csrf_token(): string {
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
      $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
  }
}

if (!function_exists('csrf_field')) {
  function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' .
      htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') .
      '">';
  }
}

if (!function_exists('csrf_verify')) {
  function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? '';

    // allow fetch header too
    if ($token === '') {
      $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }

    if (!is_string($token) || $token === '') return false;
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) return false;

    return hash_equals($_SESSION['_csrf_token'], $token);
  }
}

if (!function_exists('require_csrf')) {
  function require_csrf(): void {
    // ✅ Skip CSRF on GET/HEAD/OPTIONS
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
      return;
    }

    if (!csrf_verify()) {
      http_response_code(419);
      exit("<script>alert('Invalid request (CSRF)');history.back();</script>");
    }
  }
}

if (!function_exists('require_csrf_json')) {
  function require_csrf_json(): void {
    // ✅ Skip CSRF on GET/HEAD/OPTIONS
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
      return;
    }

    if (!csrf_verify()) {
      http_response_code(419);
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['ok' => false, 'msg' => 'Invalid request (CSRF)']);
      exit;
    }
  }
}

if (!function_exists('csrf_rotate')) {
  function csrf_rotate(): void {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
  }
}