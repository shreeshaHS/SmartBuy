<?php
require_once __DIR__ . '/../../config/bootstrap.php';


// CSRF protection
if (isPost()) { require_csrf(); }
requireLogin();

$email = $_SESSION['user'];
$err=null; $ok=null;

$stmt = db_prepare("SELECT name, phone FROM users WHERE email=? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (isPost()) {
  $name = trim($_POST['name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');

  if ($name === '' || $phone === '') $err = "Name and phone are required.";
  elseif (!preg_match('/^[0-9]{10}$/', $phone)) $err = "Phone must be 10 digits.";
  else {
    $stmt = db_prepare("UPDATE users SET name=?, phone=? WHERE email=?");
    $stmt->bind_param("sss", $name, $phone, $email);
    $stmt->execute();
    $stmt->close();

    $ok = "Profile updated ✅";

    $stmt = db_prepare("SELECT name, phone FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  }
}

$name = $me['name'] ?? 'User';
$initial = strtoupper(substr($name ?: 'U', 0, 1));

$title="Edit Profile - ".APP_NAME;
require __DIR__ . '/../../views/layout/header.php';
?>
<div class="fk-profile">

  <aside class="fx-glass fk-side">
    <div class="fk-user">
      <div class="ava-wrap"><div class="ava"><?= $initial ?></div></div>
      <div class="fk-uinfo">
        <div class="nm"><?= safe($name) ?></div>
        <div class="em"><?= safe($email) ?></div>
        <div class="pill">✨ Edit Mode</div>
      </div>
    </div>

    <nav class="fk-menu">
      <a class="fk-item" href="<?= BASE_URL ?>profile"><span class="fk-ic">👤</span><span style="flex:1;margin-left:10px;font-weight:900">My Profile</span><span class="fk-arr">›</span></a>
      <a class="fk-item active" href="<?= BASE_URL ?>profile/complete"><span class="fk-ic">✏️</span><span style="flex:1;margin-left:10px;font-weight:900">Edit Profile</span><span class="fk-arr">›</span></a>
      <a class="fk-item" href="<?= BASE_URL ?>profile/address"><span class="fk-ic">📦</span><span style="flex:1;margin-left:10px;font-weight:900">Addresses</span><span class="fk-arr">›</span></a>
      <a class="fk-item" href="<?= BASE_URL ?>logout"><span class="fk-ic">🚪</span><span style="flex:1;margin-left:10px;font-weight:900">Logout</span><span class="fk-arr">›</span></a>
    </nav>
  </aside>

  <section class="fx-glass fk-main">
    <div class="fk-head">
      <div>
        <h2>Edit Profile</h2>
        <div class="sub">Only required fields now (stable). We will add gender/dob after your DB confirms.</div>
      </div>
    </div>

    <?php if($err): ?><div class="alert error"><?= safe($err) ?></div><?php endif; ?>
    <?php if($ok): ?><div class="alert ok success-pop"><?= safe($ok) ?></div><?php endif; ?>

    <div class="fk-card">
      <form class="auth-form" method="post">
    <?= csrf_field() ?>
        <div class="f has-value">
          <div class="ic">👤</div>
          <input name="name" placeholder=" " value="<?= safe($me['name'] ?? '') ?>">
          <label>Full Name</label>
        </div>

        <div class="f has-value">
          <div class="ic">📱</div>
          <input name="phone" maxlength="10" placeholder=" " value="<?= safe($me['phone'] ?? '') ?>">
          <label>Mobile Number</label>
        </div>

        <div class="fk-actions">
          <button class="btn" type="submit">Save →</button>
          <a class="btn ghost" href="<?= BASE_URL ?>profile">Back</a>
        </div>
      </form>
    </div>
  </section>

</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
