<?php
$app = $app ?? 'Smart Buy';
$items = $items ?? [];
$totals = $totals ?? [];
?>
<div style="font-family:Segoe UI,Arial;background:#f4f6fb;padding:30px">
  <div style="max-width:720px;margin:auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.08)">

    <div style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;padding:22px">
      <div style="font-size:22px;font-weight:800"><?= mail_safe($app) ?></div>
      <div style="font-size:13px;opacity:.9">Order Confirmation</div>
    </div>

    <div style="padding:24px">

      <h2 style="margin:0 0 10px">Order Confirmed ✅</h2>

      <p>Hi <b><?= mail_safe($name) ?></b>, your order has been placed successfully.</p>

      <div style="background:#f7f8fd;padding:14px;border-radius:10px;margin:12px 0">
        <b>Order ID:</b> #<?= mail_safe($orderNo) ?>
      </div>

      <table width="100%" style="border-collapse:collapse;font-size:14px;margin-top:14px">
        <thead>
          <tr style="background:#f3f4f6">
            <th align="left" style="padding:10px">Item</th>
            <th align="center">Qty</th>
            <th align="right">Price</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($items as $it): ?>
          <tr>
            <td style="padding:8px"><?= mail_safe($it['name']) ?></td>
            <td align="center"><?= (int)$it['qty'] ?></td>
            <td align="right">₹<?= number_format($it['price'],2) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <div style="margin-top:18px;background:#f9fafb;border-radius:10px;padding:14px">
        <div style="display:flex;justify-content:space-between">
          <span>Subtotal</span>
          <b>₹<?= number_format($totals['subtotal'] ?? 0,2) ?></b>
        </div>
        <div style="display:flex;justify-content:space-between">
          <span>Delivery</span>
          <b>₹<?= number_format($totals['delivery'] ?? 0,2) ?></b>
        </div>
        <hr>
        <div style="display:flex;justify-content:space-between;font-size:16px">
          <span><b>Total</b></span>
          <b>₹<?= number_format($totals['total'] ?? 0,2) ?></b>
        </div>
      </div>

      <p style="font-size:13px;color:#666;margin-top:16px">
        We'll notify you when your order ships 🚚
      </p>

    </div>

    <div style="background:#f3f4f6;padding:14px;text-align:center;font-size:12px;color:#888">
      <?= mail_safe($app) ?> — Trusted Marketplace
    </div>
  </div>
</div>