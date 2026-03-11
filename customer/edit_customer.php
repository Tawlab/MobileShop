<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'edit_customer');

// AJAX HANDLER (สำหรับดึงข้อมูลที่อยู่)
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $data = [];

    if ($action === 'get_provinces') {
        $sql = "SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
    } elseif ($action === 'get_districts') {
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

// ดึงข้อมูลเดิมมาแสดง
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ไม่พบรหัสลูกค้า";
    header('Location: customer_list.php');
    exit;
}

$cs_id = (int)$_GET['id'];

// ดึงข้อมูลลูกค้า + ที่อยู่
$sql = "SELECT c.*, 
               a.address_id, a.home_no, a.moo, a.soi, a.road, a.village,
               sd.subdistrict_id, d.district_id, p.province_id, sd.zip_code
        FROM customers c
        LEFT JOIN addresses a ON c.Addresses_address_id = a.address_id
        LEFT JOIN subdistricts sd ON a.subdistricts_subdistrict_id = sd.subdistrict_id
        LEFT JOIN districts d ON sd.districts_district_id = d.district_id
        LEFT JOIN provinces p ON d.provinces_province_id = p.province_id
        WHERE c.cs_id = $cs_id";

$result = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    $_SESSION['error'] = "ไม่พบข้อมูลลูกค้า";
    header('Location: customer_list.php');
    exit;
}

// เตรียมข้อมูล Dropdown Prefix และรองรับ prefix_en
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM prefixs LIKE 'prefix_en'");
$has_prefix_en = mysqli_num_rows($check_col) > 0;
$sql_prefix = $has_prefix_en ? "SELECT prefix_id, prefix_th, prefix_en FROM prefixs WHERE is_active = 1" : "SELECT prefix_id, prefix_th FROM prefixs WHERE is_active = 1";
$prefixes = mysqli_query($conn, $sql_prefix);

