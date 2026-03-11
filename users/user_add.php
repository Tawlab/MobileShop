<?php
session_start();
require '../config/config.php';

// ตรวจสอบสิทธิ์ 
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

// ฟังก์ชันหา ID ถัดไป (Manual Auto-Increment)
function getNextId($conn, $table, $column) {
    $sql = "SELECT MAX($column) as max_id FROM $table";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return ($row['max_id']) ? $row['max_id'] + 1 : 1;
}

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
    } 
    elseif ($action == 'get_departments') {
        $shop_id = intval($_GET['shop_id']);
        $sql = "SELECT dept_id, dept_name FROM departments WHERE shop_info_shop_id = ? ORDER BY dept_name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $shop_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $data[] = $row;
    }
    elseif ($action == 'get_provinces') {
        $res = $conn->query("SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th");
        while ($row = $res->fetch_assoc()) $data[] = $row;
    }
    elseif ($action == 'get_districts') {
        $id = intval($_GET['id']);
        $res = $conn->query("SELECT district_id, district_name_th FROM districts WHERE provinces_province_id = $id ORDER BY district_name_th");
        while ($row = $res->fetch_assoc()) $data[] = $row;
    }
    elseif ($action == 'get_subdistricts') {
        $id = intval($_GET['id']);
        $res = $conn->query("SELECT subdistrict_id, subdistrict_name_th, zip_code FROM subdistricts WHERE districts_district_id = $id ORDER BY subdistrict_name_th");
        while ($row = $res->fetch_assoc()) $data[] = $row;
    }

    echo json_encode($data);
    exit;
}

