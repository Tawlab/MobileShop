<?php
session_start();
require '../config/config.php';
require '../config/load_theme.php';

// ตรวจสอบสิทธิ์ (ต้องมีสิทธิ์เพิ่ม User หรือ Employee)
checkPageAccess($conn, 'menu_manage_users');

$current_shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// ตรวจสอบว่าเป็น Admin หรือไม่
$is_super_admin = false;
$chk_sql = "SELECT r.role_name FROM roles r JOIN user_roles ur ON r.role_id = ur.roles_role_id WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt = $conn->prepare($chk_sql)) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $is_super_admin = true;
    $stmt->close();
}

// ==========================================================================================
// [1] AJAX HANDLER: สำหรับดึงข้อมูล Branch และ Department ตาม Shop ID
// ==========================================================================================
if (isset($_GET['ajax_action'])) {
    $action = $_GET['ajax_action'];
    $target_shop_id = isset($_GET['shop_id']) ? intval($_GET['shop_id']) : 0;

    // ถ้าไม่ใช่ Admin ห้ามดึงข้อมูลร้านอื่น
    if (!$is_super_admin && $target_shop_id != $current_shop_id) {
        echo json_encode([]); exit;
    }

    if ($action == 'get_branches') {
        $sql = "SELECT branch_id, branch_name FROM branches WHERE shop_info_shop_id = ? ORDER BY branch_name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $target_shop_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = [];
        while ($row = $res->fetch_assoc()) $data[] = $row;
        echo json_encode($data);
    } 
    elseif ($action == 'get_departments') {
        $sql = "SELECT dept_id, dept_name FROM departments WHERE shop_info_shop_id = ? ORDER BY dept_name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $target_shop_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = [];
        while ($row = $res->fetch_assoc()) $data[] = $row;
        echo json_encode($data);
    }
    exit; // จบการทำงานส่วน AJAX
}

