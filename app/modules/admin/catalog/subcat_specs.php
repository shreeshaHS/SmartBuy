<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
global $conn;

requireLogin();
// requireRole('admin');

$err = null; $ok = null;

// Load categories
$cats = $conn->query("SELECT id,name FROM categories WHERE is_active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$category_id = (int)($_GET['category_id'] ?? 0);
$subcategory_id = (int)($_GET['subcategory_id'] ?? 0);

// Subcategories (for server render fallback)
$subs = [];
if ($category_id > 0) {
  $stmt = $conn->prepare("SELECT id,name FROM subcategories WHERE category_id=? AND is_active=1 ORDER BY name");
  $stmt->bind_param("i", $category_id);
  $stmt->execute();
  $subs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

if (isPost()) {
  $action = $_POST['action'] ?? '';

  if ($action === 'add') {
    $subcategory_id = (int)($_POST['subcategory_id'] ?? 0);
    $spec_field_id  = (int)($_POST['spec_field_id'] ?? 0);
    $sort_order     = (int)($_POST['sort_order'] ?? 0);

    // override required: null/default or 1/0
    $reqMode = $_POST['req_mode'] ?? 'default';
    $is_required_override = null;
    if ($reqMode === 'required') $is_required_override = 1;
    if ($reqMode === 'optional') $is_required_override = 0;

    $options_override_json = trim($_POST['options_override_json'] ?? '');
    $optDb = null;
    if ($options_override_json !== '') {
      if ($options_override_json[0] !== '[') {
        $parts = array_values(array_filter(array_map('trim', explode(',', $options_override_json))));
        $options_override_json = json_encode($parts, JSON_UNESCAPED_UNICODE);
      }
      json_decode($options_override_json, true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $err = "Options override JSON is invalid.";
      } else $optDb = $options_override_json;
    }

    if (!$err) {
      $stmt = $conn->prepare("
        INSERT INTO subcategory_specs(subcategory_id,spec_field_id,sort_order,is_required_override,options_override_json)
        VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
          sort_order=VALUES(sort_order),
          is_required_override=VALUES(is_required_override),
          options_override_json=VALUES(options_override_json)
      ");
      // bind NULL properly:
      $stmt->bind_param("iiiis", $subcategory_id, $spec_field_id, $sort_order, $is_required_override, $optDb);
      $stmt->close();

      $stmt = $conn->prepare("
        INSERT INTO subcategory_specs(subcategory_id,spec_field_id,sort_order,is_required_override,options_override_json)
        VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
          sort_order=VALUES(sort_order),
          is_required_override=VALUES(is_required_override),
          options_override_json=VALUES(options_override_json)
      ");
      // Use set_null via variables:
      $iro = $is_required_override; // can be null
      $ooj = $optDb; // can be null
      $stmt->bind_param("iiiss", $subcategory_id, $spec_field_id, $sort_order, $iro, $ooj);
      $stmt->execute();
      $stmt->close();

      $ok = "Mapped spec to subcategory ✅";
      header("Location: ".BASE_URL."admin/catalog/subcat-specs?category_id=".$category_id."&subcategory_id=".$subcategory_id);
      exit;
    }
  }

  if ($action === 'remove') {
    $subcategory_id = (int)($_POST['subcategory_id'] ?? 0);
    $spec_field_id  = (int)($_POST['spec_field_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM subcategory_specs WHERE subcategory_id=? AND spec_field_id=?");
    $stmt->bind_param("ii", $subcategory_id, $spec_field_id);
    $stmt->execute();
    $stmt->close();
    $ok = "Removed mapping ✅";
    header("Location: ".BASE_URL."admin/catalog/subcat-specs?category_id=".$category_id."&subcategory_id=".$subcategory_id);
    exit;
  }

  if ($action === 'reorder') {
    $subcategory_id = (int)($_POST['subcategory_id'] ?? 0);
    $orderJson = $_POST['order_json'] ?? '[]';
    $arr = json_decode($orderJson, true);
    if (!is_array($arr)) $arr = [];

    $stmt = $conn->prepare("UPDATE subcategory_specs SET sort_order=? WHERE subcategory_id=? AND spec_field_id=?");
    $pos = 1;
    foreach($arr as $fid){
      $fid = (int)$fid;
      if ($fid<=0) continue;
      $stmt->bind_param("iii", $pos, $subcategory_id, $fid);
      $stmt->execute();
      $pos++;
    }
    $stmt->close();
    $ok = "Order saved ✅";
    header("Location: ".BASE_URL."admin/catalog/subcat-specs?category_id=".$category_id."&subcategory_id=".$subcategory_id);
    exit;
  }
}

// Fetch current mappings + effective values
$mappings = [];
$availableSpecs = [];

if ($subcategory_id > 0) {
  // current mappings
  $stmt = $conn->prepare("
    SELECT
      ss.spec_field_id,
      ss.sort_order,
      ss.is_required_override,
      ss.options_override_json,
      f.label,
      f.input_type,
      f.unit,
      f.is_required AS base_required,
      f.options_json AS base_options
    FROM subcategory_specs ss
    JOIN spec_fields f ON f.id = ss.spec_field_id
    WHERE ss.subcategory_id=?
    ORDER BY ss.sort_order ASC, f.sort_order ASC, f.label ASC
  ");
  $stmt->bind_param("i",$subcategory_id);
  $stmt->execute();
  $mappings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  // available specs (active only, not already mapped)
  $stmt = $conn->prepare("
    SELECT id,label,input_type
    FROM spec_fields
    WHERE is_active=1 AND id NOT IN (
      SELECT spec_field_id FROM subcategory_specs WHERE subcategory_id=?
    )
    ORDER BY sort_order ASC, id DESC
  ");
  $stmt->bind_param("i",$subcategory_id);
  $stmt->execute();
  $availableSpecs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

require __DIR__ . '/../../../views/layout/header.php';
?>

<div class="container">
  <div class="card">
    <div class="row" style="justify-content:space-between;align-items:flex-end">
      <div>
        <div class="page-title">Catalog: Map Specs to Subcategories</div>
        <div class="muted">Choose a subcategory → attach specs → override required/options → drag to reorder.</div>
      </div>
      <a class="btn small ghost" href="<?= BASE_URL ?>admin/catalog/spec-fields">Manage Spec Fields →</a>
    </div>

    <?php if($err): ?><div class="alert error"><?= safe($err) ?></div><?php endif; ?>
    <?php if($ok): ?><div class="alert ok"><?= safe($ok) ?></div><?php endif; ?>

    <div class="divider"></div>

    <div class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
      <div class="card" style="background:transparent">
        <div class="page-title">Select Target</div>

        <div class="grid" style="grid-template-columns:1fr 1fr;gap:10px">
          <div class="f has-value">
            <div class="ic">📦</div>
            <select id="catSel">
              <option value="">Select Category</option>
              <?php foreach($cats as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $category_id===(int)$c['id']?'selected':'' ?>>
                  <?= safe($c['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="sel-arrow">▾</span>
            <label>Category</label>
          </div>

          <div class="f has-value">
            <div class="ic">🧩</div>
            <select id="subSel" <?= $category_id>0 ? '' : 'disabled' ?>>
              <option value="">Select Subcategory</option>
              <?php foreach($subs as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= $subcategory_id===(int)$s['id']?'selected':'' ?>>
                  <?= safe($s['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="sel-arrow">▾</span>
            <label>Subcategory</label>
          </div>
        </div>

        <div class="muted" style="font-size:12px;margin-top:10px">
          Tip: After selecting subcategory, you can attach specs and reorder them using drag.
        </div>
      </div>

      <div class="card" style="background:transparent">
        <div class="page-title">Attach a Spec</div>

        <?php if($subcategory_id<=0): ?>
          <div class="muted">Select a subcategory to begin.</div>
        <?php else: ?>
          <form method="post" class="auth-form">
    <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="subcategory_id" value="<?= (int)$subcategory_id ?>">

            <div class="f has-value">
              <div class="ic">⚙️</div>
              <select name="spec_field_id" required>
                <option value="">Select Spec Field</option>
                <?php foreach($availableSpecs as $f): ?>
                  <option value="<?= (int)$f['id'] ?>"><?= safe($f['label']) ?> (<?= safe($f['input_type']) ?>)</option>
                <?php endforeach; ?>
              </select>
              <span class="sel-arrow">▾</span>
              <label>Spec Field</label>
            </div>

            <div class="grid" style="grid-template-columns:1fr 1fr;gap:10px">
              <div class="f has-value">
                <div class="ic">✅</div>
                <select name="req_mode">
                  <option value="default">Default</option>
                  <option value="required">Force Required</option>
                  <option value="optional">Force Optional</option>
                </select>
                <span class="sel-arrow">▾</span>
                <label>Required Override</label>
              </div>

              <div class="f">
                <div class="ic">↕️</div>
                <input name="sort_order" type="number" placeholder=" " value="0">
                <label>Sort Order (optional)</label>
              </div>
            </div>

            <div class="f">
              <div class="ic">🧾</div>
              <textarea name="options_override_json" placeholder=" " style="width:100%;min-height:90px;border-radius:16px;border:1px solid var(--stroke);background:rgba(255,255,255,.02);color:inherit;padding:14px 14px 14px 44px"></textarea>
              <label>Options Override (JSON or comma list)</label>
              <div class="muted" style="font-size:12px;margin-top:6px">
                Leave empty to use spec’s default options.
              </div>
            </div>

            <button class="btn" type="submit">Attach Spec</button>
          </form>
        <?php endif; ?>

      </div>
    </div>

    <div class="divider"></div>

    <div class="page-title">Current Mapping</div>

    <?php if($subcategory_id<=0): ?>
      <div class="muted">Select a subcategory to see mapping.</div>
    <?php else: ?>
      <div class="muted" style="margin-bottom:10px">Drag rows to reorder, then click “Save Order”.</div>

      <form method="post" class="row" style="gap:10px;flex-wrap:wrap;margin-bottom:10px">
    <?= csrf_field() ?>
        <input type="hidden" name="action" value="reorder">
        <input type="hidden" name="subcategory_id" value="<?= (int)$subcategory_id ?>">
        <input type="hidden" name="order_json" id="orderJson" value="[]">
        <button class="btn small" type="submit" onclick="document.getElementById('orderJson').value = JSON.stringify(getOrder())">Save Order</button>
        <span class="muted" id="dragHint"></span>
      </form>

      <div style="border:1px solid var(--stroke);border-radius:16px;overflow:hidden">
        <table width="100%" class="table" style="margin:0">
          <thead>
            <tr>
              <th style="width:40px">↕</th>
              <th>Spec</th>
              <th>Type</th>
              <th>Required</th>
              <th>Options</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="mapBody">
            <?php foreach($mappings as $m): ?>
              <?php
                $effectiveRequired = $m['base_required'];
                if ($m['is_required_override'] !== null) $effectiveRequired = (int)$m['is_required_override'];
                $effectiveOptions = $m['base_options'];
                if (!empty($m['options_override_json'])) $effectiveOptions = $m['options_override_json'];
              ?>
              <tr class="dragRow" draggable="true" data-fid="<?= (int)$m['spec_field_id'] ?>">
                <td style="cursor:grab;opacity:.8">☰</td>
                <td>
                  <b><?= safe($m['label']) ?></b>
                  <?php if(!empty($m['unit'])): ?><span class="muted"> (<?= safe($m['unit']) ?>)</span><?php endif; ?>
                  <div class="muted" style="font-size:12px">Order: <?= (int)$m['sort_order'] ?></div>
                </td>
                <td><?= safe($m['input_type']) ?></td>
                <td><?= (int)$effectiveRequired===1 ? 'Yes' : 'No' ?></td>
                <td class="muted" style="max-width:340px">
                  <?php if($m['input_type']==='select'): ?>
                    <?= safe($effectiveOptions ?? '—') ?>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
                <td>
                  <form method="post" onsubmit="return confirm('Remove this spec from subcategory?')">
    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="subcategory_id" value="<?= (int)$subcategory_id ?>">
                    <input type="hidden" name="spec_field_id" value="<?= (int)$m['spec_field_id'] ?>">
                    <button class="btn small ghost" type="submit">Remove</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>

            <?php if(empty($mappings)): ?>
              <tr><td colspan="6" class="muted">No specs mapped yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  </div>
</div>

<script>
const BASE_URL = "<?= BASE_URL ?>";
const catSel = document.getElementById('catSel');
const subSel = document.getElementById('subSel');

catSel?.addEventListener('change', async () => {
  const cid = catSel.value;
  subSel.innerHTML = `<option value="">Select Subcategory</option>`;
  subSel.disabled = true;

  if(!cid){
    window.location = `${BASE_URL}admin/catalog/subcat-specs`;
    return;
  }

  const res = await fetch(`${BASE_URL}api/subcategories?category_id=${cid}`);
  const data = await res.json();
  data.forEach(s => {
    const opt = document.createElement('option');
    opt.value = s.id;
    opt.textContent = s.name;
    subSel.appendChild(opt);
  });
  subSel.disabled = false;

  window.location = `${BASE_URL}admin/catalog/subcat-specs?category_id=${cid}`;
});

subSel?.addEventListener('change', () => {
  const cid = catSel.value;
  const sid = subSel.value;
  if(!sid) return;
  window.location = `${BASE_URL}admin/catalog/subcat-specs?category_id=${cid}&subcategory_id=${sid}`;
});

// Drag reorder
const tbody = document.getElementById('mapBody');
let dragEl = null;

tbody?.addEventListener('dragstart', (e) => {
  const tr = e.target.closest('.dragRow');
  if(!tr) return;
  dragEl = tr;
  tr.style.opacity = '0.5';
});

tbody?.addEventListener('dragend', (e) => {
  const tr = e.target.closest('.dragRow');
  if(tr) tr.style.opacity = '1';
  dragEl = null;
});

tbody?.addEventListener('dragover', (e) => {
  e.preventDefault();
  const tr = e.target.closest('.dragRow');
  if(!tr || tr === dragEl) return;

  const rect = tr.getBoundingClientRect();
  const after = (e.clientY - rect.top) > (rect.height / 2);

  if(after){
    tr.after(dragEl);
  } else {
    tr.before(dragEl);
  }
  document.getElementById('dragHint').textContent = 'Order changed — click “Save Order”';
});

function getOrder(){
  const rows = Array.from(document.querySelectorAll('.dragRow'));
  return rows.map(r => parseInt(r.dataset.fid, 10)).filter(Boolean);
}
</script>

<?php require __DIR__ . '/../../../views/layout/footer.php'; ?>
