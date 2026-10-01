<?php
use PHPMailer\PHPMailer\PHPMailer;
require_once __DIR__ . '/../../PHPMailer/Exception.php';
require_once __DIR__ . '/../../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/SMTP.php';
/**
 * COMMON MAIL CONFIG (ENV based)
 * Requires env.php loaded BEFORE calling mailConfig()
 */
if (!function_exists('mailConfig')) {
    function mailConfig(): PHPMailer
    {
        $mail = new PHPMailer(true);

        // Read config from .env (fallbacks for XAMPP)
        $host = env('MAIL_HOST', 'smtp.gmail.com');
        $user = env('MAIL_USER', 'smarbuy32@gmail.com');
        $pass = env('MAIL_PASS', 'nkli urhb ymen jlci');
        $port = (int) env('MAIL_PORT', '587');
        $from = env('MAIL_FROM', $user);
        $fromName = env('MAIL_FROM_NAME', 'Smart Buy');

        $secure = strtolower((string) env('MAIL_SECURE', 'tls'));
        // tls -> STARTTLS, ssl -> SMTPS
        $smtpSecure = ($secure === 'ssl')
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;

        // SMTP settings
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = $pass;
        $mail->SMTPSecure = $smtpSecure;
        $mail->Port = $port;

        // Optional debug (enable only if needed)
        // $mail->SMTPDebug = 2;

        $mail->setFrom($from, $fromName);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        return $mail;
    }
}