// ==========================================================================================
// [2] FORM SUBMISSION: บันทึกข้อมูล
// ==========================================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // รับค่าจากฟอร์ม
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $shop_select = isset($_POST['shop_id']) ? intval($_POST['shop_id']) : $current_shop_id;
    $branch_id = intval($_POST['branch_id']);
    $dept_id = intval($_POST['dept_id']);
    $role_id = intval($_POST['role_id']);
    
    $prefix_id = intval($_POST['prefix_id']);
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $gender = $_POST['gender'];
    $national_id = trim($_POST['national_id']);

    $errors = [];

    // Validation
    if (empty($username) || empty($password)) $errors[] = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
    if ($password !== $confirm_password) $errors[] = "รหัสผ่านยืนยันไม่ตรงกัน";
    if (strlen($password) < 6) $errors[] = "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
    if (empty($firstname) || empty($lastname)) $errors[] = "กรุณากรอกชื่อ-นามสกุล";
    
    // เช็ค Username ซ้ำ
    $chk_user = $conn->query("SELECT user_id FROM users WHERE username = '$username'");
    if ($chk_user->num_rows > 0) $errors[] = "ชื่อผู้ใช้ '$username' ถูกใช้งานแล้ว";

    if (empty($errors)) {
        // เริ่ม Transaction (ต้องสำเร็จทั้ง 3 ตาราง หรือไม่ก็ไม่บันทึกเลย)
        $conn->begin_transaction();

        try {
            // 1. สร้าง User
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql_user = "INSERT INTO users (username, password, user_status, create_at, update_at) VALUES (?, ?, 'Active', NOW(), NOW())";
            $stmt = $conn->prepare($sql_user);
            $stmt->bind_param("ss", $username, $hashed_password);
            $stmt->execute();
            $new_user_id = $conn->insert_id;
            $stmt->close();

            // 2. สร้าง Employee
            // (หารหัสพนักงานถัดไปแบบง่ายๆ หรือใช้ Timestamp)
            $emp_code = 'EMP' . date('ym') . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT); 
            $default_address_id = 0; // ใช้ Address 0 เป็นค่าเริ่มต้นไปก่อน
            $default_religion = 10; // ศาสนาพุทธเป็นค่าเริ่มต้น (แก้ไขได้ถ้าต้องการ)

            $sql_emp = "INSERT INTO employees (
                            emp_code, emp_national_id, firstname_th, lastname_th, 
                            emp_phone_no, emp_email, emp_gender, emp_status, 
                            prefixs_prefix_id, Addresses_address_id, religions_religion_id, 
                            departments_dept_id, branches_branch_id, users_user_id, create_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($sql_emp);
            $stmt->bind_param("sssssssiiiiii", 
                $emp_code, $national_id, $firstname, $lastname, 
                $phone, $email, $gender, 
                $prefix_id, $default_address_id, $default_religion, 
                $dept_id, $branch_id, $new_user_id
            );
            $stmt->execute();
            $stmt->close();

            // 3. กำหนด Role
            $sql_role = "INSERT INTO user_roles (roles_role_id, users_user_id, create_at) VALUES (?, ?, NOW())";
            $stmt = $conn->prepare($sql_role);
            $stmt->bind_param("ii", $role_id, $new_user_id);
            $stmt->execute();
            $stmt->close();

            // 4. เพิ่ม Config เริ่มต้น
            $sql_conf = "INSERT INTO systemconfig (user_id, theme_color, background_color, text_color, font_style, header_bg_color, header_text_color, btn_add_color, btn_edit_color, btn_delete_color) 
                         VALUES (?, '#198754', '#ffffff', '#000000', 'Prompt', '#198754', '#ffffff', '#198754', '#ffc107', '#dc3545')";
            $stmt = $conn->prepare($sql_conf);
            $stmt->bind_param("i", $new_user_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $_SESSION['success'] = "เพิ่มผู้ใช้งานและพนักงานใหม่เรียบร้อยแล้ว";
            header("Location: user_list.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// ==========================================================================================
// [3] PREPARE DATA FOR VIEW: เตรียมข้อมูลสำหรับแสดงผล (Dropdown)
// ==========================================================================================
$prefixes = $conn->query("SELECT prefix_id, prefix_th FROM prefixs WHERE is_active = 1");
$roles = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_name");
$shops = ($is_super_admin) ? $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name") : null;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เพิ่มผู้ใช้งานใหม่ - Mobile Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: <?= $background_color ?>; font-family: '<?= $font_style ?>', sans-serif; color: <?= $text_color ?>; }
        .card { border: none; border-radius: 15px; box-shadow: 0 0 20px rgba(0,0,0,0.05); }
        .card-header { background: linear-gradient(135deg, <?= $theme_color ?>, #14532d); color: white; border-radius: 15px 15px 0 0 !important; padding: 1.5rem; }
        .form-section-title { font-size: 1.1rem; font-weight: bold; color: <?= $theme_color ?>; border-bottom: 2px solid <?= $theme_color ?>20; padding-bottom: 10px; margin-bottom: 20px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="mb-0 text-white"><i class="bi bi-person-plus-fill me-2"></i>เพิ่มบัญชีผู้ใช้และพนักงานใหม่</h4>
                            </div>
                            <div class="card-body p-4">

                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger shadow-sm rounded-3">
                                        <ul class="mb-0">
                                            <?php foreach ($errors as $err): ?><li><?= $err ?></li><?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="user_add.php" class="needs-validation" novalidate>
                                    
                                    <div class="form-section-title"><i class="bi bi-shield-lock me-2"></i>ข้อมูลบัญชี (Login Details)</div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">ชื่อผู้ใช้ (Username) <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="ตั้งชื่อผู้ใช้สำหรับล็อกอิน">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">รหัสผ่าน (Password) <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" name="password" required placeholder="อย่างน้อย 6 ตัวอักษร">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">ยืนยันรหัสผ่าน <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" name="confirm_password" required>
                                        </div>
                                    </div>

                                    <div class="form-section-title"><i class="bi bi-building me-2"></i>ข้อมูลสังกัดและการทำงาน</div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">สังกัดร้านค้า (Shop)</label>
                                            <?php if ($is_super_admin): ?>
                                                <select class="form-select" name="shop_id" id="shopSelect" required onchange="loadShopData(this.value)">
                                                    <option value="">-- เลือกร้านค้า --</option>
                                                    <?php while($s = $shops->fetch_assoc()): ?>
                                                        <option value="<?= $s['shop_id'] ?>"><?= $s['shop_name'] ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            <?php else: ?>
                                                <input type="text" class="form-control bg-light" value="<?= $_SESSION['shop_name'] ?? 'ร้านค้าของคุณ' ?>" readonly>
                                                <input type="hidden" name="shop_id" id="shopSelect" value="<?= $current_shop_id ?>">
                                            <?php endif; ?>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">สาขา (Branch) <span class="text-danger">*</span></label>
                                            <select class="form-select" name="branch_id" id="branchSelect" required>
                                                <option value="">-- กรุณาเลือกร้านค้าก่อน --</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">แผนก (Department) <span class="text-danger">*</span></label>
                                            <select class="form-select" name="dept_id" id="deptSelect" required>
                                                <option value="">-- กรุณาเลือกร้านค้าก่อน --</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">บทบาทผู้ใช้งาน (Role) <span class="text-danger">*</span></label>
                                            <select class="form-select" name="role_id" required>
                                                <option value="">-- เลือกบทบาท --</option>
                                                <?php while($r = $roles->fetch_assoc()): ?>
                                                    <option value="<?= $r['role_id'] ?>"><?= $r['role_name'] ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-section-title"><i class="bi bi-person-lines-fill me-2"></i>ข้อมูลส่วนตัว (Personal Info)</div>
                                    <div class="row g-3">
                                        <div class="col-md-2">
                                            <label class="form-label fw-bold">คำนำหน้า</label>
                                            <select class="form-select" name="prefix_id" required>
                                                <?php while($p = $prefixes->fetch_assoc()): ?>
                                                    <option value="<?= $p['prefix_id'] ?>"><?= $p['prefix_th'] ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label fw-bold">ชื่อจริง (ไทย) <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="firstname" required value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label fw-bold">นามสกุล (ไทย) <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="lastname" required value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">เลขบัตรประชาชน</label>
                                            <input type="text" class="form-control" name="national_id" maxlength="13" value="<?= htmlspecialchars($_POST['national_id'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">เบอร์โทรศัพท์ <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="phone" required maxlength="10" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">อีเมล</label>
                                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">เพศ</label>
                                            <select class="form-select" name="gender" required>
                                                <option value="Male">ชาย</option>
                                                <option value="Female">หญิง</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end gap-2 mt-5">
                                        <a href="user_list.php" class="btn btn-light rounded-pill px-4">ยกเลิก</a>
                                        <button type="submit" class="btn btn-success rounded-pill px-5 fw-bold shadow-sm">
                                            <i class="bi bi-save me-2"></i>บันทึกข้อมูล
                                        </button>
                                    </div>

                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ฟังก์ชันโหลด Branch และ Department เมื่อเลือกร้าน
        function loadShopData(shopId) {
            const branchSelect = document.getElementById('branchSelect');
            const deptSelect = document.getElementById('deptSelect');

            if (!shopId) {
                branchSelect.innerHTML = '<option value="">-- กรุณาเลือกร้านค้าก่อน --</option>';
                deptSelect.innerHTML = '<option value="">-- กรุณาเลือกร้านค้าก่อน --</option>';
                return;
            }

            // โหลดสาขา
            fetch(`user_add.php?ajax_action=get_branches&shop_id=${shopId}`)
                .then(res => res.json())
                .then(data => {
                    let opts = '<option value="">-- เลือกสาขา --</option>';
                    if (data.length === 0) opts = '<option value="">ไม่พบสาขาในร้านนี้</option>';
                    data.forEach(item => {
                        opts += `<option value="${item.branch_id}">${item.branch_name}</option>`;
                    });
                    branchSelect.innerHTML = opts;
                });

            // โหลดแผนก
            fetch(`user_add.php?ajax_action=get_departments&shop_id=${shopId}`)
                .then(res => res.json())
                .then(data => {
                    let opts = '<option value="">-- เลือกแผนก --</option>';
                    if (data.length === 0) opts = '<option value="">ไม่พบแผนกในร้านนี้</option>';
                    data.forEach(item => {
                        opts += `<option value="${item.dept_id}">${item.dept_name}</option>`;
                    });
                    deptSelect.innerHTML = opts;
                });
        }

        // โหลดข้อมูลอัตโนมัติหากเป็น User ทั่วไป (มี Shop ID เดียว)
        document.addEventListener('DOMContentLoaded', () => {
            const shopSelect = document.getElementById('shopSelect');
            if (shopSelect && shopSelect.tagName === 'INPUT') { // ถ้าเป็น Input Hidden/Readonly
                loadShopData(shopSelect.value);
            }
        });

        // Bootstrap Validation
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