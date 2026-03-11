<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents("php://input"), true);
$user_otp = $data['otp'] ?? '';

if (empty($user_otp)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกรหัส OTP']);
    exit;
}

// เช็คว่าเคยส่ง OTP ไปหรือยัง
if (!isset($_SESSION['otp_code'])) {
    echo json_encode(['status' => 'error', 'message' => 'รหัส OTP หมดอายุ หรือยังไม่ได้ทำการส่ง']);
    exit;
}

// เช็คเวลาหมดอายุ (5 นาที)
if (time() > $_SESSION['otp_expires']) {
    unset($_SESSION['otp_code']); // ลบทิ้งเลย
    echo json_encode(['status' => 'error', 'message' => 'รหัส OTP หมดอายุแล้ว กรุณากดส่งใหม่']);
    exit;
}

// ตรวจสอบความถูกต้อง
if ($user_otp === $_SESSION['otp_code']) {
    // ถูกต้อง เซ็ตสถานะยืนยันตัวตนสำเร็จ
    $_SESSION['email_verified'] = true; 
    unset($_SESSION['otp_code']); // ล้างรหัสทิ้งเพื่อความปลอดภัย
    
    echo json_encode(['status' => 'success', 'message' => 'ยืนยันอีเมลสำเร็จ']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'รหัส OTP ไม่ถูกต้อง']);
}