<?php /** @var string $name @var string $businessName @var string $app */ ?>
<div style="font-family:Arial,sans-serif;max-width:620px;margin:auto;padding:18px">
  <div style="font-size:22px;font-weight:800"><?= mail_safe($app ?? 'Smart Buy') ?></div>
  <p>Hi <?= mail_safe($name ?? 'User') ?>,</p>
  <p>✅ Your seller request for <b><?= mail_safe($businessName ?? '') ?></b> has been approved.</p>
  <p style="color:#666;font-size:13px">You can now login and manage products.</p>
</div>