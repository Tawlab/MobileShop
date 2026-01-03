<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'edit_employee');

// --- ฟังก์ชัน Hash รหัสผ่าน ---
function hashPassword($password)
{
    return password_hash($password, PASSWORD_DEFAULT);
}

// --- ส่วนดึงข้อมูล (GET) และตั้งค่า ---
$emp_id = (int)($_GET['id'] ?? 0);
if ($emp_id === 0) {
    die("ไม่พบ ID พนักงานที่ต้องการแก้ไข");
}

// --- ตัวแปรสำหรับเก็บข้อมูล ---
$emp_data = null;
$form_data = []; // --- สำหรับเก็บค่าถ้ามี Error ---
$errors_to_display = [];

// --- ดึงข้อมูลสำหรับ Dropdowns  ---
$prefix_result = mysqli_query($conn, "SELECT prefix_id, prefix_th FROM prefixs WHERE is_active = 1 ORDER BY prefix_th");
$religion_result = mysqli_query($conn, "SELECT religion_id, religion_name_th FROM religions WHERE is_active = 1 ORDER BY religion_id");
$department_result = mysqli_query($conn, "SELECT dept_id, dept_name FROM departments ORDER BY dept_name");
$branch_result = mysqli_query($conn, "SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
$role_result = mysqli_query($conn, "SELECT role_id, role_name FROM roles ORDER BY role_name");
$provinces_result = mysqli_query($conn, "SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th");
$districts_result = mysqli_query($conn, "SELECT district_id, district_name_th, provinces_province_id FROM districts ORDER BY district_name_th");
$subdistricts_result = mysqli_query($conn, "SELECT subdistrict_id, subdistrict_name_th, zip_code, districts_district_id FROM subdistricts ORDER BY subdistrict_name_th");


// --- ส่วนอัปเดตข้อมูล POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- ตรวจสอบว่า ID ที่ส่งมาตรงกัน ---
    $post_emp_id = (int)$_POST['emp_id'];
    if ($post_emp_id !== $emp_id) {
        die("ID ไม่ตรงกัน!");
    }
    $emp_code = trim($_POST['emp_code']);
    $emp_national_id = trim($_POST['emp_national_id']);
    $prefixs_prefix_id = (int)$_POST['prefixs_prefix_id'];
    $firstname_th = trim($_POST['firstname_th']);
    $lastname_th = trim($_POST['lastname_th']);
    $firstname_en = !empty($_POST['firstname_en']) ? trim($_POST['firstname_en']) : NULL;
    $lastname_en = !empty($_POST['lastname_en']) ? trim($_POST['lastname_en']) : NULL;
    $emp_phone_no = trim($_POST['emp_phone_no']);
    $emp_email = !empty($_POST['emp_email']) ? trim($_POST['emp_email']) : NULL;
    $emp_line_id = !empty($_POST['emp_line_id']) ? trim($_POST['emp_line_id']) : NULL;
    $emp_birthday = !empty($_POST['emp_birthday']) ? trim($_POST['emp_birthday']) : NULL;
    $emp_gender = $_POST['emp_gender'] ?? '';
    $emp_status = $_POST['emp_status'] ?? '';
    $religions_religion_id = (int)$_POST['religions_religion_id'];
    $departments_dept_id = (int)$_POST['departments_dept_id'];
    $branches_branch_id = (int)$_POST['branches_branch_id'];

    // --- ที่อยู่ ---
    $home_no = !empty($_POST['home_no']) ? trim($_POST['home_no']) : NULL;
    $moo = !empty($_POST['moo']) ? trim($_POST['moo']) : NULL;
    $soi = !empty($_POST['soi']) ? trim($_POST['soi']) : NULL;
    $road = !empty($_POST['road']) ? trim($_POST['road']) : NULL;
    $village = !empty($_POST['village']) ? trim($_POST['village']) : NULL;
    $subdistricts_subdistrict_id = !empty($_POST['subdistricts_subdistrict_id']) ? (int)$_POST['subdistricts_subdistrict_id'] : NULL;

    // --- ผู้ใช้งาน ---
    $username = trim($_POST['username']);
    $password = $_POST['password']; 
    $confirm_password = $_POST['confirm_password'];
    $user_status = $_POST['user_status'] ?? '';
    $role_id = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;

    // --- ID ที่เกี่ยวข้องสำหรับ UPDATE ---
    $user_id = (int)$_POST['user_id'];
    $address_id = (int)$_POST['address_id'];
    $existing_image = trim($_POST['existing_image']);

    // --- ตรวจสอบข้อมูล ---
    $errors = [];
    if (empty($emp_code)) $errors[] = "กรุณากรอกรหัสพนักงาน";
    if (empty($emp_national_id)) $errors[] = "กรุณากรอกเลขบัตรประชาชน";
    if (empty($prefixs_prefix_id)) $errors[] = "กรุณาเลือกคำนำหน้า";
    if (empty($firstname_th)) $errors[] = "กรุณากรอกชื่อจริง (ไทย)";
    if (empty($lastname_th)) $errors[] = "กรุณากรอกนามสกุล (ไทย)";
    if (empty($emp_phone_no)) $errors[] = "กรุณากรอกเบอร์โทรศัพท์";
    if (empty($emp_gender)) $errors[] = "กรุณาเลือกเพศ";
    if (empty($emp_status)) $errors[] = "กรุณาเลือกสถานะพนักงาน";
    if (empty($religions_religion_id)) $errors[] = "กรุณาเลือกศาสนา";
    if (empty($departments_dept_id)) $errors[] = "กรุณาเลือกแผนก";
    if (empty($branches_branch_id)) $errors[] = "กรุณาเลือกสาขา";
    if (empty($subdistricts_subdistrict_id)) $errors[] = "กรุณาเลือกจังหวัด/อำเภอ/ตำบล";
    if (empty($username)) $errors[] = "กรุณากรอก Username";
    if (empty($user_status)) $errors[] = "กรุณาเลือกสถานะผู้ใช้งาน";
    if (empty($role_id)) $errors[] = "กรุณาเลือกบทบาทผู้ใช้งาน";

    // ---  ตรวจสอบรหัสผ่าน (ถ้ากรอก) ---
    if (!empty($password) && ($password !== $confirm_password)) {
        $errors[] = "รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน";
    }

    // --- ตรวจสอบข้อมูลซ้ำ ---
    if (empty($errors)) {
        // --- Check emp_code ---
        $stmt_check = $conn->prepare("SELECT emp_id FROM employees WHERE emp_code = ? AND emp_id != ?");
        $stmt_check->bind_param("si", $emp_code, $emp_id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) $errors[] = "รหัสพนักงาน '$emp_code' นี้มีอยู่แล้ว";
        $stmt_check->close();

        // --- Check emp_national_id ---
        $stmt_check = $conn->prepare("SELECT emp_id FROM employees WHERE emp_national_id = ? AND emp_id != ?");
        $stmt_check->bind_param("si", $emp_national_id, $emp_id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) $errors[] = "เลขบัตรประชาชน '$emp_national_id' นี้มีอยู่แล้ว";
        $stmt_check->close();

        // --- Check username ---
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $stmt_check->bind_param("si", $username, $user_id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) $errors[] = "Username '$username' นี้มีผู้ใช้งานแล้ว";
        $stmt_check->close();
    }

    // --- จัดการอัปโหลดรูป  ---
    $emp_image_filename = $existing_image; 
    $old_image_to_delete = null;

    if (isset($_FILES['emp_image']) && $_FILES['emp_image']['error'] == 0) {
        $upload_dir = '../uploads/employees/';
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = mime_content_type($_FILES['emp_image']['tmp_name']);

        if (in_array($file_type, $allowed_types)) {
            $file_extension = pathinfo($_FILES['emp_image']['name'], PATHINFO_EXTENSION);
            $new_filename = "emp_" . $emp_code . "_" . time() . "." . $file_extension;
            $target_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['emp_image']['tmp_name'], $target_path)) {
                $old_image_to_delete = $existing_image;
                $emp_image_filename = $new_filename;
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการย้ายไฟล์รูปภาพใหม่";
            }
        } else {
            $errors[] = "ไฟล์ที่อัปโหลดต้องเป็นรูปภาพ (JPG, PNG, GIF, WEBP) เท่านั้น";
        }
    }

    // --- ถ้าไม่มี Error ---
    if (empty($errors)) {

        $conn->begin_transaction();
        try {
            // --- อัปเดตที่อยู่ (addresses) ---
            $stmt_addr = $conn->prepare("UPDATE addresses SET home_no = ?, moo = ?, soi = ?, road = ?, village = ?, subdistricts_subdistrict_id = ?
                                         WHERE address_id = ?");
            $stmt_addr->bind_param("sssssii", $home_no, $moo, $soi, $road, $village, $subdistricts_subdistrict_id, $address_id);
            if (!$stmt_addr->execute()) throw new Exception("อัปเดตที่อยู่ล้มเหลว: " . $stmt_addr->error);
            $stmt_addr->close();

            // --- อัปเดตผู้ใช้งาน (users) (ไม่รวมรหัสผ่าน) ---
            $stmt_user = $conn->prepare("UPDATE users SET username = ?, user_status = ? WHERE user_id = ?");
            $stmt_user->bind_param("ssi", $username, $user_status, $user_id);
            if (!$stmt_user->execute()) throw new Exception("อัปเดตข้อมูลผู้ใช้ล้มเหลว: " . $stmt_user->error);
            $stmt_user->close();

            // --- อัปเดตรหัสผ่าน (password) ---
            if (!empty($password)) {
                $hashed_password = hashPassword($password);
                $stmt_pass = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt_pass->bind_param("si", $hashed_password, $user_id);
                if (!$stmt_pass->execute()) throw new Exception("อัปเดตรหัสผ่านล้มเหลว: " . $stmt_pass->error);
                $stmt_pass->close();
            }

            // --- อัปเดตพนักงาน (employees) ---
            $stmt_emp = $conn->prepare("UPDATE employees SET 
                                        emp_code = ?, emp_national_id = ?, firstname_th = ?, lastname_th = ?, firstname_en = ?, lastname_en = ?,
                                        emp_phone_no = ?, emp_email = ?, emp_line_id = ?, emp_birthday = ?, emp_gender = ?, emp_status = ?,
                                        prefixs_prefix_id = ?, religions_religion_id = ?, departments_dept_id = ?, branches_branch_id = ?, emp_image = ?
                                        WHERE emp_id = ?");
            $stmt_emp->bind_param(
                "ssssssssssssiiiisi", 
                $emp_code,
                $emp_national_id,
                $firstname_th,
                $lastname_th,
                $firstname_en,
                $lastname_en,
                $emp_phone_no,
                $emp_email,
                $emp_line_id,
                $emp_birthday,
                $emp_gender,
                $emp_status,
                $prefixs_prefix_id,
                $religions_religion_id,
                $departments_dept_id,
                $branches_branch_id,
                $emp_image_filename,
                $emp_id 
            );
            if (!$stmt_emp->execute()) throw new Exception("อัปเดตข้อมูลพนักงานล้มเหลว: " . $stmt_emp->error);
            $stmt_emp->close();

            // --- อัปเดตบทบาทผู้ใช้ (user_roles) ---
            $stmt_ur = $conn->prepare("UPDATE user_roles SET roles_role_id = ? WHERE users_user_id = ?");
            $stmt_ur->bind_param("ii", $role_id, $user_id);
            if (!$stmt_ur->execute()) {
                $stmt_ur_insert = $conn->prepare("INSERT INTO user_roles (roles_role_id, users_user_id) VALUES (?, ?)");
                $stmt_ur_insert->bind_param("ii", $role_id, $user_id);
                if (!$stmt_ur_insert->execute()) throw new Exception("อัปเดตบทบาทผู้ใช้ล้มเหลว: " . $stmt_ur_insert->error);
                $stmt_ur_insert->close();
            }
            $stmt_ur->close();

            // --- ถ้าทุกอย่างสำเร็จ ---
            $conn->commit();

            // --- ลบรูปเก่า  ---
            if ($old_image_to_delete && file_exists($upload_dir . $old_image_to_delete)) {
                unlink($upload_dir . $old_image_to_delete);
            }

            $_SESSION['message'] = "แก้ไขข้อมูลพนักงาน '$firstname_th $lastname_th' สำเร็จ";
            $_SESSION['message_type'] = "success";
            header("Location: employee.php"); 
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['errors'] = ["เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage()];
            $_SESSION['form_data'] = $_POST; 
            header("Location: edit_employee.php?id=$emp_id"); 
            exit();
        }
    } else {
        // --- ถ้ามี Error จาก Validation ---
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header("Location: edit_employee.php?id=$emp_id");
        exit();
    }
} else {
    // --- ส่วนดึงข้อมูล (GET) เพื่อแสดงในฟอร์ม ---

    if (isset($_SESSION['form_data'])) {
        $form_data = $_SESSION['form_data'];
        $errors_to_display = $_SESSION['errors'] ?? [];
        unset($_SESSION['form_data'], $_SESSION['errors']);

        if (!empty($form_data['subdistricts_subdistrict_id'])) {
            $sql_get_ids = $conn->prepare("SELECT d.provinces_province_id, sd.districts_district_id 
                                        FROM subdistricts sd 
                                        JOIN districts d ON sd.districts_district_id = d.district_id 
                                        WHERE sd.subdistrict_id = ?");
            $sql_get_ids->bind_param("i", $form_data['subdistricts_subdistrict_id']);
            $sql_get_ids->execute();
            $ids_row = $sql_get_ids->get_result()->fetch_assoc();
            if ($ids_row) {
                $form_data['province_id'] = $ids_row['provinces_province_id'];
                $form_data['district_id'] = $ids_row['districts_district_id'];
            }
            $sql_get_ids->close();
        }
    } else {
        // --- ถ้าเปิดหน้าครั้งแรก---
        $sql_get = "
            SELECT
                e.*, 
                a.home_no, a.moo, a.soi, a.road, a.village, a.subdistricts_subdistrict_id,
                sd.districts_district_id,
                d.provinces_province_id,
                u.username, u.user_status,
                ur.roles_role_id
            FROM employees e
            LEFT JOIN addresses a ON e.Addresses_address_id = a.address_id
            LEFT JOIN users u ON e.users_user_id = u.user_id
            LEFT JOIN user_roles ur ON u.user_id = ur.users_user_id
            LEFT JOIN subdistricts sd ON a.subdistricts_subdistrict_id = sd.subdistrict_id
            LEFT JOIN districts d ON sd.districts_district_id = d.district_id
            WHERE e.emp_id = ?
        ";
        $stmt_get = $conn->prepare($sql_get);
        $stmt_get->bind_param("i", $emp_id);
        $stmt_get->execute();
        $emp_data = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();

        if (!$emp_data) {
            die("ไม่พบข้อมูลพนักงาน ID: $emp_id");
        }
        $form_data = $emp_data;
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลพนักงาน - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: #f0fdf4;
            font-size: 0.95rem;
        }

        .form-container {
            max-width: 960px;
            margin: 40px auto;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            background: linear-gradient(135deg, #2dd4bf 0%, #15803d 100%);
            color: white;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
            padding: 1.5rem;
            border-bottom: none;
        }

        .card-header h4 {
            font-weight: 600;
            margin-bottom: 0;
        }

        .card-body {
            padding: 2rem;
        }

        .section-title {
            font-weight: 600;
            color: #15803d;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #a7f3d0;
            font-size: 1.1rem;
        }

        .btn-success {
            background: linear-gradient(135deg, #2dd4bf 0%, #15803d 100%);
            border: none;
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 1px solid #ced4da;
            padding: 0.6rem 1rem;
            font-size: 0.9rem;
            background-color: #f8f9fa;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #15803d;
            box-shadow: 0 0 0 0.2rem rgba(21, 128, 61, 0.15);
            background-color: #fff;
        }

        .form-label {
            font-weight: 500;
            color: #495057;
            display: flex;
            align-items: center;
            margin-bottom: 0.3rem;
        }

        .form-label i {
            margin-right: 8px;
            color: #15803d;
            width: 16px;
            text-align: center;
        }

        .required {
            color: #dc3545;
            margin-left: 4px;
        }

        .alert-danger ul {
            margin-bottom: 0;
            padding-left: 1.5rem;
        }

        .error-feedback {
            font-size: 0.8em;
            color: #dc3545;
            display: none;
            margin-top: 0.2rem;
        }

        .is-invalid {
            border-color: #dc3545 !important;
        }

        .is-invalid+.error-feedback,
        .is-invalid~.error-feedback {
            display: block;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.8rem 1rem;
        }

        .form-grid-full {
            grid-column: 1 / -1;
        }

        .password-toggle {
            cursor: pointer;
        }

        .custom-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 300px;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.3s ease forwards;
            z-index: 1050;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .alert-error {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            color: white;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <?php if (!empty($errors_to_display)): ?>
                    <div class="custom-alert alert-error" role="alert">
                        <i class="fas fa-exclamation-circle fa-lg"></i>
                        <div> <strong>ผิดพลาด!</strong><br>
                            <?php foreach ($errors_to_display as $error): ?>
                                <?= htmlspecialchars($error); ?><br>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close" style="filter: invert(1) grayscale(100%) brightness(200%);"></button>
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <div class="card fade-in">
                        <div class="card-header">
                            <h4 class="mb-0 text-white"><i class="fas fa-user-edit me-2"></i>แก้ไขข้อมูลพนักงาน
                                <span class="fs-6 fw-normal">(ID: <?= $emp_id ?>)</span>
                            </h4>
                        </div>
                        <div class="card-body">

                            <form method="POST" action="edit_employee.php?id=<?= $emp_id ?>" id="editEmployeeForm" enctype="multipart/form-data" novalidate>

                                <input type="hidden" name="emp_id" value="<?= $emp_id ?>">
                                <input type="hidden" name="user_id" value="<?= htmlspecialchars($form_data['users_user_id'] ?? $form_data['user_id'] ?? '') ?>">
                                <input type="hidden" name="address_id" value="<?= htmlspecialchars($form_data['Addresses_address_id'] ?? $form_data['address_id'] ?? '') ?>">
                                <input type="hidden" name="existing_image" value="<?= htmlspecialchars($form_data['emp_image'] ?? '') ?>">

                                <h5 class="section-title"><i class="fas fa-id-card-alt mx-2"></i>ข้อมูลพนักงาน</h5>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label for="emp_code" class="form-label"><i class="fas fa-hashtag"></i>รหัสพนักงาน<span class="required">*</span></label>
                                        <input type="text" class="form-control" id="emp_code" name="emp_code" required maxlength="20" value="<?= htmlspecialchars($form_data['emp_code'] ?? '') ?>">
                                        <div class="error-feedback">กรุณากรอกรหัสพนักงาน</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="emp_national_id" class="form-label"><i class="fas fa-id-card"></i>เลขบัตรประชาชน<span class="required">*</span></label>
                                        <input type="text" class="form-control" id="emp_national_id" name="emp_national_id" required maxlength="13" pattern="\d{13}" value="<?= htmlspecialchars($form_data['emp_national_id'] ?? '') ?>">
                                        <div class="error-feedback">กรุณากรอกเลขบัตร ปชช. 13 หลัก</div>
                                    </div>

                                    <div class="col-md-3 col-lg-2"> <label for="prefixs_prefix_id" class="form-label"><i class="fas fa-user-tag"></i>คำนำหน้า<span class="required">*</span></label>
                                        <select class="form-select" id="prefixs_prefix_id" name="prefixs_prefix_id" required>
                                            <option value="">-- เลือก --</option>
                                            <?php mysqli_data_seek($prefix_result, 0);
                                            while ($p = mysqli_fetch_assoc($prefix_result)): ?>
                                                <option value="<?= $p['prefix_id']; ?>" <?= (isset($form_data['prefixs_prefix_id']) && $form_data['prefixs_prefix_id'] == $p['prefix_id']) ? 'selected' : ''; ?>>
                                                    <?= htmlspecialchars($p['prefix_th']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="error-feedback">กรุณาเลือก</div>
                                    </div>
                                    <div class="col-md-5 col-lg-5"> <label for="firstname_th" class="form-label"><i class="fas fa-user"></i>ชื่อ (ไทย)<span class="required">*</span></label>
                                        <input type="text" class="form-control" id="firstname_th" name="firstname_th" required maxlength="30" value="<?= htmlspecialchars($form_data['firstname_th'] ?? '') ?>">
                                        <div class="error-feedback">กรุณากรอกชื่อ</div>
                                    </div>
                                    <div class="col-md-4 col-lg-5"> <label for="lastname_th" class="form-label"><i class="fas fa-user"></i>นามสกุล (ไทย)<span class="required">*</span></label>
                                        <input type="text" class="form-control" id="lastname_th" name="lastname_th" required maxlength="30" value="<?= htmlspecialchars($form_data['lastname_th'] ?? '') ?>">
                                        <div class="error-feedback">กรุณากรอกนามสกุล</div>
                                    </div>

                                    <div class="col-md-3 col-lg-2"> <label for="prefix_en_display" class="form-label"><i class="fas fa-user-tag"></i>คำนำหน้า (Eng)</label> <input type="text" class="form-control" id="prefix_en_display" name="prefix_en_display_placeholder" disabled placeholder="(ไม่มีข้อมูล)"> </div>
                                    <div class="col-md-5 col-lg-5"> <label for="firstname_en" class="form-label"><i class="fas fa-user"></i>ชื่อ (Eng)</label> <input type="text" class="form-control" id="firstname_en" name="firstname_en" maxlength="30" value="<?= htmlspecialchars($form_data['firstname_en'] ?? '') ?>"> </div>
                                    <div class="col-md-4 col-lg-5"> <label for="lastname_en" class="form-label"><i class="fas fa-user"></i>นามสกุล (Eng)</label> <input type="text" class="form-control" id="lastname_en" name="lastname_en" maxlength="30" value="<?= htmlspecialchars($form_data['lastname_en'] ?? '') ?>"> </div>

                                    <div class="col-12"> <label class="form-label d-block"><i class="fas fa-venus-mars"></i>เพศ<span class="required">*</span></label>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="emp_gender" id="gender_male" value="Male" <?= (isset($form_data['emp_gender']) && $form_data['emp_gender'] == 'Male') ? 'checked' : ''; ?> required>
                                            <label class="form-check-label" for="gender_male">ชาย</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="emp_gender" id="gender_female" value="Female" <?= (isset($form_data['emp_gender']) && $form_data['emp_gender'] == 'Female') ? 'checked' : ''; ?> required>
                                            <label class="form-check-label" for="gender_female">หญิง</label>
                                        </div>
                                        <div class="error-feedback d-block">กรุณาเลือกเพศ</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="emp_birthday" class="form-label"><i class="fas fa-calendar-alt"></i>วันเกิด</label>
                                        <input type="date" class="form-control" id="emp_birthday" name="emp_birthday" value="<?= htmlspecialchars($form_data['emp_birthday'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="religions_religion_id" class="form-label"><i class="fas fa-pray"></i>ศาสนา<span class="required">*</span></label>
                                        <select class="form-select" id="religions_religion_id" name="religions_religion_id" required>
                                            <option value="">-- เลือก --</option>
                                            <?php mysqli_data_seek($religion_result, 0);
                                            while ($r = mysqli_fetch_assoc($religion_result)): ?>
                                                <option value="<?= $r['religion_id']; ?>" <?= (isset($form_data['religions_religion_id']) && $form_data['religions_religion_id'] == $r['religion_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($r['religion_name_th']); ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="error-feedback">กรุณาเลือกศาสนา</div>
                                    </div>
                                </div>

                                <h5 class="section-title"><i class="fas fa-address-book"></i>ข้อมูลติดต่อ</h5>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label for="emp_phone_no" class="form-label"><i class="fas fa-phone"></i>เบอร์โทรศัพท์<span class="required">*</span></label>
                                        <input type="text" class="form-control" id="emp_phone_no" name="emp_phone_no" required maxlength="20" value="<?= htmlspecialchars($form_data['emp_phone_no'] ?? '') ?>">
                                        <div class="error-feedback">กรุณากรอกเบอร์โทร</div>
                                        <div id="phone_error" class="error-feedback">รูปแบบไม่ถูกต้อง</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="emp_line_id" class="form-label"><i class="fab fa-line"></i>Line ID</label>
                                        <input type="text" class="form-control" id="emp_line_id" name="emp_line_id" maxlength="30" value="<?= htmlspecialchars($form_data['emp_line_id'] ?? '') ?>">
                                    </div>
                                    <div class="col-12"> <label for="emp_email" class="form-label"><i class="fas fa-envelope"></i>อีเมล</label>
                                        <input type="email" class="form-control" id="emp_email" name="emp_email" maxlength="75" value="<?= htmlspecialchars($form_data['emp_email'] ?? '') ?>">
                                        <div id="email_error" class="error-feedback">รูปแบบอีเมลไม่ถูกต้อง</div>
                                    </div>
                                </div>

                                <h5 class="section-title"><i class="fas fa-map-marker-alt"></i>ที่อยู่ปัจจุบัน</h5>
                                <div class="form-grid mb-3">
                                    <div> <label for="home_no" class="form-label"><i class="fas fa-home"></i>บ้านเลขที่</label> <input type="text" name="home_no" id="home_no" class="form-control" maxlength="20" value="<?= htmlspecialchars($form_data['home_no'] ?? '') ?>"> </div>
                                    <div> <label for="moo" class="form-label">หมู่</label> <input type="text" name="moo" id="moo" class="form-control" maxlength="20" value="<?= htmlspecialchars($form_data['moo'] ?? '') ?>"> </div>
                                    <div> <label for="village" class="form-label">หมู่บ้าน/อาคาร</label> <input type="text" name="village" id="village" class="form-control" maxlength="50" value="<?= htmlspecialchars($form_data['village'] ?? '') ?>"> </div>
                                    <div> <label for="soi" class="form-label">ซอย</label> <input type="text" name="soi" id="soi" class="form-control" maxlength="50" value="<?= htmlspecialchars($form_data['soi'] ?? '') ?>"> </div>
                                    <div class="form-grid-full"> <label for="road" class="form-label">ถนน</label> <input type="text" name="road" id="road" class="form-control" maxlength="50" value="<?= htmlspecialchars($form_data['road'] ?? '') ?>"> </div>
                                    <div> <label for="provinceSelect" class="form-label">จังหวัด<span class="required">*</span></label> <select id="provinceSelect" class="form-select" required>
                                            <option value="">-- เลือก --</option> <?php mysqli_data_seek($provinces_result, 0);
                                                                                    while ($p = mysqli_fetch_assoc($provinces_result)) {
                                                                                        echo "<option value='{$p['province_id']}'>" . htmlspecialchars($p['province_name_th']) . "</option>";
                                                                                    } ?>
                                        </select>
                                        <div class="error-feedback">กรุณาเลือกจังหวัด</div>
                                    </div>
                                    <div> <label for="districtSelect" class="form-label">อำเภอ<span class="required">*</span></label> <select id="districtSelect" class="form-select" required>
                                            <option value="">-- เลือก --</option>
                                        </select>
                                        <div class="error-feedback">กรุณาเลือกอำเภอ</div>
                                    </div>
                                    <div> <label for="subdistrictSelect" class="form-label">ตำบล<span class="required">*</span></label> <select name="subdistricts_subdistrict_id" id="subdistrictSelect" class="form-select" required>
                                            <option value="">-- เลือก --</option>
                                        </select>
                                        <div class="error-feedback">กรุณาเลือกตำบล</div>
                                    </div>
                                    <div> <label for="zip_code" class="form-label">รหัสไปรษณีย์</label> <input type="text" name="zip_code" id="zip_code" class="form-control" maxlength="5" placeholder="(อัตโนมัติ)" readonly value="<?= htmlspecialchars($form_data['zip_code'] ?? '') ?>"></div>
                                </div>

                                <h5 class="section-title"><i class="fas fa-briefcase"></i>ข้อมูลการทำงาน</h5>
                                <div class="form-grid mb-3">
                                    <div>
                                        <label for="departments_dept_id" class="form-label"><i class="fas fa-sitemap"></i>แผนก<span class="required">*</span></label>
                                        <select class="form-select" id="departments_dept_id" name="departments_dept_id" required>
                                            <option value="">-- เลือก --</option>
                                            <?php mysqli_data_seek($department_result, 0);
                                            while ($d = mysqli_fetch_assoc($department_result)): ?>
                                                <option value="<?= $d['dept_id']; ?>" <?= (isset($form_data['departments_dept_id']) && $form_data['departments_dept_id'] == $d['dept_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($d['dept_name']); ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="error-feedback">กรุณาเลือกแผนก</div>
                                    </div>
                                    <div>
                                        <label for="branches_branch_id" class="form-label"><i class="fas fa-store"></i>สาขา<span class="required">*</span></label>
                                        <select class="form-select" id="branches_branch_id" name="branches_branch_id" required>
                                            <option value="">-- เลือก --</option>
                                            <?php mysqli_data_seek($branch_result, 0);
                                            while ($b = mysqli_fetch_assoc($branch_result)): ?>
                                                <option value="<?= $b['branch_id']; ?>" <?= (isset($form_data['branches_branch_id']) && $form_data['branches_branch_id'] == $b['branch_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($b['branch_name']); ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="error-feedback">กรุณาเลือกสาขา</div>
                                    </div>
                                    <div>
                                        <label class="form-label"><i class="fas fa-toggle-on"></i>สถานะพนักงาน<span class="required">*</span></label>
                                        <div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="emp_status" id="status_active_emp" value="Active" <?= (isset($form_data['emp_status']) && $form_data['emp_status'] == 'Active') ? 'checked' : ''; ?> required>
                                                <label class="form-check-label" for="status_active_emp">ทำงานอยู่</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="emp_status" id="status_resigned_emp" value="Resigned" <?= (isset($form_data['emp_status']) && $form_data['emp_status'] == 'Resigned') ? 'checked' : ''; ?> required>
                                                <label class="form-check-label" for="status_resigned_emp">ลาออก</label>
                                            </div>
                                        </div>
                                        <div class="error-feedback d-block">กรุณาเลือกสถานะ</div>
                                    </div>
                                </div>

                                <h5 class="section-title"><i class="fas fa-user-lock"></i>ข้อมูลบัญชีผู้ใช้งาน</h5>
                                <div class="form-grid mb-3">
                                    <div>
                                        <label for="username" class="form-label"><i class="fas fa-user-circle"></i>Username<span class="required">*</span></label>
                                        <input type="text" class="form-control" id="username" name="username" required maxlength="50" value="<?= htmlspecialchars($form_data['username'] ?? '') ?>">
                                        <div class="error-feedback">กรุณากรอก Username</div>
                                    </div>
                                    <div>
                                        <label for="password" class="form-label"><i class="fas fa-key"></i>Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password" name="password" placeholder="เว้นว่างไว้หากไม่เปลี่ยน">
                                            <button class="btn btn-outline-secondary password-toggle" type="button" id="togglePassword"><i class="fas fa-eye"></i></button>
                                        </div>
                                        <div class="error-feedback">กรุณากรอกรหัสผ่าน</div>
                                    </div>
                                    <div>
                                        <label for="confirm_password" class="form-label"><i class="fas fa-key"></i>Confirm Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="เว้นว่างไว้หากไม่เปลี่ยน">
                                            <button class="btn btn-outline-secondary password-toggle" type="button" id="toggleConfirmPassword"><i class="fas fa-eye"></i></button>
                                        </div>
                                        <div id="password_match_error" class="error-feedback">รหัสผ่านไม่ตรงกัน</div>
                                    </div>
                                    <div>
                                        <label for="role_id" class="form-label"><i class="fas fa-user-tag"></i>บทบาท<span class="required">*</span></label>
                                        <select class="form-select" id="role_id" name="role_id" required>
                                            <option value="">-- เลือก --</option>
                                            <?php mysqli_data_seek($role_result, 0);
                                            while ($r = mysqli_fetch_assoc($role_result)): ?>
                                                <option value="<?= $r['role_id']; ?>" <?= (isset($form_data['roles_role_id']) && $form_data['roles_role_id'] == $r['role_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($r['role_name']); ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="error-feedback">กรุณาเลือกบทบาท</div>
                                    </div>
                                    <div class="form-grid-full">
                                        <label class="form-label"><i class="fas fa-toggle-on"></i>สถานะบัญชี<span class="required">*</span></label>
                                        <div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="user_status" id="status_active_user" value="Active" <?= (isset($form_data['user_status']) && $form_data['user_status'] == 'Active') ? 'checked' : ''; ?> required>
                                                <label class="form-check-label" for="status_active_user">เปิดใช้งาน</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="user_status" id="status_inactive_user" value="Inactive" <?= (isset($form_data['user_status']) && $form_data['user_status'] == 'Inactive') ? 'checked' : ''; ?> required>
                                                <label class="form-check-label" for="status_inactive_user">ปิดใช้งาน</label>
                                            </div>
                                        </div>
                                        <div class="error-feedback d-block">กรุณาเลือกสถานะบัญชี</div>
                                    </div>

                                    <div class="col-md-12">
                                        <label for="emp_image" class="form-label"><i class="fas fa-camera"></i>รูปโปรไฟล์ <span class="text-muted small">(อัปโหลดทับรูปเดิม)</span></label>
                                        <?php if (!empty($form_data['emp_image'])): ?>
                                            <div class="mb-2">
                                                <img src="../uploads/employees/<?= htmlspecialchars($form_data['emp_image']) ?>" alt="รูปปัจจุบัน" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px;">
                                                <span class="ms-2 text-muted small">รูปปัจจุบัน</span>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" id="emp_image" name="emp_image" accept="image/jpeg, image/png, image/gif, image/webp">
                                        <div class="error-feedback">กรุณาเลือกไฟล์รูปภาพ (JPG, PNG, GIF, WEBP)</div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2 mt-4 justify-content-center">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save me-2"></i>บันทึกการแก้ไข
                                    </button>
                                    <a href="employee.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>ยกเลิก
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // =============================================================================
            // DATA & ELEMENTS
            // =============================================================================
            const provinces = <?php mysqli_data_seek($provinces_result, 0);
                                $p_arr = [];
                                while ($p = mysqli_fetch_assoc($provinces_result)) $p_arr[] = $p;
                                echo json_encode($p_arr); ?>;
            const districts = <?php mysqli_data_seek($districts_result, 0);
                                $d_arr = [];
                                while ($d = mysqli_fetch_assoc($districts_result)) $d_arr[] = $d;
                                echo json_encode($d_arr); ?>;
            const subdistricts = <?php mysqli_data_seek($subdistricts_result, 0);
                                    $s_arr = [];
                                    while ($s = mysqli_fetch_assoc($subdistricts_result)) $s_arr[] = $s;
                                    echo json_encode($s_arr); ?>;

            const provinceSelect = document.getElementById('provinceSelect');
            const districtSelect = document.getElementById('districtSelect');
            const subdistrictSelect = document.getElementById('subdistrictSelect');
            const zipcodeInput = document.getElementById('zip_code');
            const form = document.getElementById('editEmployeeForm');
            const passwordInput = document.getElementById("password");
            const confirmPasswordInput = document.getElementById("confirm_password");

            // =============================================================================
            // LANGUAGE RESTRICTION (จำกัดภาษา Real-time)
            // =============================================================================
            function restrictInput(elementId, pattern) {
                const input = document.getElementById(elementId);
                if (input) {
                    input.addEventListener('input', function() {
                        this.value = this.value.replace(pattern, '');
                        // เมื่อมีการพิมพ์และลบค่า ให้ตรวจสอบ Validation ซ้ำเพื่อให้ Error หายไป
                        if (this.classList.contains('is-invalid')) {
                            validateField(this);
                        }
                    });
                }
            }

            // ลบทุกอย่างที่ไม่ใช่ ไทย หรือ ช่องว่าง
            const regexNotThai = /[^ก-๙เแโใไฤฦๅ\s]/g;
            restrictInput('firstname_th', regexNotThai);
            restrictInput('lastname_th', regexNotThai);

            //  ลบทุกอย่างที่ไม่ใช่ อังกฤษ หรือ ช่องว่าง
            const regexNotEng = /[^a-zA-Z\s]/g;
            restrictInput('firstname_en', regexNotEng);
            restrictInput('lastname_en', regexNotEng);

            // =============================================================================
            // VALIDATION LOGIC
            // =============================================================================
            function showError(input, message, errorDivId = null) {
                if (!input) return;
                input.classList.add('is-invalid');
                let errorDiv;
                if (errorDivId) {
                    errorDiv = document.getElementById(errorDivId);
                } else {
                    errorDiv = input.parentNode.querySelector('.error-feedback');
                    if (!errorDiv && input.type === 'radio') {
                        errorDiv = input.closest('.form-check')?.parentNode?.querySelector('.error-feedback');
                    }
                }
                if (errorDiv) {
                    errorDiv.textContent = message;
                    errorDiv.style.display = 'block';
                }
            }

            function hideError(input, errorDivId = null) {
                if (!input) return;
                input.classList.remove('is-invalid');
                let errorDiv;
                if (errorDivId) {
                    errorDiv = document.getElementById(errorDivId);
                } else {
                    errorDiv = input.parentNode.querySelector('.error-feedback');
                    if (!errorDiv && input.type === 'radio') {
                        errorDiv = input.closest('.form-check')?.parentNode?.querySelector('.error-feedback');
                    }
                }
                if (errorDiv && errorDiv.style.display === 'block') {
                    // ซ่อน Error เฉพาะเมื่อไม่ใช่ field required ที่กำลังว่างอยู่
                    if (!(input.required && !input.value.trim() && errorDiv.textContent.startsWith('กรุณา'))) {
                        errorDiv.style.display = 'none';
                    }
                }
            }

            function validateField(input) {
                if (!input) return true;
                let isValid = true;
                const value = input.value.trim();

                //  Required Check 
                if (input.required && !value && input.id !== 'password' && input.id !== 'confirm_password') {
                    showError(input, 'กรุณากรอกข้อมูล');
                    isValid = false;
                }
                // National ID
                else if (input.id === 'emp_national_id' && value && !/^\d{13}$/.test(value)) {
                    showError(input, 'เลข ปชช. ต้องเป็น 13 หลัก');
                    isValid = false;
                }
                // Language Check
                else if ((input.id === 'firstname_th' || input.id === 'lastname_th') && value && !/^[ก-๙เแโใไฤฦๅ\s]+$/.test(value)) {
                    showError(input, 'กรุณากรอกภาษาไทยเท่านั้น');
                    isValid = false;
                } else if ((input.id === 'firstname_en' || input.id === 'lastname_en') && value && !/^[a-zA-Z\s]+$/.test(value)) {
                    showError(input, 'กรุณากรอกภาษาอังกฤษเท่านั้น');
                    isValid = false;
                }
                // Email
                else if (input.id === 'emp_email' && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    showError(input, 'รูปแบบอีเมลไม่ถูกต้อง', 'email_error');
                    isValid = false;
                }
                // Phone
                else if (input.id === 'emp_phone_no' && value && !/^[0-9-]+$/.test(value)) {
                    showError(input, 'รูปแบบเบอร์โทรไม่ถูกต้อง', 'phone_error');
                    isValid = false;
                }
                // Password Match
                else if (input.id === 'confirm_password') {
                    if (passwordInput.value && value !== passwordInput.value) {
                        showError(input, 'รหัสผ่านไม่ตรงกัน', 'password_match_error');
                        isValid = false;
                    } else {
                        hideError(input, 'password_match_error');
                    }
                }

                // Clear Errors if valid
                if (isValid) {
                    hideError(input);
                    if (input.id === 'emp_email') hideError(input, 'email_error');
                    if (input.id === 'emp_phone_no') hideError(input, 'phone_error');

                    // Logic พิเศษสำหรับการแก้ Password
                    if (input.id === 'password') {
                        // ถ้ารหัสผ่านเปลี่ยน ให้ไปเช็คช่องยืนยันด้วย
                        if (confirmPasswordInput) validateField(confirmPasswordInput);
                    }
                }
                return isValid;
            }

            // =============================================================================
            // EVENT LISTENERS
            // =============================================================================

            // Toggle Password Visibility
            document.querySelectorAll('.password-toggle').forEach(button => {
                button.addEventListener('click', function() {
                    const input = this.previousElementSibling;
                    const icon = this.querySelector('i');
                    if (input.type === "password") {
                        input.type = "text";
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        input.type = "password";
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });

            // Address Dropdowns
            provinceSelect?.addEventListener('change', () => onProvinceChange());
            districtSelect?.addEventListener('change', () => onDistrictChange());
            subdistrictSelect?.addEventListener('change', () => onSubdistrictChange());

            // Auto Validate on Input/Blur
            form?.querySelectorAll('input, select').forEach(element => {
                element.addEventListener('blur', function() {
                    validateField(this);
                });
                element.addEventListener('input', function() {
                    if (this.classList.contains('is-invalid')) validateField(this);
                });
                element.addEventListener('change', function() {
                    if (this.classList.contains('is-invalid')) validateField(this);
                });
            });

            // Final Submit Check
            form?.addEventListener('submit', function(e) {
                let formIsValid = true;

                // เช็คทุก field ที่ required
                form.querySelectorAll('input[required], select[required], input[type="email"], input[type="tel"]').forEach(field => {
                    if (!validateField(field)) formIsValid = false;
                });

                // เช็ค Password เฉพาะกรณีที่มีการแก้ไข (ถ้าช่อง Password ไม่ว่าง)
                if (passwordInput.value) {
                    if (!validateField(passwordInput)) formIsValid = false;
                    if (!validateField(confirmPasswordInput)) formIsValid = false;
                }

                // เช็ค Radio
                form.querySelectorAll('input[type="radio"][required]').forEach(radio => {
                    const name = radio.name;
                    if (!form.querySelector(`input[name="${name}"]:checked`)) {
                        showError(radio, 'กรุณาเลือก');
                        formIsValid = false;
                    }
                });

                if (!formIsValid) {
                    e.preventDefault();
                    const firstError = form.querySelector('.is-invalid');
                    if (firstError) firstError.focus();
                } else {
                    // Loading State
                    const submitButton = form.querySelector('button[type="submit"]');
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> กำลังบันทึก...';
                }
            });

            // =============================================================================
            // ADDRESS LOGIC (Pre-fill for Edit Page)
            // =============================================================================

            // Trigger Address Dropdowns on Load
            document.addEventListener('DOMContentLoaded', () => {
                // ดึงค่าเดิมจาก PHP (รองรับทั้งจาก DB หรือ Session)
                const existingProvince = "<?= $form_data['provinces_province_id'] ?? $form_data['province_id'] ?? '' ?>";
                const existingDistrict = "<?= $form_data['districts_district_id'] ?? $form_data['district_id'] ?? '' ?>";
                const existingSubdistrict = "<?= $form_data['subdistricts_subdistrict_id'] ?? '' ?>";

                if (existingProvince && provinceSelect) {
                    provinceSelect.value = existingProvince;
                    onProvinceChange(existingDistrict, existingSubdistrict);
                }

                // ซ่อน Alert อัตโนมัติ (ถ้ามี)
                setTimeout(() => {
                    const alert = document.querySelector('.custom-alert');
                    if (alert) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 7000);
            });

            function onProvinceChange(selectedDistrictId = null, selectedSubdistrictId = null) {
                if (!provinceSelect) return;
                const provinceId = provinceSelect.value;
                districtSelect.innerHTML = '<option value="">-- เลือก --</option>';
                subdistrictSelect.innerHTML = '<option value="">-- เลือก --</option>';
                zipcodeInput.value = '';

                if (provinceId) {
                    districts.filter(d => d.provinces_province_id == provinceId).forEach(d => {
                        const opt = new Option(d.district_name_th, d.district_id);
                        if (selectedDistrictId && d.district_id == selectedDistrictId) opt.selected = true;
                        districtSelect.add(opt);
                    });
                }
                onDistrictChange(selectedSubdistrictId);
            }

            function onDistrictChange(selectedSubdistrictId = null) {
                if (!districtSelect) return;
                const districtId = districtSelect.value;
                subdistrictSelect.innerHTML = '<option value="">-- เลือก --</option>';
                zipcodeInput.value = '';

                if (districtId) {
                    subdistricts.filter(s => s.districts_district_id == districtId).forEach(s => {
                        const opt = new Option(s.subdistrict_name_th, s.subdistrict_id);
                        opt.dataset.zip = s.zip_code;
                        if (selectedSubdistrictId && s.subdistrict_id == selectedSubdistrictId) opt.selected = true;
                        subdistrictSelect.add(opt);
                    });
                }
                // เรียก Subdistrict Change เพื่อแสดง Zip Code
                onSubdistrictChange();
            }

            function onSubdistrictChange() {
                if (!subdistrictSelect) return;
                const selectedOpt = subdistrictSelect.options[subdistrictSelect.selectedIndex];
                zipcodeInput.value = selectedOpt?.dataset?.zip || '';
            }
        </script>
</body>

</html>