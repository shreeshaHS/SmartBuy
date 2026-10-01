<?php
require_once __DIR__ . '/../../config/bootstrap.php';



// CSRF protection
if (isPost()) { require_csrf(); }
$email = $_SESSION['reset_email'] ?? '';
if ($email === '') redirect('forgot');

$err=null;

if (isPost()) {
    $otp = trim($_POST['otp'] ?? '');
    $pass = $_POST['password'] ?? '';
    $cpass = $_POST['confirm'] ?? '';

    if ($otp==='' || $pass==='' || $cpass==='') $err="All fields required.";
    elseif (strlen($pass) < 6) $err="Password must be at least 6 characters.";
    elseif ($pass !== $cpass) $err="Passwords do not match.";
    else {
        $stmt = db_prepare("SELECT otp_hash, expires_at FROM email_otps WHERE email=? AND purpose='reset_password' LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) $err="OTP request not found. Try again.";
        elseif (strtotime($row['expires_at']) < time()) $err="OTP expired. Try again.";
        elseif (!password_verify($otp, $row['otp_hash'])) $err="Invalid OTP.";
        else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);

            $stmt = db_prepare("UPDATE users SET password_hash=? WHERE email=?");
            $stmt->bind_param("ss", $hash, $email);
            $stmt->execute();
            $stmt->close();

            $stmt = db_prepare("DELETE FROM email_otps WHERE email=? AND purpose='reset_password'");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->close();

            unset($_SESSION['reset_email']);
            redirect('login');
        }
    }
}

$title="Reset Password - ".APP_NAME;
require __DIR__ . '/../../views/layout/header.php';
?>
<div class="auth-page">
  <div class="auth-card" id="authCard">
    <h1 class="auth-title">Reset Password</h1>
    <p class="auth-sub">OTP sent to <b><?= safe($email) ?></b></p>

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
        <div class="ic">🔢</div>
        <input name="otp" maxlength="6" placeholder=" ">
        <label>OTP</label>
      </div>

      <div class="f">
        <div class="ic">🔒</div>
        <input id="reset_pass" type="password" name="password" placeholder=" ">
        <label>New password</label>
        <button class="toggle-pass" type="button" data-pass-toggle="reset_pass">Show</button>
      </div>

      <div class="f">
        <div class="ic">✅</div>
        <input id="reset_cpass" type="password" name="confirm" placeholder=" ">
        <label>Confirm password</label>
        <button class="toggle-pass" type="button" data-pass-toggle="reset_cpass">Show</button>
      </div>

      <div class="auth-actions">
        <button class="btn" type="submit">Change Password →</button>
        <a class="btn ghost" href="<?= BASE_URL ?>login">Back</a>
      </div>

      <div class="small-link">After reset, login with your new password.</div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