function mail_safe($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function renderEmailTemplate(string $template, array $data = []): string
{
    $file = __DIR__ . '/../views/emails/' . $template . '.php';
    if (!file_exists($file)) {
        return "<p>Template not found: " . mail_safe($template) . "</p>";
    }
    extract($data);
    ob_start();
    require $file;
    return ob_get_clean();
}

function sendMail(string $toEmail, string $toName, string $subject, string $html): bool
{
    try {
        $mail = mailConfig();
        $mail->addAddress($toEmail, $toName ?: $toEmail);
        $mail->Subject = $subject;
        $mail->Body = $html;
        return $mail->send();
    } catch (Exception $e) {
        // Logging handled by bootstrap logger if available
        if (function_exists('app_log')) {
            app_log('error', 'Email send failed', ['to'=>$toEmail, 'err'=>$e->getMessage()]);
        }
        return false;
    }
}
// ---------- AUTH ----------
if (!function_exists('sendOtpMail')) {
  function sendOtpMail(string $email, string $name, string $otp): bool {
    $html = renderEmailTemplate('otp', [
      'name' => $name,
      'otp'  => $otp,
      'app'  => env('MAIL_FROM_NAME', 'Smart Buy'),
      'minutes' => (int)env('OTP_MINUTES', 10),
    ]);
    return sendMail($email, $name, 'Smart Buy - Email Verification OTP', $html);
  }
}

if (!function_exists('sendPasswordResetOtpMail')) {
  function sendPasswordResetOtpMail(string $email, string $name, string $otp): bool {
    $html = renderEmailTemplate('password_reset_otp', [
      'name' => $name,
      'otp'  => $otp,
      'app'  => env('MAIL_FROM_NAME', 'Smart Buy'),
      'minutes' => (int)env('OTP_MINUTES', 10),
    ]);
    return sendMail($email, $name, 'Smart Buy - Password Reset OTP', $html);
  }
}

// ---------- DELIVERY ----------
if (!function_exists('sendDeliveryOtpMail')) {
  function sendDeliveryOtpMail(string $email, string $name, string $orderNo, string $otp): bool {
    $html = renderEmailTemplate('delivery_otp', [
      'name'    => $name,
      'orderNo' => $orderNo,
      'otp'     => $otp,
      'app'     => env('MAIL_FROM_NAME', 'Smart Buy'),
      'minutes' => (int)env('DELIVERY_OTP_MINUTES', 10),
    ]);
    return sendMail($email, $name, "Smart Buy - Delivery OTP (Order {$orderNo})", $html);
  }
}

if (!function_exists('sendDeliveredMail')) {
  function sendDeliveredMail(string $email, string $name, string $orderNo): bool {
    $html = renderEmailTemplate('delivered', [
      'name'    => $name,
      'orderNo' => $orderNo,
      'app'     => env('MAIL_FROM_NAME', 'Smart Buy'),
    ]);
    return sendMail($email, $name, "Smart Buy - Delivered ✅ (Order {$orderNo})", $html);
  }
}

// ---------- ORDERS ----------
if (!function_exists('sendOrderPlacedMail')) {
  function sendOrderPlacedMail(string $email, string $name, string $orderNo, array $items = [], array $totals = []): bool {
    $html = renderEmailTemplate('order_placed', [
      'name'    => $name,
      'orderNo' => $orderNo,
      'items'   => $items,
      'totals'  => $totals,
      'app'     => env('MAIL_FROM_NAME', 'Smart Buy'),
    ]);
    return sendMail($email, $name, "Smart Buy - Order Placed (Order {$orderNo})", $html);
  }
}

if (!function_exists('sendOrderStatusMail')) {
  function sendOrderStatusMail(string $email, string $name, string $orderNo, string $status): bool {
    $html = renderEmailTemplate('order_status', [
      'name'    => $name,
      'orderNo' => $orderNo,
      'status'  => $status,
      'app'     => env('MAIL_FROM_NAME', 'Smart Buy'),
    ]);
    return sendMail($email, $name, "Smart Buy - Order Update: {$status} (Order {$orderNo})", $html);
  }
}

// ---------- SELLER REQUEST ----------
if (!function_exists('sendSellerRequestReceivedMail')) {
  function sendSellerRequestReceivedMail(string $email, string $name, string $businessName): bool {
    $html = renderEmailTemplate('seller_request_received', [
      'name' => $name,
      'businessName' => $businessName,
      'app'  => env('MAIL_FROM_NAME', 'Smart Buy'),
    ]);
    return sendMail($email, $name, "Smart Buy - Seller Request Received", $html);
  }
}

if (!function_exists('sendSellerApprovedMail')) {
  function sendSellerApprovedMail(string $email, string $name, string $businessName): bool {
    $html = renderEmailTemplate('seller_approved', [
      'name' => $name,
      'businessName' => $businessName,
      'app'  => env('MAIL_FROM_NAME', 'Smart Buy'),
    ]);
    return sendMail($email, $name, "Smart Buy - Seller Approved ✅", $html);
  }
}

if (!function_exists('sendSellerRejectedMail')) {
  function sendSellerRejectedMail(string $email, string $name, string $businessName, string $reason=''): bool {
    $html = renderEmailTemplate('seller_rejected', [
      'name' => $name,
      'businessName' => $businessName,
      'reason' => $reason,
      'app'  => env('MAIL_FROM_NAME', 'Smart Buy'),
    ]);
    return sendMail($email, $name, "Smart Buy - Seller Rejected", $html);
  }
}

// ---------- PRODUCT APPROVAL ----------
if (!function_exists('sendProductApprovedMail')) {
  function sendProductApprovedMail(string $email, string $name, string $productTitle): bool {
    $html = renderEmailTemplate('product_approved', [
      'name' => $name,
      'productTitle' => $productTitle,
      'app'  => env('MAIL_FROM_NAME', 'Smart Buy'),
    ]);
    return sendMail($email, $name, "Smart Buy - Product Approved ✅", $html);
  }
}

if (!function_exists('sendProductRejectedMail')) {
  function sendProductRejectedMail(string $email, string $name, string $productTitle, string $reason=''): bool {
    $html = renderEmailTemplate('product_rejected', [
      'name' => $name,
      'productTitle' => $productTitle,
      'reason' => $reason,
      'app'  => env('MAIL_FROM_NAME', 'Smart Buy'),
    ]);
    return sendMail($email, $name, "Smart Buy - Product Rejected", $html);
  }
}
if (!function_exists('sendOrderPlacedWithInvoiceMail')) {
  function sendOrderPlacedWithInvoiceMail(mysqli $conn, int $orderId, string $email, string $name, string $orderNo, array $items = [], array $totals = []): bool {
    try {
      $pdfPath = generateInvoicePdf($conn, $orderId);

      $html = renderEmailTemplate('order_placed', [
        'name'    => $name,
        'orderNo' => $orderNo,
        'items'   => $items,
        'totals'  => $totals,
        'app'     => env('MAIL_FROM_NAME', 'Smart Buy'),
      ]);

      $mail = mailConfig();
      $mail->addAddress($email, $name ?: $email);
      $mail->Subject = "Smart Buy - Order Placed (Order {$orderNo})";
      $mail->Body = $html;

      // Attach invoice
      if (file_exists($pdfPath)) {
        $mail->addAttachment($pdfPath, "Invoice-{$orderNo}.pdf");
      }

      return $mail->send();
    } catch (Throwable $e) {
      if (function_exists('app_log')) {
        app_log('error', 'Invoice mail failed', ['order_id'=>$orderId,'to'=>$email,'err'=>$e->getMessage()]);
      }
      return false;
    }
  }
}