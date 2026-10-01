<?php
require_once __DIR__ . '/../../config/bootstrap.php';

$err = null;
 require_csrf();
if (isPost()) {
   require_csrf();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($email === '' || $pass === '') {
        $err = "Enter email and password.";
    } else {

        $stmt = db_prepare("SELECT email, password_hash, role, status FROM users WHERE email=? LIMIT 1");

        // ✅ THIS LINE WAS MISSING
        $stmt->bind_param("s", $email);

        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) $err="No account found.";
        elseif ((int)$row['status'] !== 1) $err="Account disabled.";
        elseif (!password_verify($pass, $row['password_hash'])) $err="Wrong password.";
        else {
            $_SESSION['user'] = $row['email'];
            $_SESSION['role'] = $row['role'];

            if ($row['role'] === 'admin') redirect('admin/dashboard');
            if ($row['role'] === 'seller') redirect('seller/dashboard');
            if ($row['role'] === 'delivery') redirect('delivery/dashboard');
            redirect('profile');
        }
    }
}


$title = "Login - " . APP_NAME;
require __DIR__ . '/../../views/layout/header.php';
?>

<div class="auth-page">
  <div class="auth-card" id="authCard">
    <h1 class="auth-title">Login</h1>
    <p class="auth-sub">Welcome back.</p>

    <?php if($err): ?>
      <div class="alert error"><?= safe($err) ?></div>
      <script>
        document.addEventListener("DOMContentLoaded", ()=>{
          const c=document.getElementById("authCard");
          if(c){ c.classList.remove("shake"); void c.offsetWidth; c.classList.add("shake"); }
        });
      </script>
    <?php endif; ?>

    <form class="auth-form" method="post">
      <?= csrf_field() ?>

      <div class="f">
        <div class="ic">✉️</div>
        <input type="email" name="email" value="<?= safe($_POST['email'] ?? '') ?>" required placeholder=" ">
        <label>Email</label>
      </div>

      <div class="f">
        <div class="ic">🔒</div>
        <input id="login_pass" type="password" name="password" required placeholder=" ">
        <label>Password</label>
        <button class="toggle-pass" type="button" data-pass-toggle="login_pass">Show</button>
      </div>

      <div class="auth-actions">
        <button class="btn" type="submit">Login →</button>
        <a class="btn ghost" href="<?= BASE_URL ?>register">Register</a>
      </div>

      <div class="small-link">
        <a href="<?= BASE_URL ?>forgot">Forgot password?</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>