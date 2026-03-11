<?php
session_start();
require '../config/config.php';

// ตรวจสอบสิทธิ์
checkPageAccess($conn, 'menu_manage_users');

// ==========================================================================================
// AJAX HANDLER
// ==========================================================================================
if (isset($_GET['ajax_action'])) {
    ob_clean();
    header('Content-Type: application/json');
    $action = $_GET['ajax_action'];
    $data = [];

    if ($action == 'get_branches') {
        $shop_id = intval($_GET['shop_id']);
        $sql = "SELECT branch_id, branch_name FROM branches WHERE shop_info_shop_id = ? ORDER BY branch_name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $shop_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $data[] = $row;
    } elseif ($action == 'get_departments') {
        $shop_id = intval($_GET['shop_id']);
        $sql = "SELECT dept_id, dept_name FROM departments WHERE shop_info_shop_id = ? ORDER BY dept_name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $shop_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $data[] = $row;
    } elseif ($action == 'get_provinces') {
        $res = $conn->query("SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th");
        while ($row = $res->fetch_assoc()) $data[] = $row;
    } elseif ($action == 'get_districts') {
        $id = intval($_GET['id']);
        $res = $conn->query("SELECT district_id, district_name_th FROM districts WHERE provinces_province_id = $id ORDER BY district_name_th");
        while ($row = $res->fetch_assoc()) $data[] = $row;
    } elseif ($action == 'get_subdistricts') {
        $id = intval($_GET['id']);
        $res = $conn->query("SELECT subdistrict_id, subdistrict_name_th, zip_code FROM subdistricts WHERE districts_district_id = $id ORDER BY subdistrict_name_th");
        while ($row = $res->fetch_assoc()) $data[] = $row;
    }
    echo json_encode($data);
    exit;
}

// รับ ID ที่ต้องการแก้ไข
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: user_list.php");
    exit;
}
$edit_id = intval($_GET['id']);

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

// ==========================================================================================
// ดึงข้อมูลเดิมมาแสดง และเตรียมไว้เช็ค OTP
// ==========================================================================================
$sql = "SELECT u.username, u.user_status, 
               e.*,
               ur.roles_role_id,
               b.shop_info_shop_id as current_shop_id,
               a.*,
               s.zip_code, s.districts_district_id,
               d.provinces_province_id
        FROM users u 
        LEFT JOIN employees e ON u.user_id = e.users_user_id
        LEFT JOIN user_roles ur ON u.user_id = ur.users_user_id
        LEFT JOIN branches b ON e.branches_branch_id = b.branch_id
        LEFT JOIN addresses a ON e.Addresses_address_id = a.address_id
        LEFT JOIN subdistricts s ON a.subdistricts_subdistrict_id = s.subdistrict_id
        LEFT JOIN districts d ON s.districts_district_id = d.district_id
        WHERE u.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $edit_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    $_SESSION['error'] = "ไม่พบข้อมูลผู้ใช้งาน";
    header("Location: user_list.php");
    exit;
}

