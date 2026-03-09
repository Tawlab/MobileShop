<?php
// verify_otp.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// รับข้อมูลจาก Fetch API
$data = json_decode(file_get_contents("php://input"), true);
$user_otp = $data['otp'] ?? '';

if (empty($user_otp)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกรหัส OTP']);
    exit;
}

// 1. เช็คว่าเคยมีการกดส่ง OTP (จนเกิด Session) ไปหรือยัง
if (!isset($_SESSION['otp_code'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล OTP หรือรหัสถูกรีเซ็ตไปแล้ว กรุณากดส่งใหม่']);
    exit;
}

// 2. เช็คเวลาหมดอายุ (5 นาที)
if (time() > $_SESSION['otp_expires']) {
    unset($_SESSION['otp_code']); // ลบทิ้งเลยเมื่อหมดเวลา
    echo json_encode(['status' => 'error', 'message' => 'รหัส OTP หมดอายุแล้ว กรุณากดส่งรหัสใหม่']);
    exit;
}

// 3. ตรวจสอบความถูกต้องของรหัส
if ($user_otp === $_SESSION['otp_code']) {
    // ถ้ารหัสถูกต้อง
    $_SESSION['email_verified'] = true; // เซ็ตสถานะให้ระบบรู้ว่ายืนยันผ่านแล้ว
    unset($_SESSION['otp_code']);       // ล้างรหัสทิ้งเพื่อไม่ให้ใช้ซ้ำได้อีก
    
    echo json_encode(['status' => 'success', 'message' => 'ยืนยันอีเมลสำเร็จ']);
} else {
    // ถ้ารหัสผิด
    echo json_encode(['status' => 'error', 'message' => 'รหัส OTP ไม่ถูกต้อง กรุณาลองอีกครั้ง']);
}
?>