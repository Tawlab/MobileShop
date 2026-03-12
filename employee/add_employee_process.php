<?php
ob_start();
session_start();
require '../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_shop_id = $_SESSION['shop_id'];

// ตรวจสอบสิทธิ์ Admin
$is_admin = false;
$chk_sql = "SELECT r.role_name FROM roles r 
            JOIN user_roles ur ON r.role_id = ur.roles_role_id 
            WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt = $conn->prepare($chk_sql)) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $is_admin = true;
    $stmt->close();
}

// =================================================================================
// 1. ฟังก์ชันตรวจสอบเลขบัตรประชาชนด้วยสูตรคำนวณ (Mod 11)
// =================================================================================
function checkThaiID($id)
{
    if (strlen($id) !== 13 || !is_numeric($id)) return false;
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$id[$i] * (13 - $i);
    }
    $check = (11 - ($sum % 11)) % 10;
    return $check === (int)$id[12];
}

// ฟังก์ชันหา ID ถัดไปสำหรับตารางต่างๆ
function getNextId($conn, $table, $column)
{
    $sql = "SELECT MAX($column) as max_id FROM $table";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return ($row['max_id']) ? $row['max_id'] + 1 : 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // =================================================================================
        // 5. จัดการตรวจสอบข้อมูล แผนก สาขา และ สิทธิ์การใช้งาน
        // =================================================================================
        if ($is_admin) {
            if (empty($_POST['shop_id'])) throw new Exception("กรุณาเลือกร้านค้า");
            if (empty($_POST['branches_branch_id'])) throw new Exception("กรุณาเลือกสาขา");
            if (empty($_POST['departments_dept_id'])) throw new Exception("กรุณาเลือกแผนก");
            if (empty($_POST['role_id'])) throw new Exception("กรุณาเลือกสิทธิ์การใช้งาน");
            
            $target_branch_id = (int)$_POST['branches_branch_id'];
            $role_id = (int)$_POST['role_id'];
        } else {
            // กรณีเป็น Partner ทั่วไป ให้ดึงสาขาของตัวเองมาบันทึกให้พนักงานใหม่
            $stmt_b = $conn->prepare("SELECT branches_branch_id FROM employees WHERE users_user_id = ?");
            $stmt_b->bind_param("i", $current_user_id);
            $stmt_b->execute();
            $target_branch_id = $stmt_b->get_result()->fetch_assoc()['branches_branch_id'];

            // บังคับสิทธิ์เป็น User (พนักงานทั่วไป) เท่านั้น
            $role_res = $conn->query("SELECT role_id FROM roles WHERE role_name = 'User' LIMIT 1");
            if ($role_res->num_rows > 0) {
                $role_id = $role_res->fetch_assoc()['role_id'];
            } else {
                throw new Exception("ไม่พบสิทธิ์ User ในระบบ");
            }
        }

        // รับค่าข้อมูลทั่วไปจากฟอร์ม
        $emp_code = trim($_POST['emp_code']);
        $emp_national_id = trim($_POST['emp_national_id']);
        $firstname_th = trim($_POST['firstname_th']);
        $lastname_th = trim($_POST['lastname_th']);
        $firstname_en = trim($_POST['firstname_en'] ?? '') ?: NULL;
        $lastname_en = trim($_POST['lastname_en'] ?? '') ?: NULL;
        $emp_phone_no = trim($_POST['emp_phone_no']);
        $emp_email = trim($_POST['emp_email'] ?? '') ?: NULL;
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        // =================================================================================
        // ตรวจสอบความถูกต้อง (Validation) ก่อนบันทึกลงฐานข้อมูล
        // =================================================================================
        
        // 1. ตรวจสอบเลขบัตรประชาชน
        if (!checkThaiID($emp_national_id)) {
            throw new Exception("เลขบัตรประชาชนไม่ถูกต้องตามสูตรคำนวณ");
        }
        
        // 2. ตรวจสอบภาษาชื่อ-นามสกุล
        if (!preg_match("/^[ก-๙\s]+$/u", $firstname_th)) throw new Exception("ชื่อ (ไทย) ต้องเป็นภาษาไทยเท่านั้น");
        if (!preg_match("/^[ก-๙\s]+$/u", $lastname_th)) throw new Exception("นามสกุล (ไทย) ต้องเป็นภาษาไทยเท่านั้น");
        if (!empty($firstname_en) && !preg_match("/^[a-zA-Z\s]+$/", $firstname_en)) throw new Exception("ชื่อ (อังกฤษ) ต้องเป็นตัวอักษรภาษาอังกฤษเท่านั้น");
        if (!empty($lastname_en) && !preg_match("/^[a-zA-Z\s]+$/", $lastname_en)) throw new Exception("นามสกุล (อังกฤษ) ต้องเป็นตัวอักษรภาษาอังกฤษเท่านั้น");
        
        // 3. ตรวจสอบเบอร์โทรศัพท์
        if (!preg_match("/^(02|05|06|08|09)\d{8}$/", $emp_phone_no)) {
            throw new Exception("รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง (ต้องขึ้นต้นด้วย 02,05,06,08,09 และมี 10 หลัก)");
        }
        
        // ตรวจสอบรหัสผ่าน
        if ($password !== $_POST['confirm_password']) {
            throw new Exception("รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน");
        }

        // ตรวจสอบข้อมูลซ้ำซ้อนในฐานข้อมูล (ป้องกันการบันทึกทับ)
        $stmt_check = $conn->prepare("SELECT emp_id FROM employees WHERE emp_code = ? OR emp_national_id = ? OR emp_phone_no = ?");
        $stmt_check->bind_param("sss", $emp_code, $emp_national_id, $emp_phone_no);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            throw new Exception("รหัสพนักงาน, เลขบัตรประชาชน หรือเบอร์โทรศัพท์ มีซ้ำอยู่ในระบบแล้ว");
        }
        
        // 4. ตรวจสอบอีเมลซ้ำ (กรณีมีการกรอกอีเมลมา)
        if ($emp_email) {
            $stmt_email = $conn->prepare("SELECT emp_id FROM employees WHERE emp_email = ?");
            $stmt_email->bind_param("s", $emp_email);
            $stmt_email->execute();
            if ($stmt_email->get_result()->num_rows > 0) throw new Exception("อีเมลนี้ถูกใช้งานไปแล้ว");
        }

        // ตรวจสอบ Username ซ้ำ
        $stmt_user_check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt_user_check->bind_param("s", $username);
        $stmt_user_check->execute();
        if ($stmt_user_check->get_result()->num_rows > 0) throw new Exception("Username นี้มีผู้ใช้งานแล้ว");


        // จัดการอัปโหลดรูปภาพพนักงาน
        $emp_image_filename = NULL;
        if (isset($_FILES['emp_image']) && $_FILES['emp_image']['error'] == 0) {
            $ext = pathinfo($_FILES['emp_image']['name'], PATHINFO_EXTENSION);
            $emp_image_filename = "emp_" . $emp_code . "_" . time() . "." . $ext;
            move_uploaded_file($_FILES['emp_image']['tmp_name'], "../uploads/employees/" . $emp_image_filename);
        }

        // =================================================================================
        // เริ่มบันทึกข้อมูลแบบ Transaction
        // =================================================================================
        $conn->begin_transaction();

        // 1. บันทึกที่อยู่ (Address)
        $new_addr_id = getNextId($conn, 'addresses', 'address_id');
        $sql_addr = "INSERT INTO addresses (address_id, home_no, moo, soi, road, village, subdistricts_subdistrict_id) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_addr = $conn->prepare($sql_addr);
        $stmt_addr->bind_param("isssssi", $new_addr_id, $_POST['home_no'], $_POST['moo'], $_POST['soi'], $_POST['road'], $_POST['village'], $_POST['subdistricts_subdistrict_id']);
        $stmt_addr->execute();

        // 2. บันทึกบัญชีผู้ใช้ (User)
        $new_user_id = getNextId($conn, 'users', 'user_id');
        $hashed_pw = password_hash($password, PASSWORD_DEFAULT); // เข้ารหัสผ่าน
        $stmt_user = $conn->prepare("INSERT INTO users (user_id, username, password, user_status) VALUES (?, ?, ?, 'Active')");
        $stmt_user->bind_param("iss", $new_user_id, $username, $hashed_pw);
        $stmt_user->execute();

        // 3. บันทึกข้อมูลพนักงาน (Employee)
        $new_emp_id = getNextId($conn, 'employees', 'emp_id');
        $sql_emp = "INSERT INTO employees (
            emp_id, emp_code, emp_national_id, firstname_th, lastname_th, firstname_en, lastname_en,
            emp_phone_no, emp_email, emp_line_id, emp_birthday, emp_gender, emp_status,
            prefixs_prefix_id, Addresses_address_id, religions_religion_id, departments_dept_id, 
            branches_branch_id, users_user_id, emp_image, create_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt_emp = $conn->prepare($sql_emp);
        $stmt_emp->bind_param(
            "issssssssssssiiiiiis",
            $new_emp_id,
            $emp_code,
            $emp_national_id,
            $firstname_th,
            $lastname_th,
            $firstname_en,
            $lastname_en,
            $emp_phone_no,
            $emp_email,
            $_POST['emp_line_id'],
            $_POST['emp_birthday'],
            $_POST['emp_gender'],
            $_POST['emp_status'],
            $_POST['prefixs_prefix_id'],
            $new_addr_id,
            $_POST['religions_religion_id'],
            $_POST['departments_dept_id'],
            $target_branch_id,
            $new_user_id,
            $emp_image_filename
        );
        $stmt_emp->execute();

        // 4. บันทึกสิทธิ์ผู้ใช้งาน (User Role)
        $stmt_role = $conn->prepare("INSERT INTO user_roles (roles_role_id, users_user_id) VALUES (?, ?)");
        $stmt_role->bind_param("ii", $role_id, $new_user_id);
        $stmt_role->execute();

        // ยืนยันการบันทึก
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'เพิ่มพนักงานใหม่เรียบร้อยแล้ว']);

    } catch (Exception $e) {
        // หากเกิดข้อผิดพลาด ให้ยกเลิกข้อมูลทั้งหมดที่เพิ่มเข้าไป
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>