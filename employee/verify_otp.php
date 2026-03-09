<?php
// --- verify_otp.php ---
session_start();
header('Content-Type: application/json');

// รับค่าจาก Fetch API
$data = json_decode(file_get_contents('php://input'), true);
$user_otp = $data['otp'] ?? '';

// 1. ตรวจสอบว่ามี OTP ใน Session หรือไม่
if (!isset($_SESSION['otp_code']) || !isset($_SESSION['otp_expire'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล OTP กรุณากดส่งรหัสอีกครั้ง']);
    exit;
}

// 2. ตรวจสอบว่ารหัสหมดอายุหรือยัง (5 นาที)
if (time() > $_SESSION['otp_expire']) {
    unset($_SESSION['otp_code'], $_SESSION['otp_expire']);
    echo json_encode(['status' => 'error', 'message' => 'รหัส OTP หมดอายุแล้ว']);
    exit;
}

// 3. เปรียบเทียบรหัส
if ($user_otp == $_SESSION['otp_code']) {
    // ยืนยันสำเร็จ: ตั้งสถานะไว้ใช้เช็คก่อน Save จริง
    $_SESSION['email_verified'] = true; 
    
    // ลบ OTP ออกจาก Session ทันทีเพื่อความปลอดภัย (ป้องกันการใช้ซ้ำ)
    unset($_SESSION['otp_code'], $_SESSION['otp_expire']);
    
    echo json_encode(['status' => 'success', 'message' => 'ยืนยันอีเมลถูกต้อง']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'รหัส OTP ไม่ถูกต้อง']);
}