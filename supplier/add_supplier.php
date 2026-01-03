<?php
ob_start(); // ป้องกัน Error แทรก JSON
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
// AJAX HANDLER
// -----------------------------------------------------------------------------
if (isset($_POST['action'])) {
    ob_end_clean(); // เคลียร์ Output Buffer
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_POST['action'];
    $data = [];
    $status = 'success';
    $message = '';

    // -- โหลดข้อมูล --
    if ($action === 'get_districts') {
        $id = (int)$_POST['id'];
        $sql = "SELECT district_id, district_name_th FROM districts WHERE provinces_province_id = $id ORDER BY district_name_th";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
    } 
    elseif ($action === 'get_subdistricts') {
        $id = (int)$_POST['id'];
        $sql = "SELECT subdistrict_id, subdistrict_name_th, zip_code FROM subdistricts WHERE districts_district_id = $id ORDER BY subdistrict_name_th";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
    } 
    elseif ($action === 'get_branches') {
        $id = (int)$_POST['id'];
        $sql = "SELECT branch_id, branch_name FROM branches WHERE shop_info_shop_id = $id ORDER BY branch_name";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
    }
    // -- ตรวจสอบความถูกต้อง (Validation) --
    elseif ($action === 'validate_phone') {
        $val = trim($_POST['value']);
        if (!preg_match('/^[0-9]{10}$/', $val)) {
            $status = 'error';
            $message = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 หลักเท่านั้น';
        }
    }
    elseif ($action === 'validate_email') {
        $val = trim($_POST['value']);
        if ($val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $status = 'error';
            $message = 'รูปแบบอีเมลไม่ถูกต้อง';
        }
    }

    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

$return_url = $_GET['return_url'] ?? 'supplier.php';

// -----------------------------------------------------------------------------
// HANDLE FORM SUBMIT
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // กำหนด Shop/Branch
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

    // ที่อยู่
    $home_no = trim($_POST['home_no']) ?: NULL;
    $moo = trim($_POST['moo']) ?: NULL;
    $soi = trim($_POST['soi']) ?: NULL;
    $road = trim($_POST['road']) ?: NULL;
    $village = trim($_POST['village']) ?: NULL;
    $subdistricts_id = trim($_POST['subdistricts_subdistrict_id']) ?: NULL;

    $error_message = '';

    // Validation PHP Side
    if (empty($co_name)) $error_message = 'กรุณากรอกชื่อบริษัท';
    elseif (empty($subdistricts_id)) $error_message = 'กรุณาเลือกที่อยู่ให้ครบถ้วน';
    elseif (empty($target_shop_id) || empty($target_branch_id)) $error_message = 'ข้อมูลร้านค้าหรือสาขาไม่ถูกต้อง';
    elseif (!empty($supplier_phone_no) && !preg_match('/^[0-9]{10}$/', $supplier_phone_no)) $error_message = 'เบอร์โทรศัพท์ต้องมี 10 หลัก';
    
    if (empty($error_message)) {
        $conn->begin_transaction();
        try {
            // ID Supplier
            $res = $conn->query("SELECT IFNULL(MAX(supplier_id), 100000) + 1 as next_id FROM suppliers");
            $new_supplier_id = $res->fetch_assoc()['next_id'];

            // ID Address
            $res_addr = $conn->query("SELECT IFNULL(MAX(address_id), 0) + 1 as next_id FROM addresses");
            $new_address_id = $res_addr->fetch_assoc()['next_id'];

            // Insert Address
            $stmt_addr = $conn->prepare("INSERT INTO addresses (address_id, home_no, moo, soi, road, village, subdistricts_subdistrict_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_addr->bind_param("isssssi", $new_address_id, $home_no, $moo, $soi, $road, $village, $subdistricts_id);
            if (!$stmt_addr->execute()) throw new Exception("บันทึกที่อยู่ไม่สำเร็จ");
            $stmt_addr->close();

            // Insert Supplier (เพิ่ม branches_branch_id)
            // *ต้องมีคอลัมน์ branches_branch_id ในตาราง suppliers แล้ว*
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
            $_SESSION['success'] = "เพิ่มข้อมูลเรียบร้อยแล้ว";
            header("Location: $return_url");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

// โหลดข้อมูล Dropdown เริ่มต้น
$prefixes = $conn->query("SELECT * FROM prefixs WHERE is_active = 1");
$provinces = $conn->query("SELECT * FROM provinces ORDER BY province_name_th");
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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background-color: #f8f9fa; }
        .main-card { border-radius: 12px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); background: #fff; }
        .card-header-custom { background: linear-gradient(135deg, #198754 0%, #14532d 100%); color: white; padding: 1.2rem; border-radius: 12px 12px 0 0; }
        .form-section-title { color: #198754; font-weight: 600; border-bottom: 2px solid #e9ecef; padding-bottom: 0.5rem; margin-top: 1.5rem; margin-bottom: 1rem; }
        .required-star { color: #dc3545; }
        .admin-box { background: #e7f1ff; border: 1px solid #b6d4fe; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
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
                                <a href="<?= htmlspecialchars($return_url) ?>" class="btn btn-outline-light btn-sm">ยกเลิก</a>
                            </div>
                            <div class="card-body p-4">
                                <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger"><?= $error_message ?></div>
                                <?php endif; ?>

                                <form method="POST" class="needs-validation" novalidate>
                                    
                                    <?php if ($is_admin): ?>
                                    <div class="admin-box">
                                        <h6 class="text-primary fw-bold mb-3"><i class="fas fa-store me-2"></i> เลือกสาขาปลายทาง (Admin)</h6>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">ร้านค้า <span class="required-star">*</span></label>
                                                <select id="shopSelect" name="shop_id" class="form-select select2" required>
                                                    <option value="">-- เลือกร้านค้า --</option>
                                                    <?php foreach($shops as $s): ?>
                                                        <option value="<?= $s['shop_id'] ?>"><?= $s['shop_name'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">สาขา <span class="required-star">*</span></label>
                                                <select id="branchSelect" name="branch_id" class="form-select select2" required disabled>
                                                    <option value="">-- เลือกร้านค้าก่อน --</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="form-section-title"><i class="fas fa-info-circle"></i> ข้อมูลทั่วไป</div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">ชื่อบริษัท <span class="required-star">*</span></label>
                                            <input type="text" name="co_name" class="form-control" required value="<?= htmlspecialchars($_POST['co_name'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">เลขผู้เสียภาษี</label>
                                            <input type="text" name="tax_id" class="form-control" maxlength="13" value="<?= htmlspecialchars($_POST['tax_id'] ?? '') ?>">
                                        </div>
                                    </div>

                                    <div class="form-section-title"><i class="fas fa-user-tie"></i> ข้อมูลผู้ติดต่อ</div>
                                    <div class="row g-3">
                                        <div class="col-md-2">
                                            <label class="form-label">คำนำหน้า</label>
                                            <select name="prefixs_prefix_id" class="form-select select2">
                                                <option value="">เลือก</option>
                                                <?php while($p = $prefixes->fetch_assoc()): ?>
                                                    <option value="<?= $p['prefix_id'] ?>"><?= $p['prefix_th'] ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">ชื่อ</label>
                                            <input type="text" name="contact_firstname" class="form-control" value="<?= htmlspecialchars($_POST['contact_firstname'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">นามสกุล</label>
                                            <input type="text" name="contact_lastname" class="form-control" value="<?= htmlspecialchars($_POST['contact_lastname'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">เบอร์โทรศัพท์ (10 หลัก)</label>
                                            <input type="tel" name="supplier_phone_no" id="phoneInput" class="form-control" maxlength="10" value="<?= htmlspecialchars($_POST['supplier_phone_no'] ?? '') ?>">
                                            <div id="phoneError" class="text-danger small mt-1" style="display:none;"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">อีเมล</label>
                                            <input type="email" name="supplier_email" id="emailInput" class="form-control" value="<?= htmlspecialchars($_POST['supplier_email'] ?? '') ?>">
                                            <div id="emailError" class="text-danger small mt-1" style="display:none;"></div>
                                        </div>
                                    </div>

                                    <div class="form-section-title"><i class="fas fa-map-marker-alt"></i> ที่อยู่</div>
                                    <div class="row g-3">
                                        <div class="col-md-3"><label class="form-label">บ้านเลขที่</label><input type="text" name="home_no" class="form-control" value="<?= htmlspecialchars($_POST['home_no'] ?? '') ?>"></div>
                                        <div class="col-md-3"><label class="form-label">หมู่ที่</label><input type="text" name="moo" class="form-control" value="<?= htmlspecialchars($_POST['moo'] ?? '') ?>"></div>
                                        <div class="col-md-6"><label class="form-label">หมู่บ้าน/อาคาร</label><input type="text" name="village" class="form-control" value="<?= htmlspecialchars($_POST['village'] ?? '') ?>"></div>
                                        <div class="col-md-6"><label class="form-label">ซอย</label><input type="text" name="soi" class="form-control" value="<?= htmlspecialchars($_POST['soi'] ?? '') ?>"></div>
                                        <div class="col-md-6"><label class="form-label">ถนน</label><input type="text" name="road" class="form-control" value="<?= htmlspecialchars($_POST['road'] ?? '') ?>"></div>
                                        
                                        <div class="col-md-4">
                                            <label class="form-label">จังหวัด <span class="required-star">*</span></label>
                                            <select id="provinceSelect" class="form-select select2" required>
                                                <option value="">-- ค้นหาจังหวัด --</option>
                                                <?php while($p = $provinces->fetch_assoc()): ?>
                                                    <option value="<?= $p['province_id'] ?>"><?= $p['province_name_th'] ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">อำเภอ <span class="required-star">*</span></label>
                                            <select id="districtSelect" class="form-select select2" disabled required>
                                                <option value="">-- เลือกจังหวัดก่อน --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">ตำบล <span class="required-star">*</span></label>
                                            <select name="subdistricts_subdistrict_id" id="subdistrictSelect" class="form-select select2" disabled required>
                                                <option value="">-- เลือกอำเภอก่อน --</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label class="form-label">รหัสไปรษณีย์</label>
                                            <input type="text" id="zipcodeField" class="form-control bg-light" readonly>
                                        </div>
                                    </div>

                                    <div class="text-center mt-5">
                                        <button type="submit" class="btn btn-success px-5 rounded-pill"><i class="fas fa-save me-2"></i> บันทึกข้อมูล</button>
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
            // Init Select2 (ทำให้ Dropdown ค้นหาได้)
            $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });

            // 1. AJAX Validation: Phone
            $('#phoneInput').on('blur', function() {
                const val = $(this).val();
                if(val.length > 0) {
                    $.post('add_supplier.php', { action: 'validate_phone', value: val }, function(res) {
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

            // 2. AJAX Validation: Email
            $('#emailInput').on('blur', function() {
                const val = $(this).val();
                if(val.length > 0) {
                    $.post('add_supplier.php', { action: 'validate_email', value: val }, function(res) {
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

            // 3. Admin: Load Branches when Shop changes
            $('#shopSelect').on('change', function() {
                const shopId = $(this).val();
                const $branch = $('#branchSelect');
                $branch.empty().append('<option value="">-- เลือกสาขา --</option>').prop('disabled', true);
                
                if(shopId) {
                    $.post('add_supplier.php', { action: 'get_branches', id: shopId }, function(res) {
                        if(res.data) {
                            res.data.forEach(b => {
                                $branch.append(new Option(b.branch_name, b.branch_id));
                            });
                            $branch.prop('disabled', false);
                        }
                    }, 'json');
                }
            });

            // 4. Location Cascading (Province -> District -> Subdistrict -> Zipcode)
            $('#provinceSelect').on('change', function() {
                const id = $(this).val();
                const $dist = $('#districtSelect');
                $('#subdistrictSelect').empty().append('<option value="">-- เลือกอำเภอก่อน --</option>').prop('disabled', true);
                $('#zipcodeField').val('');
                $dist.empty().append('<option value="">-- เลือกอำเภอ --</option>').prop('disabled', true);

                if(id) {
                    $.post('add_supplier.php', { action: 'get_districts', id: id }, function(res) {
                        res.data.forEach(d => { $dist.append(new Option(d.district_name_th, d.district_id)); });
                        $dist.prop('disabled', false);
                    }, 'json');
                }
            });

            $('#districtSelect').on('change', function() {
                const id = $(this).val();
                const $sub = $('#subdistrictSelect');
                $('#zipcodeField').val('');
                $sub.empty().append('<option value="">-- เลือกตำบล --</option>').prop('disabled', true);

                if(id) {
                    $.post('add_supplier.php', { action: 'get_subdistricts', id: id }, function(res) {
                        res.data.forEach(s => {
                            const opt = new Option(s.subdistrict_name_th, s.subdistrict_id);
                            $(opt).data('zip', s.zip_code); // ฝัง zip code ไว้ใน option
                            $sub.append(opt);
                        });
                        $sub.prop('disabled', false);
                    }, 'json');
                }
            });

            $('#subdistrictSelect').on('change', function() {
                // ดึง Zip Code จาก Option ที่เลือก
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