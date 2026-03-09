<?php
// check_duplicate.php
session_start();
require '../config/config.php'; // ดึงไฟล์เชื่อมต่อฐานข้อมูล

// กำหนด Header ให้ตอบกลับเป็น JSON
header('Content-Type: application/json');

// รับค่าจาก AJAX (POST)
$type = $_POST['type'] ?? '';
$value = trim($_POST['value'] ?? '');

// รับ ID เพื่อใช้ "ยกเว้น" การเช็คซ้ำกับข้อมูลตัวเอง (สำหรับหน้าแก้ไขข้อมูล)
// ถ้าเป็นหน้า "เพิ่มข้อมูลใหม่" ค่าเหล่านี้จะเป็น 0
$emp_id = (int)($_POST['emp_id'] ?? 0);           // พนักงาน
$cs_id = (int)($_POST['cs_id'] ?? 0);             // ลูกค้า
$supplier_id = (int)($_POST['supplier_id'] ?? 0); // ผู้จัดจำหน่าย (Supplier)

$response = ['exists' => false];

if (!empty($type) && !empty($value)) {
    switch ($type) {
        
        // ==========================================================
        // ส่วนที่ 1: พนักงาน (Employees)
        // ==========================================================
        case 'national_id':
            $stmt = $conn->prepare("SELECT emp_id FROM employees WHERE emp_national_id = ? AND emp_id != ?");
            $stmt->bind_param("si", $value, $emp_id);
            break;

        case 'emp_code':
            $stmt = $conn->prepare("SELECT emp_id FROM employees WHERE emp_code = ? AND emp_id != ?");
            $stmt->bind_param("si", $value, $emp_id);
            break;

        case 'phone':
            $stmt = $conn->prepare("SELECT emp_id FROM employees WHERE emp_phone_no = ? AND emp_id != ?");
            $stmt->bind_param("si", $value, $emp_id);
            break;

        case 'email':
            $stmt = $conn->prepare("SELECT emp_id FROM employees WHERE emp_email = ? AND emp_id != ?");
            $stmt->bind_param("si", $value, $emp_id);
            break;

        case 'username':
            // หา user_id ของพนักงานก่อนเพื่อใช้ยกเว้นตัวเอง
            $stmt_user = $conn->prepare("SELECT users_user_id FROM employees WHERE emp_id = ?");
            $stmt_user->bind_param("i", $emp_id);
            $stmt_user->execute();
            $user_res = $stmt_user->get_result()->fetch_assoc();
            $user_id = $user_res['users_user_id'] ?? 0;
            $stmt_user->close();

            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
            $stmt->bind_param("si", $value, $user_id);
            break;

        // ==========================================================
        // ส่วนที่ 2: ลูกค้า (Customers)
        // ==========================================================
        case 'customer_national_id':
            $stmt = $conn->prepare("SELECT cs_id FROM customers WHERE cs_national_id = ? AND cs_id != ?");
            $stmt->bind_param("si", $value, $cs_id);
            break;

        case 'customer_phone':
            $stmt = $conn->prepare("SELECT cs_id FROM customers WHERE cs_phone_no = ? AND cs_id != ?");
            $stmt->bind_param("si", $value, $cs_id);
            break;

        case 'customer_email':
            $stmt = $conn->prepare("SELECT cs_id FROM customers WHERE cs_email = ? AND cs_id != ?");
            $stmt->bind_param("si", $value, $cs_id);
            break;

        // ==========================================================
        // ส่วนที่ 3: ผู้จัดจำหน่าย (Suppliers) -> [ส่วนที่เพิ่มใหม่ล่าสุด]
        // ==========================================================
        case 'supplier_tax_id':
            // ตรวจสอบเลขประจำตัวผู้เสียภาษีอากรซ้ำ
            $stmt = $conn->prepare("SELECT supplier_id FROM suppliers WHERE tax_id = ? AND supplier_id != ?");
            $stmt->bind_param("si", $value, $supplier_id);
            break;

        case 'supplier_phone':
            // ตรวจสอบเบอร์โทรศัพท์ผู้จัดจำหน่ายซ้ำ
            $stmt = $conn->prepare("SELECT supplier_id FROM suppliers WHERE supplier_phone_no = ? AND supplier_id != ?");
            $stmt->bind_param("si", $value, $supplier_id);
            break;

        // ==========================================================
        // กรณีพิมพ์ Type ผิด
        // ==========================================================
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid type']);
            exit;
    }

    // ประมวลผลคำสั่ง SQL
    if (isset($stmt)) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $response['exists'] = true; // พบข้อมูลซ้ำ!
        }
        $stmt->close();
    }
}

// ตอบกลับไปให้ JavaScript เพื่อแสดง SweetAlert
echo json_encode($response);
?>