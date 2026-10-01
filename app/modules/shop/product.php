<?php
require_once __DIR__ . '/../../config/bootstrap.php';
global $conn;

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') redirect('products');

/** column exists check */
function colExists(mysqli $conn, string $table, string $col): bool {
  $sql = "SELECT 1
          FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
          LIMIT 1";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ss", $table, $col);
  $stmt->execute();
  $ok = (bool)$stmt->get_result()->fetch_row();
  $stmt->close();
  return $ok;
}

$hasTitle = colExists($conn, 'products', 'title');

$sql = "
  SELECT
    p.*,
    ".($hasTitle ? "COALESCE(p.title, p.name)" : "p.name")." AS display_name,
    c.name AS category_name,
    s.name AS subcategory_name
  FROM products p
  LEFT JOIN categories c ON c.id=p.category_id
  LEFT JOIN subcategories s ON s.id=p.subcategory_id
  WHERE p.slug=? AND p.status='live'
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $slug);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

$title = "Product - " . APP_NAME;
require __DIR__ . '/../../views/layout/header.php';

if (!$product) {
  echo '<div class="card"><div class="page-title">Product not found</div>
        <div class="muted">This product may be unavailable.</div>
        <div class="divider"></div>
        <a class="btn" href="'.BASE_URL.'products">Back</a></div>';
  require __DIR__ . '/../../views/layout/footer.php';
  exit;
}

$pid = (int)$product['id'];
$title = (string)$product['display_name'];

