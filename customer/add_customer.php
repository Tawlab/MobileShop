<?php
ob_start();
session_start();
require '../config/config.php';

checkPageAccess($conn, 'add_customer');

$current_user_id = $_SESSION['user_id'];
$current_shop_id = $_SESSION['shop_id'];

// --- 1. ตรวจสอบสถานะ Admin ---
$is_admin = false;
$sql_role = "SELECT r.role_name FROM user_roles ur 
             JOIN roles r ON ur.roles_role_id = r.role_id 
             WHERE ur.users_user_id = '$current_user_id' AND r.role_name = 'Admin'";
$res_role = mysqli_query($conn, $sql_role);
if (mysqli_num_rows($res_role) > 0) {
    $is_admin = true;
}

// หาสาขาของ User (กรณีไม่ใช่ Admin)
$current_user_branch_id = 0;
if (!$is_admin) {
    $sql_emp = "SELECT branches_branch_id FROM employees WHERE users_user_id = '$current_user_id' LIMIT 1";
    $res_emp = mysqli_query($conn, $sql_emp);
    if ($row_emp = mysqli_fetch_assoc($res_emp)) {
        $current_user_branch_id = $row_emp['branches_branch_id'];
    }
}

// -----------------------------------------------------------------------------
// AJAX HANDLER
// -----------------------------------------------------------------------------
if (isset($_POST['action'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_POST['action'];
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $data = [];

    if ($action === 'get_provinces') {
        $sql = "SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th";
    } elseif ($action === 'get_districts') {
        $sql = "SELECT district_id, district_name_th FROM districts WHERE provinces_province_id = $id ORDER BY district_name_th";
    } elseif ($action === 'get_subdistricts') {
        $sql = "SELECT subdistrict_id, subdistrict_name_th, zip_code FROM subdistricts WHERE districts_district_id = $id ORDER BY subdistrict_name_th";
    } elseif ($action === 'get_branches') { 
        $sql = "SELECT branch_id, branch_name FROM branches WHERE shop_info_shop_id = $id ORDER BY branch_name";
    }

    if (isset($sql)) {
        $res = mysqli_query($conn, $sql);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $data[] = $row;
            }
        }
    }
    echo json_encode($data);
    exit;
}

$return_url = isset($_GET['return_to']) ? urldecode($_GET['return_to']) : 'customer_list.php';

