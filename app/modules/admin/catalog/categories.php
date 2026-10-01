<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
global $conn;
requireLogin();
// requireRole('admin'); // enable after admin login works

$err = null; $ok = null;

if (isPost()) {
  $name = trim($_POST['name'] ?? '');
  if ($name === '') $err = "Category name required.";
  else {
    $stmt = $conn->prepare("INSERT INTO categories(name,is_active) VALUES(?,1)");
    $stmt->bind_param("s",$name);
    $stmt->execute();
    $stmt->close();
    $ok = "Category added ✅";
  }
}

$cats = $conn->query("SELECT id,name,is_active FROM categories ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

require __DIR__ . '/../../../views/layout/header.php';
?>
<div class="container">
  <div class="card">
    <div class="page-title">Catalog: Categories</div>

    <?php if($err): ?><div class="alert error"><?= safe($err) ?></div><?php endif; ?>
    <?php if($ok): ?><div class="alert ok"><?= safe($ok) ?></div><?php endif; ?>

    <form method="post" class="row" style="gap:10px">
    <?= csrf_field() ?>
      <input name="name" placeholder="New Category name" style="flex:1;padding:12px;border-radius:14px;border:1px solid var(--stroke);background:rgba(255,255,255,.02);color:inherit">
      <button class="btn small">Add</button>
      <a class="btn small ghost" href="<?= BASE_URL ?>admin/catalog/subcategories">Subcategories →</a>
    </form>

    <div class="divider"></div>

    <table width="100%" class="table">
      <thead><tr><th>ID</th><th>Name</th><th>Active</th></tr></thead>
      <tbody>
      <?php foreach($cats as $c): ?>
        <tr>
          <td><?= (int)$c['id'] ?></td>
          <td><b><?= safe($c['name']) ?></b></td>
          <td><?= (int)$c['is_active'] === 1 ? 'Yes' : 'No' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

  </div>
</div>
<?php require __DIR__ . '/../../../views/layout/footer.php'; ?>
