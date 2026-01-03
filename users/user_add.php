<?php
session_start();
require '../config/config.php';

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

// ฟังก์ชันหา ID ถัดไป (Manual Auto-Increment)
function getNextId($conn, $table, $column) {
    $sql = "SELECT MAX($column) as max_id FROM $table";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return ($row['max_id']) ? $row['max_id'] + 1 : 1;
}

// ==========================================================================================
// [1] AJAX HANDLER
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
// [2] FORM SUBMISSION
// ==========================================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // รับค่าพื้นฐาน
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $shop_select = isset($_POST['shop_id']) ? intval($_POST['shop_id']) : $current_shop_id;
    $branch_id = intval($_POST['branch_id']);
    $dept_id = intval($_POST['dept_id']);
    $role_id = intval($_POST['role_id']); // [แก้ไข] รับค่า Role ID จากฟอร์มโดยตรง
    
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

    // Validation
    if (empty($username) || empty($password)) $errors[] = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
    if ($password !== $confirm_password) $errors[] = "รหัสผ่านยืนยันไม่ตรงกัน";
    if (empty($firstname_th) || empty($lastname_th)) $errors[] = "กรุณากรอกชื่อ-นามสกุล (ไทย)";
    if (empty($subdist_id)) $errors[] = "กรุณาเลือกจังหวัด/อำเภอ/ตำบล ให้ครบถ้วน";
    if (empty($role_id)) $errors[] = "กรุณาเลือกบทบาทผู้ใช้งาน"; // [เพิ่ม] ตรวจสอบว่าเลือกบทบาทหรือไม่
    
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
            // --- 1. บันทึกที่อยู่ ---
            $new_addr_id = getNextId($conn, 'addresses', 'address_id');
            $sql_addr = "INSERT INTO addresses (address_id, home_no, moo, soi, road, village, subdistricts_subdistrict_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_addr);
            $stmt->bind_param("isssssi", $new_addr_id, $home_no, $moo, $soi, $road, $village, $subdist_id);
            if (!$stmt->execute()) throw new Exception("บันทึกที่อยู่ไม่สำเร็จ");
            $stmt->close();

            // --- 2. สร้าง User ---
            $new_user_id = getNextId($conn, 'users', 'user_id'); 
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql_user = "INSERT INTO users (user_id, username, password, user_status, create_at, update_at) VALUES (?, ?, ?, 'Active', NOW(), NOW())";
            $stmt = $conn->prepare($sql_user);
            $stmt->bind_param("iss", $new_user_id, $username, $hashed_password);
            $stmt->execute();
            $stmt->close();

            // --- 3. สร้าง Employee ---
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
            
            // [แก้ไข] แก้จำนวนตัว s ใน bind_param ให้ถูกต้อง (19 ตัวแปร = 19 ตัวอักษร)
            // i=1, s=11, i=6, s=1 => รวมเป็น 19
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

            // --- 4. กำหนด Role ---
            $sql_role = "INSERT INTO user_roles (roles_role_id, users_user_id, create_at) VALUES (?, ?, NOW())";
            $stmt = $conn->prepare($sql_role);
            $stmt->bind_param("ii", $role_id, $new_user_id); // ใช้ $role_id ที่รับจาก POST
            $stmt->execute();
            $stmt->close();

            // --- 5. เพิ่ม Config ---
            $sql_conf = "INSERT INTO systemconfig (user_id, theme_color, background_color, text_color, font_style, header_bg_color, header_text_color) 
                         VALUES (?, '#198754', '#ffffff', '#000000', 'Prompt', '#198754', '#ffffff')";
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
            if ($emp_image_filename && file_exists("../uploads/employees/" . $emp_image_filename)) {
                unlink("../uploads/employees/" . $emp_image_filename);
            }
            $errors[] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// ==========================================================================================
