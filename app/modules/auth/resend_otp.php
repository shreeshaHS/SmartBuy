<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/mail.php';

$email = $_SESSION['pending_email'] ?? '';
if ($email === '') redirect('register');

$stmt = db_prepare("SELECT name FROM pending_registrations WHERE email=? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) redirect('register');

$otp = otp6();
$otpHash = password_hash($otp, PASSWORD_DEFAULT);
$exp = addMinutes(10);

$stmt = db_prepare("UPDATE pending_registrations SET otp_hash=?, otp_expires_at=?, attempts=0 WHERE email=?");
$stmt->bind_param("sss", $otpHash, $exp, $email);
$stmt->execute();
$stmt->close();

sendOtpMail($email, $row['name'], $otp, "Resend OTP");
redirect('verify');