// -----------------------------------------------------------------------------
// HANDLE FORM SUBMIT
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if ($is_admin) {
        $target_shop_id = isset($_POST['shop_id']) ? (int)$_POST['shop_id'] : 0;
        $target_branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
    } else {
        $target_shop_id = $current_shop_id;
        $target_branch_id = $current_user_branch_id;
    }

    $prefix_id = $_POST['prefix_id'];
    $fname_th  = trim($_POST['firstname_th']);
    $lname_th  = trim($_POST['lastname_th']);
    $phone     = trim($_POST['cs_phone_no']);
    $fname_en  = trim($_POST['firstname_en']);
    $lname_en  = trim($_POST['lastname_en']);
    $email     = trim($_POST['cs_email']);
    $line_id   = trim($_POST['cs_line_id']);
    $national  = trim($_POST['cs_national_id']);

    $home_no   = trim($_POST['home_no']);
    $moo       = trim($_POST['moo']);
    $soi       = trim($_POST['soi']);
    $road      = trim($_POST['road']);
    $village   = trim($_POST['village']); // เพิ่มรับค่าหมู่บ้าน
    $subdist_id = (int)$_POST['subdistrict_id'];

    if (empty($fname_th) || empty($lname_th) || empty($phone)) {
        $_SESSION['error'] = "กรุณากรอกข้อมูลสำคัญ (ชื่อ, นามสกุล, เบอร์โทร)";
    } elseif (empty($subdist_id)) {
        $_SESSION['error'] = "กรุณาเลือกที่อยู่ให้ครบถ้วน";
    } elseif (empty($target_shop_id) || empty($target_branch_id)) {
        $_SESSION['error'] = "ข้อมูลร้านค้าหรือสาขาไม่ถูกต้อง";
    } else {
        mysqli_autocommit($conn, false);
        try {
            $res_cs = mysqli_query($conn, "SELECT IFNULL(MAX(cs_id), 100000) + 1 as next_id FROM customers");
            $cs_id = mysqli_fetch_assoc($res_cs)['next_id'];

            $res_addr = mysqli_query($conn, "SELECT IFNULL(MAX(address_id), 0) + 1 as next_id FROM addresses");
            $addr_id = mysqli_fetch_assoc($res_addr)['next_id'];

            // บันทึกที่อยู่ (เพิ่ม village)
            $sql_addr = "INSERT INTO addresses (address_id, home_no, moo, soi, road, village, subdistricts_subdistrict_id) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_addr);
            $stmt->bind_param("isssssi", $addr_id, $home_no, $moo, $soi, $road, $village, $subdist_id);
            if (!$stmt->execute()) throw new Exception("บันทึกที่อยู่ไม่สำเร็จ");
            $stmt->close();

            $sql_cus = "INSERT INTO customers (
                            cs_id, cs_national_id, firstname_th, lastname_th, 
                            firstname_en, lastname_en, cs_phone_no, cs_email, cs_line_id, 
                            prefixs_prefix_id, Addresses_address_id, shop_info_shop_id, branches_branch_id, 
                            create_at, update_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

            $stmt2 = $conn->prepare($sql_cus);
            $stmt2->bind_param(
                "issssssssiiii",
                $cs_id, $national, $fname_th, $lname_th, $fname_en, $lname_en,
                $phone, $email, $line_id, $prefix_id, $addr_id, 
                $target_shop_id, $target_branch_id
            );

            if (!$stmt2->execute()) throw new Exception("บันทึกข้อมูลลูกค้าไม่สำเร็จ");
            $stmt2->close();

            mysqli_commit($conn);
            $_SESSION['success'] = "เพิ่มลูกค้าเรียบร้อยแล้ว (รหัส: $cs_id)";
            header("Location: $return_url");
            exit;

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// เตรียมข้อมูล Dropdown Prefix (พยายามดึง prefix_en ด้วยถ้ามี)
// เช็คก่อนว่ามีคอลัมน์ prefix_en หรือไม่ เพื่อป้องกัน Error
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM prefixs LIKE 'prefix_en'");
$has_prefix_en = mysqli_num_rows($check_col) > 0;

$sql_prefix = $has_prefix_en ? "SELECT prefix_id, prefix_th, prefix_en FROM prefixs WHERE is_active = 1" : "SELECT prefix_id, prefix_th FROM prefixs WHERE is_active = 1";
$prefixes = mysqli_query($conn, $sql_prefix);

$shops = [];
if ($is_admin) {
    $shop_res = mysqli_query($conn, "SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name");
    while ($r = mysqli_fetch_assoc($shop_res)) $shops[] = $r;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เพิ่มลูกค้าใหม่</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background-color: #f0f2f5; color: #333; }
        .main-card { border-radius: 12px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); background: #fff; overflow: hidden; }
        
        /* 1. แก้ไขส่วนหัวการ์ดเป็นสีเขียวตามธีม */
        .card-header-custom { 
            background: linear-gradient(135deg, #198754 0%, #14532d 100%); 
            color: white; 
            padding: 1.2rem; 
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
            margin-top: 1.5rem;
        }
        .form-section-title i { 
            margin-right: 10px; 
            background: #e8f5e9; 
            color: #198754; 
            width: 32px; 
            height: 32px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border-radius: 50%; 
            font-size: 0.9rem; 
        }
        .form-label { font-weight: 500; font-size: 0.95rem; color: #555; }
        .required-star { color: #dc3545; margin-left: 3px; }
        .admin-select-box { background-color: #e7f1ff; border: 1px solid #b6d4fe; border-radius: 10px; padding: 15px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <div class="main-card mb-4">
                            <div class="card-header-custom d-flex justify-content-between align-items-center">
                                <h4 class="mb-0 text-white"><i class="fas fa-user-plus me-2"></i> เพิ่มลูกค้าใหม่</h4>
                                <a href="<?= htmlspecialchars($return_url) ?>" class="btn btn-outline-light btn-sm rounded-pill px-3">ยกเลิก</a>
                            </div>
                            <div class="card-body p-4">
                                <?php if (isset($_SESSION['error'])): ?>
                                    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                                <?php endif; ?>

                                <form method="POST" class="needs-validation" novalidate>
                                    
                                    <?php if ($is_admin): ?>
                                    <div class="admin-select-box">
                                        <h6 class="text-primary fw-bold mb-3"><i class="fas fa-store-alt me-2"></i>เลือกสาขาปลายทาง (Admin)</h6>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">เลือกร้านค้า <span class="required-star">*</span></label>
                                                <select id="shop_select" name="shop_id" class="form-select" required onchange="loadBranches(this.value)">
                                                    <option value="">-- กรุณาเลือกร้านค้า --</option>
                                                    <?php foreach ($shops as $s): ?>
                                                        <option value="<?= $s['shop_id'] ?>"><?= htmlspecialchars($s['shop_name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">เลือกสาขา <span class="required-star">*</span></label>
                                                <select id="branch_select" name="branch_id" class="form-select" required disabled>
                                                    <option value="">-- กรุณาเลือกร้านค้าก่อน --</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="form-section-title"><i class="fas fa-id-card"></i> ข้อมูลส่วนตัว</div>
                                    
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-12">
                                            <label class="form-label">เลขบัตรประจำตัวประชาชน</label>
                                            <input type="text" name="cs_national_id" class="form-control" maxlength="13" placeholder="ระบุเลข 13 หลัก">
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-2">
                                            <label class="form-label">คำนำหน้า (ไทย) <span class="required-star">*</span></label>
                                            <select id="prefix_th_select" name="prefix_id" class="form-select" required onchange="updateEngPrefix()">
                                                <option value="">เลือก</option>
                                                <?php foreach ($prefixes as $p): ?>
                                                    <option value="<?= $p['prefix_id'] ?>" 
                                                            data-en="<?= $has_prefix_en ? htmlspecialchars($p['prefix_en']) : '' ?>">
                                                        <?= $p['prefix_th'] ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">ชื่อ (ไทย) <span class="required-star">*</span></label>
                                            <input type="text" name="firstname_th" class="form-control input-thai" required placeholder="เฉพาะภาษาไทย">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">นามสกุล (ไทย) <span class="required-star">*</span></label>
                                            <input type="text" name="lastname_th" class="form-control input-thai" required placeholder="เฉพาะภาษาไทย">
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-2">
                                            <label class="form-label">คำนำหน้า (Eng)</label>
                                            <input type="text" id="prefix_en_display" class="form-control bg-light" readonly placeholder="Auto">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">ชื่อ (อังกฤษ)</label>
                                            <input type="text" name="firstname_en" class="form-control input-eng" placeholder="English only">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">นามสกุล (อังกฤษ)</label>
                                            <input type="text" name="lastname_en" class="form-control input-eng" placeholder="English only">
                                        </div>
                                    </div>

                                    <div class="form-section-title"><i class="fas fa-address-book"></i> ข้อมูลติดต่อ</div>
                                    
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">เบอร์โทรศัพท์ <span class="required-star">*</span></label>
                                            <input type="tel" name="cs_phone_no" class="form-control" required maxlength="10">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Line ID</label>
                                            <input type="text" name="cs_line_id" class="form-control">
                                        </div>
                                    </div>
                                    
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-12">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="cs_email" class="form-control">
                                        </div>
                                    </div>

                                    <div class="form-section-title"><i class="fas fa-map-marker-alt"></i> ข้อมูลที่อยู่</div>
                                    
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6"><label class="form-label">บ้านเลขที่</label><input type="text" name="home_no" class="form-control"></div>
                                        <div class="col-md-6"><label class="form-label">หมู่ที่</label><input type="text" name="moo" class="form-control"></div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6"><label class="form-label">ซอย</label><input type="text" name="soi" class="form-control"></div>
                                        <div class="col-md-6"><label class="form-label">ถนน</label><input type="text" name="road" class="form-control"></div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-12"><label class="form-label">หมู่บ้าน/อาคาร</label><input type="text" name="village" class="form-control"></div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">จังหวัด <span class="required-star">*</span></label>
                                            <select id="province" class="form-select" required onchange="loadDistricts(this.value)"><option value="">-- เลือกจังหวัด --</option></select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">อำเภอ/เขต <span class="required-star">*</span></label>
                                            <select id="district" class="form-select" required onchange="loadSubdistricts(this.value)" disabled><option value="">-- เลือกอำเภอ --</option></select>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">ตำบล/แขวง <span class="required-star">*</span></label>
                                            <select id="subdistrict" name="subdistrict_id" class="form-select" required onchange="updateZipcode(this)" disabled><option value="">-- เลือกตำบล --</option></select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">รหัสไปรษณีย์</label>
                                            <input type="text" id="zipcode" class="form-control bg-light" readonly>
                                        </div>
                                    </div>

                                    <div class="text-center mt-5">
                                        <button type="submit" class="btn btn-success btn-lg px-5 shadow-sm rounded-pill"><i class="fas fa-save me-2"></i> บันทึกข้อมูลลูกค้า</button>
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
        (function() {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); }
                    form.classList.add('was-validated')
                }, false)
            })
        })()

        // 2. ตรวจเช็คภาษา (Validation)
        document.querySelectorAll('.input-thai').forEach(el => {
            el.addEventListener('input', function() {
                // อนุญาต: ก-๙, สระ, วรรณยุกต์ และ ช่องว่าง
                this.value = this.value.replace(/[^ก-๙\s]/g, '');
            });
        });

        document.querySelectorAll('.input-eng').forEach(el => {
            el.addEventListener('input', function() {
                // อนุญาต: A-Z, a-z, และ ช่องว่าง
                this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
            });
        });

        // 4. อัปเดตคำนำหน้าภาษาอังกฤษ
        function updateEngPrefix() {
            const thSelect = document.getElementById('prefix_th_select');
            const enInput = document.getElementById('prefix_en_display');
            const selectedOption = thSelect.options[thSelect.selectedIndex];
            // ดึงค่าจาก data-en ที่ฝังไว้
            enInput.value = selectedOption.getAttribute('data-en') || '';
        }

        // AJAX Functions
        window.onload = function() { fetchData('get_provinces', 0, 'province'); }

        function fetchData(action, id, targetId) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('id', id);
            fetch('add_customer.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    const select = document.getElementById(targetId);
                    if (targetId === 'district') {
                        select.innerHTML = '<option value="">-- เลือกอำเภอ --</option>';
                        document.getElementById('subdistrict').innerHTML = '<option value="">-- เลือกตำบล --</option>';
                        document.getElementById('subdistrict').disabled = true;
                        document.getElementById('zipcode').value = '';
                    } else if (targetId === 'subdistrict') {
                        select.innerHTML = '<option value="">-- เลือกตำบล --</option>';
                        document.getElementById('zipcode').value = '';
                    } else if (targetId === 'branch_select') {
                        select.innerHTML = '<option value="">-- เลือกสาขา --</option>';
                    }
                    data.forEach(item => {
                        let option = document.createElement('option');
                        if (action === 'get_provinces') { option.value = item.province_id; option.text = item.province_name_th; }
                        else if (action === 'get_districts') { option.value = item.district_id; option.text = item.district_name_th; }
                        else if (action === 'get_subdistricts') { option.value = item.subdistrict_id; option.text = item.subdistrict_name_th; option.dataset.zip = item.zip_code; }
                        else if (action === 'get_branches') { option.value = item.branch_id; option.text = item.branch_name; }
                        select.add(option);
                    });
                    if (data.length > 0) select.disabled = false;
                });
        }

        function loadBranches(shopId) {
            const branchSelect = document.getElementById('branch_select');
            branchSelect.innerHTML = '<option value="">กำลังโหลด...</option>';
            branchSelect.disabled = true;
            if(shopId) { fetchData('get_branches', shopId, 'branch_select'); }
            else { branchSelect.innerHTML = '<option value="">-- กรุณาเลือกร้านค้าก่อน --</option>'; }
        }

        function loadDistricts(id) { if(id) fetchData('get_districts', id, 'district'); }
        function loadSubdistricts(id) { if(id) fetchData('get_subdistricts', id, 'subdistrict'); }
        function updateZipcode(el) { document.getElementById('zipcode').value = el.options[el.selectedIndex].dataset.zip || ''; }
    </script>
</body>
</html>