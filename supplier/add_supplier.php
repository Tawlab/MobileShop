<?php
ob_start();
session_start();
require '../config/config.php';

checkPageAccess($conn, 'add_supplier');

$current_user_id = $_SESSION['user_id'];
$current_shop_id = $_SESSION['shop_id'];

// --- 1. ตรวจสอบสถานะ Admin ---
$is_admin = false;
$sql_role = "SELECT r.role_name FROM user_roles ur 
             JOIN roles r ON ur.roles_role_id = r.role_id 
             WHERE ur.users_user_id = '$current_user_id' AND r.role_name = 'Admin'";
$res_role = mysqli_query($conn, $sql_role);
if (mysqli_num_rows($res_role) > 0) $is_admin = true;

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
// AJAX HANDLER (สำหรับดึงข้อมูลที่อยู่และสาขา)
// -----------------------------------------------------------------------------
if (isset($_POST['action'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_POST['action'];
    $data = [];

    if ($action === 'get_provinces') {
        $sql = "SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
    } elseif ($action === 'get_districts') {
        $id = (int)$_POST['id'];
        $sql = "SELECT district_id, district_name_th FROM districts WHERE provinces_province_id = $id ORDER BY district_name_th";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
    } elseif ($action === 'get_subdistricts') {
        $id = (int)$_POST['id'];
        $sql = "SELECT subdistrict_id, subdistrict_name_th, zip_code FROM subdistricts WHERE districts_district_id = $id ORDER BY subdistrict_name_th";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
    } elseif ($action === 'get_branches') {
        $id = (int)$_POST['id'];
        $sql = "SELECT branch_id, branch_name FROM branches WHERE shop_info_shop_id = $id ORDER BY branch_name";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
    }

    echo json_encode($data);
    exit;
}

$return_url = $_GET['return_url'] ?? 'supplier.php';

// -----------------------------------------------------------------------------
// HANDLE FORM SUBMIT
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    
    if ($is_admin) {
        $target_shop_id = isset($_POST['shop_id']) ? (int)$_POST['shop_id'] : 0;
        $target_branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
    } else {
        $target_shop_id = $current_shop_id;
        $target_branch_id = $current_user_branch_id;
    }

    $co_name = trim($_POST['co_name']);
    $tax_id = trim($_POST['tax_id']) ?: NULL;
    $prefixs_prefix_id = trim($_POST['prefixs_prefix_id']) ?: NULL;
    $contact_firstname = trim($_POST['contact_firstname']) ?: NULL;
    $contact_lastname = trim($_POST['contact_lastname']) ?: NULL;
    $supplier_phone_no = trim($_POST['supplier_phone_no']) ?: NULL;
    $supplier_email = trim($_POST['supplier_email']) ?: NULL;

    $home_no = trim($_POST['home_no']) ?: NULL;
    $moo = trim($_POST['moo']) ?: NULL;
    $soi = trim($_POST['soi']) ?: NULL;
    $road = trim($_POST['road']) ?: NULL;
    $village = trim($_POST['village']) ?: NULL;
    $subdistricts_id = trim($_POST['subdistricts_subdistrict_id']) ?: NULL;

    // Validation PHP Side
    if (empty($co_name)) {
        $_SESSION['error'] = 'กรุณากรอกชื่อบริษัท';
    } elseif (empty($subdistricts_id)) {
        $_SESSION['error'] = 'กรุณาเลือกจังหวัด อำเภอ และตำบลให้ครบถ้วน';
    } elseif (empty($target_shop_id) || empty($target_branch_id)) {
        $_SESSION['error'] = 'ข้อมูลร้านค้าหรือสาขาไม่ถูกต้อง';
    } elseif (!empty($supplier_email) && (!isset($_SESSION['email_verified']) || $_SESSION['email_verified'] !== true)) {
        $_SESSION['error'] = 'กรุณายืนยันอีเมลด้วยรหัส OTP ให้สำเร็จก่อนบันทึก';
    } else {
        $conn->begin_transaction();
        try {
            $res = $conn->query("SELECT IFNULL(MAX(supplier_id), 100000) + 1 as next_id FROM suppliers");
            $new_supplier_id = $res->fetch_assoc()['next_id'];

            $res_addr = $conn->query("SELECT IFNULL(MAX(address_id), 0) + 1 as next_id FROM addresses");
            $new_address_id = $res_addr->fetch_assoc()['next_id'];

            $stmt_addr = $conn->prepare("INSERT INTO addresses (address_id, home_no, moo, soi, road, village, subdistricts_subdistrict_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_addr->bind_param("isssssi", $new_address_id, $home_no, $moo, $soi, $road, $village, $subdistricts_id);
            if (!$stmt_addr->execute()) throw new Exception("บันทึกที่อยู่ไม่สำเร็จ");
            $stmt_addr->close();

            $sql_insert = "INSERT INTO suppliers (
                supplier_id, co_name, tax_id, contact_firstname, contact_lastname, 
                supplier_email, supplier_phone_no, prefixs_prefix_id, Addresses_address_id, 
                shop_info_shop_id, branches_branch_id, create_at, update_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param(
                "issssssiiii",
                $new_supplier_id, $co_name, $tax_id, $contact_firstname, $contact_lastname,
                $supplier_email, $supplier_phone_no, $prefixs_prefix_id, $new_address_id,
                $target_shop_id, $target_branch_id
            );

            if (!$stmt_insert->execute()) throw new Exception("บันทึกข้อมูลผู้จัดจำหน่ายไม่สำเร็จ: " . $stmt_insert->error);
            $stmt_insert->close();

            $conn->commit();
            unset($_SESSION['email_verified']);
            $_SESSION['success'] = "เพิ่มผู้จัดจำหน่ายเรียบร้อยแล้ว (รหัส: $new_supplier_id)";
            header("Location: $return_url");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
        }
    }
}

$prefixes = $conn->query("SELECT * FROM prefixs WHERE is_active = 1");
$shops = [];
if ($is_admin) {
    $shop_res = $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name");
    while($r = $shop_res->fetch_assoc()) $shops[] = $r;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เพิ่มผู้จัดจำหน่าย</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background-color: #f8fafc; font-family: 'Prompt', sans-serif; color: #333; }
        .main-card { border-radius: 12px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); background: #fff; overflow: hidden; }
        .card-header-custom { background: linear-gradient(135deg, #198754 0%, #14532d 100%); color: white; padding: 1.2rem; }
        .form-section-title { font-size: 1.1rem; font-weight: 600; color: #198754; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e9ecef; display: flex; align-items: center; margin-top: 1.5rem; }
        .form-section-title i { margin-right: 10px; background: #e8f5e9; color: #198754; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 0.9rem; }
        .form-label { font-weight: 500; font-size: 0.95rem; color: #555; }
        .required-star { color: #dc3545; margin-left: 3px; }
        .admin-box { background: #e7f1ff; border: 1px solid #b6d4fe; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
        
        .collapse-btn { background-color: #f8f9fa; border: 1px dashed #adb5bd; color: #495057; width: 100%; text-align: left; padding: 12px 15px; border-radius: 8px; transition: all 0.3s; font-weight: 500; }
        .collapse-btn:hover { background-color: #e9ecef; border-color: #198754; color: #198754; }
        .is-invalid { border-color: #dc3545 !important; }
        .is-valid { border-color: #198754 !important; }
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
                                <h4 class="mb-0 text-white"><i class="fas fa-truck me-2"></i> เพิ่มผู้จัดจำหน่ายใหม่</h4>
                                <a href="<?= htmlspecialchars($return_url) ?>" class="btn btn-outline-light btn-sm rounded-pill px-3">ยกเลิก</a>
                            </div>
                            <div class="card-body p-4">
                                <?php if (isset($_SESSION['error'])): ?>
                                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                                <?php endif; ?>

                                <form method="POST" id="addSupplierForm" class="needs-validation" novalidate>
                                    
                                    <?php if ($is_admin): ?>
                                    <div class="admin-box">
                                        <h6 class="text-primary fw-bold mb-3"><i class="fas fa-store-alt me-2"></i>เลือกสาขาปลายทาง (Admin)</h6>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">ร้านค้า <span class="required-star">*</span></label>
                                                <select id="shopSelect" name="shop_id" class="form-select" required onchange="loadBranches(this.value)">
                                                    <option value="">-- เลือกร้านค้า --</option>
                                                    <?php foreach($shops as $s): ?>
                                                        <option value="<?= $s['shop_id'] ?>"><?= htmlspecialchars($s['shop_name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">สาขา <span class="required-star">*</span></label>
                                                <select id="branchSelect" name="branch_id" class="form-select" required disabled>
                                                    <option value="">-- เลือกร้านค้าก่อน --</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="form-section-title"><i class="fas fa-building"></i> ข้อมูลบริษัท</div>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">ชื่อบริษัท <span class="required-star">*</span></label>
                                            <input type="text" name="co_name" class="form-control" required placeholder="บริษัท ตัวอย่าง จำกัด">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">เลขประจำตัวผู้เสียภาษีอากร</label>
                                            <input type="text" name="tax_id" id="tax_id" class="form-control" maxlength="13" placeholder="ระบุเลข 13 หลัก">
                                            <div class="invalid-feedback">เลขผู้เสียภาษีไม่ถูกต้อง หรือซ้ำในระบบ</div>
                                        </div>
                                    </div>

                                    <div class="form-section-title"><i class="fas fa-user-tie"></i> ข้อมูลผู้ติดต่อ</div>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-2">
                                            <label class="form-label">คำนำหน้า</label>
                                            <select name="prefixs_prefix_id" class="form-select">
                                                <option value="">เลือก</option>
                                                <?php while($p = $prefixes->fetch_assoc()): ?>
                                                    <option value="<?= $p['prefix_id'] ?>"><?= $p['prefix_th'] ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">ชื่อผู้ติดต่อ</label>
                                            <input type="text" name="contact_firstname" class="form-control input-thai-eng">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">นามสกุล</label>
                                            <input type="text" name="contact_lastname" class="form-control input-thai-eng">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">เบอร์โทรศัพท์</label>
                                            <input type="tel" name="supplier_phone_no" id="supplier_phone_no" class="form-control" maxlength="10">
                                            <div class="invalid-feedback">รูปแบบเบอร์โทรไม่ถูกต้อง หรือซ้ำในระบบ</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">อีเมล <small class="text-muted">(หากกรอกจะต้องยืนยัน OTP)</small></label>
                                            <div class="input-group">
                                                <input type="email" name="supplier_email" id="supplier_email" class="form-control" placeholder="example@company.com">
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

                                    <div class="form-section-title"><i class="fas fa-map-marker-alt"></i> ที่อยู่ตั้งบริษัท</div>
                                    
                                    <button type="button" class="collapse-btn mb-3" onclick="toggleAddress()">
                                        <i class="fas fa-chevron-down me-2" id="addressIcon"></i> กรอกข้อมูลบ้านเลขที่, หมู่, ซอย, ถนน, หมู่บ้าน (คลิกเพื่อขยาย)
                                    </button>
                                    
                                    <div id="extraAddress" style="display:none;" class="p-3 mb-3 bg-white border rounded shadow-sm">
                                        <div class="row g-3">
                                            <div class="col-md-6"><label class="form-label">บ้านเลขที่</label><input type="text" name="home_no" class="form-control"></div>
                                            <div class="col-md-6"><label class="form-label">หมู่ที่</label><input type="text" name="moo" class="form-control"></div>
                                        </div>
                                        <div class="row g-3 mt-1">
                                            <div class="col-md-6"><label class="form-label">ซอย</label><input type="text" name="soi" class="form-control"></div>
                                            <div class="col-md-6"><label class="form-label">ถนน</label><input type="text" name="road" class="form-control"></div>
                                        </div>
                                        <div class="row g-3 mt-1">
                                            <div class="col-md-12"><label class="form-label">หมู่บ้าน/อาคาร</label><input type="text" name="village" class="form-control"></div>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3 mt-2">
                                        <div class="col-md-4">
                                            <label class="form-label">จังหวัด <span class="required-star">*</span></label>
                                            <select id="province" class="form-select" required onchange="fetchData('get_districts', this.value, 'district')"></select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">อำเภอ/เขต <span class="required-star">*</span></label>
                                            <select id="district" class="form-select" required onchange="fetchData('get_subdistricts', this.value, 'subdistrict')" disabled><option value="">-- เลือกอำเภอ --</option></select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">ตำบล/แขวง <span class="required-star">*</span></label>
                                            <select id="subdistrict" name="subdistricts_subdistrict_id" class="form-select" required onchange="updateZipcode(this)" disabled><option value="">-- เลือกตำบล --</option></select>
                                        </div>
                                    </div>
                                    
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-4 offset-md-8">
                                            <label class="form-label">รหัสไปรษณีย์</label>
                                            <input type="text" id="zipcode" class="form-control bg-light" readonly>
                                        </div>
                                    </div>

                                    <div class="text-center mt-5">
                                        <button type="submit" class="btn btn-success btn-lg px-5 shadow rounded-pill"><i class="fas fa-save me-2"></i> บันทึกข้อมูลผู้จัดจำหน่าย</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        let isEmailVerified = true; 

        // 1. Validation Logic
        document.querySelectorAll('.input-thai-eng').forEach(el => {
            el.addEventListener('input', function() { this.value = this.value.replace(/[^ก-๙a-zA-Z\s]/g, ''); });
        });
        $('#supplier_phone_no, #tax_id').on('input', function() { 
            this.value = this.value.replace(/[^0-9]/g, ''); 
        });

        // ฟังก์ชันคำนวณ Checksum 13 หลัก (ใช้ร่วมกันได้ทั้งเลขบัตร ปชช. และ เลขผู้เสียภาษี)
        function validateThaiID(id) {
            if (id.length !== 13) return false;
            let sum = 0;
            for (let i = 0; i < 12; i++) sum += parseInt(id.charAt(i)) * (13 - i);
            let check = (11 - (sum % 11)) % 10;
            return check === parseInt(id.charAt(12));
        }

        // 2. AJAX Duplicate Checks
        
        // ตรวจสอบเลขผู้เสียภาษี
        $('#tax_id').on('blur', function() {
            const id = $(this).val();
            if(!id) {
                $(this).removeClass('is-invalid is-valid');
                return;
            }
            if(!validateThaiID(id)) {
                $(this).addClass('is-invalid').removeClass('is-valid');
                Swal.fire('รูปแบบผิดพลาด', 'เลขผู้เสียภาษีไม่ถูกต้องตาม', 'error');
                return;
            }
            $.post('check_duplicate.php', { type: 'supplier_tax_id', value: id }, function(res) {
                if(res.exists) {
                    $('#tax_id').addClass('is-invalid').removeClass('is-valid');
                    Swal.fire('ข้อมูลซ้ำ', 'เลขประจำตัวผู้เสียภาษีนี้มีในระบบแล้ว', 'warning');
                } else {
                    $('#tax_id').removeClass('is-invalid').addClass('is-valid');
                }
            });
        });

        // ตรวจสอบเบอร์โทร
        $('#supplier_phone_no').on('blur', function() {
            const phone = $(this).val();
            if(!phone) {
                $(this).removeClass('is-invalid is-valid');
                return;
            }
            if(!/^(06|08|09)\d{8}$/.test(phone)) {
                $(this).addClass('is-invalid').removeClass('is-valid');
                Swal.fire('รูปแบบผิดพลาด', 'เบอร์โทรศัพท์ต้องเป็น 10 หลัก (06, 08, 09)', 'error');
                return;
            }
            $.post('check_duplicate.php', { type: 'supplier_phone', value: phone }, function(res) {
                if(res.exists) {
                    $('#supplier_phone_no').addClass('is-invalid').removeClass('is-valid');
                    Swal.fire('ข้อมูลซ้ำ', 'เบอร์โทรศัพท์นี้มีในระบบแล้ว', 'warning');
                } else {
                    $('#supplier_phone_no').removeClass('is-invalid').addClass('is-valid');
                }
            });
        });

        // 3. Email OTP Logic
        $('#supplier_email').on('input', function() {
            const email = $(this).val();
            if(email.length > 0) {
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
            const email = $('#supplier_email').val();
            if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return Swal.fire('ผิดพลาด', 'รูปแบบอีเมลไม่ถูกต้อง', 'error');
            
            $(this).prop('disabled', true).text('กำลังส่ง...');
            fetch('send_otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ emp_email: email }) // ใช้ parameter เดิมที่ตั้งไว้
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    Swal.fire('สำเร็จ', 'ส่งรหัส OTP ไปที่อีเมลแล้ว', 'success');
                    $('#otpBox').fadeIn();
                } else Swal.fire('ผิดพลาด', data.message, 'error');
            }).catch(err => {
                Swal.fire('ข้อผิดพลาด', 'ไม่สามารถส่งอีเมลได้', 'error');
            }).finally(() => {
                $('#btnSendOTP').prop('disabled', false).html('<i class="fas fa-paper-plane me-1"></i> ส่ง OTP');
            });
        });

        $('#btnVerifyOTP').click(function() {
            const otp = $('#otp_code').val();
            if(otp.length !== 6) return Swal.fire('แจ้งเตือน', 'กรุณากรอก OTP 6 หลัก', 'warning');

            fetch('verify_otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ otp: otp })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    Swal.fire('สำเร็จ', 'ยืนยันอีเมลสำเร็จ', 'success');
                    isEmailVerified = true;
                    $('#otpBox').fadeOut();
                    $('#btnSendOTP').fadeOut();
                    $('#supplier_email').addClass('is-valid').prop('readonly', true);
                } else Swal.fire('ผิดพลาด', data.message, 'error');
            });
        });

        // 4. UI Toggle & Address Loaders
        function toggleAddress() {
            $('#extraAddress').slideToggle();
            $('#addressIcon').toggleClass('fa-chevron-down fa-chevron-up');
        }

        window.onload = function() { fetchData('get_provinces', 0, 'province'); }

        function fetchData(action, id, targetId) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('id', id);
            fetch('add_supplier.php', { method: 'POST', body: formData })
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
                    } else if (targetId === 'branchSelect') {
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
            const branchSelect = document.getElementById('branchSelect');
            branchSelect.innerHTML = '<option value="">กำลังโหลด...</option>';
            branchSelect.disabled = true;
            if(shopId) fetchData('get_branches', shopId, 'branchSelect');
            else branchSelect.innerHTML = '<option value="">-- เลือกร้านค้าก่อน --</option>';
        }

        function updateZipcode(el) { $('#zipcode').val($(el).find(':selected').data('zip')); }

        // 5. Form Submission Intercept
        $('#addSupplierForm').on('submit', function(e) {
            if($('.is-invalid').length > 0) {
                e.preventDefault();
                Swal.fire('ข้อมูลไม่ถูกต้อง', 'กรุณาแก้ไขข้อมูลที่มีขอบสีแดงให้ถูกต้อง', 'error');
                return;
            }
            if(!isEmailVerified) {
                e.preventDefault();
                Swal.fire('รอสักครู่', 'คุณกรอกอีเมลไว้ กรุณายืนยันรหัส OTP ให้สำเร็จก่อนทำการบันทึก', 'warning');
                return;
            }
        });
    </script>
</body>
</html>