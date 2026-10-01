<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
global $conn;

requireLogin();
// requireRole('admin'); // enable after admin login is fully final

$err = null; $ok = null;

function makeKey(string $label): string {
  $k = strtolower(trim($label));
  $k = preg_replace('/[^a-z0-9]+/i', '_', $k);
  $k = trim($k, '_');
  return $k !== '' ? $k : 'spec';
}

function uniqueFieldKey(mysqli $conn, string $base, ?int $ignoreId=null): string {
  $key = $base; $i = 1;
  while (true) {
    if ($ignoreId) {
      $stmt = $conn->prepare("SELECT 1 FROM spec_fields WHERE field_key=? AND id<>? LIMIT 1");
      $stmt->bind_param("si", $key, $ignoreId);
    } else {
      $stmt = $conn->prepare("SELECT 1 FROM spec_fields WHERE field_key=? LIMIT 1");
      $stmt->bind_param("s", $key);
    }
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_row();
    $stmt->close();
    if (!$exists) return $key;
    $i++;
    $key = $base . '_' . $i;
  }
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;

if ($editId > 0) {
  $stmt = $conn->prepare("SELECT * FROM spec_fields WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $editId);
  $stmt->execute();
  $edit = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

if (isPost()) {
  $action = $_POST['action'] ?? 'save';

  if ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $to = (int)($_POST['to'] ?? 1);
    if ($id > 0) {
      $stmt = $conn->prepare("UPDATE spec_fields SET is_active=? WHERE id=?");
      $stmt->bind_param("ii", $to, $id);
      $stmt->execute();
      $stmt->close();
      $ok = "Updated ✅";
    }
  }

  if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $label = trim($_POST['label'] ?? '');
    $input_type = trim($_POST['input_type'] ?? 'text');
    $unit = trim($_POST['unit'] ?? '');
    $options_json = trim($_POST['options_json'] ?? '');
    $is_required = isset($_POST['is_required']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($label === '') {
      $err = "Label is required.";
    } else {
      // normalize options_json: if blank => NULL
      $optionsJsonDb = null;
      if ($options_json !== '') {
        // allow user to paste comma list too
        if ($options_json[0] !== '[') {
          $parts = array_values(array_filter(array_map('trim', explode(',', $options_json))));
          $options_json = json_encode($parts, JSON_UNESCAPED_UNICODE);
        }
        json_decode($options_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
          $err = "Options JSON is invalid. Use [\"A\",\"B\"] or comma list.";
        } else {
          $optionsJsonDb = $options_json;
        }
      }

      if (!$err) {
        $baseKey = makeKey($label);
        $field_key = uniqueFieldKey($conn, $baseKey, $id > 0 ? $id : null);

        if ($id > 0) {
          $stmt = $conn->prepare("UPDATE spec_fields
            SET label=?, field_key=?, input_type=?, unit=?, options_json=?, is_required=?, is_active=?, sort_order=?
            WHERE id=?");
          $stmt->bind_param(
            "sssssiiii",
            $label, $field_key, $input_type, $unit,
            $optionsJsonDb,
            $is_required, $is_active, $sort_order,
            $id
          );
          $stmt->execute();
          $stmt->close();
          $ok = "Spec updated ✅";
          header("Location: ".BASE_URL."admin/catalog/spec-fields");
          exit;
        } else {
          $stmt = $conn->prepare("INSERT INTO spec_fields(label,field_key,input_type,unit,options_json,is_required,is_active,sort_order)
                                  VALUES (?,?,?,?,?,?,?,?)");
          $stmt->bind_param(
            "sssssiis",
            $label, $field_key, $input_type, $unit, $optionsJsonDb,
            $is_required, $is_active, $sort_order
          );
          // note: last param type mismatch if bind "s"; easiest: rebind correctly
          $stmt->close();

          $stmt = $conn->prepare("INSERT INTO spec_fields(label,field_key,input_type,unit,options_json,is_required,is_active,sort_order)
                                  VALUES (?,?,?,?,?,?,?,?)");
          $stmt->bind_param(
            "sssssiii",
            $label, $field_key, $input_type, $unit, $optionsJsonDb,
            $is_required, $is_active, $sort_order
          );
          $stmt->execute();
          $stmt->close();
          $ok = "Spec added ✅";
        }
      }
    }
  }
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
  $like = "%{$q}%";
  $stmt = $conn->prepare("SELECT * FROM spec_fields WHERE label LIKE ? OR field_key LIKE ? ORDER BY is_active DESC, sort_order ASC, id DESC");
  $stmt->bind_param("ss", $like, $like);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
} else {
  $rows = $conn->query("SELECT * FROM spec_fields ORDER BY is_active DESC, sort_order ASC, id DESC")->fetch_all(MYSQLI_ASSOC);
}

require __DIR__ . '/../../../views/layout/header.php';
?>

<div class="container">
  <div class="card">
    <div class="row" style="justify-content:space-between;align-items:flex-end">
      <div>
        <div class="page-title">Catalog: Specification Fields</div>
        <div class="muted">Create global specs (RAM, Warranty, Screen Size). Later you map them to subcategories.</div>
      </div>
      <div class="row">
        <a class="btn small ghost" href="<?= BASE_URL ?>admin/catalog/subcat-specs">Map to Subcategories →</a>
      </div>
    </div>

    <?php if($err): ?><div class="alert error"><?= safe($err) ?></div><?php endif; ?>
    <?php if($ok): ?><div class="alert ok"><?= safe($ok) ?></div><?php endif; ?>

    <div class="divider"></div>

    <form method="get" class="row" style="gap:10px">
      <input name="q" value="<?= safe($q) ?>" placeholder="Search specs (Brand, RAM...)"
             style="flex:1;padding:12px;border-radius:14px;border:1px solid var(--stroke);background:rgba(255,255,255,.02);color:inherit">
      <button class="btn small ghost">Search</button>
      <a class="btn small ghost" href="<?= BASE_URL ?>admin/catalog/spec-fields">Reset</a>
    </form>

    <div class="divider"></div>

    <div class="grid" style="grid-template-columns:1fr 1.2fr;gap:12px">
      <div class="card" style="background:transparent">
        <div class="page-title"><?= $edit ? 'Edit Spec' : 'Add Spec' ?></div>

        <form method="post" class="auth-form">
    <?= csrf_field() ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">

          <div class="f">
            <div class="ic">🏷️</div>
            <input name="label" placeholder=" " value="<?= safe($edit['label'] ?? '') ?>" required>
            <label>Label *</label>
          </div>

          <div class="grid" style="grid-template-columns:1fr 1fr;gap:10px">
            <div class="f has-value">
              <div class="ic">⚙️</div>
              <select name="input_type">
                <?php
                $types = ['text','number','select','boolean','textarea'];
                $cur = $edit['input_type'] ?? 'text';
                foreach($types as $t){
                  $sel = ($cur===$t)?'selected':'';
                  echo "<option value='".safe($t)."' $sel>".safe(strtoupper($t))."</option>";
                }
                ?>
              </select>
              <span class="sel-arrow">▾</span>
              <label>Input Type</label>
            </div>

            <div class="f">
              <div class="ic">📏</div>
              <input name="unit" placeholder=" " value="<?= safe($edit['unit'] ?? '') ?>">
              <label>Unit (optional)</label>
            </div>
          </div>

          <div class="f">
            <div class="ic">🧾</div>
            <textarea name="options_json" placeholder=" " style="width:100%;min-height:100px;border-radius:16px;border:1px solid var(--stroke);background:rgba(255,255,255,.02);color:inherit;padding:14px 14px 14px 44px"><?= safe($edit['options_json'] ?? '') ?></textarea>
            <label>Options (JSON array or comma list)</label>
            <div class="muted" style="font-size:12px;margin-top:6px">
              Example: <b>["HD","Full HD","4K"]</b> or <b>HD, Full HD, 4K</b>
            </div>
          </div>

          <div class="grid" style="grid-template-columns:1fr 1fr 1fr;gap:10px">
            <div class="f">
              <div class="ic">↕️</div>
              <input name="sort_order" type="number" placeholder=" " value="<?= (int)($edit['sort_order'] ?? 0) ?>">
              <label>Sort Order</label>
            </div>

            <label class="row" style="gap:10px;align-items:center;border:1px solid var(--stroke);border-radius:16px;padding:12px;background:rgba(255,255,255,.02)">
              <input type="checkbox" name="is_required" <?= (int)($edit['is_required'] ?? 0)===1?'checked':'' ?>>
              <span>Required</span>
            </label>

            <label class="row" style="gap:10px;align-items:center;border:1px solid var(--stroke);border-radius:16px;padding:12px;background:rgba(255,255,255,.02)">
              <input type="checkbox" name="is_active" <?= ($edit ? ((int)$edit['is_active']===1) : true) ? 'checked' : '' ?>>
              <span>Active</span>
            </label>
          </div>

          <div class="auth-actions">
            <button class="btn" type="submit"><?= $edit ? 'Save Changes' : 'Add Spec' ?></button>
            <?php if($edit): ?>
              <a class="btn ghost" href="<?= BASE_URL ?>admin/catalog/spec-fields">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="card" style="background:transparent">
        <div class="page-title">All Specs (<?= count($rows) ?>)</div>

        <div style="max-height:560px;overflow:auto;border-radius:16px;border:1px solid var(--stroke)">
          <table width="100%" class="table" style="margin:0">
            <thead>
              <tr>
                <th>ID</th><th>Label</th><th>Type</th><th>Req</th><th>Active</th><th>Order</th><th>Action</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($rows as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td>
                  <b><?= safe($r['label']) ?></b><br>
                  <span class="muted" style="font-size:12px"><?= safe($r['field_key']) ?></span>
                </td>
                <td><?= safe($r['input_type']) ?></td>
                <td><?= (int)$r['is_required']===1 ? 'Yes' : 'No' ?></td>
                <td><?= (int)$r['is_active']===1 ? 'Yes' : 'No' ?></td>
                <td><?= (int)$r['sort_order'] ?></td>
                <td class="row" style="gap:8px">
                  <a class="btn small" href="<?= BASE_URL ?>admin/catalog/spec-fields?edit=<?= (int)$r['id'] ?>">Edit</a>
                  <form method="post" style="display:inline">
    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="to" value="<?= (int)$r['is_active']===1 ? 0 : 1 ?>">
                    <button class="btn small ghost" type="submit"><?= (int)$r['is_active']===1 ? 'Disable' : 'Enable' ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if(empty($rows)): ?>
              <tr><td colspan="7" class="muted">No specs found.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>

  </div>
</div>

<?php require __DIR__ . '/../../../views/layout/footer.php'; ?>
