<?php
require_once __DIR__ . '/../../config/bootstrap.php';


// CSRF protection
if (isPost()) { require_csrf(); }
requireLogin();

$email = $_SESSION['user'];
$err=null; $ok=null;

// load user
$stmt = db_prepare("SELECT name, phone FROM users WHERE email=? LIMIT 1");
$stmt->bind_param("s",$email);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

$name = $me['name'] ?? 'User';
$initial = strtoupper(substr($name ?: 'U', 0, 1));

if (isPost()) {
  $n  = trim($_POST['name'] ?? '');
  $ph = trim($_POST['phone'] ?? '');
  $l1 = trim($_POST['line1'] ?? '');
  $l2 = trim($_POST['line2'] ?? '');
  $lm = trim($_POST['landmark'] ?? '');
  $ct = trim($_POST['city'] ?? '');
  $st = trim($_POST['state'] ?? '');
  $pin= trim($_POST['pincode'] ?? '');
  $type = $_POST['address_type'] ?? 'home';
  $is_default = isset($_POST['is_default']) ? 1 : 0;

  if ($n==='' || $ph==='' || $l1==='' || $ct==='' || $st==='' || $pin==='') $err="Fill all required fields.";
  elseif (!preg_match('/^[0-9]{10}$/',$ph)) $err="Phone must be 10 digits.";
  elseif (!preg_match('/^[0-9]{6}$/',$pin)) $err="Pincode must be 6 digits.";
  elseif (!in_array($type, ['home','work','other'], true)) $err="Invalid address type.";
  else {
    if ($is_default) {
      $stmt = db_prepare("UPDATE user_addresses SET is_default=0 WHERE user_email=?");
      $stmt->bind_param("s",$email);
      $stmt->execute();
      $stmt->close();
    }

    // IMPORTANT: use user_email (FK column)
    $stmt = db_prepare("INSERT INTO user_addresses
      (user_email, name, phone, line1, line2, landmark, city, state, pincode, address_type, is_default, created_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,?, NOW())");

    // Keep 'email' column synced too (even if not FK)
    $stmt->bind_param("ssssssssssi",
      $email, $n, $ph, $l1, $l2, $lm, $ct, $st, $pin, $type, $is_default
    );
    $stmt->execute();
    $stmt->close();

    $ok = "Address saved ✅";
  }
}

// list addresses
$stmt = db_prepare("SELECT * FROM user_addresses WHERE user_email=? ORDER BY is_default DESC, id DESC");
$stmt->bind_param("s",$email);
$stmt->execute();
$addresses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$title="Addresses - ".APP_NAME;
require __DIR__ . '/../../views/layout/header.php';
?>
<div class="fk-profile">

  <aside class="fx-glass fk-side">
    <div class="fk-user">
      <div class="ava-wrap"><div class="ava"><?= $initial ?></div></div>
      <div class="fk-uinfo">
        <div class="nm"><?= safe($name) ?></div>
        <div class="em"><?= safe($email) ?></div>
        <div class="pill">📦 Address Hub</div>
      </div>
    </div>

    <nav class="fk-menu">
      <a class="fk-item" href="<?= BASE_URL ?>profile"><span class="fk-ic">👤</span><span style="flex:1;margin-left:10px;font-weight:900">My Profile</span><span class="fk-arr">›</span></a>
      <a class="fk-item" href="<?= BASE_URL ?>profile/complete"><span class="fk-ic">✏️</span><span style="flex:1;margin-left:10px;font-weight:900">Edit Profile</span><span class="fk-arr">›</span></a>
      <a class="fk-item active" href="<?= BASE_URL ?>profile/address"><span class="fk-ic">📦</span><span style="flex:1;margin-left:10px;font-weight:900">Addresses</span><span class="fk-arr">›</span></a>
      <a class="fk-item" href="<?= BASE_URL ?>logout"><span class="fk-ic">🚪</span><span style="flex:1;margin-left:10px;font-weight:900">Logout</span><span class="fk-arr">›</span></a>
    </nav>
  </aside>

  <section class="fx-glass fk-main">
    <div class="fk-head">
      <div>
        <h2>Addresses</h2>
        <div class="sub">Saved addresses used for delivery pincode checking.</div>
      </div>
    </div>

    <?php if($err): ?><div class="alert error"><?= safe($err) ?></div><?php endif; ?>
    <?php if($ok): ?><div class="alert ok success-pop"><?= safe($ok) ?></div><?php endif; ?>

    <div class="fk-card">
      <h3 style="margin:0 0 10px;font-weight:950">Saved</h3>

      <?php if(empty($addresses)): ?>
        <div class="muted">No address saved yet.</div>
      <?php else: ?>
        <?php foreach($addresses as $a): ?>
          <div class="fk-row" style="align-items:flex-start">
            <div>
              <div style="font-weight:950">
                <?= safe($a['name']) ?>
                <?= ((int)$a['is_default']===1) ? "<span class='badge'>Default</span>" : "" ?>
                <span class="badge"><?= safe($a['address_type']) ?></span>
              </div>

              <div class="muted" style="font-size:13px;line-height:1.5;margin-top:6px">
                <?= safe($a['line1']) ?><?= $a['line2'] ? ", ".safe($a['line2']) : "" ?><br>
                <?= $a['landmark'] ? "Landmark: ".safe($a['landmark'])."<br>" : "" ?>
                <?= safe($a['city']) ?>, <?= safe($a['state']) ?> - <b><?= safe($a['pincode']) ?></b><br>
                Phone: <?= safe($a['phone']) ?>
              </div>

              <div style="margin-top:10px">
                <form method="post" action="<?= BASE_URL ?>profile/address/delete" onsubmit="return confirm('Delete this address?')">
    <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <button class="btn ghost small" type="submit">Delete</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="fk-card">
      <h3 style="margin:0 0 10px;font-weight:950">Add New Address</h3>

      <form class="auth-form" method="post">
    <?= csrf_field() ?>
        <div class="f"><div class="ic">👤</div><input name="name" placeholder=" "><label>Name</label></div>
        <div class="f"><div class="ic">📱</div><input name="phone" maxlength="10" placeholder=" "><label>Phone</label></div>
        <div class="f"><div class="ic">🏠</div><input name="line1" placeholder=" "><label>Address line 1</label></div>
        <div class="f"><div class="ic">📍</div><input name="line2" placeholder=" "><label>Address line 2 (optional)</label></div>
        <div class="f"><div class="ic">🧭</div><input name="landmark" placeholder=" "><label>Landmark (optional)</label></div>
        <div class="f"><div class="ic">🏙️</div><input name="city" placeholder=" "><label>City</label></div>
        <div class="f"><div class="ic">🗺️</div><input name="state" placeholder=" "><label>State</label></div>
        <div class="f"><div class="ic">🔢</div><input name="pincode" maxlength="6" placeholder=" "><label>Pincode</label></div>

        <div class="f has-value">
          <div class="ic">🏷️</div>
          <select name="address_type">
            <option value="home">Home</option>
            <option value="work">Work</option>
            <option value="other">Other</option>
          </select>
          <span class="sel-arrow">▾</span>
          <label>Address Type</label>
        </div>

        <label style="display:flex;gap:8px;align-items:center;margin-top:4px">
          <input type="checkbox" name="is_default"> Make default
        </label>

        <div class="fk-actions">
          <button class="btn" type="submit">Save Address →</button>
        </div>
      </form>
    </div>

  </section>

</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
