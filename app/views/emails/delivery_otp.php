<?php
/** @var string $name @var string $orderNo @var string $otp @var int $minutes @var string $app */
$app = $app ?? 'Smart Buy';
?>
<div style="font-family:Segoe UI,Arial,sans-serif;background:#f4f6fb;padding:30px">
  <div style="max-width:650px;margin:auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.08)">

    <div style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;padding:22px 24px">
      <div style="font-size:22px;font-weight:800"><?= mail_safe($app) ?></div>
      <div style="opacity:.9;font-size:13px">Secure Delivery Verification</div>
    </div>

    <div style="padding:24px">

      <p style="font-size:15px;margin:0 0 10px">Hi <b><?= mail_safe($name ?? 'Customer') ?></b> 👋</p>

      <p style="font-size:14px;color:#555;line-height:1.6">
        Your order is out for delivery. Please share the OTP with the delivery partner
        only after receiving the package.
      </p>

      <div style="background:#f7f8fd;border:1px dashed #d7daf5;border-radius:12px;padding:18px;text-align:center;margin:18px 0">
        <div style="font-size:13px;color:#666;margin-bottom:6px">Delivery OTP for Order</div>
        <div style="font-weight:800;font-size:16px">#<?= mail_safe($orderNo) ?></div>

        <div style="font-size:34px;font-weight:900;letter-spacing:6px;margin-top:10px;color:#4f46e5">
          <?= mail_safe($otp) ?>
        </div>

        <div style="font-size:12px;color:#777;margin-top:6px">
          Valid for <?= (int)($minutes ?? 10) ?> minutes
        </div>
      </div>

      <div style="background:#fff7ed;border:1px solid #fed7aa;padding:14px;border-radius:10px;font-size:13px;color:#92400e">
        🔒 <b>Security Tip:</b> Never share OTP before receiving your parcel.
      </div>

      <p style="font-size:13px;color:#666;margin-top:16px">
        Need help? Contact support anytime from your account dashboard.
      </p>

    </div>

    <div style="background:#f3f4f6;padding:16px;font-size:12px;color:#888;text-align:center">
      © <?= date('Y') ?> <?= mail_safe($app) ?> — Safe & Secure Shopping
    </div>
  </div>
</div>