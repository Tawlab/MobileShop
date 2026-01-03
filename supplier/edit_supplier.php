<?php
ob_start();
session_start();
require '../config/config.php';

checkPageAccess($conn, 'edit_supplier');

$supplier_id = $_GET['id'] ?? '';

// --- 1. AJAX HANDLER (จัดการรีเควสจาก JS) ---
if (isset($_POST['action'])) {
    ob_end_clean(); // เคลียร์ Output Buffer ป้องกัน Error ปนไปกับ JSON
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_POST['action'];
    $response = ['status' => 'success', 'message' => '', 'data' => []];

    // ตรวจสอบเบอร์โทร (10 หลัก)
    if ($action === 'validate_phone') {
        $val = trim($_POST['value']);
        if (!preg_match('/^[0-9]{10}$/', $val)) {
            $response['status'] = 'error';
            $response['message'] = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 หลักเท่านั้น';
        }
    }
    // ตรวจสอบอีเมล
    elseif ($action === 'validate_email') {
        $val = trim($_POST['value']);
        if ($val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $response['status'] = 'error';
            $response['message'] = 'รูปแบบอีเมลไม่ถูกต้อง';
        }
    }
    // โหลดข้อมูลอำเภอ
    elseif ($action === 'get_districts') {
        $id = (int)$_POST['id'];
        $sql = "SELECT district_id, district_name_th FROM districts WHERE provinces_province_id = $id ORDER BY district_name_th";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) $response['data'][] = $row;
    } 
    // โหลดข้อมูลตำบล
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
$stmt->bind_param("i", $supplier_id); // ใช้ i ถ้า supplier_id เป็น int, ใช้ s ถ้าเป็น string
$stmt->execute();
$result_data = $stmt->get_result();
$data = $result_data->fetch_assoc();
$stmt->close();

if (!$data) {
    header("Location: supplier.php?error=not_found");
    exit();
}

// --- 3. เตรียมข้อมูล Dropdown (Province, District, Subdistrict) ---
// ดึงจังหวัดทั้งหมด
$provinces_result = $conn->query("SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th");

// ดึงอำเภอ (ตามจังหวัดเดิมของ Supplier)
$districts_options = [];
if (!empty($data['provinces_province_id'])) {
    $res_d = $conn->query("SELECT district_id, district_name_th FROM districts WHERE provinces_province_id = " . $data['provinces_province_id']);
    while($row = $res_d->fetch_assoc()) $districts_options[] = $row;
}

// ดึงตำบล (ตามอำเภอเดิมของ Supplier)
$subdistricts_options = [];
if (!empty($data['districts_district_id'])) {
    $res_s = $conn->query("SELECT subdistrict_id, subdistrict_name_th, zip_code FROM subdistricts WHERE districts_district_id = " . $data['districts_district_id']);
    while($row = $res_s->fetch_assoc()) $subdistricts_options[] = $row;
}

// ดึงคำนำหน้า
$prefix_result = $conn->query("SELECT prefix_id, prefix_th FROM prefixs WHERE is_active = 1 ORDER BY prefix_th");


