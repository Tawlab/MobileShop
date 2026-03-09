<?php
ob_start();
session_start();
require '../config/config.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: ../global/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// -----------------------------------------------------------------------------
// AJAX สำหรับเปลี่ยนที่อยู่ (ดึงข้อมูล Dropdown)
// -----------------------------------------------------------------------------
if (isset($_POST['action'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $data = [];

    if ($action === 'get_districts') {
        $sql = "SELECT district_id, district_name_th FROM districts WHERE provinces_province_id = $id ORDER BY district_name_th";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
    } elseif ($action === 'get_subdistricts') {
        $sql = "SELECT subdistrict_id, subdistrict_name_th, zip_code FROM subdistricts WHERE districts_district_id = $id ORDER BY subdistrict_name_th";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
    }
    echo json_encode($data);
    exit;
}

// -----------------------------------------------------------------------------
// ดึงข้อมูลปัจจุบันของผู้ใช้
// -----------------------------------------------------------------------------
$sql_user = "SELECT e.*, u.username, 
               d.dept_name, b.branch_name,
               a.address_id, a.home_no, a.moo, a.soi, a.road, 
               sd.subdistrict_id, dist.district_id, pv.province_id, sd.zip_code,
               p.prefix_th, p.prefix_en  
        FROM employees e
        JOIN users u ON e.users_user_id = u.user_id
        LEFT JOIN prefixs p ON e.prefixs_prefix_id = p.prefix_id
        LEFT JOIN departments d ON e.departments_dept_id = d.dept_id
        LEFT JOIN branches b ON e.branches_branch_id = b.branch_id
        LEFT JOIN addresses a ON e.Addresses_address_id = a.address_id
        LEFT JOIN subdistricts sd ON a.subdistricts_subdistrict_id = sd.subdistrict_id
        LEFT JOIN districts dist ON sd.districts_district_id = dist.district_id
        LEFT JOIN provinces pv ON dist.provinces_province_id = pv.province_id
        WHERE e.users_user_id = ?";

$stmt_u = $conn->prepare($sql_user);
$stmt_u->bind_param("i", $user_id);
$stmt_u->execute();
$user_data = $stmt_u->get_result()->fetch_assoc();
$stmt_u->close();

// ดึงบทบาท Role
$sql_role = "SELECT r.role_name FROM user_roles ur JOIN roles r ON ur.roles_role_id = r.role_id WHERE ur.users_user_id = ?";
$stmt_role = $conn->prepare($sql_role);
$stmt_role->bind_param("i", $user_id);
$stmt_role->execute();
$role_result = $stmt_role->get_result()->fetch_assoc();
$user_role_name = strtolower($role_result['role_name'] ?? 'user'); // แปลงเป็นตัวพิมพ์เล็กเพื่อความชัวร์
$stmt_role->close();

if (!$user_data) {
    die("ไม่พบข้อมูลพนักงาน");
}

$emp_id = $user_data['emp_id']; // เก็บ emp_id ไว้ใช้เป็น Reference ตอนเช็คข้อมูลซ้ำ

// -----------------------------------------------------------------------------
// บันทึกข้อมูล (POST)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $prefix_id = $_POST['prefix_id'];
    $fname_th  = trim($_POST['firstname_th']);
    $lname_th  = trim($_POST['lastname_th']);
    $fname_en  = trim($_POST['firstname_en']);
    $lname_en  = trim($_POST['lastname_en']);
    $national  = trim($_POST['emp_national_id']);
    $birthday  = !empty($_POST['emp_birthday']) ? $_POST['emp_birthday'] : NULL;
    $gender    = $_POST['emp_gender'];
    $religion  = $_POST['religion_id'];
    $phone     = trim($_POST['emp_phone_no']);
    $email     = trim($_POST['emp_email']);
    $line      = trim($_POST['emp_line_id']);

    $addr_id   = (int)$_POST['address_id'];
    $home_no   = trim($_POST['home_no']);
    $moo       = trim($_POST['moo']);
    $soi       = trim($_POST['soi']);
    $road      = trim($_POST['road']);
    $subdist   = (int)$_POST['subdistrict_id'];

    $errors = [];
    if (empty($fname_th) || empty($lname_th)) $errors[] = "กรุณากรอกชื่อ-นามสกุลภาษาไทยให้ครบถ้วน";
    if (empty($national)) $errors[] = "กรุณากรอกเลขบัตรประจำตัวประชาชน";
    if (empty($phone)) $errors[] = "กรุณากรอกเบอร์โทรศัพท์";

    // ตรวจสอบการยืนยันอีเมลหากมีการเปลี่ยนแปลง
    if ($email !== $user_data['emp_email'] && !empty($email) && (!isset($_SESSION['email_verified']) || $_SESSION['email_verified'] !== true)) {
        $errors[] = "คุณได้แก้ไขอีเมล กรุณายืนยันรหัส OTP ให้สำเร็จก่อนทำการบันทึก";
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
    } else {
        mysqli_autocommit($conn, false);
        try {
            // อัปเดตที่อยู่
            $sql_addr = "UPDATE addresses SET home_no=?, moo=?, soi=?, road=?, subdistricts_subdistrict_id=? WHERE address_id=?";
            $stmt_addr = $conn->prepare($sql_addr);
            $stmt_addr->bind_param("ssssii", $home_no, $moo, $soi, $road, $subdist, $addr_id);
            $stmt_addr->execute();
            $stmt_addr->close();

            // อัปเดตข้อมูลพนักงาน
            $sql_emp = "UPDATE employees SET 
                        prefixs_prefix_id=?, firstname_th=?, lastname_th=?, firstname_en=?, lastname_en=?, 
                        emp_national_id=?, emp_birthday=?, emp_gender=?, religions_religion_id=?,
                        emp_phone_no=?, emp_email=?, emp_line_id=?, update_at=NOW()
                        WHERE users_user_id=?";
            $stmt_emp = $conn->prepare($sql_emp);
            $stmt_emp->bind_param(
                "issssssiisssi",
                $prefix_id,
                $fname_th,
                $lname_th,
                $fname_en,
                $lname_en,
                $national,
                $birthday,
                $gender,
                $religion,
                $phone,
                $email,
                $line,
                $user_id
            );
            $stmt_emp->execute();
            $stmt_emp->close();

            // ---------------------------------------------------------
            // จัดการอัปโหลดรูปโปรไฟล์ (อนุญาตเฉพาะคนที่ไม่ใช่บทบาท 'user')
            // ---------------------------------------------------------
            if ($user_role_name !== 'user') {
                // เช็คว่ามีการแนบไฟล์มา และไม่มี Error
                if (isset($_FILES['emp_image']) && $_FILES['emp_image']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['emp_image']['tmp_name'];
                    $file_name = $_FILES['emp_image']['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

                    if (in_array($file_ext, $allowed_ext)) {
                        $new_file_name = "profile_" . $user_id . "_" . time() . "." . $file_ext;
                        $upload_path = '../uploads/employees/';

                        // สร้างโฟลเดอร์ถ้ายังไม่มี
                        if (!is_dir($upload_path)) {
                            mkdir($upload_path, 0777, true);
                        }

                        // อัปโหลดไฟล์ไปที่โฟลเดอร์
                        if (move_uploaded_file($file_tmp, $upload_path . $new_file_name)) {
                            // อัปเดตชื่อรูปลงในฐานข้อมูล
                            $sql_img = "UPDATE employees SET emp_image = ? WHERE users_user_id = ?";
                            $stmt_img = $conn->prepare($sql_img);
                            $stmt_img->bind_param("si", $new_file_name, $user_id);
                            $stmt_img->execute();
                            $stmt_img->close();

                            // ลบรูปเก่าทิ้ง (เพื่อประหยัดพื้นที่เซิร์ฟเวอร์)
                            if (!empty($user_data['emp_image']) && file_exists($upload_path . $user_data['emp_image'])) {
                                unlink($upload_path . $user_data['emp_image']);
                            }
                        }
                    } else {
                        throw new Exception("ไฟล์รูปภาพต้องเป็นนามสกุล JPG, PNG, หรือ GIF เท่านั้น");
                    }
                }
            }
            // ---------------------------------------------------------

            mysqli_commit($conn);
            unset($_SESSION['email_verified']); // ล้างสถานะการยืนยัน
            $_SESSION['success'] = "บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว";
            header("Location: change_profile.php");
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// Master Data Dropdowns
$prefixes = mysqli_query($conn, "SELECT * FROM prefixs WHERE is_active = 1");
$religions = mysqli_query($conn, "SELECT * FROM religions WHERE is_active = 1");
$provinces = mysqli_query($conn, "SELECT * FROM provinces ORDER BY province_name_th");

$districts = [];
if ($user_data['province_id']) {
    $res_dist = mysqli_query($conn, "SELECT * FROM districts WHERE provinces_province_id = {$user_data['province_id']} ORDER BY district_name_th");
    while ($r = mysqli_fetch_assoc($res_dist)) $districts[] = $r;
}

$subdistricts = [];
if ($user_data['district_id']) {
    $res_sub = mysqli_query($conn, "SELECT * FROM subdistricts WHERE districts_district_id = {$user_data['district_id']} ORDER BY subdistrict_name_th");
    while ($r = mysqli_fetch_assoc($res_sub)) $subdistricts[] = $r;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>แก้ไขข้อมูลส่วนตัว</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: #f8fafc;
            font-family: 'Prompt', sans-serif;
            color: #333;
        }

        .main-card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            background: #fff;
            overflow: hidden;
        }

        .card-header-custom {
            background: linear-gradient(135deg, #198754 0%, #14532d 100%);
            color: white;
            padding: 1.5rem;
        }

        .form-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #198754;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            margin-top: 2rem;
        }

        .form-section-title i {
            margin-right: 10px;
            background: #e8f5e9;
            color: #198754;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1rem;
        }

        .form-label {
            font-weight: 500;
            font-size: 0.95rem;
            color: #555;
        }

        .required-star {
            color: #dc3545;
            margin-left: 3px;
        }

        .readonly-input {
            background-color: #f1f5f9;
            cursor: not-allowed;
            color: #64748b;
            font-weight: 500;
        }

        /* สไตล์สำหรับรูปโปรไฟล์และไอคอน */
        .profile-wrapper {
            text-align: center;
            margin-bottom: 2rem;
        }

        .profile-img-container {
            width: 140px;
            height: 140px;
            margin: 0 auto;
            border-radius: 50%;
            border: 4px solid #e2e8f0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-icon {
            font-size: 6rem;
            color: #adb5bd;
        }

        .is-invalid {
            border-color: #dc3545 !important;
        }

        .is-valid {
            border-color: #198754 !important;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container py-4" style="max-width: 1000px;">

                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                    <div>
                        <h3 class="fw-bold text-success mb-1"><i class="fas fa-user-edit me-2"></i>แก้ไขข้อมูลส่วนตัว</h3>
                        <p class="text-muted mb-0">จัดการบัญชีผู้ใช้งานและการติดต่อ</p>
                    </div>
                    <a href="../home/dashboard.php" class="btn btn-outline-secondary rounded-pill px-4 shadow-sm"><i class="fas fa-home me-1"></i> กลับหน้าหลัก</a>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-3"><i class="fas fa-check-circle me-2"></i> <?= $_SESSION['success'];
                                                                                                                                                unset($_SESSION['success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3"><i class="fas fa-exclamation-circle me-2"></i> <?= $_SESSION['error'];
                                                                                                                                                    unset($_SESSION['error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <div class="main-card mb-5">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-white"><i class="fas fa-id-badge me-2"></i> แบบฟอร์มข้อมูลส่วนตัว</h5>
                        <a href="change_password.php" class="btn btn-light btn-sm fw-bold text-success rounded-pill px-3"><i class="fas fa-key me-1"></i> เปลี่ยนรหัสผ่าน</a>
                    </div>

                    <div class="card-body p-4 p-md-5">

                        <form method="POST" id="profileForm" class="needs-validation" enctype="multipart/form-data" novalidate>

                            <div class="profile-wrapper">
                                <div class="profile-img-container">
                                    <?php
                                    // ตรวจสอบว่ามีชื่อไฟล์ในฐานข้อมูล และไฟล์นั้นมีอยู่จริงในเครื่อง
                                    $image_path = '../uploads/employees/' . $user_data['emp_image'];
                                    if (!empty($user_data['emp_image']) && file_exists($image_path)):
                                    ?>
                                        <img src="<?= $image_path ?>?t=<?= time() ?>" alt="Profile" class="profile-img">
                                    <?php else: ?>
                                        <i class="bi bi-person-circle profile-icon"></i>
                                    <?php endif; ?>
                                </div>

                                <?php if ($user_role_name !== 'user'): ?>
                                    <div class="mt-3 d-flex justify-content-center">
                                        <div class="input-group input-group-sm" style="max-width: 250px;">
                                            <span class="input-group-text bg-white"><i class="fas fa-camera text-success"></i></span>
                                            <input type="file" name="emp_image" id="emp_image" class="form-control" accept="image/jpeg, image/png, image/gif" onchange="previewImage(event)">
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-3">
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill fs-6">
                                        <i class="fas fa-briefcase me-1"></i> <?= htmlspecialchars($user_data['dept_name'] ?? 'ไม่มีแผนก') ?>
                                    </span>
                                </div>
                            </div>

                            <div class="row g-3 bg-light p-3 rounded-3 border mb-4">
                                <div class="col-md-6">
                                    <label class="form-label text-muted small mb-1">รหัสพนักงาน</label>
                                    <input type="text" class="form-control readonly-input" value="<?= htmlspecialchars($user_data['emp_code']) ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small mb-1">ชื่อผู้ใช้งาน (Username)</label>
                                    <input type="text" class="form-control readonly-input" value="<?= htmlspecialchars($user_data['username']) ?>" readonly>
                                </div>
                            </div>

                            <h5 class="form-section-title"><i class="fas fa-user"></i>ข้อมูลส่วนตัว</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">เลขบัตรประจำตัวประชาชน <span class="required-star">*</span></label>
                                    <input type="text" name="emp_national_id" id="emp_national_id" class="form-control" maxlength="13" required
                                        value="<?= htmlspecialchars($user_data['emp_national_id']) ?>"
                                        data-orig="<?= htmlspecialchars($user_data['emp_national_id']) ?>">
                                    <div class="invalid-feedback">กรุณากรอกเลขบัตร 13 หลักให้ถูกต้อง</div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">วันเกิด</label>
                                    <input type="date" name="emp_birthday" class="form-control" value="<?= htmlspecialchars($user_data['emp_birthday']) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">เพศ</label>
                                    <select name="emp_gender" class="form-select">
                                        <option value="Male" <?= $user_data['emp_gender'] == 'Male' ? 'selected' : '' ?>>ชาย</option>
                                        <option value="Female" <?= $user_data['emp_gender'] == 'Female' ? 'selected' : '' ?>>หญิง</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-2">
                                    <label class="form-label">คำนำหน้า (TH)</label>
                                    <select name="prefix_id" id="prefix_id" class="form-select" onchange="updatePrefixEn()">
                                        <?php
                                        mysqli_data_seek($prefixes, 0);
                                        while ($p = $prefixes->fetch_assoc()):
                                        ?>
                                            <option value="<?= $p['prefix_id'] ?>" data-en="<?= htmlspecialchars($p['prefix_en'] ?? '') ?>" <?= $p['prefix_id'] == $user_data['prefixs_prefix_id'] ? 'selected' : '' ?>>
                                                <?= $p['prefix_th'] ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">ชื่อ (ภาษาไทย) <span class="required-star">*</span></label>
                                    <input type="text" name="firstname_th" class="form-control input-thai" value="<?= htmlspecialchars($user_data['firstname_th']) ?>" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">นามสกุล (ภาษาไทย) <span class="required-star">*</span></label>
                                    <input type="text" name="lastname_th" class="form-control input-thai" value="<?= htmlspecialchars($user_data['lastname_th']) ?>" required>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-2">
                                    <label class="form-label">คำนำหน้า (EN)</label>
                                    <input type="text" id="prefix_en" class="form-control readonly-input" value="<?= htmlspecialchars($user_data['prefix_en']) ?>" readonly>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="firstname_en" class="form-control input-eng" value="<?= htmlspecialchars($user_data['firstname_en']) ?>" placeholder="English Only">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="lastname_en" class="form-control input-eng" value="<?= htmlspecialchars($user_data['lastname_en']) ?>" placeholder="English Only">
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">ศาสนา</label>
                                    <select name="religion_id" class="form-select">
                                        <?php
                                        mysqli_data_seek($religions, 0);
                                        while ($r = $religions->fetch_assoc()):
                                        ?>
                                            <option value="<?= $r['religion_id'] ?>" <?= $r['religion_id'] == $user_data['religions_religion_id'] ? 'selected' : '' ?>>
                                                <?= $r['religion_name_th'] ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>

                            <h5 class="form-section-title"><i class="fas fa-address-book"></i>ข้อมูลการติดต่อ</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">เบอร์โทรศัพท์ <span class="required-star">*</span></label>
                                    <input type="text" name="emp_phone_no" id="emp_phone_no" class="form-control" maxlength="10" required
                                        value="<?= htmlspecialchars($user_data['emp_phone_no']) ?>"
                                        data-orig="<?= htmlspecialchars($user_data['emp_phone_no']) ?>">
                                    <div class="invalid-feedback">รูปแบบเบอร์โทรไม่ถูกต้อง หรือซ้ำกับผู้อื่น</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">LINE ID</label>
                                    <input type="text" name="emp_line_id" class="form-control" value="<?= htmlspecialchars($user_data['emp_line_id']) ?>">
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-12">
                                    <label class="form-label">อีเมล <small class="text-muted">(หากแก้ไขจะต้องยืนยันรหัส OTP ใหม่)</small></label>
                                    <div class="input-group">
                                        <input type="email" name="emp_email" id="emp_email" class="form-control"
                                            value="<?= htmlspecialchars($user_data['emp_email']) ?>"
                                            data-orig="<?= htmlspecialchars($user_data['emp_email']) ?>">
                                        <button type="button" id="btnSendOTP" class="btn btn-outline-success" style="display:none;"><i class="fas fa-paper-plane me-1"></i> ส่ง OTP</button>
                                    </div>
                                </div>
                                <div id="otpBox" class="col-md-6 offset-md-6 mt-2" style="display:none;">
                                    <div class="p-3 bg-white border rounded shadow-sm">
                                        <label class="small fw-bold text-success mb-2">กรอกรหัส OTP 6 หลักที่ได้รับในอีเมล</label>
                                        <div class="input-group">
                                            <input type="text" id="otp_code" class="form-control" maxlength="6" placeholder="******">
                                            <button type="button" id="btnVerifyOTP" class="btn btn-success">ยืนยันรหัส</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h5 class="form-section-title"><i class="fas fa-map-marker-alt"></i>ที่อยู่ปัจจุบัน</h5>
                            <input type="hidden" name="address_id" value="<?= htmlspecialchars($user_data['address_id']) ?>">
                            <div class="row g-3 mb-3">
                                <div class="col-md-3"><label class="form-label">บ้านเลขที่</label><input type="text" name="home_no" class="form-control" value="<?= htmlspecialchars($user_data['home_no']) ?>"></div>
                                <div class="col-md-2"><label class="form-label">หมู่ที่</label><input type="text" name="moo" class="form-control" value="<?= htmlspecialchars($user_data['moo']) ?>"></div>
                                <div class="col-md-3"><label class="form-label">ซอย</label><input type="text" name="soi" class="form-control" value="<?= htmlspecialchars($user_data['soi']) ?>"></div>
                                <div class="col-md-4"><label class="form-label">ถนน</label><input type="text" name="road" class="form-control" value="<?= htmlspecialchars($user_data['road']) ?>"></div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">จังหวัด <span class="required-star">*</span></label>
                                    <select id="province" class="form-select" onchange="loadDistricts(this.value)" required>
                                        <option value="">-- เลือกจังหวัด --</option>
                                        <?php
                                        mysqli_data_seek($provinces, 0);
                                        while ($p = $provinces->fetch_assoc()):
                                        ?>
                                            <option value="<?= $p['province_id'] ?>" <?= $p['province_id'] == $user_data['province_id'] ? 'selected' : '' ?>><?= $p['province_name_th'] ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">อำเภอ/เขต <span class="required-star">*</span></label>
                                    <select id="district" class="form-select" onchange="loadSubdistricts(this.value)" required>
                                        <option value="">-- เลือกอำเภอ --</option>
                                        <?php foreach ($districts as $d): ?>
                                            <option value="<?= $d['district_id'] ?>" <?= $d['district_id'] == $user_data['district_id'] ? 'selected' : '' ?>><?= $d['district_name_th'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ตำบล/แขวง <span class="required-star">*</span></label>
                                    <select id="subdistrict" name="subdistrict_id" class="form-select" onchange="updateZipcode(this)" required>
                                        <option value="">-- เลือกตำบล --</option>
                                        <?php foreach ($subdistricts as $sd): ?>
                                            <option value="<?= $sd['subdistrict_id'] ?>" data-zip="<?= $sd['zip_code'] ?>" <?= $sd['subdistrict_id'] == $user_data['subdistrict_id'] ? 'selected' : '' ?>><?= $sd['subdistrict_name_th'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-4 offset-md-8">
                                    <label class="form-label">รหัสไปรษณีย์</label>
                                    <input type="text" id="zipcode" class="form-control bg-light" readonly value="<?= htmlspecialchars($user_data['zip_code']) ?>">
                                </div>
                            </div>

                            <div class="text-center mt-5 pt-3 border-top">
                                <button type="submit" class="btn btn-success btn-lg px-5 shadow rounded-pill">
                                    <i class="fas fa-save me-2"></i> บันทึกการเปลี่ยนแปลง
                                </button>
                            </div>
                        </form> </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let isEmailVerified = true; 

        $(document).ready(function() {

            // 1. ควบคุมภาษาที่พิมพ์
            document.querySelectorAll('.input-thai').forEach(el => {
                el.addEventListener('input', function() {
                    this.value = this.value.replace(/[^ก-๙\s]/g, '');
                });
            });
            document.querySelectorAll('.input-eng').forEach(el => {
                el.addEventListener('input', function() {
                    this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
                });
            });
            $('#emp_national_id, #emp_phone_no').on('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
            });

            // สูตรเช็คเลขบัตร 13 หลัก
            function validateThaiID(id) {
                if (id.length !== 13) return false;
                let sum = 0;
                for (let i = 0; i < 12; i++) sum += parseInt(id.charAt(i)) * (13 - i);
                let check = (11 - (sum % 11)) % 10;
                return check === parseInt(id.charAt(12));
            }

            // 2. AJAX Check Duplicate บัตร ปชช.
            $('#emp_national_id').on('blur', function() {
                const id = $(this).val();
                const origId = $(this).data('orig');
                if (!id || id === origId) {
                    $(this).removeClass('is-invalid is-valid');
                    return;
                }
                if (!validateThaiID(id)) {
                    $(this).addClass('is-invalid').removeClass('is-valid');
                    Swal.fire('รูปแบบผิดพลาด', 'เลขบัตรประชาชนไม่ถูกต้อง', 'error');
                    return;
                }
                
                $.post('check_duplicate.php', {
                    type: 'national_id',
                    value: id,
                    emp_id: <?= $emp_id ?>
                }, function(res) {
                    if (res.exists) {
                        $('#emp_national_id').addClass('is-invalid').removeClass('is-valid');
                        Swal.fire('ข้อมูลซ้ำ', 'เลขบัตรประชาชนนี้มีในระบบแล้ว', 'warning');
                    } else {
                        $('#emp_national_id').removeClass('is-invalid').addClass('is-valid');
                    }
                });
            });

            // 3. AJAX Check Duplicate เบอร์โทร
            $('#emp_phone_no').on('blur', function() {
                const phone = $(this).val();
                const origPhone = $(this).data('orig');
                if (!phone || phone === origPhone) {
                    $(this).removeClass('is-invalid is-valid');
                    return;
                }
                if (!/^(06|08|09)\d{8}$/.test(phone)) {
                    $(this).addClass('is-invalid').removeClass('is-valid');
                    Swal.fire('รูปแบบผิดพลาด', 'เบอร์โทรศัพท์ต้องเป็น 10 หลัก (06, 08, 09)', 'error');
                    return;
                }
                $.post('check_duplicate.php', {
                    type: 'phone',
                    value: phone,
                    emp_id: <?= $emp_id ?>
                }, function(res) {
                    if (res.exists) {
                        $('#emp_phone_no').addClass('is-invalid').removeClass('is-valid');
                        Swal.fire('ข้อมูลซ้ำ', 'เบอร์โทรศัพท์นี้มีในระบบแล้ว', 'warning');
                    } else {
                        $('#emp_phone_no').removeClass('is-invalid').addClass('is-valid');
                    }
                });
            });

            // 4. ระบบ OTP อีเมล
            $('#emp_email').on('input', function() {
                const email = $(this).val();
                const origEmail = $(this).data('orig');

                if (email !== origEmail && email.length > 0) {
                    $('#btnSendOTP').fadeIn();
                    isEmailVerified = false; 
                } else {
                    $('#btnSendOTP').fadeOut();
                    $('#otpBox').fadeOut();
                    isEmailVerified = true;
                }
                $(this).removeClass('is-valid is-invalid');
            });

            $('#btnSendOTP').click(function() {
                const email = $('#emp_email').val();
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return Swal.fire('ผิดพลาด', 'รูปแบบอีเมลไม่ถูกต้อง', 'error');

                $(this).prop('disabled', true).text('กำลังส่ง...');
                fetch('send_otp.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            emp_email: email
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire('สำเร็จ', 'ส่งรหัส OTP ไปที่อีเมลแล้ว', 'success');
                            $('#otpBox').fadeIn();
                        } else Swal.fire('ผิดพลาด', data.message, 'error');
                    }).catch(err => {
                        Swal.fire('ข้อผิดพลาด', 'ไม่สามารถส่งอีเมลได้ กรุณาตรวจสอบการตั้งค่า SMTP', 'error');
                    }).finally(() => {
                        $('#btnSendOTP').prop('disabled', false).html('<i class="fas fa-paper-plane me-1"></i> ส่ง OTP');
                    });
            });

            $('#btnVerifyOTP').click(function() {
                const otp = $('#otp_code').val();
                if (otp.length !== 6) return Swal.fire('แจ้งเตือน', 'กรุณากรอก OTP 6 หลัก', 'warning');

                fetch('verify_otp.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            otp: otp
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire('สำเร็จ', 'ยืนยันอีเมลสำเร็จ', 'success');
                            isEmailVerified = true;
                            $('#otpBox').fadeOut();
                            $('#btnSendOTP').fadeOut();
                            $('#emp_email').addClass('is-valid').prop('readonly', true);
                        } else Swal.fire('ผิดพลาด', data.message, 'error');
                    });
            });

            // 5. ป้องกันการ Submit หากข้อมูลพังหรือไม่ได้ยืนยัน OTP
            $('#profileForm').on('submit', function(e) {
                if ($('.is-invalid').length > 0) {
                    e.preventDefault();
                    Swal.fire('ข้อมูลไม่ถูกต้อง', 'กรุณาแก้ไขข้อมูลที่มีขอบสีแดงให้ถูกต้อง', 'error');
                    return;
                }
                if (!isEmailVerified) {
                    e.preventDefault();
                    Swal.fire('รอสักครู่', 'คุณมีการแก้ไขอีเมลใหม่ กรุณายืนยันรหัส OTP ให้สำเร็จก่อนทำการบันทึก', 'warning');
                    return;
                }
            });
        });

        // ฟังก์ชันสำหรับแสดงภาพพรีวิวก่อนบันทึก
        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const container = document.querySelector('.profile-img-container');
                    container.innerHTML = `<img src="${e.target.result}" alt="Profile Preview" class="profile-img">`;
                };
                reader.readAsDataURL(file);
            }
        }

        // ---------------------------------------------------------------------
        // Helper Functions 
        // ---------------------------------------------------------------------
        function updatePrefixEn() {
            const select = document.getElementById('prefix_id');
            const prefixEn = select.options[select.selectedIndex].getAttribute('data-en');
            document.getElementById('prefix_en').value = prefixEn || '';
        }

        function fetchData(action, id, targetId, callback = null) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('id', id);

            fetch('change_profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    const select = document.getElementById(targetId);
                    if (targetId === 'district') {
                        select.innerHTML = '<option value="">-- เลือกอำเภอ --</option>';
                        document.getElementById('subdistrict').innerHTML = '<option value="">-- เลือกตำบล --</option>';
                        document.getElementById('zipcode').value = '';
                    } else {
                        select.innerHTML = '<option value="">-- เลือกตำบล --</option>';
                        document.getElementById('zipcode').value = '';
                    }

                    data.forEach(item => {
                        let option = document.createElement('option');
                        if (action === 'get_districts') {
                            option.value = item.district_id;
                            option.text = item.district_name_th;
                        } else {
                            option.value = item.subdistrict_id;
                            option.text = item.subdistrict_name_th;
                            option.dataset.zip = item.zip_code;
                        }
                        select.add(option);
                    });
                    if (callback) callback();
                });
        }

        function loadDistricts(provId) {
            if (provId) fetchData('get_districts', provId, 'district');
        }

        function loadSubdistricts(distId) {
            if (distId) fetchData('get_subdistricts', distId, 'subdistrict');
        }

        function updateZipcode(select) {
            document.getElementById('zipcode').value = select.options[select.selectedIndex].dataset.zip || '';
        }

        // Form Validation Bootstrap
        (function() {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false)
            })
        })()
    </script>
</body>

</html>