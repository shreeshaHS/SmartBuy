<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
global $conn;
function makeSlug(string $s): string {
  $s = strtolower(trim($s));
  $s = preg_replace('/[^a-z0-9]+/i', '-', $s);
  $s = trim($s, '-');
  return $s !== '' ? $s : 'subcat';
}

function uniqueSlug(mysqli $conn, string $base): string {
  $slug = $base;
  $i = 1;
  while (true) {
    $stmt = $conn->prepare("SELECT 1 FROM subcategories WHERE slug=? LIMIT 1");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_row();
    $stmt->close();
    if (!$exists) return $slug;
    $i++;
    $slug = $base . '-' . $i;
  }
}

requireLogin();
// requireRole('admin');

$err=null; $ok=null;

$cats = $conn->query("SELECT id,name FROM categories WHERE is_active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

if (isPost()) {
  $category_id = (int)($_POST['category_id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  if ($category_id<=0 || $name==='') $err="Select category + enter subcategory name.";
  else {

    // ✅ generate unique slug
    $baseSlug = makeSlug($name);
    $slug = uniqueSlug($conn, $baseSlug);

    $stmt = $conn->prepare("INSERT INTO subcategories(category_id,name,slug,is_active) VALUES (?,?,?,1)");
    $stmt->bind_param("iss", $category_id, $name, $slug);
    $stmt->execute();
    $stmt->close();

    $ok="Subcategory added ✅";
  }
}


$rows = $conn->query("
  SELECT s.id,s.name,s.is_active,c.name AS category
  FROM subcategories s
  JOIN categories c ON c.id=s.category_id
  ORDER BY s.id DESC
")->fetch_all(MYSQLI_ASSOC);

require __DIR__ . '/../../../views/layout/header.php';
?>
<div class="container">
  <div class="card">
    <div class="page-title">Catalog: Subcategories</div>

    <?php if($err): ?><div class="alert error"><?= safe($err) ?></div><?php endif; ?>
    <?php if($ok): ?><div class="alert ok"><?= safe($ok) ?></div><?php endif; ?>

    <form method="post" class="grid" style="grid-template-columns:1fr 1.5fr auto;gap:10px;align-items:end">
    <?= csrf_field() ?>
      <div class="f has-value">
        <select name="category_id" required>
          <option value="">Select Category</option>
          <?php foreach($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= safe($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <label>Category</label>
      </div>

      <div class="f">
        <input name="name" placeholder=" " required>
        <label>Subcategory name</label>
      </div>

      <button class="btn small">Add</button>
    </form>

    <div class="divider"></div>

    <table width="100%" class="table">
      <thead><tr><th>ID</th><th>Category</th><th>Subcategory</th><th>Active</th></tr></thead>
      <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= safe($r['category']) ?></td>
          <td><b><?= safe($r['name']) ?></b></td>
          <td><?= (int)$r['is_active']===1?'Yes':'No' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

  </div>
</div>
<?php require __DIR__ . '/../../../views/layout/footer.php'; ?>
