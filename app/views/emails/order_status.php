<?php $app = $app ?? 'Smart Buy'; ?>
<div style="font-family:Segoe UI;background:#f4f6fb;padding:30px">
  <div style="max-width:620px;margin:auto;background:#fff;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.08)">
    
    <div style="background:#6366f1;color:#fff;padding:20px;font-weight:800">
      <?= mail_safe($app) ?> — Order Update
    </div>

    <div style="padding:22px">
      <p>Hi <b><?= mail_safe($name) ?></b>,</p>

      <p>Your order <b>#<?= mail_safe($orderNo) ?></b> status has changed:</p>

      <div style="background:#eef2ff;padding:14px;border-radius:10px;font-size:18px;font-weight:800;text-align:center">
        <?= strtoupper(mail_safe($status)) ?>
      </div>

      <p style="font-size:13px;color:#666;margin-top:12px">
        Track your order anytime from your dashboard.
      </p>
    </div>

    <div style="background:#f3f4f6;padding:14px;text-align:center;font-size:12px;color:#888">
      Thank you for choosing <?= mail_safe($app) ?>
    </div>
  </div>
</div>