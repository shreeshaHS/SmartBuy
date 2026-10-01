<?php
require_once __DIR__ . '/../../config/bootstrap.php';
global $conn;

// Filters
$q     = trim($_GET['q'] ?? '');
$cat   = (int)($_GET['category_id'] ?? 0);
$sub   = (int)($_GET['subcategory_id'] ?? 0);
$brand = trim($_GET['brand'] ?? '');
$min   = ($_GET['min'] ?? '') !== '' ? (float)$_GET['min'] : null;
$max   = ($_GET['max'] ?? '') !== '' ? (float)$_GET['max'] : null;
$sort  = trim($_GET['sort'] ?? 'relevance');

// Sort whitelist
$orderBy = "p.id DESC";
if ($sort === 'plh') $orderBy = "p.price ASC";
if ($sort === 'phl') $orderBy = "p.price DESC";
if ($sort === 'new') $orderBy = "p.id DESC";

// ✅ session pincode (used for ETA on cards)
$userEmail = $_SESSION['user_email'] ?? $_SESSION['user'] ?? '';
$shipPin = $_SESSION['ship_pin'] ?? '';
if ($shipPin === '' && $userEmail !== '') {
  $stPin = $conn->prepare("SELECT pincode FROM user_addresses WHERE user_email=? ORDER BY is_default DESC, id DESC LIMIT 1");
  $stPin->bind_param("s", $userEmail);
  $stPin->execute();
  $rowPin = $stPin->get_result()->fetch_assoc();
  $stPin->close();
  $shipPin = (string)($rowPin['pincode'] ?? '');
}

// Precompute delivery quote (same for all cards because pincode same)
$deliveryMsgGlobal = '';
$deliveryOkGlobal = false;
if (preg_match('/^\d{6}$/', $shipPin)) {
  // use a small cart total just to compute fee message; ETA comes from pincode days
  $qq = delivery_quote($conn, $shipPin, 0.0, $userEmail);
  if ($qq !== null) {
    $deliveryOkGlobal = true;
    $deliveryMsgGlobal = "Deliver to $shipPin";
  } else {
    $deliveryMsgGlobal = "Not deliverable to $shipPin";
  }
} else {
  $deliveryMsgGlobal = "Set pincode to see delivery date";
}

// Build SQL dynamically
$sql = "
  SELECT
    p.id,
    COALESCE(p.title,p.name) AS pname,
    p.slug,p.brand,p.price,p.mrp,p.images_json,p.stock,
    c.name AS category_name,
    s.name AS subcategory_name
  FROM products p
  LEFT JOIN categories c ON c.id=p.category_id
  LEFT JOIN subcategories s ON s.id=p.subcategory_id
  WHERE p.status='live'
";

$params = [];
$types  = "";

if ($q !== '') {
  $sql .= " AND (COALESCE(p.title,p.name) LIKE ? OR p.brand LIKE ?)";
  $like = "%{$q}%";
  $params[] = $like; $types .= "s";
  $params[] = $like; $types .= "s";
}
if ($cat > 0) {
  $sql .= " AND p.category_id=?";
  $params[] = $cat; $types .= "i";
}
if ($sub > 0) {
  $sql .= " AND p.subcategory_id=?";
  $params[] = $sub; $types .= "i";
}
if ($brand !== '') {
  $sql .= " AND p.brand=?";
  $params[] = $brand; $types .= "s";
}
if ($min !== null) {
  $sql .= " AND p.price>=?";
  $params[] = $min; $types .= "d";
}
if ($max !== null) {
  $sql .= " AND p.price<=?";
  $params[] = $max; $types .= "d";
}

$sql .= " ORDER BY {$orderBy} LIMIT 120";

