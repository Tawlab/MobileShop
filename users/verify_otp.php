<?php
session_start();

// กำหนด Header ให้ระบบตอบกลับเป็น JSON
header('Content-Type: application/json; charset=utf-8');

// รับข้อมูลที่ส่งมาจาก Fetch API (JavaScript)
$data = json_decode(file_get_contents("php://input"), true);
$user_otp = trim($data['otp'] ?? '');

// ตรวจสอบว่ามีการส่งรหัสมาหรือไม่
if (empty($user_otp)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกรหัส OTP 6 หลัก']);
    exit;
}

// เช็คว่าเคยมีการกดส่ง OTP (จนเกิด Session) ไปหรือยัง
if (!isset($_SESSION['otp_code'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล OTP หรือรหัสถูกรีเซ็ตไปแล้ว กรุณากดส่งรหัสใหม่']);
    exit;
}

// เช็คเวลาหมดอายุ (ที่เราตั้งไว้ 5 นาทีในหน้า send_otp.php)
if (time() > $_SESSION['otp_expires']) {
    unset($_SESSION['otp_code']);    // ลบทิ้งเมื่อหมดเวลา
    unset($_SESSION['otp_expires']); 
    echo json_encode(['status' => 'error', 'message' => 'รหัส OTP หมดอายุแล้ว กรุณากดส่งรหัสใหม่อีกครั้ง']);
    exit;
}

// ตรวจสอบความถูกต้องของรหัส
if ($user_otp === (string)$_SESSION['otp_code']) {
    // ถ้ารหัสถูกต้อง ให้เซ็ต Session ยืนยันตัวตน
    $_SESSION['email_verified'] = true; 
    
    // ล้างรหัสทิ้งเพื่อความปลอดภัย ป้องกันการนำกลับมาใช้ซ้ำ
    unset($_SESSION['otp_code']);       
    unset($_SESSION['otp_expires']);
    
    echo json_encode(['status' => 'success', 'message' => 'ยืนยันอีเมลสำเร็จ']);
} else {
    // ถ้ารหัสผิด
    echo json_encode(['status' => 'error', 'message' => 'รหัส OTP ไม่ถูกต้อง กรุณาลองอีกครั้ง']);
}
?>