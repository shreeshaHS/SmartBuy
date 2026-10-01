<?php
require_once __DIR__ . '/../../config/bootstrap.php';
global $conn;

requireLogin();
requireRole('admin');

// CSRF protection
if (isPost()) { require_csrf(); }

$err = null;
$ok  = null;

if (isPost()) {
  $name  = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $pass  = $_POST['password'] ?? '';

  if ($name==='' || $email==='' || $phone==='' || $pass==='') {
    $err = "Fill all fields.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = "Invalid email.";
  } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
    $err = "Phone must be 10 digits.";
  } elseif (strlen($pass) < 6) {
    $err = "Password must be at least 6 characters.";
  } else {
    // check exists
    $st = $conn->prepare("SELECT 1 FROM users WHERE email=? LIMIT 1");
    $st->bind_param("s", $email);
    $st->execute();
    $exists = (bool)$st->get_result()->fetch_row();
    $st->close();

    if ($exists) {
      $err = "Email already exists.";
    } else {
      $hash = password_hash($pass, PASSWORD_DEFAULT);

      // NOTE: Your users table must have columns: email, name, phone, role, status, password_hash
      $ins = $conn->prepare("
        INSERT INTO users (email, name, phone, role, status, password_hash, created_at)
        VALUES (?, ?, ?, 'delivery', 1, ?, NOW())
      ");
      $ins->bind_param("ssss", $email, $name, $phone, $hash);

      try {
        $ins->execute();
        $ok = "Delivery account created ✅";
      } catch (Throwable $e) {
        $err = "DB error: ".$e->getMessage();
      } finally {
        $ins->close();
      }
    }
  }
}

$title = "Create Delivery Account - ".APP_NAME;
require __DIR__ . '/../../views/layout/header.php';
?>

<div class="role-shell">
  <aside class="role-nav">
    <div class="role-badge">👑 Admin</div>
    <a class="role-link" href="<?= BASE_URL ?>?page=admin/dashboard">Dashboard</a>
    <a class="role-link" href="<?= BASE_URL ?>?page=admin/sellers/requests">Seller Requests</a>
    <a class="role-link" href="<?= BASE_URL ?>?page=admin/products/pending">Pending Products</a>
    <a class="role-link" href="<?= BASE_URL ?>?page=admin/delivery/assign">Delivery Assign</a>
    <a class="role-link active" href="<?= BASE_URL ?>?page=admin/delivery-create">Create Delivery</a>
    <a class="role-link" href="<?= BASE_URL ?>?page=admin/delivery-list">Delivery List</a>
  </aside>

  <section class="role-main">
    <div class="page-title">Create Delivery Boy ID</div>
    <div class="muted">This creates a login with role <b>delivery</b>. Delivery boy can login using email + password.</div>

    <?php if($err): ?><div class="alert error"><?= safe($err) ?></div><?php endif; ?>
    <?php if($ok): ?><div class="alert ok"><?= safe($ok) ?></div><?php endif; ?>

    <div class="card" style="max-width:680px">
      <form method="post" class="auth-form">
        <?= csrf_field() ?>

        <div class="grid" style="grid-template-columns:1fr 1fr; gap:12px">
          <div class="f">
            <div class="ic">👤</div>
            <input name="name" placeholder=" " required>
            <label>Name</label>
          </div>

          <div class="f">
            <div class="ic">📞</div>
            <input name="phone" placeholder=" " required>
            <label>Phone (10 digit)</label>
          </div>
        </div>

        <div class="f">
          <div class="ic">✉️</div>
          <input name="email" placeholder=" " required>
          <label>Email (login id)</label>
        </div>

        <div class="f">
          <div class="ic">🔒</div>
          <input type="password" name="password" placeholder=" " required>
          <label>Password</label>
        </div>

        <div class="row" style="gap:10px; margin-top:10px">
          <button class="btn" type="submit">Create</button>
          <a class="btn ghost" href="<?= BASE_URL ?>?page=admin/delivery-list">View Delivery List</a>
        </div>

      </form>
    </div>
  </section>
</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>