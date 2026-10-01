<?php
$app = $app ?? 'Smart Buy';
?>
<div style="font-family:Segoe UI,Arial;background:#f4f6fb;padding:30px">
  <div style="max-width:650px;margin:auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.08)">

    <div style="background:#10b981;color:#fff;padding:22px">
      <div style="font-size:22px;font-weight:800"><?= mail_safe($app) ?></div>
      <div style="font-size:13px;opacity:.9">Order Delivered Successfully</div>
    </div>

    <div style="padding:24px">

      <h2 style="margin:0 0 10px">🎉 Delivered!</h2>

      <p style="font-size:15px">Hi <b><?= mail_safe($name) ?></b>,</p>

      <p style="color:#555;font-size:14px;line-height:1.6">
        Your order has been successfully delivered. We hope you love your purchase!
      </p>

      <div style="background:#f7f8fd;border-radius:12px;padding:16px;margin:16px 0">
        <div style="font-size:13px;color:#666">Order Number</div>
        <div style="font-weight:800;font-size:18px">#<?= mail_safe($orderNo) ?></div>
      </div>

      <p style="font-size:13px;color:#666">
        If you enjoyed shopping with us, consider leaving a review ⭐
      </p>

    </div>

    <div style="background:#f3f4f6;padding:14px;text-align:center;font-size:12px;color:#888">
      Thank you for shopping with <?= mail_safe($app) ?>
    </div>
  </div>
</div>