// [3] PREPARE VIEW DATA
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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background-color: <?= $background_color ?>; font-family: '<?= $font_style ?>', sans-serif; color: <?= $text_color ?>; }
        .card { border: none; border-radius: 15px; box-shadow: 0 0 20px rgba(0,0,0,0.05); }
        .card-header { background: linear-gradient(135deg, <?= $theme_color ?>, #14532d); color: white; border-radius: 15px 15px 0 0 !important; padding: 1.5rem; }
        .form-section-title { font-size: 1.1rem; font-weight: bold; color: <?= $theme_color ?>; border-bottom: 2px solid <?= $theme_color ?>20; padding-bottom: 10px; margin: 30px 0 20px 0; }
        .img-preview-box { width: 150px; height: 150px; border: 2px dashed #ccc; border-radius: 50%; display: flex; align-items: center; justify-content: center; overflow: hidden; margin: 0 auto; background: #f8f9fa; cursor: pointer; position: relative; }
        .img-preview-box img { width: 100%; height: 100%; object-fit: cover; }
        .img-preview-box i { font-size: 3rem; color: #ccc; }
        .img-upload-btn { position: absolute; bottom: 0; width: 100%; background: rgba(0,0,0,0.5); color: white; text-align: center; font-size: 0.8rem; padding: 5px 0; opacity: 0; transition: 0.3s; }
        .img-preview-box:hover .img-upload-btn { opacity: 1; }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        
                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h4 class="mb-0 text-white"><i class="fas fa-user-plus me-2"></i>เพิ่มบัญชีผู้ใช้และพนักงาน</h4>
                                </div>
                                <div class="card-body p-4">

                                    <?php if (!empty($errors)): ?>
                                        <div class="alert alert-danger shadow-sm rounded-3">
                                            <ul class="mb-0">
                                                <?php foreach ($errors as $err): ?><li><?= $err ?></li><?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>

                                    <div class="text-center mb-4">
                                        <label for="emp_image" class="img-preview-box" id="previewBox">
                                            <img id="previewImg" src="#" alt="Preview" style="display: none;">
                                            <i class="fas fa-camera" id="defaultIcon"></i>
                                            <div class="img-upload-btn">เปลี่ยนรูป</div>
                                        </label>
                                        <input type="file" name="emp_image" id="emp_image" class="d-none" accept="image/*" onchange="previewFile()">
                                        <div class="text-muted mt-2 small">คลิกเพื่ออัปโหลดรูปโปรไฟล์ (ถ้ามี)</div>
                                    </div>

                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <h6 class="text-primary fw-bold"><i class="fas fa-lock me-2"></i>ข้อมูลเข้าระบบ</h6>
                                            <div class="bg-light p-3 rounded-3 mb-3">
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">Username <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                                                </div>
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <label class="form-label small fw-bold">Password <span class="text-danger">*</span></label>
                                                        <input type="password" class="form-control" name="password" required>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label small fw-bold">Confirm Password <span class="text-danger">*</span></label>
                                                        <input type="password" class="form-control" name="confirm_password" required>
                                                    </div>
                                                </div>
                                            </div>

                                            <h6 class="text-primary fw-bold"><i class="fas fa-building me-2"></i>ข้อมูลสังกัด</h6>
                                            <div class="bg-light p-3 rounded-3">
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">ร้านค้า (Shop)</label>
                                                    <?php if ($is_super_admin): ?>
                                                        <select class="form-select" name="shop_id" id="shopSelect" required onchange="loadShopData(this.value)">
                                                            <option value="">-- เลือกร้านค้า --</option>
                                                            <?php while($s = $shops->fetch_assoc()): ?>
                                                                <option value="<?= $s['shop_id'] ?>"><?= $s['shop_name'] ?></option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    <?php else: ?>
                                                        <input type="text" class="form-control bg-white" value="<?= $_SESSION['shop_name'] ?? 'ร้านค้าของคุณ' ?>" readonly>
                                                        <input type="hidden" name="shop_id" id="shopSelect" value="<?= $current_shop_id ?>">
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">สาขา (Branch) <span class="text-danger">*</span></label>
                                                    <select class="form-select select2" name="branch_id" id="branchSelect" required>
                                                        <option value="">-- รอการเลือก --</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">แผนก (Department) <span class="text-danger">*</span></label>
                                                    <select class="form-select select2" name="dept_id" id="deptSelect" required>
                                                        <option value="">-- รอการเลือก --</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">บทบาท (Role) <span class="text-danger">*</span></label>
                                                    <select class="form-select select2" name="role_id" required>
                                                        <option value="">-- เลือกบทบาท --</option>
                                                        <?php while($r = $roles->fetch_assoc()): ?>
                                                            <option value="<?= $r['role_id'] ?>"><?= $r['role_name'] ?></option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <h6 class="text-primary fw-bold"><i class="fas fa-id-card me-2"></i>ข้อมูลส่วนตัว</h6>
                                            <div class="row g-2 mb-3">
                                                <div class="col-4">
                                                    <label class="form-label small fw-bold">คำนำหน้า <span class="text-danger">*</span></label>
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
                                                <div class="col-8">
                                                    <label class="form-label small fw-bold">คำนำหน้า (EN)</label>
                                                    <input type="text" id="prefix_en_display" class="form-control bg-light" readonly placeholder="Auto">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">ชื่อ (ไทย) <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="firstname" required>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">นามสกุล (ไทย) <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="lastname" required>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">First Name (EN)</label>
                                                    <input type="text" class="form-control" name="firstname_en">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">Last Name (EN)</label>
                                                    <input type="text" class="form-control" name="lastname_en">
                                                </div>
                                            </div>
                                            
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">เลขบัตร ปชช.</label>
                                                    <input type="text" class="form-control" name="national_id" maxlength="13">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">วันเกิด</label>
                                                    <input type="date" class="form-control" name="birthday">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">เพศ <span class="text-danger">*</span></label>
                                                    <select class="form-select" name="gender" required>
                                                        <option value="Male">ชาย</option>
                                                        <option value="Female">หญิง</option>
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">เบอร์โทร <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="phone" maxlength="10" required>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">อีเมล</label>
                                                    <input type="email" class="form-control" name="email">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">Line ID</label>
                                                    <input type="text" class="form-control" name="line_id">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-section-title"><i class="fas fa-map-marker-alt me-2"></i>ข้อมูลที่อยู่ (Address)</div>
                                    <div class="row g-3">
                                        <div class="col-md-2 col-6"><label class="form-label small fw-bold">บ้านเลขที่</label><input type="text" class="form-control" name="home_no"></div>
                                        <div class="col-md-2 col-6"><label class="form-label small fw-bold">หมู่ที่</label><input type="text" class="form-control" name="moo"></div>
                                        <div class="col-md-4"><label class="form-label small fw-bold">หมู่บ้าน/อาคาร</label><input type="text" class="form-control" name="village"></div>
                                        <div class="col-md-2 col-6"><label class="form-label small fw-bold">ซอย</label><input type="text" class="form-control" name="soi"></div>
                                        <div class="col-md-2 col-6"><label class="form-label small fw-bold">ถนน</label><input type="text" class="form-control" name="road"></div>
                                        
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">จังหวัด <span class="text-danger">*</span></label>
                                            <select class="form-select select2" id="provinceSelect" onchange="loadDistricts(this.value)">
                                                <option value="">-- เลือกจังหวัด --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">อำเภอ <span class="text-danger">*</span></label>
                                            <select class="form-select select2" id="districtSelect" onchange="loadSubdistricts(this.value)" disabled>
                                                <option value="">-- เลือกอำเภอ --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">ตำบล <span class="text-danger">*</span></label>
                                            <select class="form-select select2" name="subdistrict_id" id="subdistrictSelect" onchange="updateZipcode(this)" disabled required>
                                                <option value="">-- เลือกตำบล --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold">รหัสไปรษณีย์</label>
                                            <input type="text" id="zipcode" class="form-control bg-light" readonly>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-center gap-3 mt-5">
                                        <a href="user_list.php" class="btn btn-light rounded-pill px-4">ยกเลิก</a>
                                        <button type="submit" class="btn btn-success rounded-pill px-5 fw-bold shadow-sm">
                                            <i class="fas fa-save me-2"></i>บันทึกข้อมูล
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            // Init Select2
            $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
            
            // Initial Data Load
            loadProvinces();
            updateEngPrefix(); 

            // ถ้าเป็น User ทั่วไป โหลดสาขา/แผนก อัตโนมัติ
            const shopInput = document.getElementById('shopSelect');
            if(shopInput.tagName === 'INPUT') loadShopData(shopInput.value);
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
            $('#zipcode').value = '';

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
            $('#zipcode').value = '';

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

        // Form Validation
        (function() {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
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