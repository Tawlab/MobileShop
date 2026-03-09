<?php
// send_otp.php
session_start();
require '../config/config.php'; // ดึงไฟล์เชื่อมต่อฐานข้อมูล

// กำหนดให้ตอบกลับเป็น JSON
header('Content-Type: application/json; charset=utf-8');

// รับข้อมูลอีเมลปลายทางจาก Fetch API (JavaScript)
$data = json_decode(file_get_contents("php://input"), true);

// รองรับทั้งคีย์ emp_email, cs_email, หรือ supplier_email เผื่อมีการส่งชื่อตัวแปรที่ต่างกัน
$email = $data['emp_email'] ?? $data['cs_email'] ?? $data['supplier_email'] ?? $data['email'] ?? ''; 

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
    echo json_encode(['status' => 'error', 'message' => 'ยังไม่ได้ตั้งค่าระบบส่งอีเมลของร้านค้า กรุณาติดต่อ Admin เพื่อตั้งค่าในเมนูข้อมูลร้านค้า']);
    exit;
}

// --- 2. สร้างรหัส OTP และเก็บลง Session ---
$otp = sprintf("%06d", mt_rand(1, 999999)); // สุ่มเลข 6 หลัก
$_SESSION['otp_code'] = $otp;
$_SESSION['otp_expires'] = time() + (5 * 60); // ตั้งเวลาหมดอายุ 5 นาที (300 วินาที)

// --- 3. ตั้งค่าการส่งอีเมลด้วย PHPMailer ---
// โหลดไลบรารี PHPMailer (ตรวจสอบ path ให้ตรงกับโฟลเดอร์ vendor ของคุณ)
require '../vendor/autoload.php'; 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // การตั้งค่าเซิร์ฟเวอร์ SMTP (ตัวอย่างนี้ใช้ของ Gmail)
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com'; 
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
    <div style='font-family: sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 8px; max-width: 500px; margin: auto;'>
        <h2 style='color: #198754; text-align: center;'>รหัสยืนยันตัวตน (OTP)</h2>
        <p>สวัสดีครับ,</p>
        <p>คุณได้ทำการร้องขอรหัส OTP สำหรับยืนยันอีเมลในระบบของ <b>{$shop_info['shop_name']}</b></p>
        <div style='background: #f8f9fa; padding: 15px; text-align: center; font-size: 28px; font-weight: bold; letter-spacing: 6px; color: #333; border-radius: 8px; margin: 20px 0; border: 1px dashed #ccc;'>
            {$otp}
        </div>
        <p style='color: #dc3545; font-size: 0.9em;'>* รหัสนี้มีอายุการใช้งาน 5 นาที และห้ามแจ้งรหัสนี้แก่ผู้อื่นเด็ดขาด</p>
        <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
        <p style='font-size: 0.8em; color: #888; text-align: center;'>หากคุณไม่ได้เป็นผู้ดำเนินการ กรุณาเพิกเฉยต่ออีเมลฉบับนี้</p>
    </div>";
    
    $mail->send();
    
    // สำเร็จ: แอบแนบ debug_otp ไปให้คุณดูใน Console ด้วย (F12 -> Network -> Response) จะได้ไม่ต้องเปิดอีเมลดูตอนเทสระบบครับ
    echo json_encode(['status' => 'success', 'message' => 'ส่ง OTP สำเร็จ', 'debug_otp' => $otp]);
    
} catch (Exception $e) {
    // กรณีพัง (เช่น เน็ตหลุด, รหัสแอปผิด, อีเมลบล็อก)
    echo json_encode(['status' => 'error', 'message' => 'ระบบส่งอีเมลขัดข้อง: ' . $mail->ErrorInfo]);
}
?>