<?php /** @var string $name @var string $businessName @var string $reason @var string $app */ ?>
<div style="font-family:Arial,sans-serif;max-width:620px;margin:auto;padding:18px">
  <div style="font-size:22px;font-weight:800"><?= mail_safe($app ?? 'Smart Buy') ?></div>
  <p>Hi <?= mail_safe($name ?? 'User') ?>,</p>
  <p>❌ Your seller request for <b><?= mail_safe($businessName ?? '') ?></b> was rejected.</p>
  <?php if(!empty($reason)): ?>
    <p><b>Reason:</b> <?= mail_safe($reason) ?></p>
  <?php endif; ?>
</div>