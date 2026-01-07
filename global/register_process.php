<?php
// register_process.php
session_start();
require '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

// 1. รับค่าจากฟอร์ม
$prefix_id = $_POST['prefix_id'] ?? 100001; // ค่าเริ่มต้นถ้าระบุไม่ได้
$firstname = trim($_POST['firstname']);
$lastname = trim($_POST['lastname']);
$username = trim($_POST['username']);
$password = $_POST['password'];

$shop_name = trim($_POST['shop_name']);
$shop_tax_id = trim($_POST['shop_tax_id'] ?? '-'); // ถ้าไม่กรอกให้ขีดละไว้
$shop_phone = trim($_POST['shop_phone']);
$branch_name = trim($_POST['branch_name']);

// ที่อยู่ (Optional)
$subdistrict_id = !empty($_POST['subdistrict_id']) ? $_POST['subdistrict_id'] : 100101; // ถ้าไม่เลือก ใส่ค่า Default (เช่น เขตพระนคร)
$home_no = trim($_POST['home_no'] ?? '-');

// เริ่ม Transaction
mysqli_begin_transaction($conn);

try {
    // --- ตรวจสอบ Username ซ้ำ ---
    $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt_check->bind_param("s", $username);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        throw new Exception("ชื่อผู้ใช้งาน '$username' ถูกใช้งานไปแล้ว");
    }
    $stmt_check->close();

    // --- 2. จัดการ Address ---
    // หา Max ID
    $res_addr = mysqli_query($conn, "SELECT MAX(address_id) as max_id FROM addresses");
    $new_address_id = (mysqli_fetch_assoc($res_addr)['max_id'] ?? 0) + 1;

    // บันทึกที่อยู่ (ถ้าไม่ได้กรอก ก็จะบันทึกเป็นค่า Default/ว่าง เพื่อให้ ID ไหลต่อได้ตาม Constraint)
    $sql_addr = "INSERT INTO addresses (address_id, home_no, subdistricts_subdistrict_id) VALUES (?, ?, ?)";
    $stmt_addr = $conn->prepare($sql_addr);
    $stmt_addr->bind_param("isi", $new_address_id, $home_no, $subdistrict_id);
    if (!$stmt_addr->execute()) throw new Exception("บันทึกที่อยู่ไม่สำเร็จ");

    // --- 3. จัดการ Shop ---
    $res_shop = mysqli_query($conn, "SELECT MAX(shop_id) as max_id FROM shop_info");
    $new_shop_id = (mysqli_fetch_assoc($res_shop)['max_id'] ?? 0) + 1;

    $sql_shop = "INSERT INTO shop_info (shop_id, shop_name, shop_phone, tax_id, Addresses_address_id, create_at, update_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt_shop = $conn->prepare($sql_shop);
    $stmt_shop->bind_param("isssi", $new_shop_id, $shop_name, $shop_phone, $shop_tax_id, $new_address_id);
    if (!$stmt_shop->execute()) throw new Exception("บันทึกร้านค้าไม่สำเร็จ");

    // --- 4. จัดการ Branch ---
    $res_br = mysqli_query($conn, "SELECT MAX(branch_id) as max_id FROM branches");
    $new_branch_id = (mysqli_fetch_assoc($res_br)['max_id'] ?? 0) + 1;

    $sql_branch = "INSERT INTO branches (branch_id, branch_name, branch_phone, Addresses_address_id, shop_info_shop_id, create_at, update_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt_branch = $conn->prepare($sql_branch);
    $stmt_branch->bind_param("issii", $new_branch_id, $branch_name, $shop_phone, $new_address_id, $new_shop_id);
    $stmt_branch->execute();

    // --- 5. จัดการ Department ---
    $res_dept = mysqli_query($conn, "SELECT MAX(dept_id) as max_id FROM departments");
    $new_dept_id = (mysqli_fetch_assoc($res_dept)['max_id'] ?? 0) + 1;

    $sql_dept = "INSERT INTO departments (dept_id, shop_info_shop_id, dept_name) VALUES (?, ?, 'เจ้าของร้านค้า')";
    $stmt_dept = $conn->prepare($sql_dept);
    $stmt_dept->bind_param("ii", $new_dept_id, $new_shop_id);
    $stmt_dept->execute();

    // --- 6. จัดการ User ---
    $res_user = mysqli_query($conn, "SELECT MAX(user_id) as max_id FROM users");
    $new_user_id = (mysqli_fetch_assoc($res_user)['max_id'] ?? 0) + 1;

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $sql_user = "INSERT INTO users (user_id, username, password, user_status) VALUES (?, ?, ?, 'Active')";
    $stmt_user = $conn->prepare($sql_user);
    $stmt_user->bind_param("iss", $new_user_id, $username, $hashed_password);
    $stmt_user->execute();

    // --- 7. จัดการ Role ---
    // หา Role 'Partner' ถ้าไม่มีสร้างใหม่
    $role_res = mysqli_query($conn, "SELECT role_id FROM roles WHERE role_name = 'Partner' LIMIT 1");
    if ($role_row = mysqli_fetch_assoc($role_res)) {
        $role_id = $role_row['role_id'];
    } else {
        $res_role_max = mysqli_query($conn, "SELECT MAX(role_id) as max_id FROM roles");
        $role_id = (mysqli_fetch_assoc($res_role_max)['max_id'] ?? 0) + 1;
        mysqli_query($conn, "INSERT INTO roles (role_id, role_name, role_desc) VALUES ($role_id, 'Partner', 'Business Owner')");
    }
    mysqli_query($conn, "INSERT INTO user_roles (roles_role_id, users_user_id) VALUES ($role_id, $new_user_id)");

    // --- 8. จัดการ Employee ---
    $res_emp_id = mysqli_query($conn, "SELECT MAX(emp_id) as max_id FROM employees");
    $new_emp_id = (mysqli_fetch_assoc($res_emp_id)['max_id'] ?? 0) + 1;

    $res_emp_code = mysqli_query($conn, "SELECT MAX(CAST(emp_code AS UNSIGNED)) as max_code FROM employees WHERE emp_code REGEXP '^[0-9]+$'");
    $row_emp_code = mysqli_fetch_assoc($res_emp_code);
    $new_emp_code = strval(($row_emp_code['max_code'] ?? 0) + 1);

    // Default Religion = 10 (พุทธ) หรือแก้ตามความเหมาะสม
    $sql_emp = "INSERT INTO employees (emp_id, emp_code, emp_national_id, firstname_th, lastname_th, 
                emp_phone_no, prefixs_prefix_id, Addresses_address_id, religions_religion_id, 
                departments_dept_id, branches_branch_id, users_user_id, emp_status) 
                VALUES (?, ?, '-', ?, ?, ?, ?, ?, 10, ?, ?, ?, 'Active')";
    $stmt_emp = $conn->prepare($sql_emp);
    $stmt_emp->bind_param("issssiiiii", $new_emp_id, $new_emp_code, $firstname, $lastname, $shop_phone, $prefix_id, $new_address_id, $new_dept_id, $new_branch_id, $new_user_id);
    $stmt_emp->execute();

    mysqli_commit($conn);
    echo json_encode(['status' => 'success', 'message' => 'ลงทะเบียนสำเร็จ']);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>