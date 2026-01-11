<?php
// register_process.php
session_start();
require '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

// --- 1. รับค่าจากฟอร์ม ---
$existing_shop_id = !empty($_POST['existing_shop_id']) ? (int)$_POST['existing_shop_id'] : 0;

$prefix_id = $_POST['prefix_id'] ?? 100001;
$firstname = trim($_POST['firstname']);
$lastname = trim($_POST['lastname']);
$username = trim($_POST['username']);
$password = $_POST['password'];

$shop_name = trim($_POST['shop_name']);
$shop_tax_id = trim($_POST['shop_tax_id'] ?? '-');
$shop_phone = trim($_POST['shop_phone']);
$branch_name = trim($_POST['branch_name']);

// ที่อยู่ (สำหรับสาขาที่กำลังสมัคร)
$subdistrict_id = !empty($_POST['subdistrict_id']) ? $_POST['subdistrict_id'] : 100101; 
$home_no = trim($_POST['home_no'] ?? '-');

// เริ่ม Transaction
mysqli_begin_transaction($conn);

try {
    // --- 2. ตรวจสอบ Username ซ้ำ (Backend Check) ---
    $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt_check->bind_param("s", $username);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        throw new Exception("ชื่อผู้ใช้งาน '$username' ถูกใช้งานไปแล้ว");
    }
    $stmt_check->close();

    // --- 3. บันทึกที่อยู่ (Address) ---
    // (ที่อยู่นี้จะเป็นของ Branch ใหม่เสมอ)
    $res_addr = mysqli_query($conn, "SELECT MAX(address_id) as max_id FROM addresses");
    $new_address_id = (mysqli_fetch_assoc($res_addr)['max_id'] ?? 0) + 1;

    $sql_addr = "INSERT INTO addresses (address_id, home_no, subdistricts_subdistrict_id) VALUES (?, ?, ?)";
    $stmt_addr = $conn->prepare($sql_addr);
    $stmt_addr->bind_param("isi", $new_address_id, $home_no, $subdistrict_id);
    if (!$stmt_addr->execute()) throw new Exception("บันทึกที่อยู่ไม่สำเร็จ");
    $stmt_addr->close();

    // --- 4. จัดการ Shop Info ---
    $target_shop_id = 0;

    if ($existing_shop_id > 0) {
        // CASE A: เพิ่มสาขาในร้านเดิม
        // ตรวจสอบว่าร้านมีอยู่จริง
        $chk_shop = $conn->query("SELECT shop_id FROM shop_info WHERE shop_id = $existing_shop_id");
        if ($chk_shop->num_rows === 0) throw new Exception("ไม่พบข้อมูลร้านค้าหลัก");
        
        $target_shop_id = $existing_shop_id;
        
    } else {
        // CASE B: สร้างร้านใหม่
        // เช็คชื่อซ้ำอีกรอบกันพลาด
        $stmt_dup = $conn->prepare("SELECT shop_id FROM shop_info WHERE shop_name = ?");
        $stmt_dup->bind_param("s", $shop_name);
        $stmt_dup->execute();
        if ($stmt_dup->get_result()->num_rows > 0) throw new Exception("ชื่อร้านค้านี้มีอยู่แล้ว กรุณาเลือกชื่ออื่น หรือเลือกเชื่อมต่อร้านเดิม");
        
        $res_shop = mysqli_query($conn, "SELECT MAX(shop_id) as max_id FROM shop_info");
        $target_shop_id = (mysqli_fetch_assoc($res_shop)['max_id'] ?? 0) + 1;

        $sql_shop = "INSERT INTO shop_info (shop_id, shop_name, shop_phone, tax_id, Addresses_address_id, create_at, update_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt_shop = $conn->prepare($sql_shop);
        $stmt_shop->bind_param("isssi", $target_shop_id, $shop_name, $shop_phone, $shop_tax_id, $new_address_id);
        if (!$stmt_shop->execute()) throw new Exception("บันทึกร้านค้าไม่สำเร็จ");
        $stmt_shop->close();
    }

    // --- 5. จัดการ Branch ---
    // ตรวจสอบชื่อสาขาซ้ำในร้านเดียวกัน
    $stmt_br_chk = $conn->prepare("SELECT branch_id FROM branches WHERE shop_info_shop_id = ? AND branch_name = ?");
    $stmt_br_chk->bind_param("is", $target_shop_id, $branch_name);
    $stmt_br_chk->execute();
    if ($stmt_br_chk->get_result()->num_rows > 0) throw new Exception("ชื่อสาขา '$branch_name' มีอยู่แล้วในร้านนี้");
    
    $res_br = mysqli_query($conn, "SELECT MAX(branch_id) as max_id FROM branches");
    $new_branch_id = (mysqli_fetch_assoc($res_br)['max_id'] ?? 0) + 1;

    $sql_branch = "INSERT INTO branches (branch_id, branch_name, branch_phone, Addresses_address_id, shop_info_shop_id, create_at, update_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt_branch = $conn->prepare($sql_branch);
    $stmt_branch->bind_param("issii", $new_branch_id, $branch_name, $shop_phone, $new_address_id, $target_shop_id);
    if (!$stmt_branch->execute()) throw new Exception("บันทึกสาขาไม่สำเร็จ");
    $stmt_branch->close();

    // --- 6. จัดการ Department (Owner) ---
    // เช็คก่อนว่าร้านนี้มีแผนก 'เจ้าของร้านค้า' หรือยัง ถ้ามีให้ใช้ ID เดิม
    $target_dept_id = 0;
    $dept_name_owner = 'เจ้าของร้านค้า'; // หรือ 'Partner'
    
    $stmt_dept_chk = $conn->prepare("SELECT dept_id FROM departments WHERE shop_info_shop_id = ? AND dept_name = ? LIMIT 1");
    $stmt_dept_chk->bind_param("is", $target_shop_id, $dept_name_owner);
    $stmt_dept_chk->execute();
    $res_dept_chk = $stmt_dept_chk->get_result();
    
    if ($row_dept = $res_dept_chk->fetch_assoc()) {
        $target_dept_id = $row_dept['dept_id'];
    } else {
        // ถ้ายังไม่มี ให้สร้างใหม่
        $res_dept = mysqli_query($conn, "SELECT MAX(dept_id) as max_id FROM departments");
        $target_dept_id = (mysqli_fetch_assoc($res_dept)['max_id'] ?? 0) + 1;

        $sql_dept = "INSERT INTO departments (dept_id, shop_info_shop_id, dept_name) VALUES (?, ?, ?)";
        $stmt_dept = $conn->prepare($sql_dept);
        $stmt_dept->bind_param("iis", $target_dept_id, $target_shop_id, $dept_name_owner);
        $stmt_dept->execute();
        $stmt_dept->close();
    }
    $stmt_dept_chk->close();

    // --- 7. จัดการ User ---
    $res_user = mysqli_query($conn, "SELECT MAX(user_id) as max_id FROM users");
    $new_user_id = (mysqli_fetch_assoc($res_user)['max_id'] ?? 0) + 1;

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $sql_user = "INSERT INTO users (user_id, username, password, user_status) VALUES (?, ?, ?, 'Active')";
    $stmt_user = $conn->prepare($sql_user);
    $stmt_user->bind_param("iss", $new_user_id, $username, $hashed_password);
    if (!$stmt_user->execute()) throw new Exception("สร้างบัญชีผู้ใช้ไม่สำเร็จ");
    $stmt_user->close();

    // --- 8. จัดการ Role (Partner) ---
    $role_id = 0;
    $role_res = mysqli_query($conn, "SELECT role_id FROM roles WHERE role_name = 'Partner' LIMIT 1");
    if ($role_row = mysqli_fetch_assoc($role_res)) {
        $role_id = $role_row['role_id'];
    } else {
        $res_role_max = mysqli_query($conn, "SELECT MAX(role_id) as max_id FROM roles");
        $role_id = (mysqli_fetch_assoc($res_role_max)['max_id'] ?? 0) + 1;
        mysqli_query($conn, "INSERT INTO roles (role_id, role_name, role_desc) VALUES ($role_id, 'Partner', 'Business Owner')");
    }
    mysqli_query($conn, "INSERT INTO user_roles (roles_role_id, users_user_id) VALUES ($role_id, $new_user_id)");

    // --- 9. จัดการ Employee ---
    $res_emp_id = mysqli_query($conn, "SELECT MAX(emp_id) as max_id FROM employees");
    $new_emp_id = (mysqli_fetch_assoc($res_emp_id)['max_id'] ?? 0) + 1;

    // Run number รหัสพนักงาน
    $res_emp_code = mysqli_query($conn, "SELECT MAX(CAST(emp_code AS UNSIGNED)) as max_code FROM employees WHERE emp_code REGEXP '^[0-9]+$'");
    $row_emp_code = mysqli_fetch_assoc($res_emp_code);
    $new_emp_code = strval(($row_emp_code['max_code'] ?? 0) + 1);

    $sql_emp = "INSERT INTO employees (emp_id, emp_code, emp_national_id, firstname_th, lastname_th, 
                emp_phone_no, prefixs_prefix_id, Addresses_address_id, religions_religion_id, 
                departments_dept_id, branches_branch_id, users_user_id, emp_status) 
                VALUES (?, ?, '-', ?, ?, ?, ?, ?, 10, ?, ?, ?, 'Active')";
    $stmt_emp = $conn->prepare($sql_emp);
    // types: issssiiiii
    $stmt_emp->bind_param("issssiiiii", $new_emp_id, $new_emp_code, $firstname, $lastname, $shop_phone, $prefix_id, $new_address_id, $target_dept_id, $new_branch_id, $new_user_id);
    
    if (!$stmt_emp->execute()) throw new Exception("บันทึกข้อมูลพนักงานไม่สำเร็จ");
    $stmt_emp->close();

    // Commit Transaction
    mysqli_commit($conn);
    echo json_encode(['status' => 'success', 'message' => 'ลงทะเบียนสำเร็จ']);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>