<?php
// send_otp.php
session_start();
require '../config/config.php'; // ดึงไฟล์เชื่อมต่อฐานข้อมูล

// กำหนดให้ระบบตอบกลับเป็น JSON
header('Content-Type: application/json; charset=utf-8');

// รับข้อมูลอีเมลปลายทางจาก Fetch API (JavaScript)
$data = json_decode(file_get_contents("php://input"), true);

// รองรับคีย์หลายรูปแบบ (emp_email, email, shop_email)
$email = $data['emp_email'] ?? $data['email'] ?? $data['shop_email'] ?? ''; 

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'รูปแบบอีเมลไม่ถูกต้อง']);
    exit;
}

// --- 1. ดึงข้อมูลอีเมลจากร้านค้าส่วนกลาง (Admin: shop_id = 0) ---
$shop_id = 0; // บังคับใช้บัญชีส่วนกลางในการส่งอีเมลเสมอ
$stmt_shop = $conn->prepare("SELECT shop_name, shop_email, shop_app_password FROM shop_info WHERE shop_id = ?");
$stmt_shop->bind_param("i", $shop_id);
$stmt_shop->execute();
$shop_info = $stmt_shop->get_result()->fetch_assoc();
$stmt_shop->close();

// เช็คว่าร้านค้าส่วนกลางมีการตั้งค่าอีเมลและรหัสผ่าน App Password ไว้หรือยัง
if (!$shop_info || empty($shop_info['shop_email']) || empty($shop_info['shop_app_password'])) {
    echo json_encode(['status' => 'error', 'message' => 'ระบบส่วนกลางยังไม่ได้ตั้งค่าบัญชีอีเมลสำหรับส่ง กรุณาติดต่อผู้ดูแลระบบ']);
    exit;
}

// --- 2. สร้างรหัส OTP และเก็บลง Session ---
$otp = sprintf("%06d", mt_rand(1, 999999)); // สุ่มเลข 6 หลัก (เช่น 004512)
$_SESSION['otp_code'] = $otp;
$_SESSION['otp_expires'] = time() + (5 * 60); // ตั้งเวลาหมดอายุ 5 นาที (300 วินาที)

// --- 3. ตั้งค่าการส่งอีเมลด้วย PHPMailer ---
// ต้องติดตั้งไลบรารี PHPMailer ไว้ในโปรเจกต์ (โฟลเดอร์ vendor)
require '../vendor/autoload.php'; 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // การตั้งค่าเซิร์ฟเวอร์ SMTP (ตัวอย่างนี้ใช้ของ Gmail)
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com'; 
    $mail->SMTPAuth = true;
    
    // ดึงค่าบัญชีผู้ส่งจากส่วนกลางมาใช้งาน
    $mail->Username = $shop_info['shop_email'];          // อีเมลส่วนกลาง
    $mail->Password = $shop_info['shop_app_password'];   // App Password 16 หลักของส่วนกลาง
    
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8'; // รองรับภาษาไทย

    // ผู้ส่ง และ ผู้รับ
    $mail->setFrom($shop_info['shop_email'], $shop_info['shop_name']); // ชื่อผู้ส่ง
    $mail->addAddress($email); // อีเมลผู้รับ
    
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
    
    // ส่ง status กลับไปให้ Frontend
    // หมายเหตุ: ใส่ 'debug_otp' มาเพื่อให้ทดสอบง่ายขึ้น (ดูผ่าน F12 > Network ได้) หากใช้งานจริงสามารถลบออกได้
    echo json_encode(['status' => 'success', 'message' => 'ส่ง OTP สำเร็จ', 'debug_otp' => $otp]);
    
} catch (Exception $e) {
    // กรณีพัง (เช่น เน็ตหลุด, รหัสผ่านแอปผิด, Google บล็อก)
    echo json_encode(['status' => 'error', 'message' => 'ระบบส่งอีเมลขัดข้อง: ' . $mail->ErrorInfo]);
}
?>