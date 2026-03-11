<?php
session_start();
require '../config/config.php';

// กำหนด Header ให้ตอบกลับเป็น JSON
header('Content-Type: application/json');

// รับค่าจาก AJAX (POST)
$type = $_POST['type'] ?? '';
$value = trim($_POST['value'] ?? '');
$emp_id = (int)($_POST['emp_id'] ?? 0); // ID ของพนักงานปัจจุบันเพื่อ "ยกเว้น" การเช็คซ้ำกับตัวเอง

$response = ['exists' => false];

if (!empty($type) && !empty($value)) {
    switch ($type) {
        case 'national_id':
            // ตรวจสอบเลขบัตรประชาชนซ้ำในตาราง employees
            $stmt = $conn->prepare("SELECT emp_id FROM employees WHERE emp_national_id = ? AND emp_id != ?");
            $stmt->bind_param("si", $value, $emp_id);
            break;

        case 'emp_code':
            // ตรวจสอบรหัสพนักงานซ้ำในตาราง employees
            $stmt = $conn->prepare("SELECT emp_id FROM employees WHERE emp_code = ? AND emp_id != ?");
            $stmt->bind_param("si", $value, $emp_id);
            break;

        case 'phone':
            // ตรวจสอบเบอร์โทรศัพท์ซ้ำในตาราง employees โดยยกเว้น ID ตัวเอง
            $stmt = $conn->prepare("SELECT emp_id FROM employees WHERE emp_phone_no = ? AND emp_id != ?");
            $stmt->bind_param("si", $value, $emp_id);
            break;
            
        case 'email':
            // ตรวจสอบอีเมลซ้ำในตาราง employees
            $stmt = $conn->prepare("SELECT emp_id FROM employees WHERE emp_email = ? AND emp_id != ?");
            $stmt->bind_param("si", $value, $emp_id);
            break;

        case 'username':
            // ตรวจสอบชื่อผู้ใช้งานซ้ำในตาราง users โดยต้องหา user_id ที่ผูกกับ emp_id ก่อน
            $stmt_user = $conn->prepare("SELECT users_user_id FROM employees WHERE emp_id = ?");
            $stmt_user->bind_param("i", $emp_id);
            $stmt_user->execute();
            $user_res = $stmt_user->get_result()->fetch_assoc();
            $user_id = $user_res['users_user_id'] ?? 0;

            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
            $stmt->bind_param("si", $value, $user_id);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid type']);
            exit;
    }

    if (isset($stmt)) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $response['exists'] = true; // พบข้อมูลซ้ำ
        }
        $stmt->close();
    }
}

// ส่งผลลัพธ์กลับไปยัง JavaScript
echo json_encode($response);
