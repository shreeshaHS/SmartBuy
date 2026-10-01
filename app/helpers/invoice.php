<?php
// app/helpers/invoice.php
// Generates invoice PDF using TCPDF and returns absolute file path.

if (!function_exists('generateInvoicePdf')) {

  function generateInvoicePdf(mysqli $conn, int $orderId): string
  {
    // TCPDF include
    $tcpdf = __DIR__ . '/../lib/tcpdf/tcpdf.php';
    if (!file_exists($tcpdf)) {
      throw new Exception("TCPDF not found. Put it at app/lib/tcpdf/tcpdf.php");
    }
    require_once $tcpdf;

    // Load order
    $st = $conn->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
    $st->bind_param("i", $orderId);
    $st->execute();
    $order = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$order) throw new Exception("Order not found for invoice");

    // Load items
    $st2 = $conn->prepare("
      SELECT oi.*, p.name
      FROM order_items oi
      LEFT JOIN products p ON p.id=oi.product_id
      WHERE oi.order_id=?
      ORDER BY oi.id ASC
    ");
    $st2->bind_param("i", $orderId);
    $st2->execute();
    $items = $st2->get_result()->fetch_all(MYSQLI_ASSOC);
    $st2->close();

    // Address
    $addr = [];
    if (!empty($order['address_json'])) {
      $t = json_decode((string)$order['address_json'], true);
      if (is_array($t)) $addr = $t;
    }

    $invoiceNo = (string)($order['order_no'] ?? ('INV-'.$orderId));
    $invoiceDate = date('d M Y', strtotime($order['created_at'] ?? 'now'));

    // Totals
    $total = (float)($order['total'] ?? 0);
    $discount = (float)($order['discount_amount'] ?? 0);
    $delivery = (float)($order['delivery_charge'] ?? 0);
    $grand = (float)($order['grand_total'] ?? 0);

    // Build HTML
    ob_start();
    $app = defined('APP_NAME') ? APP_NAME : 'SmartBuy';
    include __DIR__ . '/../views/emails/invoice_pdf.php'; // generates $html
    $html = ob_get_clean();

    // Create PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator($app);
    $pdf->SetAuthor($app);
    $pdf->SetTitle("Invoice $invoiceNo");
    $pdf->SetMargins(12, 12, 12);
    $pdf->SetAutoPageBreak(true, 12);
    $pdf->AddPage();
    $pdf->writeHTML($html, true, false, true, false, '');

    // Save file into storage/invoices
    $root = realpath(__DIR__ . '/../../');
    if ($root === false) $root = __DIR__ . '/../../';
    $dir = rtrim($root, DIRECTORY_SEPARATOR) . '/storage/invoices';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $file = $dir . '/invoice_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $invoiceNo) . '.pdf';
    $pdf->Output($file, 'F');

    return $file;
  }
}