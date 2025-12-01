<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'change_profile');

// 1. ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: ../global/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// -----------------------------------------------------------------------------
// 2. AJAX HANDLER (สำหรับเปลี่ยนที่อยู่)
// -----------------------------------------------------------------------------
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
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
// 3. HANDLE FORM SUBMIT (บันทึกข้อมูล)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่าจากฟอร์ม
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

    // รับค่าที่อยู่
    $addr_id   = (int)$_POST['address_id'];
    $home_no   = trim($_POST['home_no']);
    $moo       = trim($_POST['moo']);
    $soi       = trim($_POST['soi']);
    $road      = trim($_POST['road']);
    $subdist   = (int)$_POST['subdistrict_id'];

    // --- Validation (ตรวจสอบข้อมูลฝั่ง Server) ---
    $errors = [];
    if (empty($fname_th) || empty($lname_th)) {
        $errors[] = "กรุณากรอกชื่อ-นามสกุลให้ครบถ้วน";
    }

    // ตรวจสอบเลขบัตรประชาชน (ตัวเลข 13 หลัก)
    if (!empty($national) && !preg_match('/^[0-9]{13}$/', $national)) {
        $errors[] = "เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก";
    }

    // ตรวจสอบเบอร์โทร (ตัวเลข 10 หลัก ขึ้นต้นด้วย 06, 08, 09)
    if (!empty($phone) && !preg_match('/^(06|08|09)[0-9]{8}$/', $phone)) {
        $errors[] = "เบอร์โทรศัพท์ไม่ถูกต้อง (ต้องขึ้นต้นด้วย 06, 08, 09 และมี 10 หลัก)";
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
    } else {
        mysqli_autocommit($conn, false);
        try {
            // A. อัปเดตที่อยู่
            $sql_addr = "UPDATE addresses SET home_no=?, moo=?, soi=?, road=?, subdistricts_subdistrict_id=? WHERE address_id=?";
            $stmt_addr = $conn->prepare($sql_addr);
            $stmt_addr->bind_param("ssssii", $home_no, $moo, $soi, $road, $subdist, $addr_id);
            $stmt_addr->execute();
            $stmt_addr->close();

            // B. อัปเดตข้อมูลพนักงาน
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

            mysqli_commit($conn);
            $_SESSION['success'] = "บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว";
            header("Location: change_profile.php");
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// -----------------------------------------------------------------------------
// 4. FETCH DATA (ดึงข้อมูลปัจจุบัน)
// -----------------------------------------------------------------------------
$sql = "SELECT e.*, u.username, 
               d.dept_name, b.branch_name,
               a.address_id, a.home_no, a.moo, a.soi, a.road, 
               sd.subdistrict_id, dist.district_id, pv.province_id, sd.zip_code,
               p.prefix_th, p.prefix_en  -- ดึงข้อมูล prefix เพิ่มเติม
        FROM employees e
        JOIN users u ON e.users_user_id = u.user_id
        LEFT JOIN prefixs p ON e.prefixs_prefix_id = p.prefix_id
        LEFT JOIN departments d ON e.departments_dept_id = d.dept_id
        LEFT JOIN branches b ON e.branches_branch_id = b.branch_id
        LEFT JOIN addresses a ON e.Addresses_address_id = a.address_id
        LEFT JOIN subdistricts sd ON a.subdistricts_subdistrict_id = sd.subdistrict_id
        LEFT JOIN districts dist ON sd.districts_district_id = dist.district_id
        LEFT JOIN provinces pv ON dist.provinces_province_id = pv.province_id
        WHERE e.users_user_id = $user_id";

$user_data = mysqli_fetch_assoc(mysqli_query($conn, $sql));

// Master Data Dropdowns
$prefixes = mysqli_query($conn, "SELECT * FROM prefixs"); // ใช้ loop เพื่อสร้าง option และ data-attribute
$religions = mysqli_query($conn, "SELECT * FROM religions");
$provinces = mysqli_query($conn, "SELECT * FROM provinces ORDER BY province_name_th");

// Pre-load Districts & Subdistricts based on current user data
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
    <title>แก้ไขข้อมูลส่วนตัว</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: #f8f9fa;
        }

        .container {
            max-width: 1000px;
            margin-top: 40px;
            margin-bottom: 40px;
        }

        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            background: white;
        }

        .card-header-custom {
            background-color: #198754;
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 20px;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #495057;
        }

        .form-section-title {
            color: #198754;
            font-weight: bold;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
            margin-bottom: 20px;
            margin-top: 20px;
        }

        .btn-success-custom {
            background-color: #198754;
            border-color: #198754;
            padding: 10px 30px;
        }

        .btn-success-custom:hover {
            background-color: #157347;
            border-color: #146c43;
        }

        .readonly-input {
            background-color: #e9ecef;
            cursor: not-allowed;
            color: #6c757d;
        }

        /* Style สำหรับรูปโปรไฟล์ */
        .profile-img-container {
            width: 150px;
            height: 150px;
            margin: 0 auto 20px auto;
            border-radius: 50%;
            overflow: hidden;
            border: 5px solid #fff;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }

        .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container">

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h3 class="fw-bold text-success"><i class="fas fa-user-edit me-2"></i>แก้ไขข้อมูลส่วนตัว</h3>
                            <p class="text-muted mb-0 ">จัดการข้อมูลบัญชีผู้ใช้ของคุณ</p>
                        </div>
                        <a href="../home/dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-home me-1"></i> กลับหน้าหลัก</a>
                    </div>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i> <?= $_SESSION['success'];
                                                                        unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle me-2"></i> <?= $_SESSION['error'];
                                                                            unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card card-custom">
                        <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 text-light"><i class="fas fa-id-card me-2"></i>แบบฟอร์มแก้ไขข้อมูล</h5>
                            <a href="change_password.php" class="btn btn-light btn-sm text-success fw-bold"><i class="fas fa-key me-1"></i> เปลี่ยนรหัสผ่าน</a>
                        </div>
                        <div class="card-body p-4">

                            <div class="text-center">
                                <div class="profile-img-container">
                                    <?php
                                    // ตรวจสอบว่ามีรูปภาพหรือไม่ ถ้าไม่มีใช้รูป Default
                                    $img_src = !empty($user_data['emp_image']) ? '../uploads/employees/' . $user_data['emp_image'] : '../assets/img/default_avatar.png';
                                    ?>
                                    <img src="<?= $img_src ?>" alt="Profile Image" class="profile-img" onerror="this.src='../assets/img/default_avatar.png'">
                                </div>
                            </div>

                            <form method="POST">

                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">รหัสพนักงาน</label>
                                        <input type="text" class="form-control readonly-input" value="<?= $user_data['emp_code'] ?>" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">ชื่อผู้ใช้ (Username)</label>
                                        <input type="text" class="form-control readonly-input" value="<?= $user_data['username'] ?>" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">ตำแหน่ง/แผนก</label>
                                        <input type="text" class="form-control readonly-input" value="<?= $user_data['dept_name'] ?? '-' ?>" readonly>
                                    </div>
                                </div>

                                <h5 class="form-section-title"><i class="fas fa-user me-2"></i>ข้อมูลส่วนตัว</h5>
                                <div class="row g-3">
                                    <div class="col-md-2">
                                        <label class="form-label">คำนำหน้า (ไทย)</label>
                                        <select name="prefix_id" id="prefix_id" class="form-select" onchange="updatePrefixEn()">
                                            <?php foreach ($prefixes as $p): ?>
                                                <option value="<?= $p['prefix_id'] ?>"
                                                    data-en="<?= $p['prefix_en'] ?>"
                                                    <?= $p['prefix_id'] == $user_data['prefixs_prefix_id'] ? 'selected' : '' ?>>
                                                    <?= $p['prefix_th'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">คำนำหน้า (EN)</label>
                                        <input type="text" id="prefix_en" class="form-control readonly-input" value="<?= $user_data['prefix_en'] ?>" readonly>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">ชื่อ (ภาษาไทย) <span class="text-danger">*</span></label>
                                        <input type="text" name="firstname_th" class="form-control" value="<?= $user_data['firstname_th'] ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">นามสกุล (ภาษาไทย) <span class="text-danger">*</span></label>
                                        <input type="text" name="lastname_th" class="form-control" value="<?= $user_data['lastname_th'] ?>" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">First Name (EN)</label>
                                        <input type="text" name="firstname_en" class="form-control" value="<?= $user_data['firstname_en'] ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Last Name (EN)</label>
                                        <input type="text" name="lastname_en" class="form-control" value="<?= $user_data['lastname_en'] ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label">วันเกิด</label>
                                        <input type="date" name="emp_birthday" class="form-control" value="<?= $user_data['emp_birthday'] ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">เพศ</label>
                                        <select name="emp_gender" class="form-select">
                                            <option value="Male" <?= $user_data['emp_gender'] == 'Male' ? 'selected' : '' ?>>ชาย</option>
                                            <option value="Female" <?= $user_data['emp_gender'] == 'Female' ? 'selected' : '' ?>>หญิง</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">ศาสนา</label>
                                        <select name="religion_id" class="form-select">
                                            <?php foreach ($religions as $r): ?>
                                                <option value="<?= $r['religion_id'] ?>" <?= $r['religion_id'] == $user_data['religions_religion_id'] ? 'selected' : '' ?>>
                                                    <?= $r['religion_name_th'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">เลขบัตรประชาชน</label>
                                        <input type="text" name="emp_national_id" id="emp_national_id" class="form-control"
                                            value="<?= $user_data['emp_national_id'] ?>" maxlength="13">
                                    </div>
                                </div>

                                <h5 class="form-section-title"><i class="fas fa-address-book me-2"></i>ข้อมูลการติดต่อ</h5>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">เบอร์โทรศัพท์ <span class="text-danger">*</span></label>
                                        <input type="text" name="emp_phone_no" id="emp_phone_no" class="form-control"
                                            value="<?= $user_data['emp_phone_no'] ?>" maxlength="10" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">อีเมล (Email)</label>
                                        <input type="email" name="emp_email" class="form-control" value="<?= $user_data['emp_email'] ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">LINE ID</label>
                                        <input type="text" name="emp_line_id" class="form-control" value="<?= $user_data['emp_line_id'] ?>">
                                    </div>
                                </div>

                                <h5 class="form-section-title"><i class="fas fa-map-marker-alt me-2"></i>ที่อยู่ปัจจุบัน</h5>
                                <input type="hidden" name="address_id" value="<?= $user_data['address_id'] ?>">

                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">บ้านเลขที่</label>
                                        <input type="text" name="home_no" class="form-control" value="<?= $user_data['home_no'] ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">หมู่ที่</label>
                                        <input type="text" name="moo" class="form-control" value="<?= $user_data['moo'] ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">ซอย</label>
                                        <input type="text" name="soi" class="form-control" value="<?= $user_data['soi'] ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">ถนน</label>
                                        <input type="text" name="road" class="form-control" value="<?= $user_data['road'] ?>">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">จังหวัด <span class="text-danger">*</span></label>
                                        <select id="province" class="form-select" onchange="loadDistricts(this.value)" required>
                                            <option value="">-- เลือกจังหวัด --</option>
                                            <?php foreach ($provinces as $p): ?>
                                                <option value="<?= $p['province_id'] ?>" <?= $p['province_id'] == $user_data['province_id'] ? 'selected' : '' ?>>
                                                    <?= $p['province_name_th'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">อำเภอ/เขต <span class="text-danger">*</span></label>
                                        <select id="district" class="form-select" onchange="loadSubdistricts(this.value)" required>
                                            <option value="">-- เลือกอำเภอ --</option>
                                            <?php foreach ($districts as $d): ?>
                                                <option value="<?= $d['district_id'] ?>" <?= $d['district_id'] == $user_data['district_id'] ? 'selected' : '' ?>>
                                                    <?= $d['district_name_th'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">ตำบล/แขวง <span class="text-danger">*</span></label>
                                        <select id="subdistrict" name="subdistrict_id" class="form-select" onchange="updateZipcode(this)" required>
                                            <option value="">-- เลือกตำบล --</option>
                                            <?php foreach ($subdistricts as $sd): ?>
                                                <option value="<?= $sd['subdistrict_id'] ?>" data-zip="<?= $sd['zip_code'] ?>" <?= $sd['subdistrict_id'] == $user_data['subdistrict_id'] ? 'selected' : '' ?>>
                                                    <?= $sd['subdistrict_name_th'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">รหัสไปรษณีย์</label>
                                        <input type="text" id="zipcode" class="form-control readonly-input" value="<?= $user_data['zip_code'] ?>" readonly>
                                    </div>
                                </div>

                                <div class="text-end mt-5">
                                    <button type="submit" class="btn btn-success-custom btn-lg shadow-sm">
                                        <i class="fas fa-save me-2"></i> บันทึกการเปลี่ยนแปลง
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // AJAX สำหรับที่อยู่
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

                    // Reset dropdown but keep first option
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
            const zip = select.options[select.selectedIndex].dataset.zip;
            document.getElementById('zipcode').value = zip || '';
        }

        // ฟังก์ชันอัปเดตคำนำหน้าชื่อภาษาอังกฤษ
        function updatePrefixEn() {
            const select = document.getElementById('prefix_id');
            const prefixEn = select.options[select.selectedIndex].getAttribute('data-en');
            document.getElementById('prefix_en').value = prefixEn || '';
        }

        // Validation Input (JS)
        const nidInput = document.getElementById('emp_national_id');
        const phoneInput = document.getElementById('emp_phone_no');

        nidInput.addEventListener('input', function(e) {
            // ให้กรอกได้เฉพาะตัวเลข
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        phoneInput.addEventListener('input', function(e) {
            // ให้กรอกได้เฉพาะตัวเลข
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // ตรวจสอบตอนเปลี่ยนค่า (blur) เพื่อแจ้งเตือนทันที (Optional)
        phoneInput.addEventListener('blur', function() {
            if (this.value.length > 0 && !this.value.match(/^(06|08|09)[0-9]{8}$/)) {
                alert('เบอร์โทรศัพท์ต้องขึ้นต้นด้วย 06, 08, 09 และมีครบ 10 หลัก');
                // หรือใช้ class is-invalid ของ bootstrap ก็ได้
            }
        });
    </script>

</body>

</html>