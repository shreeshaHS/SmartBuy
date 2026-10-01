<?php echo '
<style>
  body{margin:0;background:#0b1020;color:#e5e7eb;font-family:Arial}
  .wrap{max-width:640px;margin:0 auto;padding:24px}
  .card{background:#121a2b;border:1px solid rgba(255,255,255,.10);border-radius:18px;padding:18px}
  .h{font-size:18px;font-weight:800;margin:0 0 8px}
  .muted{color:#a5b4fc;opacity:.9}
  .otp{font-size:34px;font-weight:900;letter-spacing:8px;background:rgba(139,92,246,.15);border:1px solid rgba(139,92,246,.35);display:inline-block;padding:12px 16px;border-radius:14px}
  .btn{display:inline-block;background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:#fff;text-decoration:none;padding:12px 16px;border-radius:14px;font-weight:800}
  .foot{margin-top:14px;font-size:12px;opacity:.8}
  .line{height:1px;background:rgba(255,255,255,.10);margin:14px 0}
</style>
'; ?>
<div class="wrap">
  <div class="card">
    <div class="h"><?php echo mail_safe('Email Verification OTP'); ?></div>
    
<p>Hello <b><?php echo mail_safe($name ?? ''); ?></b>,</p>
<p class="muted"><?php echo mail_safe($purpose ?? 'Verify'); ?> using this OTP (valid <?php echo (int)($minutes ?? 10); ?> minutes):</p>
<div class="otp"><?php echo mail_safe($otp ?? ''); ?></div>
<p class="muted">Do not share this OTP with anyone.</p>

    <div class="line"></div>
    <div class="foot">© <?php echo date('Y'); ?> <?php echo mail_safe($app ?? 'SmartBuy'); ?></div>
  </div>
</div>
