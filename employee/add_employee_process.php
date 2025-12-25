<?php
// ไฟล์: add_employee_process.php
ob_start();
session_start();
require '../config/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_shop_id = $_SESSION['shop_id'];

// --- ตรวจสอบสิทธิ์ Admin ---
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

// Function หา ID ถัดไป
function getNextId($conn, $table, $column) {
    $sql = "SELECT MAX($column) as max_id FROM $table";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return ($row['max_id']) ? $row['max_id'] + 1 : 1;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_clean();
    header('Content-Type: application/json');

    try {
        // [1] จัดการ Shop ID และ Role ID ตามสิทธิ์
        if ($is_admin) {
            // ถ้าเป็น Admin: รับค่าจากที่เลือกมา
            if (empty($_POST['shop_id'])) throw new Exception("กรุณาเลือกร้านค้า");
            $target_shop_id = (int)$_POST['shop_id'];
            $role_id = (int)$_POST['role_id'];
        } else {
            // ถ้าเป็น User ทั่วไป: บังคับใช้ร้านตัวเอง และสิทธิ์ User
            $target_shop_id = $current_shop_id;
            
            // หา ID ของ Role 'User'
            $role_res = $conn->query("SELECT role_id FROM roles WHERE role_name = 'User' LIMIT 1");
            if ($role_res->num_rows == 0) throw new Exception("ไม่พบสิทธิ์ User ในระบบ");
            $role_id = $role_res->fetch_assoc()['role_id'];
        }

        // รับค่าอื่นๆ
        $emp_code = trim($_POST['emp_code']);
        $emp_national_id = trim($_POST['emp_national_id']);
        $prefixs_prefix_id = (int)$_POST['prefixs_prefix_id'];
        $firstname_th = trim($_POST['firstname_th']);
        $lastname_th = trim($_POST['lastname_th']);
        $firstname_en = trim($_POST['firstname_en']);
        $lastname_en = trim($_POST['lastname_en']);
        $emp_phone_no = trim($_POST['emp_phone_no']);
        $emp_email = trim($_POST['emp_email']);
        $emp_line_id = trim($_POST['emp_line_id']);
        $emp_birthday = !empty($_POST['emp_birthday']) ? $_POST['emp_birthday'] : NULL;
        $emp_gender = $_POST['emp_gender'] ?? '';
        $emp_status = $_POST['emp_status'] ?? 'Active';
        
        $religions_religion_id = (int)$_POST['religions_religion_id'];
        $departments_dept_id = (int)$_POST['departments_dept_id'];
        $branches_branch_id = (int)$_POST['branches_branch_id'];

        // Address
        $home_no = trim($_POST['home_no']);
        $moo = trim($_POST['moo']);
        $soi = trim($_POST['soi']);
        $road = trim($_POST['road']);
        $village = trim($_POST['village']);
        $subdistricts_subdistrict_id = (int)$_POST['subdistricts_subdistrict_id'];

        // User Account
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $user_status = $_POST['user_status'] ?? 'Active';

        // Validation
        if (empty($emp_code)) throw new Exception("กรุณากรอกรหัสพนักงาน");
        if (empty($emp_national_id) || strlen($emp_national_id) != 13) throw new Exception("เลขบัตรประชาชนต้องมี 13 หลัก");
        if ($password !== $confirm_password) throw new Exception("รหัสผ่านไม่ตรงกัน");

        // Check Duplicate
        $check_sql = "SELECT emp_id FROM employees WHERE emp_code = ? OR emp_national_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ss", $emp_code, $emp_national_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) throw new Exception("รหัสพนักงานหรือเลขบัตรประชาชนซ้ำ");
        $stmt->close();

        $check_user = "SELECT user_id FROM users WHERE username = ?";
        $stmt = $conn->prepare($check_user);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) throw new Exception("Username '$username' ถูกใช้งานแล้ว");
        $stmt->close();

        // Image Upload
        $emp_image_filename = NULL;
        if (isset($_FILES['emp_image']) && $_FILES['emp_image']['error'] == 0) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array(mime_content_type($_FILES['emp_image']['tmp_name']), $allowed)) throw new Exception("ไฟล์รูปภาพไม่ถูกต้อง");
            $ext = pathinfo($_FILES['emp_image']['name'], PATHINFO_EXTENSION);
            $emp_image_filename = "emp_" . $emp_code . "_" . time() . "." . $ext;
            move_uploaded_file($_FILES['emp_image']['tmp_name'], "../uploads/employees/" . $emp_image_filename);
        }

        // Transaction
        $conn->begin_transaction();

        // 1. Address
        $new_addr_id = getNextId($conn, 'addresses', 'address_id');
        $sql_addr = "INSERT INTO addresses (address_id, home_no, moo, soi, road, village, subdistricts_subdistrict_id) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql_addr);
        $stmt->bind_param("isssssi", $new_addr_id, $home_no, $moo, $soi, $road, $village, $subdistricts_subdistrict_id);
        if (!$stmt->execute()) throw new Exception("บันทึกที่อยู่ไม่สำเร็จ: " . $stmt->error);
        $stmt->close();

        // 2. User
        $new_user_id = getNextId($conn, 'users', 'user_id');
        $hashed_pw = hashPassword($password);
        $sql_user = "INSERT INTO users (user_id, username, password, user_status) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql_user);
        $stmt->bind_param("isss", $new_user_id, $username, $hashed_pw, $user_status);
        if (!$stmt->execute()) throw new Exception("บันทึกบัญชีผู้ใช้ไม่สำเร็จ");
        $stmt->close();

        // 3. Employee (ใช้ $target_shop_id ที่กำหนดไว้ตอนต้น)
        // หมายเหตุ: ในตาราง employees ปกติจะมี shop_info_shop_id หรืออ้างอิงผ่าน department/branch
        // ถ้าตาราง employees ไม่มีฟิลด์ shop_id โดยตรง มันจะอ้างอิงผ่าน branch/dept ที่เราเลือกซึ่งผูกกับ shop อยู่แล้ว
        $new_emp_id = getNextId($conn, 'employees', 'emp_id');
        $sql_emp = "INSERT INTO employees (
            emp_id, emp_code, emp_national_id, firstname_th, lastname_th, firstname_en, lastname_en,
            emp_phone_no, emp_email, emp_line_id, emp_birthday, emp_gender, emp_status,
            prefixs_prefix_id, Addresses_address_id, religions_religion_id, departments_dept_id, 
            branches_branch_id, users_user_id, emp_image, create_at, update_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $conn->prepare($sql_emp);
        $stmt->bind_param(
            "issssssssssssiiiiiis",
            $new_emp_id, $emp_code, $emp_national_id, $firstname_th, $lastname_th, $firstname_en, $lastname_en,
            $emp_phone_no, $emp_email, $emp_line_id, $emp_birthday, $emp_gender, $emp_status,
            $prefixs_prefix_id, $new_addr_id, $religions_religion_id, $departments_dept_id,
            $branches_branch_id, $new_user_id, $emp_image_filename
        );
        if (!$stmt->execute()) throw new Exception("บันทึกข้อมูลพนักงานไม่สำเร็จ: " . $stmt->error);
        $stmt->close();

        // 4. User Role (ใช้ $role_id ที่กำหนดไว้ตอนต้น)
        $sql_role = "INSERT INTO user_roles (roles_role_id, users_user_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql_role);
        $stmt->bind_param("ii", $role_id, $new_user_id);
        if (!$stmt->execute()) throw new Exception("บันทึกสิทธิ์ผู้ใช้งานไม่สำเร็จ");
        $stmt->close();

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'เพิ่มพนักงานเรียบร้อยแล้ว']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
?>