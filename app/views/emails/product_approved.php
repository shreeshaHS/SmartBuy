<?php /** @var string $name @var string $productTitle @var string $app */ ?>
<div style="font-family:Arial,sans-serif;max-width:620px;margin:auto;padding:18px">
  <div style="font-size:22px;font-weight:800"><?= mail_safe($app ?? 'Smart Buy') ?></div>
  <p>Hi <?= mail_safe($name ?? 'Seller') ?>,</p>
  <p>✅ Your product <b><?= mail_safe($productTitle ?? '') ?></b> has been approved and is now LIVE.</p>
</div>