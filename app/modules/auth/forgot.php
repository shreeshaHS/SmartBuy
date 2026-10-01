<?php
require_once __DIR__ . '/../../config/bootstrap.php';


// CSRF protection
if (isPost()) { require_csrf(); }
require_once __DIR__ . '/../../config/mail.php';

$err=null;

if (isPost()) {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err="Invalid email.";
    else {
        $stmt = db_prepare("SELECT name FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) $err="No email found.";
        else {
            $otp = otp6();
            $otpHash = password_hash($otp, PASSWORD_DEFAULT);
            $exp = addMinutes(10);

            // Ensure UNIQUE for (email,purpose): add unique index if not already
            // If you didn't add it in DB, this still works if duplicate not exists; else you can delete old first.
            $stmt = db_prepare("DELETE FROM email_otps WHERE email=? AND purpose='reset_password'");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->close();

            $stmt = db_prepare("INSERT INTO email_otps(email,purpose,otp_hash,expires_at,attempts)
                                VALUES (?,?,?,?,0)");
            $purpose = 'reset_password';
            $stmt->bind_param("ssss", $email, $purpose, $otpHash, $exp);
            $stmt->execute();
            $stmt->close();

            if (sendOtpMail($email, $user['name'], $otp, "Reset Password")) {
                $_SESSION['reset_email'] = $email;
                redirect('reset');
            } else $err="Failed to send OTP mail.";
        }
    }
}

$title="Forgot Password - ".APP_NAME;
require __DIR__ . '/../../views/layout/header.php';
?>
<div class="auth-page">
  <div class="auth-card" id="authCard">
    <h1 class="auth-title">Forgot Password</h1>
    <p class="auth-sub">We’ll send an OTP to reset your password.</p>

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
        <input name="email" placeholder=" ">
        <label>Registered email</label>
      </div>

      <div class="auth-actions">
        <button class="btn" type="submit">Send Reset OTP →</button>
        <a class="btn ghost" href="<?= BASE_URL ?>login">Back</a>
      </div>

      <div class="small-link">OTP expires in 10 minutes.</div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