// Sidebar data
$cats = $conn->query("SELECT id,name FROM categories WHERE is_active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$subs = [];
if ($cat > 0) {
  $stmt = $conn->prepare("SELECT id,name FROM subcategories WHERE category_id=? AND is_active=1 ORDER BY name");
  $stmt->bind_param("i", $cat);
  $stmt->execute();
  $subs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

$brands = $conn->query("
  SELECT DISTINCT brand
  FROM products
  WHERE status='live' AND brand IS NOT NULL AND brand<>''
  ORDER BY brand
  LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

// Execute products query
$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function buildLink($overrides){
  $qs = $_GET;
  foreach($overrides as $k=>$v){
    if($v === null || $v === '') unset($qs[$k]);
    else $qs[$k] = $v;
  }
  return BASE_URL . 'products?' . http_build_query($qs);
}

$title = "Shop - " . APP_NAME;
require __DIR__ . '/../../views/layout/header.php';
?>

<div class="container">

  <div class="fk-breadcrumb muted">
    <a href="<?= BASE_URL ?>">Home</a> <span>›</span> <b>Products</b>
    <?php if($cat>0): ?>
      <span>›</span> <span><?= safe(($rows[0]['category_name'] ?? '')) ?></span>
    <?php endif; ?>
  </div>

  <!-- ✅ TOP PINCODE BAR -->
  <div class="card" style="margin-bottom:12px">
    <div class="row" style="justify-content:space-between;gap:10px;flex-wrap:wrap">
      <div class="muted"><?= safe($deliveryMsgGlobal) ?></div>
      <form method="post" action="<?= BASE_URL ?>?page=api/set-pincode&slug=" style="display:flex;gap:8px;flex-wrap:wrap">
        <?= csrf_field() ?>
        <input name="pincode" maxlength="6" placeholder="Enter pincode" style="max-width:160px">
        <button class="btn small" type="submit">Set</button>
      </form>
    </div>
  </div>

  <div class="fk-layout">

    <aside class="fk-filters card">
      <div class="fk-filter-head">
        <div class="fk-filter-title">Filters</div>
        <a class="fk-clear" href="<?= BASE_URL ?>products">Clear All</a>
      </div>
      <div class="divider"></div>

      <form method="get" id="filterForm">
        <input type="hidden" name="q" value="<?= safe($q) ?>">
        <input type="hidden" name="sort" value="<?= safe($sort) ?>">

        <div class="fk-sec">
          <div class="fk-sec-title">Category</div>
          <select class="fk-in" name="category_id" id="catSel">
            <option value="">All Categories</option>
            <?php foreach($cats as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $cat===(int)$c['id']?'selected':'' ?>><?= safe($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="fk-sec">
          <div class="fk-sec-title">Subcategory</div>
          <select class="fk-in" name="subcategory_id" id="subSel" <?= $cat>0 ? '' : 'disabled' ?>>
            <option value="">All</option>
            <?php foreach($subs as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= $sub===(int)$s['id']?'selected':'' ?>><?= safe($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="fk-sec">
          <div class="fk-sec-title">Brand</div>
          <select class="fk-in" name="brand">
            <option value="">All</option>
            <?php foreach($brands as $b): $bv = $b['brand'] ?? ''; if($bv==='') continue; ?>
              <option value="<?= safe($bv) ?>" <?= $brand===$bv?'selected':'' ?>><?= safe($bv) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="fk-sec">
          <div class="fk-sec-title">Price</div>
          <div class="fk-two">
            <input class="fk-in" name="min" type="number" step="0.01" placeholder="Min" value="<?= $min!==null ? safe((string)$min) : '' ?>">
            <input class="fk-in" name="max" type="number" step="0.01" placeholder="Max" value="<?= $max!==null ? safe((string)$max) : '' ?>">
          </div>
        </div>

        <button class="btn" style="width:100%" type="submit">Apply Filters</button>
      </form>
    </aside>

    <main>

      <div class="fk-sort card">
        <div class="fk-sort-left">
          <b><?= count($rows) ?> results</b>
          <span class="muted">for</span>
          <span class="fk-query">"<?= safe($q !== '' ? $q : 'all products') ?>"</span>
        </div>
        <div class="fk-sort-right">
          <span class="muted">Sort By</span>
          <a class="fk-sort-link <?= $sort==='relevance'?'active':'' ?>" href="<?= buildLink(['sort'=>'relevance']) ?>">Relevance</a>
          <a class="fk-sort-link <?= $sort==='plh'?'active':'' ?>" href="<?= buildLink(['sort'=>'plh']) ?>">Price -- Low to High</a>
          <a class="fk-sort-link <?= $sort==='phl'?'active':'' ?>" href="<?= buildLink(['sort'=>'phl']) ?>">Price -- High to Low</a>
          <a class="fk-sort-link <?= $sort==='new'?'active':'' ?>" href="<?= buildLink(['sort'=>'new']) ?>">Newest First</a>
        </div>
      </div>

      <div class="divider"></div>

      <?php if(empty($rows)): ?>
        <div class="card">
          <div class="page-title">No products found</div>
          <div class="muted">Try changing filters or search.</div>
        </div>
      <?php else: ?>

        <div class="fk-grid">
          <?php foreach($rows as $p): ?>
            <?php
              $imgs = [];
              if (!empty($p['images_json'])) {
                $t = json_decode($p['images_json'], true);
                if (is_array($t)) $imgs = $t;
              }
              $img = $imgs[0] ?? '';
              $mrp = (float)($p['mrp'] ?? 0);
              $price = (float)($p['price'] ?? 0);
              $off = 0;
              if ($mrp > 0 && $price > 0 && $mrp > $price) $off = (int)round((($mrp-$price)/$mrp)*100);
              $oos = ((int)($p['stock'] ?? 0) <= 0);

              // ✅ ETA on card (only if pincode is set + serviceable)
              $etaLine = '';
              if (preg_match('/^\d{6}$/', $shipPin)) {
                $qq = delivery_quote($conn, $shipPin, max(0.0, $price), $userEmail);
                if ($qq !== null) {
                  $by = date('D, d M', strtotime($qq['eta']));
                  $etaLine = "Arrives by $by";
                } else {
                  $etaLine = "Not deliverable to $shipPin";
                }
              } else {
                $etaLine = "Set pincode for ETA";
              }
            ?>

            <div class="fk-card">
              <a class="fk-img" href="<?= BASE_URL ?>product?slug=<?= urlencode($p['slug']) ?>">
                <?php if($img): ?>
                  <img src="<?= safe($img) ?>" alt="">
                <?php else: ?>
                  <div class="fk-img-ph">No image</div>
                <?php endif; ?>
              </a>

              <div class="fk-meta">
                <a class="fk-title line-clamp" href="<?= BASE_URL ?>product?slug=<?= urlencode($p['slug']) ?>"><?= safe($p['pname']) ?></a>

                <div class="fk-sub muted">
                  <?= safe($p['brand'] ?? '') ?>
                  <?php if(!empty($p['subcategory_name'])): ?> • <?= safe($p['subcategory_name']) ?><?php endif; ?>
                </div>

                <div class="fk-price-row">
                  <div class="fk-price">₹<?= number_format($price,2) ?></div>
                  <?php if($mrp>0 && $mrp>$price): ?>
                    <div class="fk-mrp">₹<?= number_format($mrp,2) ?></div>
                    <div class="fk-off"><?= $off ?>% off</div>
                  <?php endif; ?>
                </div>

                <!-- ✅ ETA LINE (downside like you asked) -->
                <div class="muted" style="font-size:12px;margin-top:6px">
                  🚚 <?= safe($etaLine) ?>
                </div>

                <div class="fk-actions">
                  <button class="btn small add-cart" data-id="<?= (int)$p['id'] ?>" <?= $oos?'disabled':'' ?>>
                    <?= $oos ? 'Out of stock' : '🛒 Add' ?>
                  </button>
                  <a class="btn small ghost" href="<?= BASE_URL ?>product?slug=<?= urlencode($p['slug']) ?>">View</a>
                </div>

              </div>
            </div>

          <?php endforeach; ?>
        </div>

      <?php endif; ?>

    </main>

  </div>

</div>

<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';
const BASE_URL = "<?= BASE_URL ?>";

const catSel = document.getElementById('catSel');
const subSel = document.getElementById('subSel');

catSel?.addEventListener('change', async () => {
  const cid = catSel.value;
  subSel.innerHTML = `<option value="">All</option>`;
  subSel.disabled = true;

  if(!cid){
    document.getElementById('filterForm').submit();
    return;
  }

  const res = await fetch(`${BASE_URL}api/subcategories?category_id=${encodeURIComponent(cid)}`);
  const data = await res.json().catch(()=>[]);
  data.forEach(s => {
    const opt = document.createElement('option');
    opt.value = s.id;
    opt.textContent = s.name;
    subSel.appendChild(opt);
  });
  subSel.disabled = false;
});

document.querySelectorAll('.add-cart').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const id = btn.dataset.id;
    const old = btn.textContent;
    btn.textContent = 'Adding…';

    const res = await fetch(`${BASE_URL}api/cart-add`, {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-TOKEN':CSRF_TOKEN},
      body:`id=${encodeURIComponent(id)}&qty=1`
    });

    const data = await res.json().catch(()=>({ok:false}));

    if(data.ok){
      btn.textContent = '✔ Added';
      const el = document.getElementById('cartCount');
      if(el) el.textContent = data.count;
    } else if(data.oos) {
      btn.textContent = 'Out of stock';
      alert('Out of stock');
    } else {
      btn.textContent = 'Error';
    }

    setTimeout(()=>btn.textContent = old, 1200);
  });
});
</script>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>