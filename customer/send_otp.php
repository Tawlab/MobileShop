<?php
// send_otp.php
session_start();
require '../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// รับข้อมูลอีเมลปลายทางจาก Fetch API
$data = json_decode(file_get_contents("php://input"), true);
$email = $data['emp_email'] ?? ''; 

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'รูปแบบอีเมลไม่ถูกต้อง']);
    exit;
}

// ดึงข้อมูลอีเมลร้านค้าจากฐานข้อมูล 
$shop_id = $_SESSION['shop_id'] ?? 1;
$stmt_shop = $conn->prepare("SELECT shop_name, shop_email, shop_app_password FROM shop_info WHERE shop_id = ?");
$stmt_shop->bind_param("i", $shop_id);
$stmt_shop->execute();
$shop_info = $stmt_shop->get_result()->fetch_assoc();
$stmt_shop->close();

// เช็คว่าร้านค้ามีการตั้งค่าอีเมลไว้หรือยัง
if (!$shop_info || empty($shop_info['shop_email']) || empty($shop_info['shop_app_password'])) {
    echo json_encode(['status' => 'error', 'message' => 'ยังไม่ได้ตั้งค่าอีเมลร้านค้าในระบบ กรุณาติดต่อ Admin']);
    exit;
}

// สร้างรหัส OTP และเก็บลง Session 
$otp = sprintf("%06d", mt_rand(1, 999999));
$_SESSION['otp_code'] = $otp;
$_SESSION['otp_expires'] = time() + (5 * 60); // หมดอายุใน 5 นาที

// ตั้งค่าการส่งอีเมลด้วย PHPMailer
require '../vendor/autoload.php'; 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    
    // ดึงค่าจากตาราง shop_info มาใส่ตรงนี้!
    $mail->Username = $shop_info['shop_email'];          
    $mail->Password = $shop_info['shop_app_password'];   
    
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8'; // รองรับภาษาไทย

    // ผู้ส่ง และ ผู้รับ
    $mail->setFrom($shop_info['shop_email'], $shop_info['shop_name']); // ชื่อผู้ส่งเป็นชื่อร้าน
    $mail->addAddress($email);
    
    // เนื้อหาอีเมล
    $mail->isHTML(true);
    $mail->Subject = 'รหัส OTP สำหรับยืนยันอีเมลของคุณ';
    $mail->Body = "สวัสดีครับ,<br><br>รหัส OTP ของคุณคือ: <b>{$otp}</b><br><br><i>* รหัสนี้มีอายุการใช้งาน 5 นาที</i>";
    
    $mail->send();
    echo json_encode(['status' => 'success', 'message' => 'ส่ง OTP สำเร็จ']);
    
} catch (Exception $e) {
    // ถ้าส่งไม่ผ่าน จะพ่น Error ออกมาให้รู้สาเหตุ
    echo json_encode(['status' => 'error', 'message' => 'ส่งอีเมลไม่สำเร็จ: ' . $mail->ErrorInfo]);
}
?>