// ==========================================================================================
// FORM SUBMISSION
// ==========================================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // รับค่าพื้นฐาน
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $shop_select = isset($_POST['shop_id']) ? intval($_POST['shop_id']) : $current_shop_id;
    $branch_id = intval($_POST['branch_id']);
    $dept_id = intval($_POST['dept_id']);
    $role_id = intval($_POST['role_id']); 
    
    // ข้อมูลส่วนตัว
    $prefix_id = intval($_POST['prefix_id']);
    $firstname_th = trim($_POST['firstname']); 
    $lastname_th = trim($_POST['lastname']);
    $firstname_en = trim($_POST['firstname_en']); 
    $lastname_en = trim($_POST['lastname_en']);
    $national_id = trim($_POST['national_id']);
    $birthday = !empty($_POST['birthday']) ? $_POST['birthday'] : NULL;
    $gender = $_POST['gender'];
    
    // ข้อมูลติดต่อ
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $line_id = trim($_POST['line_id']);

    // ข้อมูลที่อยู่
    $home_no = trim($_POST['home_no']);
    $moo = trim($_POST['moo']);
    $village = trim($_POST['village']);
    $soi = trim($_POST['soi']);
    $road = trim($_POST['road']);
    $subdist_id = intval($_POST['subdistrict_id']);

    $errors = [];

    // Backend Validation
    if (empty($username) || empty($password)) $errors[] = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
    if ($password !== $confirm_password) $errors[] = "รหัสผ่านยืนยันไม่ตรงกัน";
    if (empty($firstname_th) || empty($lastname_th)) $errors[] = "กรุณากรอกชื่อ-นามสกุล (ไทย)";
    if (empty($subdist_id)) $errors[] = "กรุณาเลือกจังหวัด/อำเภอ/ตำบล ให้ครบถ้วน";
    if (empty($role_id)) $errors[] = "กรุณาเลือกบทบาทผู้ใช้งาน"; 
    
    // เช็ค OTP อีเมล (ถ้ามีการกรอกอีเมล)
    if (!empty($email) && (!isset($_SESSION['email_verified']) || $_SESSION['email_verified'] !== true)) {
        $errors[] = "คุณได้กรอกอีเมล กรุณายืนยันรหัส OTP ให้สำเร็จก่อนทำการบันทึก";
    }

    // เช็ค Username ซ้ำ
    $chk_user = $conn->query("SELECT user_id FROM users WHERE username = '$username'");
    if ($chk_user->num_rows > 0) $errors[] = "ชื่อผู้ใช้ '$username' ถูกใช้งานแล้ว";

    // จัดการไฟล์รูปภาพ
    $emp_image_filename = NULL;
    if (isset($_FILES['emp_image']) && $_FILES['emp_image']['error'] == 0) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array(mime_content_type($_FILES['emp_image']['tmp_name']), $allowed)) {
            $errors[] = "ไฟล์รูปภาพไม่ถูกต้อง (รองรับ jpg, png, webp)";
        } else {
            $ext = pathinfo($_FILES['emp_image']['name'], PATHINFO_EXTENSION);
            $emp_image_filename = "emp_" . time() . "_" . rand(100,999) . "." . $ext;
            if (!is_dir("../uploads/employees/")) mkdir("../uploads/employees/", 0777, true);
            move_uploaded_file($_FILES['emp_image']['tmp_name'], "../uploads/employees/" . $emp_image_filename);
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();

        try {
            // --- บันทึกที่อยู่ ---
            $new_addr_id = getNextId($conn, 'addresses', 'address_id');
            $sql_addr = "INSERT INTO addresses (address_id, home_no, moo, soi, road, village, subdistricts_subdistrict_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_addr);
            $stmt->bind_param("isssssi", $new_addr_id, $home_no, $moo, $soi, $road, $village, $subdist_id);
            if (!$stmt->execute()) throw new Exception("บันทึกที่อยู่ไม่สำเร็จ");
            $stmt->close();

            // --- สร้าง User ---
            $new_user_id = getNextId($conn, 'users', 'user_id'); 
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql_user = "INSERT INTO users (user_id, username, password, user_status, create_at, update_at) VALUES (?, ?, ?, 'Active', NOW(), NOW())";
            $stmt = $conn->prepare($sql_user);
            $stmt->bind_param("iss", $new_user_id, $username, $hashed_password);
            $stmt->execute();
            $stmt->close();

            // --- สร้าง Employee ---
            $new_emp_id = getNextId($conn, 'employees', 'emp_id'); 
            $emp_code = 'EMP' . date('ym') . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT); 
            $default_religion = 10; 

            $sql_emp = "INSERT INTO employees (
                            emp_id, emp_code, emp_national_id, 
                            firstname_th, lastname_th, firstname_en, lastname_en,
                            emp_phone_no, emp_email, emp_line_id, emp_birthday,
                            emp_gender, emp_status, 
                            prefixs_prefix_id, Addresses_address_id, religions_religion_id, 
                            departments_dept_id, branches_branch_id, users_user_id, emp_image,
                            create_at, update_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $conn->prepare($sql_emp);
            $stmt->bind_param("isssssssssssiiiiiis", 
                $new_emp_id, $emp_code, $national_id, 
                $firstname_th, $lastname_th, $firstname_en, $lastname_en,
                $phone, $email, $line_id, $birthday,
                $gender, 
                $prefix_id, $new_addr_id, $default_religion, 
                $dept_id, $branch_id, $new_user_id, $emp_image_filename
            );
            $stmt->execute();
            $stmt->close();

            // --- กำหนด Role ---
            $sql_role = "INSERT INTO user_roles (roles_role_id, users_user_id, create_at) VALUES (?, ?, NOW())";
            $stmt = $conn->prepare($sql_role);
            $stmt->bind_param("ii", $role_id, $new_user_id);
            $stmt->execute();
            $stmt->close();

            // --- เพิ่ม Config ---
            $sql_conf = "INSERT INTO systemconfig (user_id, theme_color, background_color, text_color, font_style, header_bg_color, header_text_color) 
                         VALUES (?, '#198754', '#ffffff', '#000000', 'Prompt', '#198754', '#ffffff')";
            $stmt = $conn->prepare($sql_conf);
            $stmt->bind_param("i", $new_user_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            unset($_SESSION['email_verified']); // ล้างสถานะ OTP เมื่อบันทึกสำเร็จ
            $_SESSION['success'] = "เพิ่มผู้ใช้งานและพนักงานใหม่เรียบร้อยแล้ว";
            header("Location: user_list.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            if ($emp_image_filename && file_exists("../uploads/employees/" . $emp_image_filename)) {
                unlink("../uploads/employees/" . $emp_image_filename);
            }
            $errors[] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// ==========================================================================================
// PREPARE VIEW DATA
// ==========================================================================================
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
    <title>เพิ่มผู้ใช้งานใหม่</title>
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
                    <h3 class="fw-bold text-success mb-0"><i class="fas fa-user-plus me-2"></i>เพิ่มผู้ใช้งานใหม่</h3>
                    <a href="user_list.php" class="btn btn-outline-secondary rounded-pill px-4 shadow-sm"><i class="fas fa-arrow-left me-1"></i> กลับหน้าจัดการ</a>
                </div>

                <form method="POST" id="addForm" enctype="multipart/form-data" novalidate>
                    <div class="card mb-5">
                        <div class="card-header-custom d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 text-white"><i class="fas fa-address-card me-2"></i> แบบฟอร์มสร้างบัญชีใหม่</h5>
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
                                    <img id="previewImg" src="#" alt="Preview" style="display: none;">
                                    <i class="bi bi-person-circle" id="defaultIcon"></i>
                                    <div class="img-upload-btn"><i class="fas fa-camera"></i> อัปโหลดรูป</div>
                                </label>
                                <input type="file" name="emp_image" id="emp_image" class="d-none" accept="image/jpeg, image/png, image/webp" onchange="previewFile()">
                            </div>

                            <div class="row g-4">
                                <div class="col-lg-6">
                                    <div class="form-section-title" style="margin-top: 0;"><i class="fas fa-lock"></i>ข้อมูลเข้าระบบ</div>
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-12">
                                            <label class="form-label">Username <span class="required-star">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                                <input type="text" class="form-control" name="username" id="username" required placeholder="อย่างน้อย 6 ตัวอักษร" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Password <span class="required-star">*</span></label>
                                            <input type="password" class="form-control" name="password" id="password" required placeholder="มีตัวอักษรและตัวเลข">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Confirm Password <span class="required-star">*</span></label>
                                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" required placeholder="ยืนยันรหัสผ่าน">
                                        </div>
                                    </div>

                                    <div class="form-section-title"><i class="fas fa-building"></i>ข้อมูลสังกัดร้านค้า</div>
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-12">
                                            <label class="form-label">ร้านค้า (Shop) <span class="required-star">*</span></label>
                                            <?php if ($is_super_admin): ?>
                                                <select class="form-select select2" name="shop_id" id="shopSelect" required onchange="loadShopData(this.value)">
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
                                        <div class="col-md-6">
                                            <label class="form-label">สาขา (Branch) <span class="required-star">*</span></label>
                                            <select class="form-select select2" name="branch_id" id="branchSelect" required>
                                                <option value="">-- รอการเลือก --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">แผนก (Department) <span class="required-star">*</span></label>
                                            <select class="form-select select2" name="dept_id" id="deptSelect" required>
                                                <option value="">-- รอการเลือก --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">บทบาท (Role) <span class="required-star">*</span></label>
                                            <select class="form-select select2" name="role_id" required>
                                                <option value="">-- เลือกบทบาท --</option>
                                                <?php while($r = $roles->fetch_assoc()): ?>
                                                    <option value="<?= $r['role_id'] ?>"><?= $r['role_name'] ?></option>
                                                <?php endwhile; ?>
                                            </select>
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
                                                while($p = $prefixes->fetch_assoc()): 
                                                    $en_val = $has_prefix_en ? $p['prefix_en'] : '';
                                                ?>
                                                    <option value="<?= $p['prefix_id'] ?>" data-en="<?= $en_val ?>"><?= $p['prefix_th'] ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label">คำนำหน้า (EN)</label>
                                            <input type="text" id="prefix_en_display" class="form-control bg-light" readonly placeholder="Auto">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">ชื่อ (ภาษาไทย) <span class="required-star">*</span></label>
                                            <input type="text" class="form-control input-thai" name="firstname" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">นามสกุล (ภาษาไทย) <span class="required-star">*</span></label>
                                            <input type="text" class="form-control input-thai" name="lastname" required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">First Name (EN)</label>
                                            <input type="text" class="form-control input-eng" name="firstname_en">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Last Name (EN)</label>
                                            <input type="text" class="form-control input-eng" name="lastname_en">
                                        </div>

                                        <div class="col-md-12">
                                            <label class="form-label">เลขบัตรประชาชน <span class="text-muted small">(ถ้ามี)</span></label>
                                            <input type="text" class="form-control check-national-id" id="national_id" name="national_id" maxlength="13" placeholder="ระบุ 13 หลัก">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">วันเกิด</label>
                                            <input type="date" class="form-control" name="birthday">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">เพศ <span class="required-star">*</span></label>
                                            <select class="form-select" name="gender" required>
                                                <option value="Male">ชาย</option>
                                                <option value="Female">หญิง</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section-title"><i class="fas fa-address-book"></i>ข้อมูลการติดต่อ</div>
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">เบอร์โทรศัพท์ <span class="required-star">*</span></label>
                                    <input type="text" class="form-control check-phone" id="phone" name="phone" maxlength="10" required placeholder="08XXXXXXXX">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Line ID</label>
                                    <input type="text" class="form-control" name="line_id" placeholder="เพิ่มเพื่อนผ่าน Line">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">อีเมล <span class="text-muted small">(ถ้ากรอกต้อง OTP)</span></label>
                                    <div class="input-group">
                                        <input type="email" class="form-control" name="email" id="email" placeholder="example@mail.com">
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
                                <div class="col-md-3"><label class="form-label">บ้านเลขที่ </label><input type="text" class="form-control" name="home_no"></div>
                                <div class="col-md-2"><label class="form-label">หมู่ที่</label><input type="text" class="form-control" name="moo"></div>
                                <div class="col-md-3"><label class="form-label">หมู่บ้าน/อาคาร</label><input type="text" class="form-control" name="village"></div>
                                <div class="col-md-2"><label class="form-label">ซอย</label><input type="text" class="form-control" name="soi"></div>
                                <div class="col-md-2"><label class="form-label">ถนน</label><input type="text" class="form-control" name="road"></div>
                                
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
                                    <input type="text" id="zipcode" class="form-control bg-light" readonly>
                                </div>
                            </div>

                            <div class="text-center mt-5 pt-3 border-top">
                                <a href="user_list.php" class="btn btn-outline-secondary btn-lg px-4 me-2 rounded-pill shadow-sm">ยกเลิก</a>
                                <button type="submit" class="btn btn-success btn-lg px-5 shadow-sm rounded-pill">
                                    <i class="fas fa-save me-2"></i> สร้างบัญชีผู้ใช้งาน
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
        let isEmailVerified = true; // สถานะเริ่มต้นเป็น True เพราะอีเมลไม่บังคับกรอก

        $(document).ready(function() {
            // Init Select2
            $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
            
            // โหลดข้อมูลเริ่มต้น
            loadProvinces();
            updateEngPrefix(); 
            const shopInput = document.getElementById('shopSelect');
            if(shopInput.tagName === 'INPUT') loadShopData(shopInput.value);

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
            // ตรวจสอบ Username & Password
            // ========================================================
            $('#username').on('blur', function() {
                let el = $(this);
                let usr = el.val().trim();
                if(usr.length > 0) {
                    $.post('check_availability.php', { action: 'check_username', username: usr }, function(res) {
                        if(res.status === 'invalid' || res.status === 'taken') {
                            el.addClass('is-invalid').removeClass('is-valid-custom');
                            Swal.fire('ชื่อผู้ใช้ไม่ถูกต้อง', res.message, 'warning');
                        } else {
                            el.removeClass('is-invalid').addClass('is-valid-custom');
                        }
                    }, 'json');
                } else el.removeClass('is-invalid is-valid-custom');
            });

            $('#password').on('blur', function() {
                let el = $(this);
                let pwd = el.val();
                if(pwd.length > 0) {
                    $.post('check_availability.php', { action: 'check_password', password: pwd }, function(res) {
                        if(res.status === 'invalid') {
                            el.addClass('is-invalid').removeClass('is-valid-custom');
                            Swal.fire('รหัสผ่านไม่ปลอดภัย', res.message, 'warning');
                        } else {
                            el.removeClass('is-invalid').addClass('is-valid-custom');
                        }
                    }, 'json');
                } else el.removeClass('is-invalid is-valid-custom');
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
                    if(!validateThaiID(id)) {
                        el.addClass('is-invalid').removeClass('is-valid-custom');
                        Swal.fire('รูปแบบผิดพลาด', 'เลขประจำตัวประชาชนไม่ถูกต้อง', 'error');
                    } else {
                        // ส่งไปเช็คซ้ำ (emp_id = 0 เพราะเป็น user ใหม่)
                        $.post('check_duplicate.php', { type: 'national_id', value: id, emp_id: 0 }, function(res) {
                            if(res.exists) {
                                el.addClass('is-invalid').removeClass('is-valid-custom');
                                Swal.fire('ข้อมูลซ้ำ', 'เลขบัตรประชาชนนี้มีอยู่ในระบบแล้ว', 'warning');
                            } else {
                                el.removeClass('is-invalid').addClass('is-valid-custom');
                            }
                        }, 'json');
                    }
                } else {
                    el.removeClass('is-invalid is-valid-custom'); // ไม่บังคับกรอก
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
            // ตรวจสอบอีเมล และ OTP
            // ========================================================
            $('#email').on('input', function() {
                const email = $(this).val().trim();
                if (email.length > 0) {
                    $('#btnSendOTP').fadeIn();
                    isEmailVerified = false; 
                } else {
                    $('#btnSendOTP').fadeOut();
                    $('#otpBox').fadeOut();
                    isEmailVerified = true; 
                    $(this).removeClass('is-invalid is-valid-custom');
                }
            });

            $('#email').on('blur', function() {
                let el = $(this);
                let email = el.val().trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if(email.length > 0 && !emailRegex.test(email)) {
                    el.addClass('is-invalid').removeClass('is-valid-custom');
                    Swal.fire('ผิดพลาด', 'รูปแบบอีเมลไม่ถูกต้อง', 'warning');
                    $('#btnSendOTP').hide();
                } else if(email.length > 0) {
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

            // ปุ่มส่ง OTP
            $('#btnSendOTP').click(function() {
                const email = $('#email').val();
                if($('#email').hasClass('is-invalid') || !email) return; 
                
                $(this).prop('disabled', true).text('กำลังส่ง...');
                fetch('send_otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('สำเร็จ', 'ส่งรหัส OTP ไปที่อีเมลของคุณแล้ว', 'success');
                        $('#otpBox').fadeIn();
                    } else Swal.fire('ผิดพลาด', data.message, 'error');
                }).catch(err => {
                    Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ส่งอีเมลได้', 'error');
                }).finally(() => {
                    $('#btnSendOTP').prop('disabled', false).html('<i class="fas fa-paper-plane me-1"></i>ส่ง OTP');
                });
            });

            // ปุ่มยืนยัน OTP
            $('#btnVerifyOTP').click(function() {
                const otp = $('#otp_code').val();
                if (otp.length !== 6) return Swal.fire('แจ้งเตือน', 'กรุณากรอก OTP 6 หลักให้ครบ', 'warning');

                fetch('verify_otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ otp: otp })
                })
                .then(res => res.json())
                .then(data => {
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
            $('#addForm').on('submit', function(e) {
                e.preventDefault();

                // เช็คว่ามีช่องไหนแจ้งเตือน Error ค้างอยู่ไหม
                if ($('.is-invalid').length > 0) {
                    Swal.fire('ข้อมูลไม่ถูกต้อง', 'กรุณาแก้ไขข้อมูลในช่องที่มีขอบสีแดงให้ถูกต้อง', 'error');
                    return;
                }

                // เช็คว่าช่องที่บังคับกรอก (required) มีค่าว่างไหม
                let emptyFields = 0;
                $(this).find('input[required], select[required]').each(function() {
                    let val = $(this).val();
                    if (val === null || val.trim() === '') {
                        $(this).addClass('is-invalid');
                        emptyFields++;
                    }
                });

                if (emptyFields > 0) {
                    Swal.fire('ข้อมูลไม่ครบ', 'กรุณากรอกข้อมูลในช่องที่มีเครื่องหมายดอกจัน (*) ให้ครบถ้วน', 'warning');
                    return;
                }

                // เช็ครหัสผ่านให้ตรงกัน
                if ($('#password').val() !== $('#confirm_password').val()) {
                    Swal.fire('ข้อมูลไม่ตรงกัน', 'กรุณายืนยันรหัสผ่านให้ตรงกัน', 'warning');
                    $('#confirm_password').addClass('is-invalid');
                    return;
                }

                // เช็คสถานะการยืนยันอีเมล
                if (!isEmailVerified) {
                    Swal.fire({ icon: 'warning', title: 'รอสักครู่', text: 'กรุณายืนยันรหัส OTP ของอีเมลก่อนทำการบันทึก' });
                    return;
                }

                // หากผ่านทั้งหมด ให้ Submit ตามปกติ
                Swal.fire({
                    title: 'กำลังบันทึกข้อมูล...',
                    text: 'โปรดรอสักครู่',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                
                this.submit();
            });

        });

        // --- Image Preview ---
        function previewFile() {
            const preview = document.getElementById('previewImg');
            const defaultIcon = document.getElementById('defaultIcon');
            const file = document.getElementById('emp_image').files[0];
            const reader = new FileReader();

            reader.onloadend = function() {
                preview.src = reader.result;
                preview.style.display = 'block';
                defaultIcon.style.display = 'none';
            }
            if (file) reader.readAsDataURL(file);
        }

        // --- Prefix EN Logic ---
        function updateEngPrefix() {
            const select = document.getElementById('prefixSelect');
            const display = document.getElementById('prefix_en_display');
            const selectedOpt = select.options[select.selectedIndex];
            display.value = selectedOpt.getAttribute('data-en') || '';
        }

        // --- Location Logic ---
        function loadProvinces() {
            fetch('user_add.php?ajax_action=get_provinces')
                .then(r => r.json())
                .then(data => {
                    const sel = $('#provinceSelect');
                    data.forEach(d => sel.append(new Option(d.province_name_th, d.province_id)));
                });
        }

        function loadDistricts(provId) {
            const dist = $('#districtSelect');
            const sub = $('#subdistrictSelect');
            dist.empty().append('<option value="">-- เลือกอำเภอ --</option>').prop('disabled', true);
            sub.empty().append('<option value="">-- เลือกตำบล --</option>').prop('disabled', true);
            document.getElementById('zipcode').value = '';

            if(provId) {
                fetch(`user_add.php?ajax_action=get_districts&id=${provId}`)
                    .then(r => r.json())
                    .then(data => {
                        data.forEach(d => dist.append(new Option(d.district_name_th, d.district_id)));
                        dist.prop('disabled', false);
                    });
            }
        }

        function loadSubdistricts(distId) {
            const sub = $('#subdistrictSelect');
            sub.empty().append('<option value="">-- เลือกตำบล --</option>').prop('disabled', true);
            document.getElementById('zipcode').value = '';

            if(distId) {
                fetch(`user_add.php?ajax_action=get_subdistricts&id=${distId}`)
                    .then(r => r.json())
                    .then(data => {
                        data.forEach(d => {
                            let opt = new Option(d.subdistrict_name_th, d.subdistrict_id);
                            $(opt).data('zip', d.zip_code);
                            sub.append(opt);
                        });
                        sub.prop('disabled', false);
                    });
            }
        }

        function updateZipcode(select) {
            const zip = $(select).find(':selected').data('zip');
            document.getElementById('zipcode').value = zip || '';
        }

        // --- Shop/Branch Logic ---
        function loadShopData(shopId) {
            if(!shopId) return;
            // Load Branch
            fetch(`user_add.php?ajax_action=get_branches&shop_id=${shopId}`)
                .then(r => r.json())
                .then(data => {
                    const sel = $('#branchSelect');
                    sel.empty().append('<option value="">-- เลือกสาขา --</option>');
                    data.forEach(d => sel.append(new Option(d.branch_name, d.branch_id)));
                });
            // Load Dept
            fetch(`user_add.php?ajax_action=get_departments&shop_id=${shopId}`)
                .then(r => r.json())
                .then(data => {
                    const sel = $('#deptSelect');
                    sel.empty().append('<option value="">-- เลือกแผนก --</option>');
                    data.forEach(d => sel.append(new Option(d.dept_name, d.dept_id)));
                });
        }
    </script>
</body>
</html>