<?php
require_once __DIR__ . '/../../config/bootstrap.php';



// CSRF protection
if (isPost()) { require_csrf(); }
$email = $_SESSION['pending_email'] ?? '';
if ($email === '') redirect('register');

$err=null;

if (isPost()) {
    $otp = trim($_POST['otp'] ?? '');

    $stmt = db_prepare("SELECT * FROM pending_registrations WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) $err="No pending registration found. Please register again.";
    else {
        if (strtotime($row['otp_expires_at']) < time()) $err="OTP expired. Click resend OTP.";
        else if (!preg_match('/^[0-9]{6}$/', $otp)) $err="Enter a valid 6-digit OTP.";
        else if (!password_verify($otp, $row['otp_hash'])) {
            $stmt = db_prepare("UPDATE pending_registrations SET attempts=attempts+1 WHERE email=?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->close();
            $err="Invalid OTP.";
        } else {
            $role = 'user'; $status = 1;

            $stmt = db_prepare("INSERT INTO users(email,name,phone,password_hash,role,status) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("sssssi", $row['email'], $row['name'], $row['phone'], $row['password_hash'], $role, $status);
            $stmt->execute();
            $stmt->close();

            $stmt = db_prepare("INSERT IGNORE INTO user_profiles(email,is_profile_complete) VALUES (?,0)");
            $stmt->bind_param("s", $row['email']);
            $stmt->execute();
            $stmt->close();

            $stmt = db_prepare("DELETE FROM pending_registrations WHERE email=?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->close();

            $_SESSION['user'] = $email;
            $_SESSION['role'] = 'user';
            unset($_SESSION['pending_email']);

            // show success animation page
            
            redirect(' ');
        }
    }
}

$title="Verify OTP - ".APP_NAME;
require __DIR__ . '/../../views/layout/header.php';
?>
<div class="auth-page">
  <div class="auth-card" id="authCard">
    <h1 class="auth-title">Verify OTP</h1>
    <p class="auth-sub">Enter OTP sent to <b><?= safe($email) ?></b></p>

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
      <input type="hidden" name="otp" value="">

      <div data-otp-wrap class="otp-grid">
        <input class="otp-box" inputmode="numeric" maxlength="1" autocomplete="one-time-code">
        <input class="otp-box" inputmode="numeric" maxlength="1">
        <input class="otp-box" inputmode="numeric" maxlength="1">
        <input class="otp-box" inputmode="numeric" maxlength="1">
        <input class="otp-box" inputmode="numeric" maxlength="1">
        <input class="otp-box" inputmode="numeric" maxlength="1">
      </div>

 <div style="margin-top:14px">
  <button class="btn" style="width:100%" type="submit">Verify →</button>
</div>

<div class="small-link" style="margin-top:12px">
  <a class="link"
     data-resend-btn
     data-seconds="60"
     href="<?= BASE_URL ?>resend-otp">Resend OTP</a>

  <div style="margin-top:6px" data-resend-timer>Resend available in 60s</div>
</div>


      
    </form>
  </div>
</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