// ==========================================================================================
// FORM SUBMISSION
// ==========================================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_status = $_POST['user_status'];
    $role_id = intval($_POST['role_id']);

    $shop_id = isset($_POST['shop_id']) ? intval($_POST['shop_id']) : $current_shop_id;
    $branch_id = intval($_POST['branch_id']);
    $dept_id = intval($_POST['dept_id']);

    $prefix_id = intval($_POST['prefix_id']);
    $firstname_th = trim($_POST['firstname']);
    $lastname_th = trim($_POST['lastname']);
    $firstname_en = trim($_POST['firstname_en']);
    $lastname_en = trim($_POST['lastname_en']);
    $national_id = trim($_POST['national_id']);
    $birthday = !empty($_POST['birthday']) ? $_POST['birthday'] : NULL;
    $gender = $_POST['gender'];
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $line_id = trim($_POST['line_id']);

    $home_no = trim($_POST['home_no']);
    $moo = trim($_POST['moo']);
    $village = trim($_POST['village']);
    $soi = trim($_POST['soi']);
    $road = trim($_POST['road']);
    $subdist_id = intval($_POST['subdistrict_id']);

    $errors = [];

    if (empty($firstname_th) || empty($lastname_th)) $errors[] = "กรุณากรอกชื่อ-นามสกุล (ไทย)";
    if (empty($branch_id) || empty($dept_id)) $errors[] = "กรุณาเลือกสาขาและแผนก";
    if (empty($subdist_id)) $errors[] = "กรุณาเลือกข้อมูลที่อยู่ให้ครบถ้วน";

    // ตรวจสอบระบบ OTP หากมีการเปลี่ยนอีเมล
    if ($email !== $data['emp_email'] && !empty($email) && (!isset($_SESSION['email_verified']) || $_SESSION['email_verified'] !== true)) {
        $errors[] = "คุณได้แก้ไขอีเมล กรุณายืนยันรหัส OTP ให้สำเร็จก่อนทำการบันทึก";
    }

    $emp_image_filename = $_POST['current_image']; 
    if (isset($_FILES['emp_image']) && $_FILES['emp_image']['error'] == 0) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array(mime_content_type($_FILES['emp_image']['tmp_name']), $allowed)) {
            $errors[] = "ไฟล์รูปภาพไม่ถูกต้อง (รองรับ jpg, png, webp)";
        } else {
            $ext = pathinfo($_FILES['emp_image']['name'], PATHINFO_EXTENSION);
            $new_filename = "emp_" . time() . "_" . rand(100, 999) . "." . $ext;
            if (!is_dir("../uploads/employees/")) mkdir("../uploads/employees/", 0777, true);

            if (move_uploaded_file($_FILES['emp_image']['tmp_name'], "../uploads/employees/" . $new_filename)) {
                if (!empty($emp_image_filename) && file_exists("../uploads/employees/" . $emp_image_filename)) {
                    unlink("../uploads/employees/" . $emp_image_filename);
                }
                $emp_image_filename = $new_filename;
            }
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $get_addr = $conn->query("SELECT Addresses_address_id FROM employees WHERE users_user_id = $edit_id");
            $old_addr_row = $get_addr->fetch_assoc();
            $address_id = $old_addr_row['Addresses_address_id'];

            if ($address_id) {
                $sql_addr = "UPDATE addresses SET home_no=?, moo=?, soi=?, road=?, village=?, subdistricts_subdistrict_id=? WHERE address_id=?";
                $stmt = $conn->prepare($sql_addr);
                $stmt->bind_param("sssssii", $home_no, $moo, $soi, $road, $village, $subdist_id, $address_id);
                $stmt->execute();
                $stmt->close();
            }

            $sql_user = "UPDATE users SET user_status = ?, update_at = NOW() WHERE user_id = ?";
            $stmt = $conn->prepare($sql_user);
            $stmt->bind_param("si", $user_status, $edit_id);
            $stmt->execute();
            $stmt->close();

            $sql_emp = "UPDATE employees SET 
                        firstname_th = ?, lastname_th = ?, firstname_en = ?, lastname_en = ?,
                        emp_national_id = ?, emp_birthday = ?, emp_gender = ?,
                        emp_phone_no = ?, emp_email = ?, emp_line_id = ?, emp_image = ?,
                        prefixs_prefix_id = ?, branches_branch_id = ?, departments_dept_id = ?, 
                        update_at = NOW()
                        WHERE users_user_id = ?";
            $stmt = $conn->prepare($sql_emp);
            $stmt->bind_param(
                "sssssssssssiiii",
                $firstname_th, $lastname_th, $firstname_en, $lastname_en,
                $national_id, $birthday, $gender, $phone, $email, $line_id, $emp_image_filename,
                $prefix_id, $branch_id, $dept_id, $edit_id
            );
            $stmt->execute();
            $stmt->close();

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
            unset($_SESSION['email_verified']); // ล้างสถานะ OTP เมื่อบันทึกสำเร็จ
            $_SESSION['success'] = "บันทึกข้อมูลเรียบร้อยแล้ว";
            header("Location: user_list.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// Initial Values for JS
$js_province_id = $data['provinces_province_id'] ?? '';
$js_district_id = $data['districts_district_id'] ?? '';
$js_subdistrict_id = $data['subdistricts_subdistrict_id'] ?? '';
$js_shop_id = $data['current_shop_id'] ?? ($is_super_admin ? '' : $current_shop_id);
$js_branch_id = $data['branches_branch_id'] ?? '';
$js_dept_id = $data['departments_dept_id'] ?? '';

// Prepare Dropdown Data
$chk_col = $conn->query("SHOW COLUMNS FROM prefixs LIKE 'prefix_en'");
$has_prefix_en = ($chk_col->num_rows > 0);
$sql_prefix = $has_prefix_en ? "SELECT prefix_id, prefix_th, prefix_en FROM prefixs WHERE is_active = 1" : "SELECT prefix_id, prefix_th FROM prefixs WHERE is_active = 1";
$prefixes = $conn->query($sql_prefix);

$roles = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_name");
$shops = ($is_super_admin) ? $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name") : null;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขข้อมูลผู้ใช้งาน</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background-color: <?= $background_color ?>; font-family: '<?= $font_style ?>', sans-serif; color: <?= $text_color ?>; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .card-header-custom { background: linear-gradient(135deg, <?= $theme_color ?>, #14532d); color: white; border-radius: 15px 15px 0 0 !important; padding: 1.5rem; }
        .form-section-title { font-size: 1.15rem; font-weight: 600; color: <?= $theme_color ?>; padding-bottom: 8px; border-bottom: 2px solid #e9ecef; margin-bottom: 1.5rem; margin-top: 2rem; display: flex; align-items: center; }
        .form-section-title i { margin-right: 10px; background: #e8f5e9; color: <?= $theme_color ?>; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 1rem; }
        .img-preview-box { width: 140px; height: 140px; border: 4px solid #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; overflow: hidden; margin: 0 auto; background: #f8f9fa; cursor: pointer; position: relative; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .img-preview-box img { width: 100%; height: 100%; object-fit: cover; }
        .img-preview-box i { font-size: 3rem; color: #adb5bd; }
        .img-upload-btn { position: absolute; bottom: 0; width: 100%; background: rgba(0,0,0,0.6); color: white; text-align: center; font-size: 0.8rem; padding: 5px 0; opacity: 0; transition: 0.3s; }
        .img-preview-box:hover .img-upload-btn { opacity: 1; }
        .form-label { font-weight: 500; font-size: 0.95rem; color: #555; }
        .required-star { color: #dc3545; margin-left: 3px; }
        .is-valid-custom { border-color: #198754 !important; }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container py-4" style="max-width: 1100px;">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold text-success mb-0"><i class="fas fa-user-edit me-2"></i>แก้ไขข้อมูลผู้ใช้งาน</h3>
                    <a href="user_list.php" class="btn btn-outline-secondary rounded-pill px-4 shadow-sm"><i class="fas fa-arrow-left me-1"></i> กลับหน้าจัดการ</a>
                </div>

                <form method="POST" id="editForm" enctype="multipart/form-data" novalidate>
                    <div class="card mb-5">
                        <div class="card-header-custom d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 text-white"><i class="fas fa-address-card me-2"></i> ผู้ใช้งาน: <?= htmlspecialchars($data['username']) ?></h5>
                        </div>
                        <div class="card-body p-4 p-md-5">

                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $err): ?><li><i class="fas fa-exclamation-circle me-2"></i><?= $err ?></li><?php endforeach; ?>
                                    </ul>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <div class="text-center mb-4">
                                <label for="emp_image" class="img-preview-box" id="previewBox">
                                    <?php if (!empty($data['emp_image']) && file_exists("../uploads/employees/" . $data['emp_image'])): ?>
                                        <img id="previewImg" src="../uploads/employees/<?= $data['emp_image'] ?>" alt="Profile">
                                        <i class="bi bi-person-circle" id="defaultIcon" style="display: none;"></i>
                                    <?php else: ?>
                                        <img id="previewImg" src="#" alt="Preview" style="display: none;">
                                        <i class="bi bi-person-circle" id="defaultIcon"></i>
                                    <?php endif; ?>
                                    <div class="img-upload-btn"><i class="fas fa-camera"></i> เปลี่ยนรูป</div>
                                </label>
                                <input type="file" name="emp_image" id="emp_image" class="d-none" accept="image/jpeg, image/png, image/webp" onchange="previewFile()">
                                <input type="hidden" name="current_image" value="<?= $data['emp_image'] ?>">
                            </div>

                            <div class="row g-4">
                                <div class="col-lg-6">
                                    <div class="form-section-title" style="margin-top: 0;"><i class="fas fa-lock"></i>สถานะและสิทธิ์</div>
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-12">
                                            <label class="form-label">Username</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($data['username']) ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">บทบาท (Role) <span class="required-star">*</span></label>
                                            <select class="form-select select2" name="role_id" required>
                                                <?php mysqli_data_seek($roles, 0); while ($r = $roles->fetch_assoc()): ?>
                                                    <option value="<?= $r['role_id'] ?>" <?= ($r['role_id'] == $data['roles_role_id']) ? 'selected' : '' ?>><?= $r['role_name'] ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">สถานะบัญชี <span class="required-star">*</span></label>
                                            <select class="form-select" name="user_status" required>
                                                <option value="Active" <?= ($data['user_status'] == 'Active') ? 'selected' : '' ?>>ใช้งานปกติ (Active)</option>
                                                <option value="Inactive" <?= ($data['user_status'] == 'Inactive') ? 'selected' : '' ?>>ระงับการใช้งาน (Inactive)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-section-title"><i class="fas fa-building"></i>ข้อมูลสังกัดร้านค้า</div>
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-12">
                                            <label class="form-label">ร้านค้า (Shop) <span class="required-star">*</span></label>
                                            <?php if ($is_super_admin): ?>
                                                <select class="form-select select2" name="shop_id" id="shopSelect" required>
                                                    <option value="">-- เลือกร้านค้า --</option>
                                                    <?php while ($s = $shops->fetch_assoc()): ?>
                                                        <option value="<?= $s['shop_id'] ?>" <?= ($s['shop_id'] == $js_shop_id) ? 'selected' : '' ?>><?= $s['shop_name'] ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            <?php else: ?>
                                                <input type="text" class="form-control bg-light" value="<?= $_SESSION['shop_name'] ?? 'ร้านค้าของคุณ' ?>" readonly>
                                                <input type="hidden" name="shop_id" id="shopSelect" value="<?= $current_shop_id ?>">
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">สาขา (Branch) <span class="required-star">*</span></label>
                                            <select class="form-select select2" name="branch_id" id="branchSelect" required></select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">แผนก (Department) <span class="required-star">*</span></label>
                                            <select class="form-select select2" name="dept_id" id="deptSelect" required></select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6 border-start ps-lg-4">
                                    <div class="form-section-title" style="margin-top: 0;"><i class="fas fa-id-badge"></i>ข้อมูลส่วนตัวพนักงาน</div>
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-4">
                                            <label class="form-label">คำนำหน้า <span class="required-star">*</span></label>
                                            <select class="form-select" name="prefix_id" id="prefixSelect" onchange="updateEngPrefix()" required>
                                                <?php 
                                                mysqli_data_seek($prefixes, 0);
                                                while ($p = $prefixes->fetch_assoc()): 
                                                    $en_val = $has_prefix_en ? $p['prefix_en'] : '';
                                                ?>
                                                    <option value="<?= $p['prefix_id'] ?>" data-en="<?= $en_val ?>" <?= ($p['prefix_id'] == $data['prefixs_prefix_id']) ? 'selected' : '' ?>>
                                                        <?= $p['prefix_th'] ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label">คำนำหน้า (EN)</label>
                                            <input type="text" id="prefix_en_display" class="form-control bg-light" readonly placeholder="Auto">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">ชื่อ (ภาษาไทย) <span class="required-star">*</span></label>
                                            <input type="text" class="form-control input-thai" name="firstname" value="<?= htmlspecialchars($data['firstname_th']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">นามสกุล (ภาษาไทย) <span class="required-star">*</span></label>
                                            <input type="text" class="form-control input-thai" name="lastname" value="<?= htmlspecialchars($data['lastname_th']) ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">First Name (EN)</label>
                                            <input type="text" class="form-control input-eng" name="firstname_en" value="<?= htmlspecialchars($data['firstname_en']) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Last Name (EN)</label>
                                            <input type="text" class="form-control input-eng" name="lastname_en" value="<?= htmlspecialchars($data['lastname_en']) ?>">
                                        </div>

                                        <div class="col-md-12">
                                            <label class="form-label">เลขบัตรประชาชน <span class="text-muted small">(ถ้ามี)</span></label>
                                            <input type="text" class="form-control check-national-id" id="national_id" name="national_id" 
                                                   value="<?= htmlspecialchars($data['emp_national_id']) ?>" 
                                                   data-orig="<?= htmlspecialchars($data['emp_national_id']) ?>" 
                                                   maxlength="13" placeholder="ระบุ 13 หลัก">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">วันเกิด</label>
                                            <input type="date" class="form-control" name="birthday" value="<?= $data['emp_birthday'] ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">เพศ <span class="required-star">*</span></label>
                                            <select class="form-select" name="gender" required>
                                                <option value="Male" <?= ($data['emp_gender'] == 'Male') ? 'selected' : '' ?>>ชาย</option>
                                                <option value="Female" <?= ($data['emp_gender'] == 'Female') ? 'selected' : '' ?>>หญิง</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section-title"><i class="fas fa-address-book"></i>ข้อมูลการติดต่อ</div>
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">เบอร์โทรศัพท์ <span class="required-star">*</span></label>
                                    <input type="text" class="form-control check-phone" id="phone" name="phone" 
                                           value="<?= htmlspecialchars($data['emp_phone_no']) ?>" 
                                           data-orig="<?= htmlspecialchars($data['emp_phone_no']) ?>" 
                                           maxlength="10" required placeholder="08XXXXXXXX">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Line ID</label>
                                    <input type="text" class="form-control" name="line_id" value="<?= htmlspecialchars($data['emp_line_id']) ?>" placeholder="เพิ่มเพื่อนผ่าน Line">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">อีเมล <span class="text-muted small">(ถ้าเปลี่ยนต้อง OTP)</span></label>
                                    <div class="input-group">
                                        <input type="email" class="form-control" name="email" id="email" 
                                               value="<?= htmlspecialchars($data['emp_email']) ?>" 
                                               data-orig="<?= htmlspecialchars($data['emp_email']) ?>" 
                                               placeholder="example@mail.com">
                                        <button type="button" id="btnSendOTP" class="btn btn-outline-success" style="display:none;"><i class="fas fa-paper-plane me-1"></i>ส่ง OTP</button>
                                    </div>
                                </div>
                                <div id="otpBox" class="col-md-4 offset-md-8 mt-2" style="display:none;">
                                    <div class="p-3 bg-white border rounded shadow-sm">
                                        <label class="small fw-bold text-success mb-2">กรอกรหัส OTP 6 หลักที่ได้รับ</label>
                                        <div class="input-group">
                                            <input type="text" id="otp_code" class="form-control" maxlength="6" placeholder="******">
                                            <button type="button" id="btnVerifyOTP" class="btn btn-success">ยืนยันรหัส</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section-title"><i class="fas fa-map-marker-alt"></i>ที่อยู่พนักงาน (บังคับ)</div>
                            <div class="row g-3 mb-4">
                                <div class="col-md-3"><label class="form-label">บ้านเลขที่ <span class="required-star">*</span></label><input type="text" class="form-control" name="home_no" value="<?= htmlspecialchars($data['home_no']) ?>" required></div>
                                <div class="col-md-2"><label class="form-label">หมู่ที่</label><input type="text" class="form-control" name="moo" value="<?= htmlspecialchars($data['moo']) ?>"></div>
                                <div class="col-md-3"><label class="form-label">หมู่บ้าน/อาคาร</label><input type="text" class="form-control" name="village" value="<?= htmlspecialchars($data['village']) ?>"></div>
                                <div class="col-md-2"><label class="form-label">ซอย</label><input type="text" class="form-control" name="soi" value="<?= htmlspecialchars($data['soi']) ?>"></div>
                                <div class="col-md-2"><label class="form-label">ถนน</label><input type="text" class="form-control" name="road" value="<?= htmlspecialchars($data['road']) ?>"></div>
                                
                                <div class="col-md-3">
                                    <label class="form-label">จังหวัด <span class="required-star">*</span></label>
                                    <select class="form-select select2" id="provinceSelect" onchange="loadDistricts(this.value)" required>
                                        <option value="">-- เลือกจังหวัด --</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">อำเภอ <span class="required-star">*</span></label>
                                    <select class="form-select select2" id="districtSelect" onchange="loadSubdistricts(this.value)" disabled required>
                                        <option value="">-- เลือกอำเภอ --</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">ตำบล <span class="required-star">*</span></label>
                                    <select class="form-select select2" name="subdistrict_id" id="subdistrictSelect" onchange="updateZipcode(this)" disabled required>
                                        <option value="">-- เลือกตำบล --</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">รหัสไปรษณีย์</label>
                                    <input type="text" id="zipcode" class="form-control bg-light" value="<?= htmlspecialchars($data['zip_code']) ?>" readonly>
                                </div>
                            </div>

                            <div class="text-center mt-5 pt-3 border-top">
                                <a href="user_list.php" class="btn btn-outline-secondary btn-lg px-4 me-2 rounded-pill shadow-sm">ยกเลิก</a>
                                <button type="submit" class="btn btn-success btn-lg px-5 shadow-sm rounded-pill">
                                    <i class="fas fa-save me-2"></i> บันทึกการแก้ไข
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    // --- รับค่าจาก PHP (ค่าเดิมของผู้ใช้) ---
    const savedProv = "<?= $js_province_id ?>";
    const savedDist = "<?= $js_district_id ?>";
    const savedSub = "<?= $js_subdistrict_id ?>";
    const savedBranch = "<?= $js_branch_id ?>";
    const savedDept = "<?= $js_dept_id ?>";
    const currentShopId = "<?= $js_shop_id ?>"; 
    
    // ไว้ใช้สำหรับหน้าแก้ไขโดยเฉพาะ (ยกเว้นการตรวจสอบซ้ำถ้าไม่ได้เปลี่ยนค่า)
    const empId = "<?= $data['emp_id'] ?>"; 
    const origNationalId = $('#national_id').data('orig') || "";
    const origPhone = $('#phone').data('orig') || "";
    const origEmail = $('#email').data('orig') || "";

    // ตัวแปรเช็คการแก้ Email (ตั้งค่าเริ่มต้นเป็น true เพราะอีเมลเดิมถือว่ายืนยันแล้ว)
    let isEmailVerified = true; 

    $(document).ready(function() {
        // Init Select2
        $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
        
        // โหลดข้อมูลเริ่มต้น
        loadProvinces();
        updateEngPrefix();
        if (currentShopId) loadShopData(currentShopId, true); 

        $('#shopSelect').on('change', function() { loadShopData($(this).val(), false); });
        
        // ========================================================
        // ควบคุมภาษาที่พิมพ์
        // ========================================================
        document.querySelectorAll('.input-thai').forEach(el => {
            el.addEventListener('input', function() { this.value = this.value.replace(/[^ก-๙\s]/g, ''); });
        });
        document.querySelectorAll('.input-eng').forEach(el => {
            el.addEventListener('input', function() { this.value = this.value.replace(/[^a-zA-Z\s]/g, ''); });
        });
        $('.check-national-id, .check-phone, #otp_code').on('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // ========================================================
        // ตรวจสอบเลขบัตรประชาชน 13 หลัก
        // ========================================================
        function validateThaiID(id) {
            if (id.length !== 13) return false;
            let sum = 0;
            for (let i = 0; i < 12; i++) sum += parseInt(id.charAt(i)) * (13 - i);
            let check = (11 - (sum % 11)) % 10;
            return check === parseInt(id.charAt(12));
        }

        $('#national_id').on('blur', function() {
            let el = $(this);
            let id = el.val().trim();
            
            if(id.length > 0) {
                if (id === origNationalId) {
                    el.removeClass('is-invalid').addClass('is-valid-custom'); // เหมือนเดิม ปล่อยผ่าน
                    return;
                }
                
                if(!validateThaiID(id)) {
                    el.addClass('is-invalid').removeClass('is-valid-custom');
                    Swal.fire('รูปแบบผิดพลาด', 'เลขประจำตัวประชาชนไม่ถูกต้องตามสูตร', 'error');
                } else {
                    $.post('check_duplicate.php', { type: 'national_id', value: id, emp_id: empId }, function(res) {
                        if(res.exists) {
                            el.addClass('is-invalid').removeClass('is-valid-custom');
                            Swal.fire('ข้อมูลซ้ำ', 'เลขบัตรประชาชนนี้มีอยู่ในระบบแล้ว', 'warning');
                        } else {
                            el.removeClass('is-invalid').addClass('is-valid-custom');
                        }
                    }, 'json');
                }
            } else {
                el.removeClass('is-invalid is-valid-custom'); 
            }
        });

        // ========================================================
        // ตรวจสอบเบอร์โทรศัพท์
        // ========================================================
        $('#phone').on('blur', function() {
            let el = $(this);
            let phone = el.val().trim();
            const phoneRegex = /^(06|08|09)\d{8}$/; 
            
            if (phone.length > 0) {
                if (phone === origPhone) {
                    el.removeClass('is-invalid').addClass('is-valid-custom'); // เหมือนเดิม ปล่อยผ่าน
                    return;
                }

                if (!phoneRegex.test(phone)) {
                    el.addClass('is-invalid').removeClass('is-valid-custom');
                    Swal.fire('รูปแบบผิดพลาด', 'เบอร์โทรศัพท์ต้องขึ้นต้นด้วย 06, 08, 09 และมี 10 หลัก', 'warning');
                } else {
                    $.post('check_availability.php', { action: 'check_phone', phone: phone }, function(res) {
                        if (res.status === 'taken') {
                            el.addClass('is-invalid').removeClass('is-valid-custom');
                            Swal.fire('ข้อมูลซ้ำ', 'เบอร์โทรศัพท์นี้มีในระบบแล้ว', 'warning');
                        } else {
                            el.removeClass('is-invalid').addClass('is-valid-custom');
                        }
                    }, 'json');
                }
            } else {
                el.removeClass('is-invalid is-valid-custom'); 
            }
        });

        // ========================================================
        // ตรวจสอบอีเมล และ OTP (กรณีแก้ไขอีเมลใหม่)
        // ========================================================
        $('#email').on('input', function() {
            const email = $(this).val().trim();
            if (email !== origEmail && email.length > 0) {
                $('#btnSendOTP').fadeIn();
                isEmailVerified = false; // มีการเปลี่ยนอีเมล บังคับยืนยันใหม่
            } else {
                $('#btnSendOTP').fadeOut();
                $('#otpBox').fadeOut();
                isEmailVerified = true;  // ถ้ากลับมาเป็นอีเมลเดิม หรือ ปล่อยว่าง ถือว่าผ่าน
                $(this).removeClass('is-invalid is-valid-custom');
            }
        });

        $('#email').on('blur', function() {
            let el = $(this);
            let email = el.val().trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email === origEmail || email.length === 0) return; // ไม่ตรวจสอบถ้าเหมือนเดิมหรือปล่อยว่าง

            if (!emailRegex.test(email)) {
                el.addClass('is-invalid').removeClass('is-valid-custom');
                Swal.fire('ผิดพลาด', 'รูปแบบอีเมลไม่ถูกต้อง', 'warning');
                $('#btnSendOTP').hide();
            } else {
                $.post('check_availability.php', { action: 'check_email', email: email }, function(res) {
                    if(res.status === 'taken') {
                        el.addClass('is-invalid').removeClass('is-valid-custom');
                        Swal.fire('อีเมลซ้ำ', 'อีเมลนี้ถูกใช้งานแล้ว', 'warning');
                        $('#btnSendOTP').hide();
                    } else {
                        el.removeClass('is-invalid').addClass('is-valid-custom');
                        $('#btnSendOTP').show();
                    }
                }, 'json');
            }
        });

        $('#btnSendOTP').click(function() {
            const email = $('#email').val();
            if($('#email').hasClass('is-invalid') || !email) return; 
            
            $(this).prop('disabled', true).text('กำลังส่ง...');
            fetch('send_otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email })
            }).then(res => res.json()).then(data => {
                if (data.status === 'success') {
                    Swal.fire('สำเร็จ', 'ส่งรหัส OTP ไปที่อีเมลของคุณแล้ว', 'success');
                    $('#otpBox').fadeIn();
                } else Swal.fire('ผิดพลาด', data.message, 'error');
            }).catch(err => Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้', 'error'))
              .finally(() => $('#btnSendOTP').prop('disabled', false).html('<i class="fas fa-paper-plane me-1"></i>ส่ง OTP'));
        });

        $('#btnVerifyOTP').click(function() {
            const otp = $('#otp_code').val();
            if (otp.length !== 6) return Swal.fire('แจ้งเตือน', 'กรุณากรอก OTP 6 หลักให้ครบ', 'warning');

            fetch('verify_otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ otp: otp })
            }).then(res => res.json()).then(data => {
                if (data.status === 'success') {
                    Swal.fire('สำเร็จ', 'ยืนยันอีเมลสำเร็จ', 'success');
                    isEmailVerified = true;
                    $('#otpBox').fadeOut();
                    $('#btnSendOTP').fadeOut();
                    $('#email').addClass('is-valid-custom').prop('readonly', true);
                } else Swal.fire('รหัสไม่ถูกต้อง', data.message, 'error');
            });
        });

        // ========================================================
        // ควบคุมการ Submit ฟอร์มขั้นสุดท้าย
        // ========================================================
        $('#editForm').on('submit', function(e) {
            e.preventDefault();

            if ($('.is-invalid').length > 0) {
                return Swal.fire('ข้อมูลไม่ถูกต้อง', 'กรุณาแก้ไขข้อมูลที่มีขอบสีแดงให้ถูกต้อง', 'error');
            }

            let emptyFields = 0;
            $(this).find('input[required], select[required]').each(function() {
                if (!$(this).val() || $(this).val().trim() === '') {
                    $(this).addClass('is-invalid');
                    emptyFields++;
                }
            });

            if (emptyFields > 0) {
                return Swal.fire('ข้อมูลไม่ครบ', 'กรุณากรอกข้อมูลในช่องดอกจัน (*) ให้ครบถ้วน', 'warning');
            }

            if (!isEmailVerified) {
                return Swal.fire({ icon: 'warning', title: 'รอสักครู่', text: 'คุณเปลี่ยนอีเมลใหม่ กรุณายืนยันรหัส OTP ก่อนบันทึก' });
            }

            Swal.fire({
                title: 'กำลังบันทึกข้อมูล...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            this.submit();
        });

    });

    // ==========================================
    // SHOP / BRANCH / DEPARTMENT LOGIC
    // ==========================================
    function loadShopData(shopId, isInit = false) {
        const branchSelect = $('#branchSelect');
        const deptSelect = $('#deptSelect');
        if (!shopId) {
            branchSelect.empty().append('<option value="">-- รอการเลือก --</option>').prop('disabled', true);
            deptSelect.empty().append('<option value="">-- รอการเลือก --</option>').prop('disabled', true);
            return;
        }

        $.getJSON(`user_edit.php?ajax_action=get_branches&shop_id=${shopId}`, function(data) {
            branchSelect.empty().append('<option value="">-- เลือกสาขา --</option>');
            $.each(data, function(i, d) {
                const isSel = (isInit && d.branch_id == savedBranch);
                branchSelect.append(new Option(d.branch_name, d.branch_id, false, isSel));
            });
            branchSelect.prop('disabled', false).trigger('change'); 
        });

        $.getJSON(`user_edit.php?ajax_action=get_departments&shop_id=${shopId}`, function(data) {
            deptSelect.empty().append('<option value="">-- เลือกแผนก --</option>');
            $.each(data, function(i, d) {
                const isSel = (isInit && d.dept_id == savedDept);
                deptSelect.append(new Option(d.dept_name, d.dept_id, false, isSel));
            });
            deptSelect.prop('disabled', false).trigger('change'); 
        });
    }

    // ==========================================
    // LOCATION ที่อยู่
    // ==========================================
    function loadProvinces() {
        $.getJSON('user_edit.php?ajax_action=get_provinces', function(data) {
            const sel = $('#provinceSelect');
            $.each(data, function(i, d) {
                const isSel = (d.province_id == savedProv);
                sel.append(new Option(d.province_name_th, d.province_id, false, isSel));
            });
            if (savedProv) {
                sel.trigger('change.select2');
                loadDistricts(savedProv, true);
            }
        });
    }

    function loadDistricts(provId, isInit = false) {
        const dist = $('#districtSelect');
        const sub = $('#subdistrictSelect');

        dist.empty().append('<option value="">-- เลือกอำเภอ --</option>').prop('disabled', true);
        if (!isInit) {
            sub.empty().append('<option value="">-- เลือกตำบล --</option>').prop('disabled', true);
            $('#zipcode').val('');
        }

        if (provId) {
            $.getJSON(`user_edit.php?ajax_action=get_districts&id=${provId}`, function(data) {
                $.each(data, function(i, d) {
                    const isSel = (isInit && d.district_id == savedDist);
                    dist.append(new Option(d.district_name_th, d.district_id, false, isSel));
                });
                dist.prop('disabled', false);
                if (isInit && savedDist) {
                    dist.trigger('change.select2');
                    loadSubdistricts(savedDist, true);
                }
            });
        }
    }

    function loadSubdistricts(distId, isInit = false) {
        const sub = $('#subdistrictSelect');
        sub.empty().append('<option value="">-- เลือกตำบล --</option>').prop('disabled', true);

        if (distId) {
            $.getJSON(`user_edit.php?ajax_action=get_subdistricts&id=${distId}`, function(data) {
                $.each(data, function(i, d) {
                    const isSel = (isInit && d.subdistrict_id == savedSub);
                    let opt = new Option(d.subdistrict_name_th, d.subdistrict_id, false, isSel);
                    $(opt).data('zip', d.zip_code);
                    sub.append(opt);
                });
                sub.prop('disabled', false);
                if (isInit && savedSub) {
                    sub.trigger('change.select2');
                    updateZipcode();
                }
            });
        }
    }

    function updateZipcode() {
        $('#zipcode').val($('#subdistrictSelect').find(':selected').data('zip') || '');
    }

    // ==========================================
    // UTILITIES
    // ==========================================
    function previewFile() {
        const file = document.getElementById('emp_image').files[0];
        if (file) {
            const reader = new FileReader();
            reader.onloadend = function() {
                $('#previewImg').attr('src', reader.result).show();
                $('#defaultIcon').hide();
            }
            reader.readAsDataURL(file);
        }
    }

    function updateEngPrefix() {
        const select = document.getElementById('prefixSelect');
        if(select) $('#prefix_en_display').val(select.options[select.selectedIndex].getAttribute('data-en') || '');
    }
    </script>
</body>
</html>