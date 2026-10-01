<?php
require_once __DIR__ . '/../../config/bootstrap.php';
global $conn;

if (isPost()) { require_csrf(); }
requireLogin();
requireRole('seller');

$seller = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
if ($seller === '') { http_response_code(401); exit('Unauthorized'); }

$err = null;
$ok  = null;

function slugify(string $text): string {
  $text = strtolower(trim($text));
  $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
  $text = trim($text, '-');
  return $text !== '' ? $text : 'product';
}

function uniqueProductSlug(mysqli $conn, string $baseSlug): string {
  $slug = $baseSlug;
  $i = 0;
  $stmt = $conn->prepare("SELECT 1 FROM products WHERE slug=? LIMIT 1");
  while (true) {
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_row();
    if (!$exists) break;
    $i++;
    $slug = $baseSlug . '-' . $i;
  }
  $stmt->close();
  return $slug;
}

/**
 * Upload product images and return relative web paths.
 * Saves to: public/uploads/products/<productId>/
 */
function uploadProductImages(int $productId, string $field = 'images'): array {
  if (empty($_FILES[$field]) || !isset($_FILES[$field]['name'])) return [];
  $files = $_FILES[$field];
  if (!is_array($files['name'])) return [];

  $allowedMime = ['image/jpeg','image/png','image/webp'];
  $maxSize = 3 * 1024 * 1024; // 3MB each
  $maxFiles = 6;

  $saved = [];

  $publicDir = realpath(__DIR__ . '/../../../public');
  if ($publicDir === false) return [];

  $baseDir = $publicDir . '/uploads/products/' . $productId . '/';
  if (!is_dir($baseDir)) @mkdir($baseDir, 0777, true);

  $count = min(count($files['name']), $maxFiles);

  for ($i=0; $i<$count; $i++) {
    if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
    if (($files['size'][$i] ?? 0) > $maxSize) continue;

    $tmp = $files['tmp_name'][$i] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) continue;

    $mime = @mime_content_type($tmp) ?: '';
    if (!in_array($mime, $allowedMime, true)) continue;

    $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) $ext = 'jpg';

    $name = 'img_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $baseDir . $name;

    if (!move_uploaded_file($tmp, $dest)) continue;

    $saved[] = 'uploads/products/' . $productId . '/' . $name;
  }

  return $saved;
}

// categories list
$cats = $conn->query("SELECT id,name FROM categories WHERE is_active=1 ORDER BY sort_order, name")
            ->fetch_all(MYSQLI_ASSOC);