/* images */
$imgs = [];
try {
  $stmt = $conn->prepare("
    SELECT image_path
    FROM product_images
    WHERE product_id=?
    ORDER BY is_primary DESC, sort_order ASC, id ASC
  ");
  $stmt->bind_param("i", $pid);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  foreach ($rows as $r) {
    $p = trim((string)($r['image_path'] ?? ''));
    if ($p === '') continue;
    $imgs[] = (strpos($p, 'http') === 0) ? $p : rtrim(BASE_URL,'/').'/'.ltrim($p,'/');
  }
} catch (Throwable $e) {}

if (!$imgs && !empty($product['images_json'])) {
  $arr = json_decode((string)$product['images_json'], true);
  if (is_array($arr)) {
    foreach ($arr as $p) {
      $p = trim((string)$p);
      if ($p === '') continue;
      $imgs[] = (strpos($p, 'http') === 0) ? $p : rtrim(BASE_URL,'/').'/'.ltrim($p,'/');
    }
  }
}

$placeholder = rtrim(BASE_URL,'/') . "/assets/img/no-image.png";
$mainImg = $imgs[0] ?? $placeholder;

/* price */
$mrp   = (float)($product['mrp'] ?? 0);
$price = (float)($product['price'] ?? 0);
$off = 0;
if ($mrp > 0 && $price > 0 && $mrp > $price) $off = (int)round((($mrp-$price)/$mrp)*100);

/* specs */
$specs = [];
try {
  $stmt = $conn->prepare("
    SELECT f.label, f.unit, ps.value
    FROM product_specs ps
    JOIN spec_fields f ON f.id = ps.spec_field_id
    WHERE ps.product_id=?
    ORDER BY f.label ASC
  ");
  $stmt->bind_param("i", $pid);
  $stmt->execute();
  $specs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
} catch(Throwable $e){}

/* delivery */
$userEmail = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
$shipPin = $_SESSION['ship_pin'] ?? '';
if ($shipPin === '' && $userEmail !== '') {
  $stPin = $conn->prepare("
    SELECT pincode
    FROM user_addresses
    WHERE user_email=?
    ORDER BY is_default DESC, id DESC
    LIMIT 1
  ");
  $stPin->bind_param("s", $userEmail);
  $stPin->execute();
  $rowPin = $stPin->get_result()->fetch_assoc();
  $stPin->close();
  $shipPin = (string)($rowPin['pincode'] ?? '');
}

$deliveryBox = ['ok'=>false,'text'=>'Enter pincode to check delivery date.','fee'=>'','pin'=>''];
if (preg_match('/^\d{6}$/', $shipPin)) {
  $q = delivery_quote($conn, $shipPin, max(0.0,$price), $userEmail);
  if ($q === null) {
    $deliveryBox = ['ok'=>false,'text'=>"Delivery not available to $shipPin",'fee'=>'','pin'=>$shipPin];
  } else {
    $days = (int)$q['days'];
    $min = max(1, $days-1);
    $max = $days+1;
    $by = date('D, d M', strtotime($q['eta']));
    $deliveryBox = ['ok'=>true,'text'=>"Arrives in {$min}–{$max} days (by {$by})",'fee'=>$q['msg'],'pin'=>$shipPin];
  }
}
?>

<div class="card">
  <div class="row" style="justify-content:space-between;align-items:center;flex-wrap:wrap">
    <div>
      <div class="page-title"><?= safe($product['display_name']) ?></div>
      <div class="muted">
        <?= safe($product['brand'] ?? '') ?>
        <?php if(!empty($product['category_name'])): ?> • <?= safe($product['category_name']) ?><?php endif; ?>
        <?php if(!empty($product['subcategory_name'])): ?> • <?= safe($product['subcategory_name']) ?><?php endif; ?>
      </div>
    </div>
    <a class="btn small ghost" href="<?= BASE_URL ?>products">← Back</a>
  </div>

  <div class="divider"></div>

  <div class="product-wrap">
    <div>
      <div class="big-img" style="display:flex;align-items:center;justify-content:center;overflow:hidden">
        <img id="mainImg" src="<?= safe($mainImg) ?>" alt="" style="max-height:320px;max-width:100%;object-fit:contain">
      </div>

      <?php if(count($imgs) > 1): ?>
        <div class="row" style="gap:10px;margin-top:10px;flex-wrap:wrap">
          <?php foreach($imgs as $imgUrl): ?>
            <button type="button" class="icon-btn" style="padding:6px 8px;border-radius:14px" onclick="swapImg('<?= safe($imgUrl) ?>')">
              <img src="<?= safe($imgUrl) ?>" style="height:44px;width:64px;object-fit:contain;display:block">
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div>
      <div class="row" style="gap:12px;align-items:center;flex-wrap:wrap">
        <div class="price" style="font-size:28px">₹<?= number_format($price,2) ?></div>
        <?php if($mrp>0): ?><div class="muted" style="text-decoration:line-through">₹<?= number_format($mrp,2) ?></div><?php endif; ?>
        <?php if($off>0): ?><span class="badge"><?= $off ?>% OFF</span><?php endif; ?>
      </div>

      <div class="card" style="margin-top:12px">
        <?php if($deliveryBox['ok']): ?>
          <div style="font-weight:900">🚚 <?= safe($deliveryBox['text']) ?></div>
          <div class="muted" style="margin-top:6px"><?= safe($deliveryBox['fee']) ?> • Pin: <b><?= safe($deliveryBox['pin']) ?></b></div>
        <?php else: ?>
          <div class="muted"><?= safe($deliveryBox['text']) ?></div>
          <?php if($deliveryBox['pin']): ?><div class="muted" style="margin-top:6px">Pin: <b><?= safe($deliveryBox['pin']) ?></b></div><?php endif; ?>
        <?php endif; ?>

        <form method="post" action="<?= BASE_URL ?>?page=api/set-pincode&slug=<?= urlencode($slug) ?>"
              style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap">
          <?= csrf_field() ?>
          <input name="pincode" placeholder="Enter pincode" maxlength="6" style="max-width:180px">
          <button class="btn small" type="submit">Check</button>
        </form>
      </div>

      <div class="divider"></div>

      <div class="row" style="gap:10px;flex-wrap:wrap">
        <button class="btn" type="button" onclick="addToCart(<?= $pid ?>)">Add to Cart</button>

        <!-- ✅ FIX: use $pid (not $p) -->
      <button id="btnWish" class="btn ghost" data-id="<?= (int)$pid ?>">♡ Wishlist</button>

        <a class="btn ghost" href="#specs">View Specs</a>
      </div>

      <div class="divider"></div>

      <div class="page-title" style="font-size:16px">Description</div>
      <div class="muted" style="line-height:1.65">
        <?= nl2br(safe($product['description'] ?? 'No description provided.')) ?>
      </div>

      <div class="divider"></div>

      <div class="row muted" style="font-size:12px;gap:14px">
        <div>Stock: <b><?= (int)($product['stock'] ?? 0) ?></b></div>
        <div>Status: <b><?= safe($product['status'] ?? '') ?></b></div>
      </div>
    </div>
  </div>

  <div class="divider"></div>

  <div id="specs" class="page-title">Specifications</div>
  <?php if(empty($specs)): ?>
    <div class="muted">No specifications saved for this product.</div>
  <?php else: ?>
    <div style="border:1px solid var(--stroke);border-radius:16px;overflow:hidden;margin-top:10px">
      <table width="100%" class="table" style="margin:0">
        <thead><tr><th style="width:38%">Field</th><th>Value</th></tr></thead>
        <tbody>
        <?php foreach($specs as $s): ?>
          <tr>
            <td><b><?= safe($s['label']) ?></b><?php if(!empty($s['unit'])): ?> <span class="muted">(<?= safe($s['unit']) ?>)</span><?php endif; ?></td>
            <td><?= safe($s['value']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
const BASE_URL = <?= json_encode(BASE_URL) ?>;
const CSRF = <?= json_encode(csrf_token()) ?>;

async function postJSON(url, data){
  const fd = new FormData();
  fd.append('csrf_token', CSRF);
  for(const k in data) fd.append(k, data[k]);

  const res = await fetch(url, { method:'POST', body: fd });
  const text = await res.text();
  try { return JSON.parse(text); } catch(e){ return {ok:false, msg:text || 'Server error'}; }
}

async function addToCart(id){
  const r = await postJSON(BASE_URL + '?page=api/cart-add', {id, qty:1});
  alert(r.ok ? 'Added to cart' : (r.msg || 'Failed'));
}

function swapImg(src){
  const el = document.getElementById('mainImg');
  if(el) el.src = src;
}

document.getElementById('btnWish')?.addEventListener('click', async ()=>{
  const btn = document.getElementById('btnWish');
  const product_id = btn.dataset.id;

  const fd = new FormData();
  fd.append('csrf_token', CSRF);
  fd.append('product_id', product_id); // IMPORTANT

  const res = await fetch(BASE_URL + "?page=api/wishlist-toggle", {
    method:"POST",
    body: fd
  });

  const text = await res.text();
  let data;
  try { data = JSON.parse(text); } catch { data = {ok:false,msg:text}; }

  if(!data.ok){
    alert(data.msg || "Failed");
    return;
  }

  btn.textContent = data.liked ? "♥ Wishlisted" : "♡ Wishlist";
});
</script>


<?php require __DIR__ . '/../../views/layout/footer.php'; ?>