<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();

$email = $_SESSION['user'];
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) redirect('profile/address');

$stmt = db_prepare("DELETE FROM user_addresses WHERE id=? AND user_email=?");
$stmt->bind_param("is", $id, $email);
$stmt->execute();
$stmt->close();

redirect('profile/address');
