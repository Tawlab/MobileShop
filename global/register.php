<?php
session_start();
require '../config/config.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. รับค่าจากฟอร์ม
    $shop_name = trim($_POST['shop_name']);
    $shop_phone = trim($_POST['shop_phone']);
    $branch_name = trim($_POST['branch_name']); // เพิ่มการรับชื่อสาขา
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);

    // เริ่มต้น Transaction เพื่อความปลอดภัยของข้อมูล
    mysqli_begin_transaction($conn);

    try {
        // --- ตรวจสอบ Username ซ้ำ ---
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            throw new Exception("ชื่อผู้ใช้งานนี้ถูกใช้งานไปแล้ว");
        }
        $stmt_check->close();

        // --- 2. จัดการ Address ID (Manual Increment) ---
        $res_addr = mysqli_query($conn, "SELECT MAX(address_id) as max_id FROM addresses");
        $row_addr = mysqli_fetch_assoc($res_addr);
        $new_address_id = ($row_addr['max_id'] ?? 0) + 1;

        $sql_addr = "INSERT INTO addresses (address_id, subdistricts_subdistrict_id) VALUES (?, 100101)";
        $stmt_addr = $conn->prepare($sql_addr);
        $stmt_addr->bind_param("i", $new_address_id);
        $stmt_addr->execute();

        // --- 3. จัดการ Shop ID (Manual Increment) ---
        $res_shop = mysqli_query($conn, "SELECT MAX(shop_id) as max_id FROM shop_info");
        $row_shop = mysqli_fetch_assoc($res_shop);
        $new_shop_id = ($row_shop['max_id'] ?? 0) + 1;

        $sql_shop = "INSERT INTO shop_info (shop_id, shop_name, shop_phone, Addresses_address_id, tax_id) VALUES (?, ?, ?, ?, '-')";
        $stmt_shop = $conn->prepare($sql_shop);
        $stmt_shop->bind_param("issi", $new_shop_id, $shop_name, $shop_phone, $new_address_id);
        $stmt_shop->execute();

        // --- 4. จัดการ Branch ID (Manual Increment + ใช้ชื่อสาขาที่กรอก) ---
        $res_br = mysqli_query($conn, "SELECT MAX(branch_id) as max_id FROM branches");
        $row_br = mysqli_fetch_assoc($res_br);
        $new_branch_id = ($row_br['max_id'] ?? 0) + 1;

        $sql_branch = "INSERT INTO branches (branch_id, branch_name, branch_phone, Addresses_address_id, shop_info_shop_id) VALUES (?, ?, ?, ?, ?)";
        $stmt_branch = $conn->prepare($sql_branch);
        $stmt_branch->bind_param("issii", $new_branch_id, $branch_name, $shop_phone, $new_address_id, $new_shop_id);
        $stmt_branch->execute();

        // --- 5. จัดการ Department ID (แผนกเจ้าของร้านค้า) ---
        $res_dept = mysqli_query($conn, "SELECT MAX(dept_id) as max_id FROM departments");
        $row_dept = mysqli_fetch_assoc($res_dept);
        $new_dept_id = ($row_dept['max_id'] ?? 0) + 1;

        $sql_dept = "INSERT INTO departments (dept_id, shop_info_shop_id, dept_name) VALUES (?, ?, 'เจ้าของร้านค้า')";
        $stmt_dept = $conn->prepare($sql_dept);
        $stmt_dept->bind_param("ii", $new_dept_id, $new_shop_id);
        $stmt_dept->execute();

        // --- 6. จัดการ User ID ---
        $res_user = mysqli_query($conn, "SELECT MAX(user_id) as max_id FROM users");
        $row_user = mysqli_fetch_assoc($res_user);
        $new_user_id = ($row_user['max_id'] ?? 0) + 1;

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql_user = "INSERT INTO users (user_id, username, password, user_status) VALUES (?, ?, ?, 'Active')";
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->bind_param("iss", $new_user_id, $username, $hashed_password);
        $stmt_user->execute();

        // --- 7. จัดการ Role (Partner) ---
        $role_res = mysqli_query($conn, "SELECT role_id FROM roles WHERE role_name = 'Partner' LIMIT 1");
        if ($role_row = mysqli_fetch_assoc($role_res)) {
            $role_id = $role_row['role_id'];
        } else {
            $res_role_max = mysqli_query($conn, "SELECT MAX(role_id) as max_id FROM roles");
            $role_id = (mysqli_fetch_assoc($res_role_max)['max_id'] ?? 0) + 1;
            mysqli_query($conn, "INSERT INTO roles (role_id, role_name, role_desc) VALUES ($role_id, 'User', 'User')");
        }
        mysqli_query($conn, "INSERT INTO user_roles (roles_role_id, users_user_id) VALUES ($role_id, $new_user_id)");

        // --- 8. จัดการ Employee (รหัสพนักงานเป็นตัวเลขเพิ่มขึ้นทีละ 1) ---
        $res_emp_id = mysqli_query($conn, "SELECT MAX(emp_id) as max_id FROM employees");
        $new_emp_id = (mysqli_fetch_assoc($res_emp_id)['max_id'] ?? 0) + 1;

        $res_emp_code = mysqli_query($conn, "SELECT MAX(CAST(emp_code AS UNSIGNED)) as max_code FROM employees WHERE emp_code REGEXP '^[0-9]+$'");
        $row_emp_code = mysqli_fetch_assoc($res_emp_code);
        $new_emp_code = strval(($row_emp_code['max_code'] ?? 0) + 1);

        // บันทึกข้อมูลพนักงาน (อ้างอิง Prefix 100001=นาย, Religion 10=พุทธ)
        $sql_emp = "INSERT INTO employees (emp_id, emp_code, emp_national_id, firstname_th, lastname_th, 
                    emp_phone_no, prefixs_prefix_id, Addresses_address_id, religions_religion_id, 
                    departments_dept_id, branches_branch_id, users_user_id, emp_status, emp_gender) 
                    VALUES (?, ?, '-', ?, ?, ?, 100001, ?, 10, ?, ?, ?, 'Active', 'Male')";
        $stmt_emp = $conn->prepare($sql_emp);
        $stmt_emp->bind_param("issssiiii", $new_emp_id, $new_emp_code, $firstname, $lastname, $shop_phone, $new_address_id, $new_dept_id, $new_branch_id, $new_user_id);
        $stmt_emp->execute();

        mysqli_commit($conn);
        $success_message = 'ลงทะเบียนสำเร็จ! คุณสามารถเข้าสู่ระบบได้ทันที';

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_message = 'การลงทะเบียนล้มเหลว: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก Partner - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f8f9fa; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .card-header { background-color: #198754; color: white; border-radius: 15px 15px 0 0 !important; }
        .btn-success { background-color: #198754; border: none; border-radius: 10px; padding: 12px; font-weight: 600; }
        .section-title { color: #198754; font-weight: 600; border-bottom: 2px solid #e9ecef; padding-bottom: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header text-center py-4">
                    <h3 class="mb-0"><i class="bi bi-person-plus-fill me-2"></i>Register Partner Account</h3>
                </div>
                <div class="card-body p-4 p-md-5">
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?= $error_message ?></div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <?= $success_message ?> <br>
                            <a href="../global/login.php" class="btn btn-sm btn-outline-success mt-2">ไปหน้าเข้าสู่ระบบ</a>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <div class="section-title"><i class="bi bi-shop me-2"></i>ข้อมูลร้านค้าและสาขา</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">ชื่อร้านค้า <span class="text-danger">*</span></label>
                                <input type="text" name="shop_name" class="form-control" required placeholder="เช่น ขุมทรัพย์ โมบาย">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ชื่อสาขาแรก <span class="text-danger">*</span></label>
                                <input type="text" name="branch_name" class="form-control" required placeholder="เช่น สำนักงานใหญ่ หรือ สาขากรุงเทพ">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">เบอร์โทรศัพท์ติดต่อ <span class="text-danger">*</span></label>
                                <input type="text" name="shop_phone" class="form-control" required placeholder="0XXXXXXXXX">
                            </div>
                        </div>

                        <div class="section-title"><i class="bi bi-person-badge me-2"></i>ข้อมูลเจ้าของร้าน</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">ชื่อจริง <span class="text-danger">*</span></label>
                                <input type="text" name="firstname" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                <input type="text" name="lastname" class="form-control" required>
                            </div>
                        </div>

                        <div class="section-title"><i class="bi bi-shield-lock me-2"></i>ข้อมูลเข้าสู่ระบบ</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">ชื่อผู้ใช้งาน (Username) <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">รหัสผ่าน (Password) <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" required minlength="6">
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-5">
                            <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-2"></i>ยืนยันการลงทะเบียน</button>
                            <a href="../global/login.php" class="btn btn-link text-decoration-none text-muted">มีบัญชีอยู่แล้ว? เข้าสู่ระบบ</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // การตรวจสอบความถูกต้องของฟอร์ม (Bootstrap Validation)
    (() => {
        'use strict'
        const forms = document.querySelectorAll('.needs-validation')
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
    })()
</script>

</body>
</html>