// --- 4. HANDLE FORM SUBMIT (บันทึกแก้ไข) ---
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่า
    $co_name = trim($_POST['co_name']);
    $tax_id = trim($_POST['tax_id']);
    $supplier_phone_no = trim($_POST['supplier_phone_no']);
    $supplier_email = trim($_POST['supplier_email']);
    $subdistricts_id = $_POST['subdistricts_subdistrict_id'] ?? null;

    // Validation เบื้องต้น (PHP Side)
    if (empty($co_name)) $error_message = "กรุณากรอกชื่อบริษัท";
    elseif (!empty($supplier_phone_no) && !preg_match('/^[0-9]{10}$/', $supplier_phone_no)) $error_message = "เบอร์โทรศัพท์ต้องเป็นเลข 10 หลัก";
    elseif (empty($subdistricts_id)) $error_message = "กรุณาเลือกข้อมูลที่อยู่ให้ครบถ้วน";

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
            header("Location: supplier.php?success=edited"); // เด้งกลับหน้ารายการ
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
    <?php require '../config/load_theme.php'; ?>
    
    <style>
        body { background-color: #f8f9fa; }
        .main-card { border-radius: 12px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); background: #fff; }
        .card-header-custom { background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: white; padding: 1.2rem; border-radius: 12px 12px 0 0; }
        .form-label { font-weight: 500; }
        .required-star { color: #dc3545; }
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
                                <a href="supplier.php" class="btn btn-outline-light btn-sm">ย้อนกลับ</a>
                            </div>
                            <div class="card-body p-4">
                                <?php if ($error_message): ?>
                                    <div class="alert alert-danger"><?= $error_message ?></div>
                                <?php endif; ?>

                                <form method="POST" class="needs-validation" novalidate>
                                    
                                    <h6 class="text-warning fw-bold mb-3 border-bottom pb-2">ข้อมูลทั่วไป</h6>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">ชื่อบริษัท <span class="required-star">*</span></label>
                                            <input type="text" name="co_name" class="form-control" required value="<?= htmlspecialchars($data['co_name']) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">เลขผู้เสียภาษี</label>
                                            <input type="text" name="tax_id" class="form-control" maxlength="13" value="<?= htmlspecialchars($data['tax_id']) ?>">
                                        </div>
                                    </div>

                                    <h6 class="text-warning fw-bold mb-3 border-bottom pb-2 pt-3">ข้อมูลผู้ติดต่อ</h6>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-2">
                                            <label class="form-label">คำนำหน้า</label>
                                            <select name="prefixs_prefix_id" class="form-select select2">
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
                                            <label class="form-label">ชื่อ</label>
                                            <input type="text" name="contact_firstname" class="form-control" value="<?= htmlspecialchars($data['contact_firstname']) ?>">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">นามสกุล</label>
                                            <input type="text" name="contact_lastname" class="form-control" value="<?= htmlspecialchars($data['contact_lastname']) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">เบอร์โทรศัพท์ (10 หลัก) <span class="required-star">*</span></label>
                                            <input type="tel" name="supplier_phone_no" id="phoneInput" class="form-control" maxlength="10" value="<?= htmlspecialchars($data['supplier_phone_no']) ?>">
                                            <div id="phoneError" class="text-danger small mt-1" style="display:none;"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">อีเมล</label>
                                            <input type="email" name="supplier_email" id="emailInput" class="form-control" value="<?= htmlspecialchars($data['supplier_email']) ?>">
                                            <div id="emailError" class="text-danger small mt-1" style="display:none;"></div>
                                        </div>
                                    </div>

                                    <h6 class="text-warning fw-bold mb-3 border-bottom pb-2 pt-3">ที่อยู่</h6>
                                    <div class="row g-3">
                                        <div class="col-md-3"><label class="form-label">บ้านเลขที่</label><input type="text" name="home_no" class="form-control" value="<?= htmlspecialchars($data['home_no']) ?>"></div>
                                        <div class="col-md-3"><label class="form-label">หมู่ที่</label><input type="text" name="moo" class="form-control" value="<?= htmlspecialchars($data['moo']) ?>"></div>
                                        <div class="col-md-6"><label class="form-label">หมู่บ้าน/อาคาร</label><input type="text" name="village" class="form-control" value="<?= htmlspecialchars($data['village']) ?>"></div>
                                        <div class="col-md-6"><label class="form-label">ซอย</label><input type="text" name="soi" class="form-control" value="<?= htmlspecialchars($data['soi']) ?>"></div>
                                        <div class="col-md-6"><label class="form-label">ถนน</label><input type="text" name="road" class="form-control" value="<?= htmlspecialchars($data['road']) ?>"></div>
                                        
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
                                        
                                        <div class="col-md-3">
                                            <label class="form-label">รหัสไปรษณีย์</label>
                                            <input type="text" id="zipcodeField" class="form-control bg-light" readonly value="<?= htmlspecialchars($data['zip_code']) ?>">
                                        </div>
                                    </div>

                                    <div class="text-center mt-5">
                                        <button type="submit" class="btn btn-warning px-5 rounded-pill shadow-sm"><i class="fas fa-save me-2"></i> บันทึกการแก้ไข</button>
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

    <script>
        $(document).ready(function() {
            // Init Select2
            $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });

            // AJAX Validation: Phone
            $('#phoneInput').on('blur', function() {
                const val = $(this).val();
                if(val.length > 0) {
                    $.post('edit_supplier.php', { action: 'validate_phone', value: val }, function(res) {
                        if(res.status === 'error') {
                            $('#phoneInput').addClass('is-invalid');
                            $('#phoneError').text(res.message).show();
                        } else {
                            $('#phoneInput').removeClass('is-invalid');
                            $('#phoneError').hide();
                        }
                    }, 'json');
                }
            });

            // AJAX Validation: Email
            $('#emailInput').on('blur', function() {
                const val = $(this).val();
                if(val.length > 0) {
                    $.post('edit_supplier.php', { action: 'validate_email', value: val }, function(res) {
                        if(res.status === 'error') {
                            $('#emailInput').addClass('is-invalid');
                            $('#emailError').text(res.message).show();
                        } else {
                            $('#emailInput').removeClass('is-invalid');
                            $('#emailError').hide();
                        }
                    }, 'json');
                }
            });

            // Cascading Location
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
        });

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