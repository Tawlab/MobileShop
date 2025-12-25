<?php
session_start();
require '../config/config.php';
require '../config/load_theme.php';

// ตรวจสอบสิทธิ์ (ต้องมีสิทธิ์จัดการ User)
checkPageAccess($conn, 'menu_manage_users');

$current_shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// ตรวจสอบสิทธิ์ Admin
$is_super_admin = false;
$chk_sql = "SELECT r.role_name FROM roles r JOIN user_roles ur ON r.role_id = ur.roles_role_id WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt = $conn->prepare($chk_sql)) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $is_super_admin = true;
    $stmt->close();
}

// รับ ID ที่ต้องการแก้ไข
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: user_list.php");
    exit;
}
$edit_id = intval($_GET['id']);

// ==========================================================================================
// [1] AJAX HANDLER: สำหรับโหลด Dropdown
// ==========================================================================================
if (isset($_GET['ajax_action'])) {
    $action = $_GET['ajax_action'];
    $target_shop_id = isset($_GET['shop_id']) ? intval($_GET['shop_id']) : 0;

    // Security Check for AJAX
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
    exit;
}

// ==========================================================================================
// [2] FORM SUBMISSION: บันทึกการแก้ไข
// ==========================================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $national_id = trim($_POST['national_id']);
    $gender = $_POST['gender'];
    
    $shop_id = isset($_POST['shop_id']) ? intval($_POST['shop_id']) : $current_shop_id;
    $branch_id = intval($_POST['branch_id']);
    $dept_id = intval($_POST['dept_id']);
    $role_id = intval($_POST['role_id']);
    $user_status = $_POST['user_status'];
    $prefix_id = intval($_POST['prefix_id']);

    $errors = [];

    // Validation
    if (empty($firstname) || empty($lastname)) $errors[] = "กรุณากรอกชื่อ-นามสกุล";
    if (empty($branch_id) || empty($dept_id)) $errors[] = "กรุณาเลือกสาขาและแผนก";

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // 1. อัปเดตข้อมูล User (Status)
            $sql_user = "UPDATE users SET user_status = ?, update_at = NOW() WHERE user_id = ?";
            $stmt = $conn->prepare($sql_user);
            $stmt->bind_param("si", $user_status, $edit_id);
            $stmt->execute();
            $stmt->close();

            // 2. อัปเดตข้อมูล Employee
            $sql_emp = "UPDATE employees SET 
                        firstname_th = ?, lastname_th = ?, emp_phone_no = ?, emp_email = ?, 
                        emp_national_id = ?, emp_gender = ?, prefixs_prefix_id = ?,
                        branches_branch_id = ?, departments_dept_id = ?, update_at = NOW()
                        WHERE users_user_id = ?";
            $stmt = $conn->prepare($sql_emp);
            $stmt->bind_param("ssssssiiii", 
                $firstname, $lastname, $phone, $email, 
                $national_id, $gender, $prefix_id,
                $branch_id, $dept_id, $edit_id
            );
            $stmt->execute();
            $stmt->close();

            // 3. อัปเดต Role (ลบเก่าแล้วเพิ่มใหม่ หรือ อัปเดต)
            // เช็คว่ามี role เดิมไหม
            $check_role = $conn->query("SELECT * FROM user_roles WHERE users_user_id = $edit_id");
            if ($check_role->num_rows > 0) {
                $sql_role = "UPDATE user_roles SET roles_role_id = ? WHERE users_user_id = ?";
                $stmt = $conn->prepare($sql_role);
                $stmt->bind_param("ii", $role_id, $edit_id);
                $stmt->execute();
            } else {
                $sql_role = "INSERT INTO user_roles (roles_role_id, users_user_id, create_at) VALUES (?, ?, NOW())";
                $stmt = $conn->prepare($sql_role);
                $stmt->bind_param("ii", $role_id, $edit_id);
                $stmt->execute();
            }
            $stmt->close();

            $conn->commit();
            $_SESSION['success'] = "บันทึกการแก้ไขเรียบร้อยแล้ว";
            header("Location: user_list.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// ==========================================================================================
// [3] LOAD DATA: ดึงข้อมูลเดิมมาแสดง
// ==========================================================================================
$sql = "SELECT u.username, u.user_status, 
               e.*,
               ur.roles_role_id,
               b.shop_info_shop_id as current_shop_id
        FROM users u 
        LEFT JOIN employees e ON u.user_id = e.users_user_id
        LEFT JOIN user_roles ur ON u.user_id = ur.users_user_id
        LEFT JOIN branches b ON e.branches_branch_id = b.branch_id
        WHERE u.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $edit_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

if (!$user_data) {
    $_SESSION['error'] = "ไม่พบข้อมูลผู้ใช้งาน";
    header("Location: user_list.php");
    exit;
}

// เตรียมข้อมูล Dropdown
$prefixes = $conn->query("SELECT prefix_id, prefix_th FROM prefixs WHERE is_active = 1");
$roles = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_name");
$shops = ($is_super_admin) ? $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name") : null;

// ค่าเริ่มต้นสำหรับ Form
$selected_shop = $user_data['current_shop_id'] ?? ($is_super_admin ? 0 : $current_shop_id);
$selected_branch = $user_data['branches_branch_id'];
$selected_dept = $user_data['departments_dept_id'];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขข้อมูลผู้ใช้งาน - Mobile Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: <?= $background_color ?>; font-family: '<?= $font_style ?>', sans-serif; color: <?= $text_color ?>; }
        .card-header { background: linear-gradient(135deg, <?= $theme_color ?>, #14532d); color: white; }
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
                        
                        <div class="card shadow border-0 rounded-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h4 class="mb-0 text-white"><i class="bi bi-pencil-square me-2"></i>แก้ไขข้อมูลผู้ใช้งาน: <?= htmlspecialchars($user_data['username']) ?></h4>
                            </div>
                            <div class="card-body p-4">

                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger shadow-sm">
                                        <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= $err ?></li><?php endforeach; ?></ul>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" class="needs-validation" novalidate>
                                    
                                    <div class="form-section-title mt-0"><i class="bi bi-shield-check me-2"></i>สถานะและสิทธิ์การใช้งาน</div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">ชื่อผู้ใช้ (Username)</label>
                                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user_data['username']) ?>" readonly>
                                            <div class="form-text">ชื่อผู้ใช้ไม่สามารถแก้ไขได้</div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">บทบาท (Role) <span class="text-danger">*</span></label>
                                            <select class="form-select" name="role_id" required>
                                                <?php mysqli_data_seek($roles, 0); while($r = $roles->fetch_assoc()): ?>
                                                    <option value="<?= $r['role_id'] ?>" <?= ($r['role_id'] == $user_data['roles_role_id']) ? 'selected' : '' ?>>
                                                        <?= $r['role_name'] ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">สถานะบัญชี <span class="text-danger">*</span></label>
                                            <select class="form-select" name="user_status" required>
                                                <option value="Active" <?= ($user_data['user_status'] == 'Active') ? 'selected' : '' ?>>ใช้งานปกติ (Active)</option>
                                                <option value="Inactive" <?= ($user_data['user_status'] == 'Inactive') ? 'selected' : '' ?>>ระงับการใช้งาน (Inactive)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-section-title"><i class="bi bi-building me-2"></i>ข้อมูลสังกัด</div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">สังกัดร้านค้า</label>
                                            <?php if ($is_super_admin): ?>
                                                <select class="form-select" name="shop_id" id="shopSelect" onchange="loadShopData(this.value)">
                                                    <option value="">-- เลือกร้านค้า --</option>
                                                    <?php while($s = $shops->fetch_assoc()): ?>
                                                        <option value="<?= $s['shop_id'] ?>" <?= ($s['shop_id'] == $selected_shop) ? 'selected' : '' ?>>
                                                            <?= $s['shop_name'] ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            <?php else: ?>
                                                <input type="text" class="form-control bg-light" value="<?= $_SESSION['shop_name'] ?>" readonly>
                                                <input type="hidden" name="shop_id" id="shopSelect" value="<?= $current_shop_id ?>">
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">สาขา <span class="text-danger">*</span></label>
                                            <select class="form-select" name="branch_id" id="branchSelect" required></select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">แผนก <span class="text-danger">*</span></label>
                                            <select class="form-select" name="dept_id" id="deptSelect" required></select>
                                        </div>
                                    </div>

                                    <div class="form-section-title"><i class="bi bi-person-lines-fill me-2"></i>ข้อมูลส่วนตัวพนักงาน</div>
                                    <div class="row g-3">
                                        <div class="col-md-2">
                                            <label class="form-label fw-bold">คำนำหน้า</label>
                                            <select class="form-select" name="prefix_id" required>
                                                <?php mysqli_data_seek($prefixes, 0); while($p = $prefixes->fetch_assoc()): ?>
                                                    <option value="<?= $p['prefix_id'] ?>" <?= ($p['prefix_id'] == $user_data['prefixs_prefix_id']) ? 'selected' : '' ?>>
                                                        <?= $p['prefix_th'] ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label fw-bold">ชื่อจริง (ไทย) <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="firstname" required value="<?= htmlspecialchars($user_data['firstname_th']) ?>">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label fw-bold">นามสกุล (ไทย) <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="lastname" required value="<?= htmlspecialchars($user_data['lastname_th']) ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">เลขบัตรประชาชน</label>
                                            <input type="text" class="form-control" name="national_id" value="<?= htmlspecialchars($user_data['emp_national_id']) ?>" maxlength="13">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">เบอร์โทรศัพท์</label>
                                            <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($user_data['emp_phone_no']) ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">อีเมล</label>
                                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user_data['emp_email']) ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">เพศ</label>
                                            <select class="form-select" name="gender" required>
                                                <option value="Male" <?= ($user_data['emp_gender'] == 'Male') ? 'selected' : '' ?>>ชาย</option>
                                                <option value="Female" <?= ($user_data['emp_gender'] == 'Female') ? 'selected' : '' ?>>หญิง</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end gap-2 mt-5">
                                        <a href="user_list.php" class="btn btn-light rounded-pill px-4">ยกเลิก</a>
                                        <button type="submit" class="btn btn-warning text-white rounded-pill px-5 fw-bold shadow-sm">
                                            <i class="bi bi-save me-2"></i>บันทึกการแก้ไข
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
        // โหลดข้อมูลสาขาและแผนก (พร้อม Pre-select ค่าเดิม)
        function loadShopData(shopId, currentBranch = null, currentDept = null) {
            const branchSelect = document.getElementById('branchSelect');
            const deptSelect = document.getElementById('deptSelect');

            if (!shopId) return;

            // โหลดสาขา
            fetch(`user_edit.php?ajax_action=get_branches&shop_id=${shopId}`)
                .then(res => res.json())
                .then(data => {
                    let opts = '<option value="">-- เลือกสาขา --</option>';
                    data.forEach(item => {
                        const selected = (item.branch_id == currentBranch) ? 'selected' : '';
                        opts += `<option value="${item.branch_id}" ${selected}>${item.branch_name}</option>`;
                    });
                    branchSelect.innerHTML = opts;
                });

            // โหลดแผนก
            fetch(`user_edit.php?ajax_action=get_departments&shop_id=${shopId}`)
                .then(res => res.json())
                .then(data => {
                    let opts = '<option value="">-- เลือกแผนก --</option>';
                    data.forEach(item => {
                        const selected = (item.dept_id == currentDept) ? 'selected' : '';
                        opts += `<option value="${item.dept_id}" ${selected}>${item.dept_name}</option>`;
                    });
                    deptSelect.innerHTML = opts;
                });
        }

        // เริ่มต้นโหลดข้อมูลทันทีที่เปิดหน้า
        document.addEventListener('DOMContentLoaded', () => {
            const shopSelect = document.getElementById('shopSelect');
            // ส่งค่าเดิมเข้าไปเพื่อเลือก option ที่ถูกต้อง
            loadShopData(
                shopSelect.value, 
                "<?= $selected_branch ?>", 
                "<?= $selected_dept ?>"
            );
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