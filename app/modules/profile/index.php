<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();

$email = $_SESSION['user'];

// Load user basic info (users table must have email,name,phone)
$stmt = db_prepare("SELECT email, name, phone FROM users WHERE email=? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

$name = $me['name'] ?? 'User';
$phone = $me['phone'] ?? '-';
$initial = strtoupper(substr($name ?: 'U', 0, 1));

$title = "My Profile - " . APP_NAME;
require __DIR__ . '/../../views/layout/header.php';
?>
<div class="fk-profile">

  <aside class="fx-glass fk-side">
    <div class="fk-user">
      <div class="ava-wrap">
        <div class="ava"><?= $initial ?></div>
      </div>

      <div class="fk-uinfo">
        <div class="nm"><?= safe($name) ?></div>
        <div class="em"><?= safe($email) ?></div>
        <div class="pill warn">⚡ Profile Panel</div>
      </div>
    </div>

    <nav class="fk-menu">
      <a class="fk-item active" href="<?= BASE_URL ?>profile">
        <span class="fk-ic">👤</span>
        <span style="flex:1;margin-left:10px;font-weight:900">My Profile</span>
        <span class="fk-arr">›</span>
      </a>

      <a class="fk-item" href="<?= BASE_URL ?>profile/complete">
        <span class="fk-ic">✏️</span>
        <span style="flex:1;margin-left:10px;font-weight:900">Edit Profile</span>
        <span class="fk-arr">›</span>
      </a>

      <a class="fk-item" href="<?= BASE_URL ?>profile/address">
        <span class="fk-ic">📦</span>
        <span style="flex:1;margin-left:10px;font-weight:900">Addresses</span>
        <span class="fk-arr">›</span>
      </a>

      <a class="fk-item" href="<?= BASE_URL ?>logout">
        <span class="fk-ic">🚪</span>
        <span style="flex:1;margin-left:10px;font-weight:900">Logout</span>
        <span class="fk-arr">›</span>
      </a>
    </nav>
  </aside>

  <section class="fx-glass fk-main">
    <div class="fk-head">
      <div>
        <h2>Personal Information</h2>
        <div class="sub">Update your details for faster checkout and delivery estimation.</div>
      </div>
      <a class="btn ghost" href="<?= BASE_URL ?>profile/complete">Edit</a>
    </div>

    <div class="fk-card">
      <div class="fk-row"><div class="k">Full Name</div><div class="v"><?= safe($name) ?></div></div>
      <div class="fk-row"><div class="k">Email</div><div class="v"><?= safe($email) ?></div></div>
      <div class="fk-row"><div class="k">Mobile</div><div class="v"><?= safe($phone) ?></div></div>
    </div>

    <div class="fk-actions">
      <a class="btn" href="<?= BASE_URL ?>profile/address">Manage Addresses →</a>
      <a class="btn ghost" href="<?= BASE_URL ?>">Continue Shopping</a>
    </div>
  </section>

</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
