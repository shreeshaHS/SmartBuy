<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
requireLogin();
requireRole('admin');
global $conn;

$id = (int)($_GET['id'] ?? 0);
if ($id<=0) { http_response_code(404); echo "Not found"; exit; }

$stmt = $conn->prepare("SELECT * FROM seller_requests WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$r){ http_response_code(404); echo "Not found"; exit; }

function docLink($path){
  $path = trim((string)$path);
  if ($path === '') return '<span class="muted">—</span>';

  // ✅ BASE_URL already has /public/
  $url = rtrim(BASE_URL,'/') . '/' . ltrim($path,'/');

  return '<a class="btn small ghost" target="_blank" href="'.safe($url).'">Open</a>';
}

$title = "Seller Request #".(int)$r['id']." - ".APP_NAME;
require __DIR__ . '/../../../views/layout/header.php';
?>

<div class="container">
  <div class="card">
    <div class="row" style="justify-content:space-between">
      <div>
        <div class="page-title">Seller Request #<?= (int)$r['id'] ?></div>
        <div class="muted">Status: <b><?= safe($r['status']) ?></b></div>
      </div>
      <a class="btn ghost" href="<?= BASE_URL ?>admin/seller_requests">Back</a>
    </div>

    <div class="divider"></div>

    <div class="grid" style="grid-template-columns:repeat(2,1fr);gap:12px">
      <div class="fk-card">
        <div style="font-weight:950;margin-bottom:10px">Business</div>
        <div><b>Email:</b> <?= safe($r['user_email']) ?></div>
        <div><b>Store:</b> <?= safe($r['store_name']) ?></div>
        <div><b>Owner:</b> <?= safe($r['owner_name']) ?></div>
        <div><b>Phone:</b> <?= safe($r['phone']) ?></div>
        <div><b>GSTIN:</b> <?= safe($r['gstin'] ?? '') ?></div>
        <div><b>PAN:</b> <?= safe($r['pan'] ?? '') ?></div>
      </div>

      <div class="fk-card">
        <div style="font-weight:950;margin-bottom:10px">Pickup Address</div>
        <div><?= safe($r['address_line1']) ?></div>
        <div><?= safe($r['address_city']) ?>, <?= safe($r['address_state']) ?> - <?= safe($r['address_pincode']) ?></div>
      </div>

      <div class="fk-card">
        <div style="font-weight:950;margin-bottom:10px">Bank</div>
        <div><b>Holder:</b> <?= safe($r['bank_holder']) ?></div>
        <div><b>Account:</b> <?= safe($r['bank_account']) ?></div>
        <div><b>IFSC:</b> <?= safe($r['ifsc']) ?></div>
      </div>

      <div class="fk-card">
        <div style="font-weight:950;margin-bottom:10px">Documents</div>
        <div class="row" style="justify-content:space-between"><span>PAN Proof</span><?= docLink($r['doc_pan'] ?? '') ?></div>
        <div class="row" style="justify-content:space-between"><span>Aadhar Proof</span><?= docLink($r['doc_aadhar'] ?? '') ?></div>
        <div class="row" style="justify-content:space-between"><span>GST Certificate</span><?= docLink($r['doc_gstin'] ?? '') ?></div>
        <div class="row" style="justify-content:space-between"><span>Bank Proof</span><?= docLink($r['doc_bank'] ?? '') ?></div>
        <div class="row" style="justify-content:space-between"><span>Shop License</span><?= docLink($r['doc_shop_license'] ?? '') ?></div>
        <div class="row" style="justify-content:space-between"><span>Store Logo</span><?= docLink($r['store_logo'] ?? '') ?></div>
      </div>
    </div>

  </div>
</div>

<?php require __DIR__ . '/../../../views/layout/footer.php'; ?>