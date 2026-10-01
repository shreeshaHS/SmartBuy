<?php
require_once __DIR__ . '/../../config/bootstrap.php';
global $conn;

if (isPost()) { require_csrf(); }
requireLogin();

$email = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
if ($email === '') { http_response_code(401); exit('Unauthorized'); }

$err = null;
$ok  = null;

// If you want: show last request status to user
$lastReq = null;
try {
  $st = $conn->prepare("SELECT id,status,created_at FROM seller_requests WHERE user_email=? ORDER BY id DESC LIMIT 1");
  $st->bind_param("s", $email);
  $st->execute();
  $lastReq = $st->get_result()->fetch_assoc();
  $st->close();
} catch(Throwable $e){
  // ignore if table missing, but your project expects this table
}

if (isPost()) {
  $store_name  = trim($_POST['store_name'] ?? '');
  $owner_name  = trim($_POST['owner_name'] ?? '');
  $phone       = trim($_POST['phone'] ?? '');

  $gstin       = trim($_POST['gstin'] ?? '');
  $pan         = trim($_POST['pan'] ?? '');

  $bank_holder = trim($_POST['bank_holder'] ?? '');
  $bank_account= trim($_POST['bank_account'] ?? '');
  $ifsc        = trim($_POST['ifsc'] ?? '');

  $addr1       = trim($_POST['address_line1'] ?? '');
  $city        = trim($_POST['city'] ?? '');
  $state       = trim($_POST['state'] ?? '');
  $pincode     = trim($_POST['pincode'] ?? '');

  // ✅ file paths (uploaded earlier through API)
  $doc_pan     = trim($_POST['doc_pan_path'] ?? '');
  $doc_aadhar  = trim($_POST['doc_aadhar_path'] ?? '');
  $doc_gstin   = trim($_POST['doc_gstin_path'] ?? '');
  $doc_bank    = trim($_POST['doc_bank_path'] ?? '');
  $doc_shop    = trim($_POST['doc_shop_license_path'] ?? '');
  $logo        = trim($_POST['store_logo_path'] ?? '');

  if (
    $store_name==='' || $owner_name==='' || $phone==='' ||
    $bank_holder==='' || $bank_account==='' || $ifsc==='' ||
    $addr1==='' || $city==='' || $state==='' || $pincode===''
  ) {
    $err = "Fill all required fields.";
  } else {

    // prevent duplicate pending request
    $existing = null;
    $stmt = $conn->prepare("SELECT id,status FROM seller_requests WHERE user_email=? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing && ($existing['status'] ?? '') === 'pending') {
      $err = "You already have a pending request. Wait for admin approval.";
    } else {

      $sql = "
        INSERT INTO seller_requests
        (user_email,store_name,owner_name,phone,gstin,pan,bank_holder,bank_account,ifsc,
         address_line1,address_city,address_state,address_pincode,
         doc_pan,doc_aadhar,doc_gstin,doc_bank,doc_shop_license,store_logo,status,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',NOW())
      ";

      $stmt = $conn->prepare($sql);
      $stmt->bind_param(
        "sssssssssssssssssss",
        $email,$store_name,$owner_name,$phone,$gstin,$pan,$bank_holder,$bank_account,$ifsc,
        $addr1,$city,$state,$pincode,
        $doc_pan,$doc_aadhar,$doc_gstin,$doc_bank,$doc_shop,$logo
      );

      try {
        $stmt->execute();
        // ✅ EMAIL: seller request received
if (function_exists('sendSellerRequestReceivedMail')) {
  @sendSellerRequestReceivedMail($email, $owner_name ?: $email, $store_name);
}
        $ok = "Seller request submitted ✅ Admin will review your documents.";
      } catch (Throwable $e) {
        $err = "Submit failed. (Check seller_requests table columns)";
        if (defined('APP_DEBUG') && (string)APP_DEBUG === '1') {
          $err .= " :: " . $e->getMessage();
        }
      } finally {
        $stmt->close();
      }
    }
  }
}

$title = "Become a Seller - ".APP_NAME;
require __DIR__ . '/../../views/layout/header.php';
?>

<div class="container">
  <div class="card">
    <div class="page-title">Become a Seller</div>
    <div class="muted">Upload documents one-by-one (no large submit). Then submit details.</div>

    <?php if($lastReq): ?>
      <div class="card" style="margin-top:10px">
        <div><b>Last Request:</b> <?= safe($lastReq['status']) ?> <span class="muted">• <?= safe($lastReq['created_at']) ?></span></div>
      </div>
    <?php endif; ?>

    <?php if($err): ?><div class="alert error" style="margin-top:10px"><?= safe($err) ?></div><?php endif; ?>
    <?php if($ok): ?><div class="alert ok" style="margin-top:10px"><?= safe($ok) ?></div><?php endif; ?>

    <form method="post" class="auth-form" id="sellerForm">
      <?= csrf_field() ?>

      <!-- hidden paths -->
      <input type="hidden" name="doc_pan_path" id="doc_pan_path">
      <input type="hidden" name="doc_aadhar_path" id="doc_aadhar_path">
      <input type="hidden" name="doc_gstin_path" id="doc_gstin_path">
      <input type="hidden" name="doc_bank_path" id="doc_bank_path">
      <input type="hidden" name="doc_shop_license_path" id="doc_shop_license_path">
      <input type="hidden" name="store_logo_path" id="store_logo_path">

      <div class="grid" style="grid-template-columns:repeat(2,1fr);gap:12px">
        <div class="f"><input name="store_name" placeholder=" " required><label>Store Name *</label></div>
        <div class="f"><input name="owner_name" placeholder=" " required><label>Owner Name *</label></div>
        <div class="f"><input name="phone" placeholder=" " required><label>Phone *</label></div>
        <div class="f"><input name="gstin" placeholder=" "><label>GSTIN (optional)</label></div>
        <div class="f"><input name="pan" placeholder=" "><label>PAN (optional)</label></div>
      </div>

      <div class="divider"></div>

      <div class="page-title" style="font-size:16px">Bank Details</div>
      <div class="grid" style="grid-template-columns:repeat(2,1fr);gap:12px">
        <div class="f"><input name="bank_holder" placeholder=" " required><label>Account Holder *</label></div>
        <div class="f"><input name="bank_account" placeholder=" " required><label>Account Number *</label></div>
        <div class="f"><input name="ifsc" placeholder=" " required><label>IFSC *</label></div>
      </div>

      <div class="divider"></div>

      <div class="page-title" style="font-size:16px">Pickup Address</div>
      <div class="grid" style="grid-template-columns:repeat(2,1fr);gap:12px">
        <div class="f" style="grid-column:1/-1"><input name="address_line1" placeholder=" " required><label>Address Line *</label></div>
        <div class="f"><input name="city" placeholder=" " required><label>City *</label></div>
        <div class="f"><input name="state" placeholder=" " required><label>State *</label></div>
        <div class="f"><input name="pincode" placeholder=" " required><label>Pincode *</label></div>
      </div>

      <div class="divider"></div>

      <div class="page-title" style="font-size:16px">Upload Documents (max 3MB each)</div>
      <div class="muted">Each file uploads immediately — no big submit.</div>

      <div class="grid" style="grid-template-columns:repeat(2,1fr);gap:12px;margin-top:10px">
        <div class="f has-value">
          <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" onchange="uploadDoc(this,'doc_pan')">
          <label>PAN Proof</label>
          <div class="muted" id="doc_pan_status"></div>
        </div>

        <div class="f has-value">
          <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" onchange="uploadDoc(this,'doc_aadhar')">
          <label>Aadhar Proof</label>
          <div class="muted" id="doc_aadhar_status"></div>
        </div>

        <div class="f has-value">
          <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" onchange="uploadDoc(this,'doc_gstin')">
          <label>GST Certificate</label>
          <div class="muted" id="doc_gstin_status"></div>
        </div>

        <div class="f has-value">
          <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" onchange="uploadDoc(this,'doc_bank')">
          <label>Bank Proof</label>
          <div class="muted" id="doc_bank_status"></div>
        </div>

        <div class="f has-value">
          <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" onchange="uploadDoc(this,'doc_shop_license')">
          <label>Shop License</label>
          <div class="muted" id="doc_shop_license_status"></div>
        </div>

        <div class="f has-value">
          <input type="file" accept=".jpg,.jpeg,.png,.webp" onchange="uploadDoc(this,'store_logo')">
          <label>Store Logo</label>
          <div class="muted" id="store_logo_status"></div>
        </div>
      </div>

      <div class="divider"></div>

      <button class="btn" type="submit" onclick="return beforeSubmitSeller()">Submit for Verification</button>
    </form>
  </div>
</div>

<script>
const BASE_URL = "<?= BASE_URL ?>";
const CSRF = "<?= csrf_token() ?>";

async function uploadDoc(input, field){
  const status = document.getElementById(field + "_status");
  const hidden = document.getElementById(field + "_path");

  if(!input.files || !input.files[0]){
    status.textContent = "";
    return;
  }

  const file = input.files[0];

  if(file.size > 3* 1024 * 1024){
    status.textContent = "❌ File too large (max 30MB)";
    input.value = "";
    return;
  }

  status.textContent = "Uploading…";

  const fd = new FormData();
  fd.append("csrf_token", CSRF);
  fd.append("field", field);
  fd.append("file", file);

  const res = await fetch(BASE_URL + "?page=api/upload_seller_doc", {
    method: "POST",
    body: fd
  });

  const data = await res.json().catch(()=>({ok:false,msg:"Upload failed"}));

  if(data.ok){
    hidden.value = data.path;
    status.textContent = "✅ Uploaded";
  } else {
    hidden.value = "";
    status.textContent = "❌ " + (data.msg || "Upload failed");
    input.value = "";
  }
}

function beforeSubmitSeller(){
  // Optional: Force at least PAN + Aadhar + Bank proof
  const pan = document.getElementById('doc_pan_path').value.trim();
  const aad = document.getElementById('doc_aadhar_path').value.trim();
  const bank = document.getElementById('doc_bank_path').value.trim();

  if(!pan || !aad || !bank){
    alert("Please upload PAN + Aadhar + Bank Proof before submitting.");
    return false;
  }
  return true;
}
</script>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>