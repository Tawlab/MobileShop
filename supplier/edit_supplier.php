<?php
ob_start();
session_start();
require '../config/config.php';

checkPageAccess($conn, 'edit_supplier');

$supplier_id = $_GET['id'] ?? '';

// --- 1. AJAX HANDLER (จัดการรีเควสจาก JS เพื่อโหลดตำแหน่งที่ตั้ง) ---
if (isset($_POST['action'])) {
    ob_end_clean(); 
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_POST['action'];
    $response = ['status' => 'success', 'message' => '', 'data' => []];

    if ($action === 'get_districts') {
        $id = (int)$_POST['id'];
        $sql = "SELECT district_id, district_name_th FROM districts WHERE provinces_province_id = $id ORDER BY district_name_th";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) $response['data'][] = $row;
    } 
    elseif ($action === 'get_subdistricts') {
        $id = (int)$_POST['id'];
        $sql = "SELECT subdistrict_id, subdistrict_name_th, zip_code FROM subdistricts WHERE districts_district_id = $id ORDER BY subdistrict_name_th";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) $response['data'][] = $row;
    }

    echo json_encode($response);
    exit;
}

// ตรวจสอบ ID ถ้าไม่มีให้เด้งกลับ
if (empty($supplier_id)) {
    header("Location: supplier.php?error=not_found");
    exit();
}

// --- 2. QUERY ข้อมูลเดิม ---
$sql_data = "SELECT s.*, 
                a.address_id, a.home_no, a.moo, a.soi, a.road, a.village,
                a.subdistricts_subdistrict_id,
                sd.districts_district_id,
                d.provinces_province_id,
                sd.zip_code
             FROM suppliers s
             LEFT JOIN addresses a ON s.Addresses_address_id = a.address_id
             LEFT JOIN subdistricts sd ON a.subdistricts_subdistrict_id = sd.subdistrict_id
             LEFT JOIN districts d ON sd.districts_district_id = d.district_id
             WHERE s.supplier_id = ?";

$stmt = $conn->prepare($sql_data);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result_data = $stmt->get_result();
$data = $result_data->fetch_assoc();
$stmt->close();

if (!$data) {
    header("Location: supplier.php?error=not_found");
    exit();
}

// --- 3. เตรียมข้อมูล Dropdown (Province, District, Subdistrict) ---
$provinces_result = $conn->query("SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th");

$districts_options = [];
if (!empty($data['provinces_province_id'])) {
    $res_d = $conn->query("SELECT district_id, district_name_th FROM districts WHERE provinces_province_id = " . $data['provinces_province_id']);
    while($row = $res_d->fetch_assoc()) $districts_options[] = $row;
}

$subdistricts_options = [];
if (!empty($data['districts_district_id'])) {
    $res_s = $conn->query("SELECT subdistrict_id, subdistrict_name_th, zip_code FROM subdistricts WHERE districts_district_id = " . $data['districts_district_id']);
    while($row = $res_s->fetch_assoc()) $subdistricts_options[] = $row;
}

$prefix_result = $conn->query("SELECT prefix_id, prefix_th FROM prefixs WHERE is_active = 1 ORDER BY prefix_th");

