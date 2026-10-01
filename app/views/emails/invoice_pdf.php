<?php
// app/views/emails/invoice_pdf.php
// Variables available from generateInvoicePdf():
// $app, $invoiceNo, $invoiceDate, $order, $items, $addr, $total, $discount, $delivery, $grand

function inv($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$buyerName = $addr['name'] ?? $order['user_email'] ?? 'Customer';
$buyerPhone = $addr['phone'] ?? '';
$buyerLine1 = $addr['address_line1'] ?? ($addr['address'] ?? '');
$buyerCity  = $addr['city'] ?? '';
$buyerState = $addr['state'] ?? '';
$buyerPin   = $addr['pincode'] ?? '';
?>
<style>
  .h1{font-size:18px;font-weight:800}
  .muted{color:#666;font-size:11px}
  .box{border:1px solid #ddd;border-radius:6px;padding:10px}
  table{width:100%;border-collapse:collapse}
  th{background:#f2f2f2;font-size:11px;padding:8px;border:1px solid #ddd}
  td{font-size:11px;padding:8px;border:1px solid #ddd}
  .right{text-align:right}
</style>

<div>
  <table>
    <tr>
      <td style="border:none">
        <div class="h1"><?= inv($app) ?> - TAX INVOICE</div>
        <div class="muted">Invoice generated for order confirmation</div>
      </td>
      <td style="border:none" class="right">
        <div><b>Invoice:</b> <?= inv($invoiceNo) ?></div>
        <div><b>Date:</b> <?= inv($invoiceDate) ?></div>
        <div><b>Payment:</b> <?= inv($order['payment_method'] ?? '') ?> / <?= inv($order['payment_status'] ?? '') ?></div>
      </td>
    </tr>
  </table>

  <br>

  <table>
    <tr>
      <td class="box">
        <b>Bill To</b><br>
        <?= inv($buyerName) ?><br>
        <?= inv($buyerLine1) ?><br>
        <?= inv($buyerCity) ?> <?= inv($buyerState) ?> - <?= inv($buyerPin) ?><br>
        <?= $buyerPhone ? ('Phone: '.inv($buyerPhone).'<br>') : '' ?>
        Email: <?= inv($order['user_email'] ?? '') ?>
      </td>
      <td class="box">
        <b>Sold By</b><br>
        <?= inv($app) ?><br>
        Support: <?= inv(env('MAIL_USER','support@example.com')) ?><br>
        <span class="muted">This is a system generated invoice.</span>
      </td>
    </tr>
  </table>

  <br>

  <table>
    <thead>
      <tr>
        <th style="width:44%">Item</th>
        <th style="width:12%" class="right">Qty</th>
        <th style="width:22%" class="right">Price</th>
        <th style="width:22%" class="right">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($items as $it): ?>
        <tr>
          <td><?= inv($it['name'] ?? ('Product #'.$it['product_id'])) ?></td>
          <td class="right"><?= (int)($it['qty'] ?? 0) ?></td>
          <td class="right">₹<?= number_format((float)($it['price'] ?? 0), 2) ?></td>
          <td class="right">₹<?= number_format((float)($it['subtotal'] ?? 0), 2) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <br>

  <table>
    <tr>
      <td style="border:none"></td>
      <td style="border:none" class="right" width="40%">
        <table>
          <tr><td>Items Total</td><td class="right">₹<?= number_format($total,2) ?></td></tr>
          <tr><td>Discount</td><td class="right">- ₹<?= number_format($discount,2) ?></td></tr>
          <tr><td>Delivery</td><td class="right">₹<?= number_format($delivery,2) ?></td></tr>
          <tr><th>Total Payable</th><th class="right">₹<?= number_format($grand,2) ?></th></tr>
        </table>
      </td>
    </tr>
  </table>

  <br>
  <div class="muted">
    Note: Keep this invoice for warranty/returns. For support, contact us from your account dashboard.
  </div>
</div>