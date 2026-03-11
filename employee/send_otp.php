<?php
session_start();
require '../config/config.php'; 
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

$data = json_decode(file_get_contents('php://input'), true);
$email_to = $data['emp_email'] ?? '';
$shop_id = $_SESSION['shop_id']; // ดึง id ร้านจาก session

if (empty($email_to) || !filter_var($email_to, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'อีเมลผู้รับไม่ถูกต้อง']);
    exit;
}

// ดึงข้อมูล SMTP จาก shop_info
$sql_shop = "SELECT shop_email, shop_app_password, shop_name FROM shop_info WHERE shop_id = ?";
$stmt_shop = $conn->prepare($sql_shop);
$stmt_shop->bind_param("i", $shop_id);
$stmt_shop->execute();
$shop_info = $stmt_shop->get_result()->fetch_assoc();

if (!$shop_info || empty($shop_info['shop_email']) || empty($shop_info['shop_app_password'])) {
    echo json_encode(['status' => 'error', 'message' => 'ยังไม่ได้ตั้งค่าอีเมลของร้านค้าในระบบ']);
    exit;
}

// สุ่มรหัส OTP และเก็บใน Session
$otp = rand(100000, 999999);
$_SESSION['otp_code'] = $otp;
$_SESSION['otp_expire'] = time() + (5 * 60);

// ตั้งค่าการส่งอีเมล
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $shop_info['shop_email']; // ดึงจาก DB
    $mail->Password = $shop_info['shop_app_password']; // ดึงจาก DB
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom($shop_info['shop_email'], $shop_info['shop_name']);
    $mail->addAddress($email_to);

    $mail->isHTML(true);
    $mail->Subject = 'รหัสยืนยัน OTP สำหรับพนักงานใหม่';
    $mail->Body = "รหัส OTP ของคุณคือ: <b>$otp</b> (ใช้งานได้ใน 5 นาที)";

    $mail->send();
    echo json_encode(['status' => 'success', 'message' => 'ส่งรหัส OTP เรียบร้อยแล้ว']);
}
catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => "ส่งไม่สำเร็จ: {$mail->ErrorInfo}"]);
}