if (isPost()) {
  $category_id    = (int)($_POST['category_id'] ?? 0);
  $subcategory_id = (int)($_POST['subcategory_id'] ?? 0);

  $name  = trim($_POST['title'] ?? '');
  $brand = trim($_POST['brand'] ?? '');
  $price = (float)($_POST['price'] ?? 0);

  $mrp_raw = trim($_POST['mrp'] ?? '');
  $mrp     = ($mrp_raw === '') ? null : (float)$mrp_raw;

  $stock = (int)($_POST['stock'] ?? 0);

  if ($category_id<=0 || $subcategory_id<=0 || $name==='' || $price<=0) {
    $err = "Fill Category, Subcategory, Title and Price.";
  } else {

    // Ensure selected subcategory belongs to selected category
    $chk = $conn->prepare("SELECT 1 FROM subcategories WHERE id=? AND category_id=? LIMIT 1");
    $chk->bind_param("ii", $subcategory_id, $category_id);
    $chk->execute();
    $okSub = $chk->get_result()->fetch_row();
    $chk->close();

    if (!$okSub) {
      $err = "Invalid subcategory selected.";
    } else {

      $slug = uniqueProductSlug($conn, slugify($name));

      $stmt = $conn->prepare("
        INSERT INTO products
        (seller_email, category_id, subcategory_id, name, slug, brand, price, mrp, stock, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
      ");
      $stmt->bind_param(
        "siisssddi",
        $seller, $category_id, $subcategory_id, $name, $slug, $brand, $price, $mrp, $stock
      );
      $stmt->execute();
      $pid = (int)$stmt->insert_id;
      $stmt->close();

      if ($pid <= 0) {
        $err = "Failed to create product. Try again.";
      } else {

        /**
         * SAVE SPECS:
         * Only allow spec_field_id that belongs to this subcategory (IMPORTANT!)
         * This prevents saving "random specs".
         */
        $allowed = [];
        $a = $conn->prepare("SELECT spec_field_id FROM subcat_specs WHERE subcategory_id=?");
        $a->bind_param("i", $subcategory_id);
        $a->execute();
        $resA = $a->get_result();
        while($row = $resA->fetch_assoc()) $allowed[(int)$row['spec_field_id']] = true;
        $a->close();

        $specs = $_POST['spec'] ?? [];
        if (is_array($specs) && !empty($allowed)) {
          $ins = $conn->prepare("
            INSERT INTO product_specs(product_id, spec_field_id, value)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE value = VALUES(value)
          ");

          foreach ($specs as $fieldId => $val) {
            $fid = (int)$fieldId;
            if ($fid <= 0) continue;
            if (!isset($allowed[$fid])) continue; // ✅ key fix

            if (is_array($val)) $val = json_encode($val, JSON_UNESCAPED_UNICODE);
            $val = trim((string)$val);
            if ($val === '') continue;

            $ins->bind_param("iis", $pid, $fid, $val);
            $ins->execute();
          }
          $ins->close();
        }

        // images
        $paths = uploadProductImages($pid, 'images');

        if (!empty($paths)) {
          $imgIns = $conn->prepare("
            INSERT INTO product_images(product_id, image_path, is_primary, sort_order)
            VALUES (?, ?, ?, ?)
          ");
          $sort = 0;
          foreach ($paths as $p) {
            $isPrimary = ($sort === 0) ? 1 : 0;
            $imgIns->bind_param("isii", $pid, $p, $isPrimary, $sort);
            $imgIns->execute();
            $sort++;
          }
          $imgIns->close();

          $js = json_encode($paths, JSON_UNESCAPED_SLASHES);
          $up = $conn->prepare("UPDATE products SET images_json=? WHERE id=?");
          $up->bind_param("si", $js, $pid);
          $up->execute();
          $up->close();
        }

        $ok = "Product submitted for admin approval ✅";
      }
    }
  }
}

$titlePage = "Add Product - " . (defined('APP_NAME') ? APP_NAME : 'SmartBuy');
require __DIR__ . '/../../views/layout/header.php';
?>

<div class="container">
  <div class="card">
    <div class="page-title">Add Product</div>

    <?php if($err): ?><div class="alert error"><?= safe($err) ?></div><?php endif; ?>
    <?php if($ok): ?><div class="alert ok"><?= safe($ok) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="addProductForm" class="auth-form">
      <?= csrf_field() ?>

      <div class="f has-value">
        <select name="category_id" id="categorySelect" required>
          <option value="">Select Category</option>
          <?php foreach($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= safe($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <label>Category</label>
      </div>

      <div class="f has-value">
        <select name="subcategory_id" id="subCategorySelect" disabled required>
          <option value="">Select Subcategory</option>
        </select>
        <label>Subcategory</label>
      </div>

      <div class="f">
        <input name="title" placeholder=" " required>
        <label>Product Title</label>
      </div>

      <div class="f">
        <input name="brand" placeholder=" ">
        <label>Brand (optional)</label>
      </div>

      <div class="grid" style="grid-template-columns:repeat(3,1fr)">
        <div class="f">
          <input name="price" type="number" step="0.01" placeholder=" " required>
          <label>Selling Price</label>
        </div>

        <div class="f">
          <input name="mrp" type="number" step="0.01" placeholder=" ">
          <label>MRP (optional)</label>
        </div>

        <div class="f">
          <input name="stock" type="number" placeholder=" " value="1" min="0">
          <label>Stock</label>
        </div>
      </div>

      <div class="divider"></div>

      <div class="page-title">Product Images</div>
      <div class="muted">Upload 1–6 images (JPG/PNG/WebP, max 3MB each). First image becomes primary.</div>
      <div class="f has-value">
        <input type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple>
        <label>Images</label>
      </div>

      <div class="divider"></div>

      <div class="page-title">Specifications</div>
      <div id="specArea" class="card" style="background:transparent">
        <div class="muted">Select category + subcategory to load specs.</div>
      </div>

      <div class="auth-actions">
        <button class="btn" type="submit">Submit for Approval</button>
        <a class="btn ghost" href="<?= BASE_URL ?>seller/dashboard">Back</a>
      </div>
    </form>
  </div>
</div>

<script>
const BASE_URL = "<?= BASE_URL ?>";
const catSel = document.getElementById('categorySelect');
const subSel = document.getElementById('subCategorySelect');
const specArea = document.getElementById('specArea');

catSel.addEventListener('change', async () => {
  subSel.innerHTML = `<option value="">Select Subcategory</option>`;
  subSel.disabled = true;
  specArea.innerHTML = `<div class="muted">Select subcategory to load specs.</div>`;
  if(!catSel.value) return;

  const res = await fetch(`${BASE_URL}?page=api/subcategories&category_id=${encodeURIComponent(catSel.value)}`);
  const data = await res.json().catch(()=>[]);
  data.forEach(s => {
    const opt = document.createElement('option');
    opt.value = s.id;
    opt.textContent = s.name;
    subSel.appendChild(opt);
  });
  subSel.disabled = false;
});

subSel.addEventListener('change', async () => {
  if(!subSel.value){
    specArea.innerHTML = `<div class="muted">Select subcategory to load specs.</div>`;
    return;
  }

  specArea.innerHTML = `<div class="muted">Loading specifications…</div>`;
  const res = await fetch(`${BASE_URL}?page=api/subcat_specs&subcategory_id=${encodeURIComponent(subSel.value)}`);
  const specs = await res.json().catch(()=>[]);

  if(!specs.length){
    specArea.innerHTML = `<div class="muted">No specifications configured for this subcategory yet.</div>`;
    return;
  }

  let html = `<div class="grid" style="grid-template-columns:repeat(2,1fr);gap:12px">`;

  for(const f of specs){
    const req = (parseInt(f.is_required) === 1);
    const label = `${f.label}${req ? " *" : ""}${f.unit ? " ("+f.unit+")" : ""}`;
    html += `<div class="f has-value">
      ${renderInput(f)}
      <label>${escapeHtml(label)}</label>
    </div>`;
  }

  html += `</div>`;
  specArea.innerHTML = html;
});

function renderInput(field){
  const name = `spec[${field.id}]`;
  const type = (field.input_type || 'text').toLowerCase();

  if(type === 'textarea'){
    return `<textarea name="${name}" placeholder=" " rows="3"></textarea>`;
  }

  if(type === 'boolean'){
    return `<select name="${name}">
      <option value="">Select</option>
      <option value="Yes">Yes</option>
      <option value="No">No</option>
    </select>`;
  }

  if(type === 'select'){
    let opts = [];
    try { opts = JSON.parse(field.options_json || "[]"); } catch(e){}
    let out = `<select name="${name}"><option value="">Select</option>`;
    for(const o of opts) out += `<option value="${escapeHtml(o)}">${escapeHtml(o)}</option>`;
    out += `</select>`;
    return out;
  }

  const inputType = (type === 'number') ? 'number' : 'text';
  return `<input name="${name}" type="${inputType}" placeholder=" ">`;
}

function escapeHtml(s){
  return String(s)
    .replaceAll("&","&amp;")
    .replaceAll("<","&lt;")
    .replaceAll(">","&gt;")
    .replaceAll('"',"&quot;");
}
</script>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>