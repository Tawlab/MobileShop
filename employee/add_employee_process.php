<?php
ob_start();
session_start();
require '../config/config.php';

header('Content-Type: application/json');

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

// ฟังก์ชันตรวจสอบเลขบัตรประชาชน
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

// ฟังก์ชันหา ID ถัดไป
function getNextId($conn, $table, $column)
{
    $sql = "SELECT MAX($column) as max_id FROM $table";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return ($row['max_id']) ? $row['max_id'] + 1 : 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // จัดการ Role และ Branch ตามสิทธิ์
        if ($is_admin) {
            if (empty($_POST['shop_id'])) throw new Exception("กรุณาเลือกร้านค้า");
            $target_branch_id = (int)$_POST['branches_branch_id'];
            $role_id = (int)$_POST['role_id'];
        } else {
            // ดึงสาขาของผู้ที่กำลังเพิ่มข้อมูล
            $stmt_b = $conn->prepare("SELECT branches_branch_id FROM employees WHERE users_user_id = ?");
            $stmt_b->bind_param("i", $current_user_id);
            $stmt_b->execute();
            $target_branch_id = $stmt_b->get_result()->fetch_assoc()['branches_branch_id'];

            // บังคับสิทธิ์เป็น User
            $role_res = $conn->query("SELECT role_id FROM roles WHERE role_name = 'User' LIMIT 1");
            $role_id = $role_res->fetch_assoc()['role_id'];
        }

        // รับค่าและตรวจสอบข้อมูลพื้นฐาน
        $emp_code = trim($_POST['emp_code']);
        $emp_national_id = trim($_POST['emp_national_id']);
        $firstname_th = trim($_POST['firstname_th']);
        $lastname_th = trim($_POST['lastname_th']);
        $firstname_en = trim($_POST['firstname_en']);
        $lastname_en = trim($_POST['lastname_en']);
        $emp_phone_no = trim($_POST['emp_phone_no']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        // Validations 
        if (!checkThaiID($emp_national_id)) throw new Exception("เลขบัตรประชาชนไม่ถูกต้อง");
        if (!preg_match("/^[ก-๙\s]+$/u", $firstname_th)) throw new Exception("ชื่อไทยต้องเป็นภาษาไทยเท่านั้น");
        if (!preg_match("/^[ก-๙\s]+$/u", $lastname_th)) throw new Exception("นามสกุลไทยต้องเป็นภาษาไทยเท่านั้น");
        if (!empty($firstname_en) && !preg_match("/^[a-zA-Z\s]+$/", $firstname_en)) throw new Exception("ชื่ออังกฤษต้องเป็นภาษาอังกฤษเท่านั้น");
        if (!preg_match("/^(06|08|09)\d{8}$/", $emp_phone_no)) throw new Exception("รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง");
        if ($password !== $_POST['confirm_password']) throw new Exception("รหัสผ่านไม่ตรงกัน");

        // ตรวจสอบข้อมูลซ้ำ
        $stmt = $conn->prepare("SELECT emp_id FROM employees WHERE emp_code = ? OR emp_national_id = ?");
        $stmt->bind_param("ss", $emp_code, $emp_national_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) throw new Exception("รหัสพนักงานหรือเลขบัตรประชาชนซ้ำในระบบ");

        // Image Upload
        $emp_image_filename = NULL;
        if (isset($_FILES['emp_image']) && $_FILES['emp_image']['error'] == 0) {
            $ext = pathinfo($_FILES['emp_image']['name'], PATHINFO_EXTENSION);
            $emp_image_filename = "emp_" . $emp_code . "_" . time() . "." . $ext;
            move_uploaded_file($_FILES['emp_image']['tmp_name'], "../uploads/employees/" . $emp_image_filename);
        }

        // เริ่มบันทึกข้อมูล (Transaction)
        $conn->begin_transaction();

        // บันทึกที่อยู่
        $new_addr_id = getNextId($conn, 'addresses', 'address_id');
        $sql_addr = "INSERT INTO addresses (address_id, home_no, moo, soi, road, village, subdistricts_subdistrict_id) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_addr = $conn->prepare($sql_addr);
        $stmt_addr->bind_param("isssssi", $new_addr_id, $_POST['home_no'], $_POST['moo'], $_POST['soi'], $_POST['road'], $_POST['village'], $_POST['subdistricts_subdistrict_id']);
        $stmt_addr->execute();

        // บันทึกบัญชีผู้ใช้
        $new_user_id = getNextId($conn, 'users', 'user_id');
        $hashed_pw = password_hash($password, PASSWORD_DEFAULT);
        $stmt_user = $conn->prepare("INSERT INTO users (user_id, username, password, user_status) VALUES (?, ?, ?, 'Active')");
        $stmt_user->bind_param("iss", $new_user_id, $username, $hashed_pw);
        $stmt_user->execute();

        // บันทึกข้อมูลพนักงาน
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
            $_POST['emp_email'],
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

        // บันทึกสิทธิ์
        $stmt_role = $conn->prepare("INSERT INTO user_roles (roles_role_id, users_user_id) VALUES (?, ?)");
        $stmt_role->bind_param("ii", $role_id, $new_user_id);
        $stmt_role->execute();

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'เพิ่มพนักงานใหม่เรียบร้อยแล้ว']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