// --- 4. HANDLE FORM SUBMIT (บันทึกแก้ไข) ---
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    
    $co_name = trim($_POST['co_name']);
    $tax_id = trim($_POST['tax_id']);
    $supplier_phone_no = trim($_POST['supplier_phone_no']);
    $supplier_email = trim($_POST['supplier_email']);
    $subdistricts_id = $_POST['subdistricts_subdistrict_id'] ?? null;

    // Validation เบื้องต้น (PHP Side)
    if (empty($co_name)) {
        $error_message = "กรุณากรอกชื่อบริษัท";
    } elseif (empty($subdistricts_id)) {
        $error_message = "กรุณาเลือกข้อมูลที่อยู่ให้ครบถ้วน";
    } 
    // เช็ค OTP หากอีเมลถูกเปลี่ยน
    elseif ($supplier_email !== $data['supplier_email'] && !empty($supplier_email) && (!isset($_SESSION['email_verified']) || $_SESSION['email_verified'] !== true)) {
        $error_message = "คุณได้แก้ไขอีเมล กรุณายืนยันรหัส OTP ให้สำเร็จก่อนทำการบันทึก";
    }

    if (empty($error_message)) {
        $conn->begin_transaction();
        try {
            // อัปเดต Address
            $stmt_addr = $conn->prepare("UPDATE addresses SET home_no=?, moo=?, soi=?, road=?, village=?, subdistricts_subdistrict_id=? WHERE address_id=?");
            $stmt_addr->bind_param("sssssii", 
                $_POST['home_no'], $_POST['moo'], $_POST['soi'], $_POST['road'], $_POST['village'], 
                $subdistricts_id, $data['address_id']
            );
            $stmt_addr->execute();
            $stmt_addr->close();

            // อัปเดต Supplier
            $stmt_sup = $conn->prepare("UPDATE suppliers SET 
                co_name=?, tax_id=?, contact_firstname=?, contact_lastname=?, 
                supplier_email=?, supplier_phone_no=?, prefixs_prefix_id=?, update_at=NOW() 
                WHERE supplier_id=?");
            $stmt_sup->bind_param("ssssssii", 
                $co_name, $tax_id, $_POST['contact_firstname'], $_POST['contact_lastname'], 
                $supplier_email, $supplier_phone_no, $_POST['prefixs_prefix_id'], $supplier_id
            );
            $stmt_sup->execute();
            $stmt_sup->close();

            $conn->commit();
            unset($_SESSION['email_verified']); // ล้างสถานะ OTP
            $_SESSION['success'] = "แก้ไขข้อมูลผู้จัดจำหน่ายเรียบร้อยแล้ว";
            header("Location: supplier.php"); 
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขผู้จัดจำหน่าย</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    
    <style>
        body { background-color: #f8f9fa; font-family: 'Prompt', sans-serif; }
        .main-card { border-radius: 12px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); background: #fff; }
        .card-header-custom { background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: white; padding: 1.2rem; border-radius: 12px 12px 0 0; }
        
        .form-section-title { font-size: 1.1rem; font-weight: 600; color: #ff9800; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e9ecef; display: flex; align-items: center; margin-top: 1.5rem; }
        .form-section-title i { margin-right: 10px; background: #fff3e0; color: #ff9800; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 0.9rem; }
        .form-label { font-weight: 500; font-size: 0.95rem; color: #555; }
        .required-star { color: #dc3545; margin-left: 3px; }
        
        .collapse-btn { background-color: #f8f9fa; border: 1px dashed #adb5bd; color: #495057; width: 100%; text-align: left; padding: 12px 15px; border-radius: 8px; transition: all 0.3s; font-weight: 500; }
        .collapse-btn:hover { background-color: #e9ecef; border-color: #ff9800; color: #ff9800; }
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
                                <h4 class="mb-0 text-white"><i class="fas fa-edit me-2"></i> แก้ไขข้อมูลผู้จัดจำหน่าย</h4>
                                <a href="supplier.php" class="btn btn-outline-light btn-sm rounded-pill px-3">ย้อนกลับ</a>
                            </div>
                            <div class="card-body p-4">
                                <?php if ($error_message): ?>
                                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?></div>
                                <?php endif; ?>

                                <form method="POST" id="editSupplierForm" class="needs-validation" novalidate>
                                    
                                    <div class="form-section-title"><i class="fas fa-building"></i> ข้อมูลบริษัท</div>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">ชื่อบริษัท <span class="required-star">*</span></label>
                                            <input type="text" name="co_name" class="form-control" required value="<?= htmlspecialchars($data['co_name']) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">เลขประจำตัวผู้เสียภาษีอากร</label>
                                            <input type="text" name="tax_id" id="tax_id" class="form-control" maxlength="13" 
                                                   value="<?= htmlspecialchars($data['tax_id']) ?>" 
                                                   data-orig="<?= htmlspecialchars($data['tax_id']) ?>">
                                            <div class="invalid-feedback">เลขผู้เสียภาษีไม่ถูกต้อง หรือซ้ำในระบบ</div>
                                        </div>
                                    </div>

                                    <div class="form-section-title"><i class="fas fa-user-tie"></i> ข้อมูลผู้ติดต่อ</div>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-2">
                                            <label class="form-label">คำนำหน้า</label>
                                            <select name="prefixs_prefix_id" class="form-select select2">
                                                <option value="">เลือก</option>
                                                <?php 
                                                mysqli_data_seek($prefix_result, 0);
                                                while($p = $prefix_result->fetch_assoc()): 
                                                    $sel = ($p['prefix_id'] == $data['prefixs_prefix_id']) ? 'selected' : '';
                                                ?>
                                                    <option value="<?= $p['prefix_id'] ?>" <?= $sel ?>><?= $p['prefix_th'] ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">ชื่อผู้ติดต่อ</label>
                                            <input type="text" name="contact_firstname" class="form-control input-thai-eng" value="<?= htmlspecialchars($data['contact_firstname']) ?>">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">นามสกุล</label>
                                            <input type="text" name="contact_lastname" class="form-control input-thai-eng" value="<?= htmlspecialchars($data['contact_lastname']) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">เบอร์โทรศัพท์ (10 หลัก)</label>
                                            <input type="tel" name="supplier_phone_no" id="supplier_phone_no" class="form-control" maxlength="10" 
                                                   value="<?= htmlspecialchars($data['supplier_phone_no']) ?>"
                                                   data-orig="<?= htmlspecialchars($data['supplier_phone_no']) ?>">
                                            <div class="invalid-feedback">รูปแบบเบอร์โทรไม่ถูกต้อง หรือซ้ำในระบบ</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">อีเมล <small class="text-muted">(หากแก้ไขจะต้องยืนยัน OTP ใหม่)</small></label>
                                            <div class="input-group">
                                                <input type="email" name="supplier_email" id="supplier_email" class="form-control" 
                                                       value="<?= htmlspecialchars($data['supplier_email']) ?>"
                                                       data-orig="<?= htmlspecialchars($data['supplier_email']) ?>">
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
                                        <i class="fas fa-chevron-down me-2" id="addressIcon"></i> ข้อมูลบ้านเลขที่, หมู่, ซอย, ถนน, หมู่บ้าน (คลิกเพื่อแก้ไข)
                                    </button>
                                    
                                    <div id="extraAddress" style="display:none;" class="p-3 mb-3 bg-white border rounded shadow-sm">
                                        <div class="row g-3">
                                            <div class="col-md-3"><label class="form-label">บ้านเลขที่</label><input type="text" name="home_no" class="form-control" value="<?= htmlspecialchars($data['home_no']) ?>"></div>
                                            <div class="col-md-3"><label class="form-label">หมู่ที่</label><input type="text" name="moo" class="form-control" value="<?= htmlspecialchars($data['moo']) ?>"></div>
                                            <div class="col-md-6"><label class="form-label">หมู่บ้าน/อาคาร</label><input type="text" name="village" class="form-control" value="<?= htmlspecialchars($data['village']) ?>"></div>
                                            <div class="col-md-6"><label class="form-label">ซอย</label><input type="text" name="soi" class="form-control" value="<?= htmlspecialchars($data['soi']) ?>"></div>
                                            <div class="col-md-6"><label class="form-label">ถนน</label><input type="text" name="road" class="form-control" value="<?= htmlspecialchars($data['road']) ?>"></div>
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">จังหวัด <span class="required-star">*</span></label>
                                            <select id="provinceSelect" class="form-select select2" required>
                                                <option value="">-- เลือกจังหวัด --</option>
                                                <?php 
                                                mysqli_data_seek($provinces_result, 0);
                                                while($p = $provinces_result->fetch_assoc()): 
                                                    $sel = ($p['province_id'] == $data['provinces_province_id']) ? 'selected' : '';
                                                ?>
                                                    <option value="<?= $p['province_id'] ?>" <?= $sel ?>><?= $p['province_name_th'] ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">อำเภอ <span class="required-star">*</span></label>
                                            <select id="districtSelect" class="form-select select2" required>
                                                <option value="">-- เลือกอำเภอ --</option>
                                                <?php foreach($districts_options as $d): 
                                                    $sel = ($d['district_id'] == $data['districts_district_id']) ? 'selected' : '';
                                                ?>
                                                    <option value="<?= $d['district_id'] ?>" <?= $sel ?>><?= $d['district_name_th'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">ตำบล <span class="required-star">*</span></label>
                                            <select name="subdistricts_subdistrict_id" id="subdistrictSelect" class="form-select select2" required>
                                                <option value="">-- เลือกตำบล --</option>
                                                <?php foreach($subdistricts_options as $s): 
                                                    $sel = ($s['subdistrict_id'] == $data['subdistricts_subdistrict_id']) ? 'selected' : '';
                                                ?>
                                                    <option value="<?= $s['subdistrict_id'] ?>" data-zip="<?= $s['zip_code'] ?>" <?= $sel ?>>
                                                        <?= $s['subdistrict_name_th'] ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-4 offset-md-8 mt-3 mb-3">
                                            <label class="form-label">รหัสไปรษณีย์</label>
                                            <input type="text" id="zipcodeField" class="form-control bg-light" readonly value="<?= htmlspecialchars($data['zip_code']) ?>">
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
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let isEmailVerified = true; // ค่าเริ่มต้นคือ true เพราะข้อมูลเก่าถูกต้องอยู่แล้ว

        $(document).ready(function() {
            $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });

            // 1. Validation กรองข้อมูลการพิมพ์
            document.querySelectorAll('.input-thai-eng').forEach(el => {
                el.addEventListener('input', function() { this.value = this.value.replace(/[^ก-๙a-zA-Z\s]/g, ''); });
            });
            $('#supplier_phone_no, #tax_id').on('input', function() { 
                this.value = this.value.replace(/[^0-9]/g, ''); 
            });

            function validateThaiID(id) {
                if (id.length !== 13) return false;
                let sum = 0;
                for (let i = 0; i < 12; i++) sum += parseInt(id.charAt(i)) * (13 - i);
                let check = (11 - (sum % 11)) % 10;
                return check === parseInt(id.charAt(12));
            }

            // 2. AJAX Check Duplicate: เลขผู้เสียภาษี
            $('#tax_id').on('blur', function() {
                const id = $(this).val();
                const origId = $(this).data('orig');
                
                if(!id || id === origId) {
                    $(this).removeClass('is-invalid is-valid');
                    return;
                }

                if(!validateThaiID(id)) {
                    $(this).addClass('is-invalid').removeClass('is-valid');
                    Swal.fire('รูปแบบผิดพลาด', 'เลขผู้เสียภาษีไม่ถูกต้อง', 'error');
                    return;
                }
                $.post('check_duplicate.php', { type: 'supplier_tax_id', value: id, supplier_id: <?= $supplier_id ?> }, function(res) {
                    if(res.exists) {
                        $('#tax_id').addClass('is-invalid').removeClass('is-valid');
                        Swal.fire('ข้อมูลซ้ำ', 'เลขประจำตัวผู้เสียภาษีนี้มีซัพพลายเออร์รายอื่นใช้งานแล้ว', 'warning');
                    } else {
                        $('#tax_id').removeClass('is-invalid').addClass('is-valid');
                    }
                });
            });

            // AJAX Check Duplicate: เบอร์โทร
            $('#supplier_phone_no').on('blur', function() {
                const phone = $(this).val();
                const origPhone = $(this).data('orig');
                
                if(!phone || phone === origPhone) {
                    $(this).removeClass('is-invalid is-valid');
                    return;
                }

                if(!/^(06|08|09)\d{8}$/.test(phone)) {
                    $(this).addClass('is-invalid').removeClass('is-valid');
                    Swal.fire('รูปแบบผิดพลาด', 'เบอร์โทรศัพท์ต้องเป็น 10 หลัก (06, 08, 09)', 'error');
                    return;
                }
                $.post('check_duplicate.php', { type: 'supplier_phone', value: phone, supplier_id: <?= $supplier_id ?> }, function(res) {
                    if(res.exists) {
                        $('#supplier_phone_no').addClass('is-invalid').removeClass('is-valid');
                        Swal.fire('ข้อมูลซ้ำ', 'เบอร์โทรศัพท์นี้มีในระบบแล้ว', 'warning');
                    } else {
                        $('#supplier_phone_no').removeClass('is-invalid').addClass('is-valid');
                    }
                });
            });

            // 3. Email OTP
            $('#supplier_email').on('input', function() {
                const email = $(this).val();
                const origEmail = $(this).data('orig');
                
                if(email !== origEmail && email.length > 0) {
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
                    body: JSON.stringify({ emp_email: email })
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

            // 4. Cascading Location (โหลดอำเภอ ตำบล แบบเชื่อมโยง)
            $('#provinceSelect').on('change', function() {
                const id = $(this).val();
                const $dist = $('#districtSelect');
                $('#subdistrictSelect').empty().append('<option value="">-- เลือกอำเภอก่อน --</option>');
                $('#zipcodeField').val('');
                $dist.empty().append('<option value="">-- เลือกอำเภอ --</option>');

                if(id) {
                    $.post('edit_supplier.php', { action: 'get_districts', id: id }, function(res) {
                        res.data.forEach(d => { $dist.append(new Option(d.district_name_th, d.district_id)); });
                    }, 'json');
                }
            });

            $('#districtSelect').on('change', function() {
                const id = $(this).val();
                const $sub = $('#subdistrictSelect');
                $('#zipcodeField').val('');
                $sub.empty().append('<option value="">-- เลือกตำบล --</option>');

                if(id) {
                    $.post('edit_supplier.php', { action: 'get_subdistricts', id: id }, function(res) {
                        res.data.forEach(s => {
                            const opt = new Option(s.subdistrict_name_th, s.subdistrict_id);
                            $(opt).data('zip', s.zip_code);
                            $sub.append(opt);
                        });
                    }, 'json');
                }
            });

            $('#subdistrictSelect').on('change', function() {
                const zip = $(this).find(':selected').data('zip');
                $('#zipcodeField').val(zip || '');
            });

            // 5. Submit Intercept (ป้องกันการเซฟถ้าข้อมูลพัง หรือลืม OTP)
            $('#editSupplierForm').on('submit', function(e) {
                if($('.is-invalid').length > 0) {
                    e.preventDefault();
                    Swal.fire('ข้อมูลไม่ถูกต้อง', 'กรุณาแก้ไขข้อมูลที่มีขอบสีแดงให้ถูกต้องก่อนบันทึก', 'error');
                    return;
                }
                if(!isEmailVerified) {
                    e.preventDefault();
                    Swal.fire('รอสักครู่', 'คุณมีการแก้ไขอีเมลใหม่ กรุณายืนยันรหัส OTP ให้สำเร็จก่อนทำการบันทึก', 'warning');
                    return;
                }
            });
        });

        function toggleAddress() {
            $('#extraAddress').slideToggle();
            $('#addressIcon').toggleClass('fa-chevron-down fa-chevron-up');
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