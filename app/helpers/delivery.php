<?php
// app/helpers/delivery.php
// Pincode-based delivery engine.
// Table expected: pincodes(pincode,is_serviceable,delivery_days,delivery_fee,district,state...)

if (!function_exists('safe')) {
  function safe($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('user_is_first_order')) {
  function user_is_first_order(mysqli $conn, string $email): bool {
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM orders WHERE user_email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $c === 0;
  }
}

if (!function_exists('get_delivery_row')) {
  function get_delivery_row(mysqli $conn, string $pincode): ?array {
    $pincode = preg_replace('/\D+/', '', $pincode);
    if (strlen($pincode) !== 6) return null;

    $stmt = $conn->prepare("
      SELECT pincode, is_serviceable, delivery_days, delivery_fee
      FROM pincodes
      WHERE pincode=?
      LIMIT 1
    ");
    $stmt->bind_param("s", $pincode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
  }
}

if (!function_exists('get_delivery_days')) {
  function get_delivery_days(mysqli $conn, string $pincode): int {
    $row = get_delivery_row($conn, $pincode);
    if (!$row) return 5;

    if (isset($row['is_serviceable']) && (int)$row['is_serviceable'] === 0) return 0;

    $days = (int)($row['delivery_days'] ?? 5);
    return $days > 0 ? $days : 5;
  }
}

if (!function_exists('calc_delivery_eta')) {
  function calc_delivery_eta(mysqli $conn, string $pincode): ?string {
    $days = get_delivery_days($conn, $pincode);
    if ($days <= 0) return null;
    return date('Y-m-d', strtotime("+$days days"));
  }
}

/**
 * delivery_quote()
 * returns:
 *   ['days'=>int,'eta'=>'YYYY-MM-DD','fee'=>float,'msg'=>string,'serviceable'=>1]
 * OR null if not serviceable / not found
 */
if (!function_exists('delivery_quote')) {
  function delivery_quote(mysqli $conn, string $pincode, float $cartTotalAfterDiscount, string $userEmail): ?array {
    $pincode = trim($pincode);
    if (!preg_match('/^\d{6}$/', $pincode)) return null;

    $row = get_delivery_row($conn, $pincode);
    if (!$row) return null;

    if ((int)($row['is_serviceable'] ?? 0) !== 1) return null;

    $days = (int)($row['delivery_days'] ?? 5);
    if ($days <= 0) $days = 5;

    // ✅ always DATE like 2026-02-28
    $eta = date('Y-m-d', strtotime("+{$days} days"));

    // Base fee from table if present else constant else 49
    $baseFee = 49.0;
    if (isset($row['delivery_fee']) && $row['delivery_fee'] !== null && $row['delivery_fee'] !== '') {
      $baseFee = (float)$row['delivery_fee'];
    } elseif (defined('DELIVERY_BASE_FEE')) {
      $baseFee = (float)DELIVERY_BASE_FEE;
    }

    // Rule can be: FREE_999 | FREE_499 | FIRST_ORDER
    $rule = defined('DELIVERY_FREE_RULE') ? (string)DELIVERY_FREE_RULE : 'FREE_999';

    $fee = $baseFee;
    $msg = "₹{$baseFee} delivery";

    if ($rule === 'FREE_999') {
      $th = defined('DELIVERY_FREE_ABOVE_999') ? (float)DELIVERY_FREE_ABOVE_999 : 999.0;
      if ($cartTotalAfterDiscount >= $th) {
        $fee = 0.0;
        $msg = "Free delivery (above ₹{$th})";
      } else {
        $msg = "₹{$baseFee} delivery (free above ₹{$th})";
      }
    } elseif ($rule === 'FREE_499') {
      $th = defined('DELIVERY_FREE_ABOVE_499') ? (float)DELIVERY_FREE_ABOVE_499 : 499.0;
      if ($cartTotalAfterDiscount >= $th) {
        $fee = 0.0;
        $msg = "Free delivery (above ₹{$th})";
      } else {
        $msg = "₹{$baseFee} delivery (free above ₹{$th})";
      }
    } else { // FIRST_ORDER
      $isFirst = user_is_first_order($conn, $userEmail);
      if ($isFirst) {
        $fee = 0.0;
        $msg = "Free delivery (first order)";
      } else {
        $msg = "₹{$baseFee} delivery (first order free)";
      }
    }

    return [
      'serviceable' => 1,
      'days'        => $days,
      'eta'         => $eta,
      'fee'         => $fee,
      'msg'         => $msg
    ];
  }
}

function order_status_steps(): array {
  return [
    'placed' => 'Placed',
    'packed' => 'Packed',
    'shipped' => 'Shipped',
    'out_for_delivery' => 'Out for delivery',
    'delivered' => 'Delivered',
  ];
}

function order_step_index(string $status): int {
  $status = strtolower(trim($status));
  $keys = array_keys(order_status_steps());
  $i = array_search($status, $keys, true);
  return ($i === false) ? 0 : (int)$i;
}

function render_order_timeline(string $status, ?string $etaDate = null): string {
  $steps = order_status_steps();
  $idx = order_step_index($status);

  $etaHtml = '';
  if (!empty($etaDate)) {
    $etaHtml = '<div class="muted" style="margin-top:8px">Estimated delivery: <b>'.safe($etaDate).'</b></div>';
  }

  $html = '<div class="timeline">';
  $i = 0;
  foreach ($steps as $key => $label) {
    $state = ($i < $idx) ? 'done' : (($i === $idx) ? 'active' : 'todo');
    $html .= '
      <div class="tstep '.$state.'">
        <div class="dot"></div>
        <div class="lbl">'.safe($label).'</div>
      </div>
    ';
    $i++;
  }
  $html .= '</div>'.$etaHtml;

  return $html;
}