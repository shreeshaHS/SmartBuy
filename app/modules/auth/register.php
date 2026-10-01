<?php
require_once __DIR__ . '/../../config/bootstrap.php';


// CSRF protection
if (isPost()) { require_csrf(); }
require_once __DIR__ . '/../../config/mail.php';

$err = null;

if (isPost()) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $cpass = $_POST['confirm'] ?? '';

    if ($name==='' || $email==='' || $phone==='' || $pass==='' || $cpass==='') $err="All fields are required.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err="Invalid email.";
    elseif (!preg_match('/^[0-9]{10}$/', $phone)) $err="Phone must be 10 digits.";
    elseif (strlen($pass) < 6) $err="Password must be at least 6 characters.";
    elseif ($pass !== $cpass) $err="Passwords do not match.";

    if (!$err) {
        $stmt = db_prepare("SELECT email FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) $err="Email already registered. Please login.";
        $stmt->close();
    }

    if (!$err) {
        $otp = otp6();
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);
        $passHash = password_hash($pass, PASSWORD_DEFAULT);
        $exp = addMinutes(10);

        $stmt = db_prepare("INSERT INTO pending_registrations (email,name,phone,password_hash,otp_hash,otp_expires_at,attempts)
                            VALUES (?,?,?,?,?,?,0)
                            ON DUPLICATE KEY UPDATE
                              name=VALUES(name),
                              phone=VALUES(phone),
                              password_hash=VALUES(password_hash),
                              otp_hash=VALUES(otp_hash),
                              otp_expires_at=VALUES(otp_expires_at),
                              attempts=0");
        $stmt->bind_param("ssssss", $email, $name, $phone, $passHash, $otpHash, $exp);
        $stmt->execute();
        $stmt->close();

        $sent = sendOtpMail($email, $name, $otp, "Verify Email");
        if (!$sent) $err = "OTP email failed. Check SMTP/app password.";
        else {
            $_SESSION['pending_email'] = $email;
            redirect('verify');
        }
    }
}

$title="Register - ".APP_NAME;
require __DIR__ . '/../../views/layout/header.php';
?>
<div class="auth-page">
  <div class="auth-card" id="authCard">
    <h1 class="auth-title">Create Account</h1>
    <p class="auth-sub">We’ll send a 6-digit OTP to verify your email.</p>

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
        <div class="ic">👤</div>
        <input name="name" placeholder=" " value="<?= safe($_POST['name'] ?? '') ?>">
        <label>Full name</label>
      </div>

      <div class="f">
        <div class="ic">✉️</div>
        <input name="email" placeholder=" " value="<?= safe($_POST['email'] ?? '') ?>">
        <label>Email address</label>
      </div>

      <div class="f">
        <div class="ic">📱</div>
        <input name="phone" maxlength="10" placeholder=" " value="<?= safe($_POST['phone'] ?? '') ?>">
        <label>Mobile (10 digits)</label>
      </div>

      <div class="f">
        <div class="ic">🔒</div>
        <input id="reg_pass" type="password" name="password" placeholder=" ">
        <label>Password</label>
        <button class="toggle-pass" type="button" data-pass-toggle="reg_pass">Show</button>
      </div>

      <div class="f">
        <div class="ic">✅</div>
        <input id="reg_cpass" type="password" name="confirm" placeholder=" ">
        <label>Confirm password</label>
        <button class="toggle-pass" type="button" data-pass-toggle="reg_cpass">Show</button>
      </div>

      <div class="auth-actions">
        <button class="btn" type="submit">Send OTP →</button>
        <a class="btn ghost" href="<?= BASE_URL ?>login">Login</a>
      </div>

      <div class="small-link">
        Your account will be created only after OTP verification.
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
