<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents("php://input"), true);
$user_otp = $data['otp'] ?? '';

// ตรวจสอบว่ามีการขอ OTP ไว้หรือไม่
if (!isset($_SESSION['otp_code']) || !isset($_SESSION['otp_time'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลการขอ OTP กรุณากดส่งใหม่อีกครั้ง']);
    exit;
}

// ตรวจสอบเวลาหมดอายุ (สมมติให้ 5 นาที = 300 วินาที)
$time_elapsed = time() - $_SESSION['otp_time'];
if ($time_elapsed > 300) {
    unset($_SESSION['otp_code']);
    unset($_SESSION['otp_time']);
    unset($_SESSION['otp_email']);
    echo json_encode(['status' => 'error', 'message' => 'รหัส OTP หมดอายุแล้ว กรุณากดขอใหม่']);
    exit;
}

// ตรวจสอบว่ารหัสตรงกันหรือไม่
if ($user_otp === $_SESSION['otp_code']) {
    // ถ้ายืนยันสำเร็จ ให้ลบ Session ทิ้งป้องกันการใช้ซ้ำ
    unset($_SESSION['otp_code']);
    unset($_SESSION['otp_time']);
    
    echo json_encode(['status' => 'success', 'message' => 'ยืนยันตัวตนสำเร็จ']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'รหัส OTP ไม่ถูกต้อง']);
}
?>