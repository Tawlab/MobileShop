<?php
// check_duplicate.php
session_start();
require '../config/config.php'; // ดึงไฟล์เชื่อมต่อฐานข้อมูล

// กำหนด Header ให้ตอบกลับเป็น JSON เพื่อให้ JavaScript ใช้งานได้
header('Content-Type: application/json');

// รับค่าจาก AJAX (POST)
$type = $_POST['type'] ?? '';
$value = trim($_POST['value'] ?? '');

// รับ ID เพื่อใช้ "ยกเว้น" การเช็คซ้ำกับข้อมูลของตัวเองในกรณีหน้า "แก้ไขข้อมูล"
// ถ้าเป็นหน้า "เพิ่มข้อมูลใหม่" ค่าเหล่านี้จะไม่มีส่งมา และจะถูกตั้งค่าเป็น 0 อัตโนมัติ
$emp_id = (int)($_POST['emp_id'] ?? 0); // สำหรับพนักงาน
$cs_id = (int)($_POST['cs_id'] ?? 0);   // สำหรับลูกค้า (เผื่อใช้หน้าแก้ไขลูกค้าในอนาคต)

$response = ['exists' => false];

if (!empty($type) && !empty($value)) {
    switch ($type) {
        
        // ==========================================================
        // ส่วนที่ 1: การตรวจสอบข้อมูล "พนักงาน" (Employees)
        // ==========================================================
        
        case 'national_id':
            // ตรวจสอบเลขบัตร ปชช. พนักงาน
            $stmt = $conn->prepare("SELECT emp_id FROM employees WHERE emp_national_id = ? AND emp_id != ?");
            $stmt->bind_param("si", $value, $emp_id);
            break;

        case 'emp_code':
            // ตรวจสอบรหัสพนักงาน
            $stmt = $conn->prepare("SELECT emp_id FROM employees WHERE emp_code = ? AND emp_id != ?");
            $stmt->bind_param("si", $value, $emp_id);
            break;

        case 'phone':
            // ตรวจสอบเบอร์โทรศัพท์พนักงาน
            $stmt = $conn->prepare("SELECT emp_id FROM employees WHERE emp_phone_no = ? AND emp_id != ?");
            $stmt->bind_param("si", $value, $emp_id);
            break;

        case 'email':
            // ตรวจสอบอีเมลพนักงาน
            $stmt = $conn->prepare("SELECT emp_id FROM employees WHERE emp_email = ? AND emp_id != ?");
            $stmt->bind_param("si", $value, $emp_id);
            break;

        case 'username':
            // ตรวจสอบ Username ซ้ำ
            // ต้องหา user_id ของพนักงานคนนี้ก่อนเพื่อใช้ยกเว้นตัวเอง
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
        // ส่วนที่ 2: การตรวจสอบข้อมูล "ลูกค้า" (Customers)
        // ==========================================================
        
        case 'customer_national_id':
            // ตรวจสอบเลขบัตร ปชช. ลูกค้า
            $stmt = $conn->prepare("SELECT cs_id FROM customers WHERE cs_national_id = ? AND cs_id != ?");
            $stmt->bind_param("si", $value, $cs_id);
            break;

        case 'customer_phone':
            // ตรวจสอบเบอร์โทรศัพท์ลูกค้า
            $stmt = $conn->prepare("SELECT cs_id FROM customers WHERE cs_phone_no = ? AND cs_id != ?");
            $stmt->bind_param("si", $value, $cs_id);
            break;

        case 'customer_email':
            // ตรวจสอบอีเมลลูกค้า (เผื่อต้องการใช้งานในอนาคต)
            $stmt = $conn->prepare("SELECT cs_id FROM customers WHERE cs_email = ? AND cs_id != ?");
            $stmt->bind_param("si", $value, $cs_id);
            break;

        // ==========================================================
        // กรณีระบุ Type ผิดพลาด
        // ==========================================================
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid type']);
            exit;
    }

    // ประมวลผลคำสั่ง SQL และตรวจสอบผลลัพธ์
    if (isset($stmt)) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $response['exists'] = true; // พบข้อมูลซ้ำในฐานข้อมูล
        }
        $stmt->close();
    }
}

// ส่งผลลัพธ์กลับไปยัง JavaScript (Frontend)
echo json_encode($response);
?>