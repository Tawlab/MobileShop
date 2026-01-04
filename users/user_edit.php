<?php
session_start();
require '../config/config.php';

// ตรวจสอบสิทธิ์
checkPageAccess($conn, 'menu_manage_users');

// ==========================================================================================
// [1] AJAX HANDLER: (ต้องอยู่บนสุด ก่อนการเช็ค ID หรือ Query อื่นๆ)
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
// [2] FORM SUBMISSION: บันทึกการแก้ไข
// ==========================================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // รับค่าและ Sanitize
    $user_status = $_POST['user_status'];
    $role_id = intval($_POST['role_id']);

    // ข้อมูลสังกัด
    $shop_id = isset($_POST['shop_id']) ? intval($_POST['shop_id']) : $current_shop_id;
    $branch_id = intval($_POST['branch_id']);
    $dept_id = intval($_POST['dept_id']);

    // ข้อมูลส่วนตัว
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

    // ข้อมูลที่อยู่
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

    // จัดการรูปภาพ
    $emp_image_filename = $_POST['current_image']; // ค่าเดิม
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
            // 1. ดึง address_id เดิม
            $get_addr = $conn->query("SELECT Addresses_address_id FROM employees WHERE users_user_id = $edit_id");
            $old_addr_row = $get_addr->fetch_assoc();
            $address_id = $old_addr_row['Addresses_address_id'];

            // 2. อัปเดตที่อยู่
            if ($address_id) {
                $sql_addr = "UPDATE addresses SET home_no=?, moo=?, soi=?, road=?, village=?, subdistricts_subdistrict_id=? WHERE address_id=?";
                $stmt = $conn->prepare($sql_addr);
                $stmt->bind_param("sssssii", $home_no, $moo, $soi, $road, $village, $subdist_id, $address_id);
                $stmt->execute();
                $stmt->close();
            }

            // 3. อัปเดต Users
            $sql_user = "UPDATE users SET user_status = ?, update_at = NOW() WHERE user_id = ?";
            $stmt = $conn->prepare($sql_user);
            $stmt->bind_param("si", $user_status, $edit_id);
            $stmt->execute();
            $stmt->close();

            // 4. อัปเดต Employees
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

            // 5. อัปเดต Role
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
            $_SESSION['success'] = "บันทึกข้อมูลเรียบร้อยแล้ว";
            header("Location: user_list.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// ==========================================================================================
// [3] LOAD DATA: ดึงข้อมูลเดิมมาแสดง (ต้องทำก่อนกำหนดค่า JS)
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

// Initial Values for JS (กำหนดหลังจากมีค่า $data แล้ว)
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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            color: <?= $text_color ?>;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            background: linear-gradient(135deg, <?= $theme_color ?>, #14532d);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }

        .form-section-title {
            font-size: 1.1rem;
            font-weight: bold;
            color: <?= $theme_color ?>;
            border-bottom: 2px solid <?= $theme_color ?>20;
            padding-bottom: 10px;
            margin: 30px 0 20px 0;
        }

        .img-preview-box {
            width: 150px;
            height: 150px;
            border: 2px dashed #ccc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin: 0 auto;
            background: #f8f9fa;
            cursor: pointer;
            position: relative;
        }

        .img-preview-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .img-preview-box i {
            font-size: 3rem;
            color: #ccc;
        }

        .img-upload-btn {
            position: absolute;
            bottom: 0;
            width: 100%;
            background: rgba(0, 0, 0, 0.5);
            color: white;
            text-align: center;
            font-size: 0.8rem;
            padding: 5px 0;
            opacity: 0;
            transition: 0.3s;
        }

        .img-preview-box:hover .img-upload-btn {
            opacity: 1;
        }
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
                                    <h4 class="mb-0 text-white"><i class="fas fa-user-edit me-2"></i>แก้ไขข้อมูลผู้ใช้งาน: <?= htmlspecialchars($data['username']) ?></h4>
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
                                            <?php if (!empty($data['emp_image']) && file_exists("../uploads/employees/" . $data['emp_image'])): ?>
                                                <img id="previewImg" src="../uploads/employees/<?= $data['emp_image'] ?>" alt="Profile">
                                                <i class="fas fa-camera" id="defaultIcon" style="display: none;"></i>
                                            <?php else: ?>
                                                <img id="previewImg" src="#" alt="Preview" style="display: none;">
                                                <i class="fas fa-camera" id="defaultIcon"></i>
                                            <?php endif; ?>
                                            <div class="img-upload-btn">เปลี่ยนรูป</div>
                                        </label>
                                        <input type="file" name="emp_image" id="emp_image" class="d-none" accept="image/*" onchange="previewFile()">
                                        <input type="hidden" name="current_image" value="<?= $data['emp_image'] ?>">
                                        <div class="text-muted mt-2 small">คลิกเพื่อเปลี่ยนรูปโปรไฟล์</div>
                                    </div>

                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <h6 class="text-primary fw-bold"><i class="fas fa-lock me-2"></i>สถานะและสิทธิ์</h6>
                                            <div class="bg-light p-3 rounded-3 mb-3">
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">Username</label>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($data['username']) ?>" readonly>
                                                </div>
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <label class="form-label small fw-bold">บทบาท (Role) <span class="text-danger">*</span></label>
                                                        <select class="form-select select2" name="role_id" required>
                                                            <?php mysqli_data_seek($roles, 0);
                                                            while ($r = $roles->fetch_assoc()): ?>
                                                                <option value="<?= $r['role_id'] ?>" <?= ($r['role_id'] == $data['roles_role_id']) ? 'selected' : '' ?>>
                                                                    <?= $r['role_name'] ?>
                                                                </option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label small fw-bold">สถานะบัญชี <span class="text-danger">*</span></label>
                                                        <select class="form-select" name="user_status" required>
                                                            <option value="Active" <?= ($data['user_status'] == 'Active') ? 'selected' : '' ?>>ใช้งานปกติ (Active)</option>
                                                            <option value="Inactive" <?= ($data['user_status'] == 'Inactive') ? 'selected' : '' ?>>ระงับการใช้งาน (Inactive)</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <h6 class="text-primary fw-bold"><i class="fas fa-building me-2"></i>ข้อมูลสังกัด</h6>
                                            <div class="bg-light p-3 rounded-3">
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">ร้านค้า (Shop)</label>
                                                    <?php if ($is_super_admin): ?>
                                                        <select class="form-select" name="shop_id" id="shopSelect">
                                                            <option value="">-- เลือกร้านค้า --</option>
                                                            <?php while ($s = $shops->fetch_assoc()): ?>
                                                                <option value="<?= $s['shop_id'] ?>" <?= ($s['shop_id'] == $js_shop_id) ? 'selected' : '' ?>>
                                                                    <?= $s['shop_name'] ?>
                                                                </option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    <?php else: ?>
                                                        <input type="text" class="form-control bg-white" value="<?= $_SESSION['shop_name'] ?? 'ร้านค้าของคุณ' ?>" readonly>
                                                        <input type="hidden" name="shop_id" id="shopSelect" value="<?= $current_shop_id ?>">
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">สาขา (Branch) <span class="text-danger">*</span></label>
                                                    <select class="form-select select2" name="branch_id" id="branchSelect" required></select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">แผนก (Department) <span class="text-danger">*</span></label>
                                                    <select class="form-select select2" name="dept_id" id="deptSelect" required></select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <h6 class="text-primary fw-bold"><i class="fas fa-id-card me-2"></i>ข้อมูลส่วนตัว</h6>
                                            <div class="row g-2 mb-3">
                                                <div class="col-4">
                                                    <label class="form-label small fw-bold">คำนำหน้า <span class="text-danger">*</span></label>
                                                    <select class="form-select" name="prefix_id" id="prefixSelect" required>
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
                                                <div class="col-8">
                                                    <label class="form-label small fw-bold">คำนำหน้า (EN)</label>
                                                    <input type="text" id="prefix_en_display" class="form-control bg-light" readonly placeholder="Auto">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">ชื่อ (ไทย) <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="firstname" value="<?= htmlspecialchars($data['firstname_th']) ?>" required>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">นามสกุล (ไทย) <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="lastname" value="<?= htmlspecialchars($data['lastname_th']) ?>" required>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">First Name (EN)</label>
                                                    <input type="text" class="form-control" name="firstname_en" value="<?= htmlspecialchars($data['firstname_en']) ?>">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">Last Name (EN)</label>
                                                    <input type="text" class="form-control" name="lastname_en" value="<?= htmlspecialchars($data['lastname_en']) ?>">
                                                </div>
                                            </div>

                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">เลขบัตร ปชช.</label>
                                                    <input type="text" class="form-control" name="national_id" value="<?= htmlspecialchars($data['emp_national_id']) ?>" maxlength="13">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">วันเกิด</label>
                                                    <input type="date" class="form-control" name="birthday" value="<?= $data['emp_birthday'] ?>">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">เพศ <span class="text-danger">*</span></label>
                                                    <select class="form-select" name="gender" required>
                                                        <option value="Male" <?= ($data['emp_gender'] == 'Male') ? 'selected' : '' ?>>ชาย</option>
                                                        <option value="Female" <?= ($data['emp_gender'] == 'Female') ? 'selected' : '' ?>>หญิง</option>
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">เบอร์โทร <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($data['emp_phone_no']) ?>" maxlength="10" required>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">อีเมล</label>
                                                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($data['emp_email']) ?>">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">Line ID</label>
                                                    <input type="text" class="form-control" name="line_id" value="<?= htmlspecialchars($data['emp_line_id']) ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-section-title"><i class="fas fa-map-marker-alt me-2"></i>ข้อมูลที่อยู่ (Address)</div>
                                    <div class="row g-3">
                                        <div class="col-md-2 col-6"><label class="form-label small fw-bold">บ้านเลขที่</label><input type="text" class="form-control" name="home_no" value="<?= htmlspecialchars($data['home_no']) ?>"></div>
                                        <div class="col-md-2 col-6"><label class="form-label small fw-bold">หมู่ที่</label><input type="text" class="form-control" name="moo" value="<?= htmlspecialchars($data['moo']) ?>"></div>
                                        <div class="col-md-4"><label class="form-label small fw-bold">หมู่บ้าน/อาคาร</label><input type="text" class="form-control" name="village" value="<?= htmlspecialchars($data['village']) ?>"></div>
                                        <div class="col-md-2 col-6"><label class="form-label small fw-bold">ซอย</label><input type="text" class="form-control" name="soi" value="<?= htmlspecialchars($data['soi']) ?>"></div>
                                        <div class="col-md-2 col-6"><label class="form-label small fw-bold">ถนน</label><input type="text" class="form-control" name="road" value="<?= htmlspecialchars($data['road']) ?>"></div>

                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">จังหวัด <span class="text-danger">*</span></label>
                                            <select class="form-select select2" id="provinceSelect">
                                                <option value="">-- เลือกจังหวัด --</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">อำเภอ <span class="text-danger">*</span></label>
                                            <select class="form-select select2" id="districtSelect" disabled>
                                                <option value="">-- เลือกอำเภอ --</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">ตำบล <span class="text-danger">*</span></label>
                                            <select class="form-select select2" name="subdistrict_id" id="subdistrictSelect" disabled required>
                                                <option value="">-- เลือกตำบล --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold">รหัสไปรษณีย์</label>
                                            <input type="text" id="zipcode" class="form-control bg-light" value="<?= htmlspecialchars($data['zip_code']) ?>" readonly>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-center gap-3 mt-5">
                                        <a href="user_list.php" class="btn btn-light rounded-pill px-4">ยกเลิก</a>
                                        <button type="submit" class="btn btn-success rounded-pill px-5 fw-bold shadow-sm">
                                            <i class="fas fa-save me-2"></i>บันทึกการแก้ไข
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
    // --- 1. รับค่าจาก PHP (ค่าเดิมของผู้ใช้) ---
    const savedProv = "<?= $js_province_id ?>";
    const savedDist = "<?= $js_district_id ?>";
    const savedSub = "<?= $js_subdistrict_id ?>";
    const savedBranch = "<?= $js_branch_id ?>";
    const savedDept = "<?= $js_dept_id ?>";
    const currentShopId = "<?= $js_shop_id ?>"; // Shop ID เดิมของผู้ใช้

    $(document).ready(function() {
        // --- 2. เริ่มต้น Select2 ---
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });

        // --- 3. ผูก Event Listeners (จัดการการเปลี่ยนค่า) ---
        
        // 3.1 เปลี่ยนร้านค้า (สำหรับ Admin)
        $('#shopSelect').on('change', function() {
            // ส่งค่า false ไปบอกว่า นี่ไม่ใช่การโหลดครั้งแรก (ไม่ต้องจำค่าสาขาเดิม)
            loadShopData($(this).val(), false); 
        });

        // 3.2 เปลี่ยนที่อยู่
        $('#provinceSelect').on('change', function() {
            loadDistricts($(this).val());
        });

        $('#districtSelect').on('change', function() {
            loadSubdistricts($(this).val());
        });

        $('#subdistrictSelect').on('change', function() {
            updateZipcode();
        });

        // 3.3 เปลี่ยนคำนำหน้า
        $('#prefixSelect').on('change', function() {
            updateEngPrefix();
        });

        // --- 4. โหลดข้อมูลเริ่มต้น (Initial Load) ---
        loadProvinces();
        updateEngPrefix();
        
        // โหลดข้อมูลร้านค้า/สาขาเริ่มต้น (ส่ง true เพื่อบอกว่าให้เลือกค่าเดิมที่เซฟไว้)
        if (currentShopId) {
            loadShopData(currentShopId, true); 
        }
    });

    // ==========================================
    // SHOP / BRANCH / DEPARTMENT LOGIC (ส่วนที่แก้ไข)
    // ==========================================
    function loadShopData(shopId, isInit = false) {
        const branchSelect = $('#branchSelect');
        const deptSelect = $('#deptSelect');

        // กรณีไม่ได้เลือกร้านค้า ให้เคลียร์ค่าและปิดการใช้งาน
        if (!shopId) {
            branchSelect.empty().append('<option value="">-- รอการเลือก --</option>').prop('disabled', true);
            deptSelect.empty().append('<option value="">-- รอการเลือก --</option>').prop('disabled', true);
            return;
        }

        // 1. โหลดสาขา (Branch)
        $.getJSON(`user_edit.php?ajax_action=get_branches&shop_id=${shopId}`, function(data) {
            branchSelect.empty().append('<option value="">-- เลือกสาขา --</option>');
            
            $.each(data, function(i, d) {
                // เช็คเงื่อนไข: ต้องเป็นการโหลดครั้งแรก AND ID ตรงกับของเดิม ถึงจะ Selected
                const isSel = (isInit && d.branch_id == savedBranch);
                branchSelect.append(new Option(d.branch_name, d.branch_id, false, isSel));
            });
            
            branchSelect.prop('disabled', false).trigger('change'); // รีเฟรช Select2
        });

        // 2. โหลดแผนก (Department)
        $.getJSON(`user_edit.php?ajax_action=get_departments&shop_id=${shopId}`, function(data) {
            deptSelect.empty().append('<option value="">-- เลือกแผนก --</option>');
            
            $.each(data, function(i, d) {
                const isSel = (isInit && d.dept_id == savedDept);
                deptSelect.append(new Option(d.dept_name, d.dept_id, false, isSel));
            });
            
            deptSelect.prop('disabled', false).trigger('change'); // รีเฟรช Select2
        });
    }

    // ==========================================
    // LOCATION LOGIC (ที่อยู่)
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
        
        // ถ้าเปลี่ยนจังหวัด ต้องเคลียร์ตำบลและรหัสไปรษณีย์ด้วย (ยกเว้นตอนโหลดครั้งแรก)
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
        const zip = $('#subdistrictSelect').find(':selected').data('zip');
        $('#zipcode').val(zip || '');
    }

    // ==========================================
    // UTILITIES (รูปภาพ / คำนำหน้า / Validation)
    // ==========================================
    function previewFile() {
        const preview = document.getElementById('previewImg');
        const defaultIcon = document.getElementById('defaultIcon');
        const file = document.getElementById('emp_image').files[0];
        const reader = new FileReader();

        reader.onloadend = function() {
            preview.src = reader.result;
            preview.style.display = 'block';
            if(defaultIcon) defaultIcon.style.display = 'none';
        }
        if (file) reader.readAsDataURL(file);
    }

    function updateEngPrefix() {
        const select = document.getElementById('prefixSelect');
        const display = document.getElementById('prefix_en_display');
        if(select && display) {
            const selectedOpt = select.options[select.selectedIndex];
            display.value = selectedOpt.getAttribute('data-en') || '';
        }
    }

    // Bootstrap Form Validation
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