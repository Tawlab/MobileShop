<?php
// send_otp.php
session_start();
require '../config/config.php'; // ดึงไฟล์เชื่อมต่อฐานข้อมูล

// กำหนดให้ตอบกลับเป็น JSON
header('Content-Type: application/json; charset=utf-8');

// รับข้อมูลอีเมลปลายทางจาก Fetch API (JavaScript)
$data = json_decode(file_get_contents("php://input"), true);
$email = $data['emp_email'] ?? ''; 

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'รูปแบบอีเมลไม่ถูกต้อง']);
    exit;
}

// --- 1. ดึงข้อมูลอีเมลร้านค้าจากฐานข้อมูล ---
$shop_id = $_SESSION['shop_id'] ?? 1; // ดึง shop_id จาก session ของผู้ใช้ที่ล็อกอินอยู่
$stmt_shop = $conn->prepare("SELECT shop_name, shop_email, shop_app_password FROM shop_info WHERE shop_id = ?");
$stmt_shop->bind_param("i", $shop_id);
$stmt_shop->execute();
$shop_info = $stmt_shop->get_result()->fetch_assoc();
$stmt_shop->close();

// เช็คว่าร้านค้ามีการตั้งค่าอีเมลและรหัสผ่าน App Password ไว้หรือยัง
if (!$shop_info || empty($shop_info['shop_email']) || empty($shop_info['shop_app_password'])) {
    echo json_encode(['status' => 'error', 'message' => 'ยังไม่ได้ตั้งค่าอีเมลร้านค้าในระบบ กรุณาติดต่อผู้ดูแลระบบ']);
    exit;
}

// --- 2. สร้างรหัส OTP และเก็บลง Session ---
$otp = sprintf("%06d", mt_rand(1, 999999)); // สุ่มเลข 6 หลัก
$_SESSION['otp_code'] = $otp;
$_SESSION['otp_expires'] = time() + (5 * 60); // ตั้งเวลาหมดอายุ 5 นาที

// --- 3. ตั้งค่าการส่งอีเมลด้วย PHPMailer ---
// โหลดไลบรารี PHPMailer (ตรวจสอบ path ให้ตรงกับที่เก็บโฟลเดอร์ vendor ของคุณ)
require '../vendor/autoload.php'; 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // การตั้งค่าเซิร์ฟเวอร์
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com'; // สมมติว่าใช้ Gmail
    $mail->SMTPAuth = true;
    
    // ดึงค่าจากตาราง shop_info มาใช้งาน
    $mail->Username = $shop_info['shop_email'];          // อีเมลร้านค้า
    $mail->Password = $shop_info['shop_app_password'];   // App Password 16 หลัก
    
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8'; // รองรับภาษาไทย

    // ผู้ส่ง และ ผู้รับ
    $mail->setFrom($shop_info['shop_email'], $shop_info['shop_name']); // ชื่อผู้ส่งเป็นชื่อร้าน
    $mail->addAddress($email);
    
    // เนื้อหาอีเมล
    $mail->isHTML(true);
    $mail->Subject = 'รหัส OTP สำหรับยืนยันอีเมลของคุณ';
    
    // ออกแบบหน้าตาอีเมลแบบ HTML
    $mail->Body = "
    <div style='font-family: sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 8px; max-width: 500px;'>
        <h2 style='color: #198754;'>รหัสยืนยันตัวตน (OTP)</h2>
        <p>สวัสดีครับ,</p>
        <p>คุณได้ทำการร้องขอรหัส OTP สำหรับยืนยันอีเมลในระบบของ <b>{$shop_info['shop_name']}</b></p>
        <div style='background: #f8f9fa; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; color: #333; border-radius: 5px; margin: 20px 0;'>
            {$otp}
        </div>
        <p style='color: #dc3545; font-size: 0.9em;'>* รหัสนี้มีอายุการใช้งาน 5 นาที และห้ามให้รหัสนี้แก่ผู้อื่นเด็ดขาด</p>
        <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
        <p style='font-size: 0.8em; color: #888;'>หากคุณไม่ได้เป็นผู้ดำเนินการ กรุณาเพิกเฉยต่ออีเมลฉบับนี้</p>
    </div>";
    
    $mail->send();
    
    // (แอบส่ง debug_otp กลับไปให้คุณดูใน Console เผื่ออยากทดสอบโดยไม่ต้องเปิดอีเมล)
    echo json_encode(['status' => 'success', 'message' => 'ส่ง OTP สำเร็จ', 'debug_otp' => $otp]);
    
} catch (Exception $e) {
    // ถ้าส่งไม่ผ่าน
    echo json_encode(['status' => 'error', 'message' => 'ระบบส่งอีเมลขัดข้อง: ' . $mail->ErrorInfo]);
}
?>