// HANDLE UPDATE (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prefix_id = $_POST['prefix_id'];
    $fname_th  = trim($_POST['firstname_th']);
    $lname_th  = trim($_POST['lastname_th']);
    $phone     = trim($_POST['cs_phone_no']);
    $fname_en  = trim($_POST['firstname_en']);
    $lname_en  = trim($_POST['lastname_en']);
    $email     = trim($_POST['cs_email']);
    $line_id   = trim($_POST['cs_line_id']);
    $national  = trim($_POST['cs_national_id']);

    $addr_id   = $data['address_id']; 
    $home_no   = trim($_POST['home_no']);
    $moo       = trim($_POST['moo']);
    $soi       = trim($_POST['soi']);
    $road      = trim($_POST['road']);
    $village   = trim($_POST['village']);
    $subdist_id = (int)$_POST['subdistrict_id'];

    // Validation เบื้องต้นฝั่ง Server
    if (empty($fname_th) || empty($lname_th) || empty($phone)) {
        $_SESSION['error'] = "กรุณากรอกข้อมูลสำคัญให้ครบ (ชื่อ, นามสกุล, เบอร์โทร)";
    } 
    // ตรวจสอบ OTP ถ้ามีการเปลี่ยนอีเมล
    elseif ($email !== $data['cs_email'] && !empty($email) && (!isset($_SESSION['email_verified']) || $_SESSION['email_verified'] !== true)) {
        $_SESSION['error'] = "คุณได้ทำการแก้ไขอีเมล กรุณายืนยันอีเมลด้วยรหัส OTP ให้สำเร็จก่อนบันทึก";
    } 
    else {
        mysqli_autocommit($conn, false);
        try {
            // อัปเดตที่อยู่
            $sql_addr = "UPDATE addresses SET home_no=?, moo=?, soi=?, road=?, village=?, subdistricts_subdistrict_id=? WHERE address_id=?";
            $stmt = $conn->prepare($sql_addr);
            $stmt->bind_param("sssssii", $home_no, $moo, $soi, $road, $village, $subdist_id, $addr_id);
            $stmt->execute();

            // อัปเดตข้อมูลลูกค้า
            $sql_cus = "UPDATE customers SET 
                        cs_national_id=?, firstname_th=?, lastname_th=?, firstname_en=?, lastname_en=?, 
                        cs_phone_no=?, cs_email=?, cs_line_id=?, prefixs_prefix_id=?, update_at=NOW() 
                        WHERE cs_id=?";
            $stmt2 = $conn->prepare($sql_cus);
            $stmt2->bind_param("ssssssssii", $national, $fname_th, $lname_th, $fname_en, $lname_en, $phone, $email, $line_id, $prefix_id, $cs_id);
            $stmt2->execute();

            mysqli_commit($conn);
            unset($_SESSION['email_verified']); // ล้างสถานะ OTP เมื่อสำเร็จ
            $_SESSION['success'] = "แก้ไขข้อมูลลูกค้าเรียบร้อยแล้ว";
            header("Location: customer_list.php");
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>แก้ไขข้อมูลลูกค้า - <?= htmlspecialchars($data['firstname_th']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background-color: #f0f2f5; font-family: 'Prompt', sans-serif; color: #333; }
        .main-card { border-radius: 12px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); background: #fff; overflow: hidden; }
        
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
        
        .collapse-btn { 
            background-color: #f8f9fa; 
            border: 1px dashed #adb5bd; 
            color: #495057; 
            width: 100%; 
            text-align: left; 
            padding: 12px 15px; 
            border-radius: 8px; 
            transition: all 0.3s; 
            font-weight: 500;
        }
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
                                <h4 class="mb-0 text-white"><i class="fas fa-user-edit me-2"></i> แก้ไขข้อมูลลูกค้า</h4>
                                <a href="customer_list.php" class="btn btn-outline-light btn-sm rounded-pill px-3">ยกเลิก/กลับ</a>
                            </div>
                            <div class="card-body p-4">
                                <?php if (isset($_SESSION['error'])): ?>
                                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                                <?php endif; ?>

                                <form method="POST" id="editCustomerForm" class="needs-validation" novalidate>
                                    
                                    <div class="form-section-title"><i class="fas fa-id-card"></i> ข้อมูลส่วนตัว</div>
                                    
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-12">
                                            <label class="form-label">เลขบัตรประจำตัวประชาชน</label>
                                            <input type="text" name="cs_national_id" id="cs_national_id" class="form-control" maxlength="13" 
                                                   value="<?= htmlspecialchars($data['cs_national_id'] ?? '') ?>" 
                                                   data-orig="<?= htmlspecialchars($data['cs_national_id'] ?? '') ?>">
                                            <div class="invalid-feedback">เลขบัตรประชาชนไม่ถูกต้อง หรือมีผู้อื่นใช้งานแล้ว</div>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-2">
                                            <label class="form-label">คำนำหน้า <span class="required-star">*</span></label>
                                            <select id="prefix_th_select" name="prefix_id" class="form-select" required onchange="updateEngPrefix()">
                                                <?php while ($p = mysqli_fetch_assoc($prefixes)): ?>
                                                    <option value="<?= $p['prefix_id'] ?>" data-en="<?= $has_prefix_en ? htmlspecialchars($p['prefix_en']) : '' ?>" <?= $p['prefix_id'] == $data['prefixs_prefix_id'] ? 'selected' : '' ?>>
                                                        <?= $p['prefix_th'] ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">ชื่อ (ไทย) <span class="required-star">*</span></label>
                                            <input type="text" name="firstname_th" class="form-control input-thai" value="<?= htmlspecialchars($data['firstname_th']) ?>" required>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">นามสกุล (ไทย) <span class="required-star">*</span></label>
                                            <input type="text" name="lastname_th" class="form-control input-thai" value="<?= htmlspecialchars($data['lastname_th']) ?>" required>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-2">
                                            <label class="form-label">คำนำหน้า (Eng)</label>
                                            <input type="text" id="prefix_en_display" class="form-control bg-light" readonly>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">ชื่อ (อังกฤษ)</label>
                                            <input type="text" name="firstname_en" class="form-control input-eng" value="<?= htmlspecialchars($data['firstname_en'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">นามสกุล (อังกฤษ)</label>
                                            <input type="text" name="lastname_en" class="form-control input-eng" value="<?= htmlspecialchars($data['lastname_en'] ?? '') ?>">
                                        </div>
                                    </div>

                                    <div class="form-section-title"><i class="fas fa-address-book"></i> ข้อมูลติดต่อ</div>
                                    
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">เบอร์โทรศัพท์ <span class="required-star">*</span></label>
                                            <input type="tel" name="cs_phone_no" id="cs_phone_no" class="form-control" maxlength="10" 
                                                   value="<?= htmlspecialchars($data['cs_phone_no']) ?>" 
                                                   data-orig="<?= htmlspecialchars($data['cs_phone_no']) ?>" required>
                                            <div class="invalid-feedback">รูปแบบเบอร์โทรไม่ถูกต้อง หรือซ้ำในระบบ</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Line ID</label>
                                            <input type="text" name="cs_line_id" class="form-control" value="<?= htmlspecialchars($data['cs_line_id'] ?? '') ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-12">
                                            <label class="form-label">Email <small class="text-muted">(หากแก้ไขจะต้องยืนยัน OTP ใหม่)</small></label>
                                            <div class="input-group">
                                                <input type="email" name="cs_email" id="cs_email" class="form-control" 
                                                       value="<?= htmlspecialchars($data['cs_email'] ?? '') ?>" 
                                                       data-orig="<?= htmlspecialchars($data['cs_email'] ?? '') ?>">
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

                                    <div class="form-section-title"><i class="fas fa-map-marker-alt"></i> ข้อมูลที่อยู่</div>
                                    
                                    <button type="button" class="collapse-btn mb-3" onclick="toggleAddress()">
                                        <i class="fas fa-chevron-down me-2" id="addressIcon"></i> ข้อมูลบ้านเลขที่, หมู่, ซอย, ถนน, หมู่บ้าน (คลิกเพื่อแก้ไข)
                                    </button>
                                    
                                    <div id="extraAddress" style="display:none;" class="p-3 mb-3 bg-white border rounded shadow-sm">
                                        <div class="row g-3">
                                            <div class="col-md-6"><label class="form-label">บ้านเลขที่</label><input type="text" name="home_no" class="form-control" value="<?= htmlspecialchars($data['home_no'] ?? '') ?>"></div>
                                            <div class="col-md-6"><label class="form-label">หมู่ที่</label><input type="text" name="moo" class="form-control" value="<?= htmlspecialchars($data['moo'] ?? '') ?>"></div>
                                        </div>
                                        <div class="row g-3 mt-1">
                                            <div class="col-md-6"><label class="form-label">ซอย</label><input type="text" name="soi" class="form-control" value="<?= htmlspecialchars($data['soi'] ?? '') ?>"></div>
                                            <div class="col-md-6"><label class="form-label">ถนน</label><input type="text" name="road" class="form-control" value="<?= htmlspecialchars($data['road'] ?? '') ?>"></div>
                                        </div>
                                        <div class="row g-3 mt-1">
                                            <div class="col-md-12"><label class="form-label">หมู่บ้าน/อาคาร</label><input type="text" name="village" class="form-control" value="<?= htmlspecialchars($data['village'] ?? '') ?>"></div>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3 mt-2">
                                        <div class="col-md-6">
                                            <label class="form-label">จังหวัด <span class="required-star">*</span></label>
                                            <select id="province" class="form-select" onchange="loadDistricts(this.value)" required>
                                                <option value="">-- เลือกจังหวัด --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">อำเภอ/เขต <span class="required-star">*</span></label>
                                            <select id="district" class="form-select" onchange="loadSubdistricts(this.value)" required>
                                                <option value="">-- เลือกอำเภอ --</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label">ตำบล/แขวง <span class="required-star">*</span></label>
                                            <select id="subdistrict" name="subdistrict_id" class="form-select" onchange="updateZipcode(this)" required>
                                                <option value="">-- เลือกตำบล --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">รหัสไปรษณีย์</label>
                                            <input type="text" id="zipcode" class="form-control bg-light" value="<?= htmlspecialchars($data['zip_code'] ?? '') ?>" readonly>
                                        </div>
                                    </div>

                                    <div class="text-center mt-5">
                                        <button type="submit" class="btn btn-warning btn-lg px-5 text-white shadow rounded-pill"><i class="fas fa-save me-2"></i> บันทึกการแก้ไข</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="old_province" value="<?= $data['province_id'] ?>">
                <input type="hidden" id="old_district" value="<?= $data['district_id'] ?>">
                <input type="hidden" id="old_subdistrict" value="<?= $data['subdistrict_id'] ?>">
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Validation Logic & Restrictions
        let isEmailVerified = true; // หน้าแก้ไขเริ่มต้นเป็น true เพราะอีเมลเดิมใช้งานได้อยู่แล้ว

        // จำกัดภาษาการพิมพ์
        document.querySelectorAll('.input-thai').forEach(el => {
            el.addEventListener('input', function() { this.value = this.value.replace(/[^ก-๙\s]/g, ''); });
        });
        document.querySelectorAll('.input-eng').forEach(el => {
            el.addEventListener('input', function() { this.value = this.value.replace(/[^a-zA-Z\s]/g, ''); });
        });
        $('#cs_phone_no, #cs_national_id').on('input', function() { 
            this.value = this.value.replace(/[^0-9]/g, ''); 
        });

        // ฟังก์ชันคำนวณ Checksum บัตรประชาชนไทย
        function validateThaiID(id) {
            if (id.length !== 13) return false;
            let sum = 0;
            for (let i = 0; i < 12; i++) sum += parseInt(id.charAt(i)) * (13 - i);
            let check = (11 - (sum % 11)) % 10;
            return check === parseInt(id.charAt(12));
        }

        // AJAX Duplicate Checks
        
        // ตรวจสอบเลขบัตร ปชช.
        $('#cs_national_id').on('blur', function() {
            const id = $(this).val();
            const origId = $(this).data('orig');
            
            // ถ้าไม่ได้กรอกหรือเป็นค่าเดิม ไม่ต้องตรวจสอบซ้ำ
            if(!id || id === origId) {
                $(this).removeClass('is-invalid is-valid');
                return;
            }

            if(!validateThaiID(id)) {
                $(this).addClass('is-invalid').removeClass('is-valid');
                Swal.fire('รูปแบบผิดพลาด', 'เลขบัตรประชาชนไม่ถูกต้องตามสูตรคำนวณ', 'error');
                return;
            }
            
            // ส่ง cs_id ไปด้วยเพื่อยกเว้นการตรวจสอบกับตัวเอง
            $.post('check_duplicate.php', { type: 'customer_national_id', value: id, cs_id: <?= $cs_id ?> }, function(res) {
                if(res.exists) {
                    $('#cs_national_id').addClass('is-invalid').removeClass('is-valid');
                    Swal.fire('ข้อมูลซ้ำ', 'เลขบัตรประชาชนนี้มีผู้อื่นใช้งานแล้ว', 'warning');
                } else {
                    $('#cs_national_id').removeClass('is-invalid').addClass('is-valid');
                }
            });
        });

        // ตรวจสอบเบอร์โทร
        $('#cs_phone_no').on('blur', function() {
            const phone = $(this).val();
            const origPhone = $(this).data('orig');
            
            // ถ้าไม่ได้กรอก หรือเป็นค่าเดิม ไม่ต้องตรวจสอบซ้ำ
            if(!phone || phone === origPhone) {
                $(this).removeClass('is-invalid is-valid');
                return;
            }

            if(!/^(06|08|09)\d{8}$/.test(phone)) {
                $(this).addClass('is-invalid').removeClass('is-valid');
                Swal.fire('รูปแบบผิดพลาด', 'เบอร์โทรศัพท์ต้องเป็น 10 หลัก (06, 08, 09)', 'error');
                return;
            }

            $.post('check_duplicate.php', { type: 'customer_phone', value: phone, cs_id: <?= $cs_id ?> }, function(res) {
                if(res.exists) {
                    $('#cs_phone_no').addClass('is-invalid').removeClass('is-valid');
                    Swal.fire('ข้อมูลซ้ำ', 'เบอร์โทรศัพท์นี้มีผู้อื่นใช้งานแล้ว', 'warning');
                } else {
                    $('#cs_phone_no').removeClass('is-invalid').addClass('is-valid');
                }
            });
        });

        // Email OTP
        $('#cs_email').on('input', function() {
            const email = $(this).val();
            const origEmail = $(this).data('orig');
            
            // หากมีการพิมพ์อีเมลใหม่ที่แตกต่างจากของเดิม และไม่ใช่ช่องว่าง
            if(email !== origEmail && email.length > 0) {
                $('#btnSendOTP').fadeIn();
                isEmailVerified = false; // ต้องยืนยันใหม่
            } else {
                // ถ้ากลับมาเป็นค่าเดิม หรือลบอีเมลออก ไม่ต้องยืนยัน
                $('#btnSendOTP').fadeOut();
                $('#otpBox').fadeOut();
                isEmailVerified = true; 
            }
            $(this).removeClass('is-valid is-invalid');
        });

        $('#btnSendOTP').click(function() {
            const email = $('#cs_email').val();
            if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return Swal.fire('ผิดพลาด', 'รูปแบบอีเมลไม่ถูกต้อง', 'error');
            
            $(this).prop('disabled', true).text('กำลังส่ง...');
            fetch('send_otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ emp_email: email })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    Swal.fire('สำเร็จ', 'ส่งรหัส OTP ไปที่อีเมลแล้ว', 'success');
                    $('#otpBox').fadeIn();
                } else Swal.fire('ผิดพลาด', data.message, 'error');
            }).catch(err => {
                Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อระบบส่งอีเมลได้', 'error');
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
                    $('#cs_email').addClass('is-valid').prop('readonly', true);
                } else Swal.fire('ผิดพลาด', data.message, 'error');
            });
        });

        // UI Toggle & Address Loaders
        
        function toggleAddress() {
            $('#extraAddress').slideToggle();
            $('#addressIcon').toggleClass('fa-chevron-down fa-chevron-up');
        }

        function updateEngPrefix() {
            const thSelect = document.getElementById('prefix_th_select');
            const enInput = document.getElementById('prefix_en_display');
            const selectedOpt = thSelect.options[thSelect.selectedIndex];
            enInput.value = selectedOpt.getAttribute('data-en') || '';
        }

        // โหลดข้อมูล Dropdown ตำแหน่งที่ตั้ง 
        window.onload = function() {
            updateEngPrefix(); // เซ็ตคำนำหน้า Eng ตอนโหลด
            const oldProv = document.getElementById('old_province').value;
            fetchData('get_provinces', 0, 'province', oldProv, () => {
                if (oldProv) {
                    const oldDist = document.getElementById('old_district').value;
                    loadDistricts(oldProv, oldDist, () => {
                        if (oldDist) {
                            const oldSub = document.getElementById('old_subdistrict').value;
                            loadSubdistricts(oldDist, oldSub);
                        }
                    });
                }
            });
        }

        function fetchData(action, id, targetId, selectedValue = null, callback = null) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('id', id);

            fetch('edit_customer.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById(targetId);
                    select.innerHTML = select.options[0].outerHTML; 
                    data.forEach(item => {
                        let option = document.createElement('option');
                        if (action === 'get_provinces') { option.value = item.province_id; option.text = item.province_name_th; } 
                        else if (action === 'get_districts') { option.value = item.district_id; option.text = item.district_name_th; } 
                        else if (action === 'get_subdistricts') { option.value = item.subdistrict_id; option.text = item.subdistrict_name_th; option.dataset.zip = item.zip_code; }

                        if (selectedValue && option.value == selectedValue) option.selected = true;
                        select.add(option);
                    });
                    if (callback) callback();
                });
        }

        function loadDistricts(provId, selectedVal = null, callback = null) {
            document.getElementById('district').innerHTML = '<option value="">-- เลือกอำเภอ --</option>';
            document.getElementById('subdistrict').innerHTML = '<option value="">-- เลือกตำบล --</option>';
            document.getElementById('zipcode').value = '';
            if (provId) fetchData('get_districts', provId, 'district', selectedVal, callback);
        }

        function loadSubdistricts(distId, selectedVal = null) {
            document.getElementById('subdistrict').innerHTML = '<option value="">-- เลือกตำบล --</option>';
            document.getElementById('zipcode').value = '';
            if (distId) fetchData('get_subdistricts', distId, 'subdistrict', selectedVal, () => {
                if (selectedVal) updateZipcode(document.getElementById('subdistrict'));
            });
        }

        function updateZipcode(select) {
            const zip = select.options[select.selectedIndex].dataset.zip;
            document.getElementById('zipcode').value = zip || '';
        }

        // Form Submission Intercept 
        $('#editCustomerForm').on('submit', function(e) {
            if($('.is-invalid').length > 0) {
                e.preventDefault();
                Swal.fire('ข้อมูลไม่ถูกต้อง', 'กรุณาแก้ไขข้อมูลที่มีขอบสีแดงให้ถูกต้อง', 'error');
                return;
            }
            if(!isEmailVerified) {
                e.preventDefault();
                Swal.fire('รอสักครู่', 'คุณมีการแก้ไขอีเมลใหม่ กรุณายืนยันรหัส OTP ก่อนทำการบันทึก', 'warning');
                return;
            }
        });
    </script>
</body>
</html>