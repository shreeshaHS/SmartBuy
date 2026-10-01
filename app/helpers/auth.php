<?php
// app/helpers/auth.php
// Lightweight auth helpers (session-based)
function current_user_email(): ?string {
  return $_SESSION['email'] ?? null;
}
function current_user_role(): ?string {
  return $_SESSION['role'] ?? null;
}
function is_logged_in(): bool {
  return !!current_user_email();
}
function require_login(): void {
  if (!is_logged_in()) {
    header("Location: ".BASE_URL."login");
    exit;
  }
}
function require_role(string $role): void {
  require_login();
  if ((string)current_user_role() !== $role) {
    http_response_code(403);
    require __DIR__ . '/../../public/error/403.php';
    exit;
  }
}
