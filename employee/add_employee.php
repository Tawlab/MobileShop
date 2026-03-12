<?php
session_start();
require '../config/config.php';

// ตรวจสอบสิทธิ์ Admin
$current_user_id = $_SESSION['user_id'];
$current_shop_id = $_SESSION['shop_id'];
$is_admin = false;

$chk_sql = "SELECT r.role_name FROM roles r 
            JOIN user_roles ur ON r.role_id = ur.roles_role_id 
            WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt = $conn->prepare($chk_sql)) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $is_admin = true;
    $stmt->close();
}

// หา Branch ID ของ User ปัจจุบัน (กรณีไม่ใช่ Admin)
$current_branch_id = 0;
if (!$is_admin) {
    $stmt_b = $conn->prepare("SELECT branches_branch_id FROM employees WHERE users_user_id = ?");
    $stmt_b->bind_param("i", $current_user_id);
    $stmt_b->execute();
    $res_b = $stmt_b->get_result();
    if ($row_b = $res_b->fetch_assoc()) {
        $current_branch_id = $row_b['branches_branch_id'];
    }
    $stmt_b->close();
}

// Prepare Data Queries

// Shops (Load only if Admin)
$shops_data = [];
if ($is_admin) {
    $shops_res = $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name");
    while ($row = $shops_res->fetch_assoc()) $shops_data[] = $row;
}

// Departments & Branches
// Admin: โหลดทั้งหมด เพื่อนำไปให้ JavaScript กรองตาม Shop ที่เลือก
// User:  โหลดเฉพาะของ "สาขาตัวเอง"
if ($is_admin) {
    $dept_sql = "SELECT dept_id, dept_name, shop_info_shop_id FROM departments";
    $branch_sql = "SELECT branch_id, branch_name, shop_info_shop_id FROM branches";
} else {
    $dept_sql = "SELECT dept_id, dept_name, shop_info_shop_id FROM departments WHERE branches_branch_id = '$current_branch_id'";
    $branch_sql = "SELECT branch_id, branch_name, shop_info_shop_id FROM branches WHERE branch_id = '$current_branch_id'";
}

$depts_res = $conn->query($dept_sql);
$depts_data = [];
while ($row = $depts_res->fetch_assoc()) $depts_data[] = $row;

$branches_res = $conn->query($branch_sql);
$branches_data = [];
while ($row = $branches_res->fetch_assoc()) $branches_data[] = $row;

// บทบาท
$roles_res = $conn->query("SELECT * FROM roles");
$roles_data = [];
while ($row = $roles_res->fetch_assoc()) $roles_data[] = $row;

// Other static data
$prefixs = $conn->query("SELECT * FROM prefixs");
$religions = $conn->query("SELECT * FROM religions WHERE is_active = 1");
$provinces = $conn->query("SELECT * FROM provinces ORDER BY province_name_th");

// Location Data (JSON for JS)
$subdistricts_res = $conn->query("SELECT s.subdistrict_id, s.subdistrict_name_th, s.zip_code, d.district_name_th, p.province_id 
                                  FROM subdistricts s 
                                  JOIN districts d ON s.districts_district_id = d.district_id 
                                  JOIN provinces p ON d.provinces_province_id = p.province_id");
$all_locations = [];
while ($row = $subdistricts_res->fetch_assoc()) $all_locations[] = $row;
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>เพิ่มพนักงานใหม่</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="add_employee.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        /* จัดการขอบแดงเมื่อกรอกผิด */
        .is-invalid { border-color: #dc3545 !important; }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>

        <div class="main-content w-100">
            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-lg-11">

                        <div class="card shadow border-0 rounded-4">
                            <div class="card-header-custom d-flex justify-content-between align-items-center">
                                <h4 class="mb-0"><i class="bi bi-person-plus-fill me-2"></i>เพิ่มพนักงานใหม่</h4>
                            </div>

                            <div class="card-body p-4">
                                <form id="addEmpForm" enctype="multipart/form-data" class="needs-validation" novalidate>

                                    <?php if ($is_admin): ?>
                                        <div class="alert alert-light border border-secondary border-opacity-25 mb-4">
                                            <div class="row align-items-center">
                                                <div class="col-md-6">
                                                    <label class="fw-bold text-success mb-1"><i class="bi bi-shop me-1"></i> เลือกร้านค้าที่สังกัด (Admin Only)</label>
                                                    <select class="form-select select2" name="shop_id" id="shopSelect" required>
                                                        <option value="">-- กรุณาเลือกร้านค้า --</option>
                                                        <?php foreach ($shops_data as $s): ?>
                                                            <option value="<?= $s['shop_id'] ?>"><?= $s['shop_name'] ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 text-muted small mt-2 mt-md-0">
                                                    * การเลือกร้านค้าจะกรองแผนกและสาขาโดยอัตโนมัติ
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <input type="hidden" name="shop_id" id="shopSelect" value="<?= $current_shop_id ?>">
                                    <?php endif; ?>

                                    <div class="form-section-title">ข้อมูลส่วนตัว</div>
                                    <div class="row g-3">
                                        <div class="col-md-2 text-center">
                                            <div class="mb-2"><img id="previewImg" src="../assets/img/default-avatar.png" class="img-preview" alt="รูปพนักงาน"></div>
                                            <label class="btn btn-sm btn-outline-primary rounded-pill w-100">
                                                <i class="bi bi-camera me-1"></i> เลือกรูป
                                                <input type="file" name="emp_image" class="d-none" onchange="previewFile()">
                                            </label>
                                        </div>
                                        <div class="col-md-10">
                                            <div class="row g-3">
                                                <div class="col-md-3">
                                                    <label class="form-label fw-bold">รหัสพนักงาน <span class="required-star">*</span></label>
                                                    <input type="text" class="form-control" name="emp_code" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-bold">เลขบัตรประชาชน (13 หลัก) <span class="required-star">*</span></label>
                                                    <input type="text" class="form-control" id="emp_national_id" name="emp_national_id" maxlength="13" required>
                                                </div>
                                                <div class="col-md-5">
                                                    <label class="form-label">คำนำหน้า</label>
                                                    <select class="form-select select2" name="prefixs_prefix_id">
                                                        <?php while ($p = $prefixs->fetch_assoc()): ?>
                                                            <option value="<?= $p['prefix_id'] ?>"><?= $p['prefix_th'] ?></option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold">ชื่อ (ไทย) <span class="required-star">*</span></label>
                                                    <input type="text" class="form-control" name="firstname_th" id="firstname_th" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold">นามสกุล (ไทย) <span class="required-star">*</span></label>
                                                    <input type="text" class="form-control" name="lastname_th" id="lastname_th" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">ชื่อ (อังกฤษ) <span class="text-muted small">(กรอกเฉพาะ A-Z)</span></label>
                                                    <input type="text" class="form-control" name="firstname_en" id="firstname_en">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">นามสกุล (อังกฤษ) <span class="text-muted small">(กรอกเฉพาะ A-Z)</span></label>
                                                    <input type="text" class="form-control" name="lastname_en" id="lastname_en">
                                                </div>
                                                <div class="col-md-3"><label class="form-label">วันเกิด</label><input type="date" class="form-control" name="emp_birthday"></div>
                                                <div class="col-md-3">
                                                    <label class="form-label">เพศ</label>
                                                    <select class="form-select" name="emp_gender">
                                                        <option value="Male">ชาย</option>
                                                        <option value="Female">หญิง</option>
                                                        <option value="LGBTQ+">LGBTQ+</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">ศาสนา</label>
                                                    <select class="form-select select2" name="religions_religion_id">
                                                        <?php while ($r = $religions->fetch_assoc()): ?>
                                                            <option value="<?= $r['religion_id'] ?>"><?= $r['religion_name_th'] ?></option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-section-title">ข้อมูลการติดต่อ</div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">เบอร์โทรศัพท์ <span class="required-star">*</span></label>
                                            <input type="text" class="form-control" id="emp_phone_no" name="emp_phone_no" maxlength="10" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">อีเมลพนักงาน <span class="text-muted small">(ไม่บังคับ / หากกรอกต้องยืนยัน OTP)</span></label>
                                            <div class="input-group">
                                                <input type="email" class="form-control" name="emp_email" id="emp_email">
                                                <button type="button" class="btn btn-outline-primary" id="btnSendOTP" style="display:none;">ส่งรหัส OTP</button>
                                            </div>
                                        </div>

                                        <div id="otpSection" style="display:none;" class="col-md-6 offset-md-4 mt-2">
                                            <label class="form-label text-success small fw-bold">กรอกรหัส OTP 6 หลักที่ได้รับในอีเมล</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="otp_code" maxlength="6">
                                                <button type="button" class="btn btn-success" id="btnVerifyOTP">ยืนยันรหัส</button>
                                            </div>
                                        </div>
                                        <div class="col-md-4"><label class="form-label">LINE ID</label><input type="text" class="form-control" name="emp_line_id"></div>
                                    </div>

                                    <div class="form-section-title">ที่อยู่ตามทะเบียนบ้าน</div>
                                    <div class="row g-3">
                                        <div class="col-md-2"><label class="form-label">เลขที่</label><input type="text" class="form-control" name="home_no"></div>
                                        <div class="col-md-2"><label class="form-label">หมู่ที่</label><input type="text" class="form-control" name="moo"></div>
                                        <div class="col-md-4"><label class="form-label">หมู่บ้าน/อาคาร</label><input type="text" class="form-control" name="village"></div>
                                        <div class="col-md-2"><label class="form-label">ซอย</label><input type="text" class="form-control" name="soi"></div>
                                        <div class="col-md-2"><label class="form-label">ถนน</label><input type="text" class="form-control" name="road"></div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">จังหวัด <span class="required-star">*</span></label>
                                            <select class="form-select select2" id="provinceSelect" required>
                                                <option value="">-- ค้นหาจังหวัด --</option>
                                                <?php mysqli_data_seek($provinces, 0);
                                                while ($p = $provinces->fetch_assoc()): ?>
                                                    <option value="<?= $p['province_id'] ?>"><?= $p['province_name_th'] ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">อำเภอ <span class="required-star">*</span></label>
                                            <select class="form-select select2" id="districtSelect" disabled required>
                                                <option value="">-- เลือกอำเภอ --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">ตำบล <span class="required-star">*</span></label>
                                            <select class="form-select select2" name="subdistricts_subdistrict_id" id="subdistrictSelect" disabled required>
                                                <option value="">-- เลือกตำบล --</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-section-title">ข้อมูลการทำงาน & บัญชีผู้ใช้</div>
                                    <div class="row g-3">

                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">แผนก <span class="required-star">*</span></label>
                                            <select class="form-select select2" name="departments_dept_id" id="deptSelect" required>
                                                <option value="">-- กรุณาเลือกแผนก --</option>
                                                <?php if (!$is_admin): ?>
                                                    <?php foreach ($depts_data as $d): ?>
                                                        <option value="<?= $d['dept_id'] ?>"><?= $d['dept_name'] ?></option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">สาขาประจำ <span class="required-star">*</span></label>
                                            <?php if ($is_admin): ?>
                                                <select class="form-select select2" name="branches_branch_id" id="branchSelect" required>
                                                    <option value="">-- กรุณาเลือกสาขา --</option>
                                                </select>
                                            <?php else: ?>
                                                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($branches_data[0]['branch_name'] ?? 'สาขาปัจจุบัน') ?>" readonly>
                                                <input type="hidden" name="branches_branch_id" value="<?= $current_branch_id ?>">
                                            <?php endif; ?>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">สถานะพนักงาน</label>
                                            <select class="form-select" name="emp_status">
                                                <option value="Active">ทำงานอยู่ (Active)</option>
                                                <option value="Resigned">ลาออก (Resigned)</option>
                                                <option value="Suspended">พักงาน (Suspended)</option>
                                            </select>
                                        </div>

                                        <div class="col-md-12">
                                            <hr class="my-2">
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Username <span class="required-star">*</span></label>
                                            <input type="text" class="form-control" name="username" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Password <span class="required-star">*</span></label>
                                            <input type="password" class="form-control" name="password" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Confirm Password <span class="required-star">*</span></label>
                                            <input type="password" class="form-control" name="confirm_password" required>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">สิทธิ์ใช้งาน (Role) <span class="required-star">*</span></label>
                                            <?php if ($is_admin): ?>
                                                <select class="form-select select2" name="role_id" required>
                                                    <option value="">-- เลือกสิทธิ์ --</option>
                                                    <?php foreach ($roles_data as $rl): ?>
                                                        <option value="<?= $rl['role_id'] ?>"><?= $rl['role_name'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php else: ?>
                                                <input type="text" class="form-control bg-light" value="User (พนักงานทั่วไป)" readonly>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-center gap-3 mt-5">
                                        <a href="employee.php" class="btn btn-light rounded-pill px-4">ยกเลิก</a>
                                        <button type="submit" class="btn btn-success rounded-pill px-5 fw-bold shadow-sm">
                                            <i class="bi bi-save2-fill me-2"></i> บันทึกข้อมูล
                                        </button>
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
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        const allLocations = <?= json_encode($all_locations) ?>;
        const isAdmin = <?= json_encode($is_admin) ?>;
        
        // ข้อมูลสำหรับ Dropdown แผนกและสาขา
        const deptsData = <?= json_encode($depts_data) ?>;
        const branchesData = <?= json_encode($branches_data) ?>;
        
        let isEmailVerified = true; // ค่าเริ่มต้นเป็น true เพราะเมลไม่บังคับกรอก

        $(document).ready(function() {
            // ตั้งค่า Select2
            $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });

            // ------------------------------------------------------------------
            // 5. ปรับปรุงการโหลดแผนกและสาขาเมื่อ Admin เลือกร้านค้า
            // ------------------------------------------------------------------
            if (isAdmin) {
                $('#shopSelect').on('change', function() {
                    const shopId = $(this).val();
                    const $branchSelect = $('#branchSelect');
                    const $deptSelect = $('#deptSelect');

                    // เคลียร์ค่าเก่า
                    $branchSelect.empty().append('<option value="">-- กรุณาเลือกสาขา --</option>');
                    $deptSelect.empty().append('<option value="">-- กรุณาเลือกแผนก --</option>');

                    if (shopId) {
                        // กรองสาขาตาม Shop ID
                        const filteredBranches = branchesData.filter(b => b.shop_info_shop_id == shopId);
                        filteredBranches.forEach(b => $branchSelect.append(new Option(b.branch_name, b.branch_id)));

                        // กรองแผนกตาม Shop ID
                        const filteredDepts = deptsData.filter(d => d.shop_info_shop_id == shopId);
                        filteredDepts.forEach(d => $deptSelect.append(new Option(d.dept_name, d.dept_id)));
                    }
                });
            }

            // ------------------------------------------------------------------
            // 2. ตรวจสอบการกรอกภาษาชื่อ-นามสกุล
            // ------------------------------------------------------------------
            $('#firstname_th, #lastname_th').on('input', function() {
                this.value = this.value.replace(/[^ก-๙\s]/g, ''); // บังคับภาษาไทย
            });

            $('#firstname_en, #lastname_en').on('input', function() {
                this.value = this.value.replace(/[^a-zA-Z\s]/g, ''); // บังคับภาษาอังกฤษ
            });

            // ------------------------------------------------------------------
            // 1. ตรวจสอบเลขบัตรประชาชน (Real-time + สูตรคำนวณ + AJAX)
            // ------------------------------------------------------------------
            function validateThaiID(id) {
                if (id.length !== 13 || !/^\d{13}$/.test(id)) return false;
                let sum = 0;
                for (let i = 0; i < 12; i++) sum += parseInt(id.charAt(i)) * (13 - i);
                let check = (11 - (sum % 11)) % 10;
                return check === parseInt(id.charAt(12));
            }

            $('#emp_national_id').on('input', function() {
                this.value = this.value.replace(/[^0-9]/g, ''); // พิมพ์ได้แค่ตัวเลข
            }).on('blur', function() {
                let el = $(this);
                let val = el.val().trim();
                
                if (val.length > 0) {
                    if (val.length !== 13 || !validateThaiID(val)) {
                        el.addClass('is-invalid');
                        Swal.fire('รูปแบบผิดพลาด', 'เลขบัตรประชาชนไม่ถูกต้องตามสูตรคำนวณ', 'error');
                    } else {
                        // เช็คข้อมูลซ้ำในฐานข้อมูล
                        $.post('check_availability.php', { action: 'check_national_id', national_id: val }, function(res) {
                            if (res.status === 'taken') {
                                el.addClass('is-invalid');
                                Swal.fire('ข้อมูลซ้ำ', 'เลขบัตรประชาชนนี้มีอยู่ในระบบแล้ว', 'warning');
                            } else {
                                el.removeClass('is-invalid');
                            }
                        }, 'json');
                    }
                } else {
                    el.removeClass('is-invalid');
                }
            });

            // ------------------------------------------------------------------
            // 3. ตรวจสอบเบอร์โทรศัพท์ (Real-time + Regex + AJAX)
            // ------------------------------------------------------------------
            $('#emp_phone_no').on('input', function() {
                this.value = this.value.replace(/[^0-9]/g, ''); // พิมพ์ได้แค่ตัวเลข
            }).on('blur', function() {
                let el = $(this);
                let val = el.val().trim();
                const phonePattern = /^(02|05|06|08|09)\d{8}$/;

                if (val.length > 0) {
                    if (!phonePattern.test(val)) {
                        el.addClass('is-invalid');
                        Swal.fire('รูปแบบผิดพลาด', 'เบอร์โทรศัพท์ไม่ถูกต้อง (ต้องขึ้นต้นด้วย 02,05,06,08,09 และมี 10 หลัก)', 'error');
                    } else {
                        // เช็คข้อมูลซ้ำ
                        $.post('check_availability.php', { action: 'check_phone', phone: val }, function(res) {
                            if (res.status === 'taken') {
                                el.addClass('is-invalid');
                                Swal.fire('ข้อมูลซ้ำ', 'เบอร์โทรศัพท์นี้ถูกใช้งานแล้ว', 'warning');
                            } else {
                                el.removeClass('is-invalid');
                            }
                        }, 'json');
                    }
                } else {
                    el.removeClass('is-invalid');
                }
            });

            // ------------------------------------------------------------------
            // 4. ระบบ OTP กรณีมีการกรอกอีเมล
            // ------------------------------------------------------------------
            $('#emp_email').on('input', function() {
                const email = $(this).val().trim();
                if (email.length > 0) {
                    $('#btnSendOTP').fadeIn();
                    isEmailVerified = false; // ถ้าพิมพ์อีเมล บังคับต้องยืนยัน
                } else {
                    $('#btnSendOTP').fadeOut();
                    $('#otpSection').fadeOut();
                    isEmailVerified = true; // ปล่อยว่างได้
                    $(this).removeClass('is-invalid');
                }
            }).on('blur', function() {
                let el = $(this);
                let email = el.val().trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                if (email.length > 0) {
                    if (!emailRegex.test(email)) {
                        el.addClass('is-invalid');
                        Swal.fire('ผิดพลาด', 'รูปแบบอีเมลไม่ถูกต้อง', 'warning');
                        $('#btnSendOTP').hide();
                    } else {
                        $.post('check_availability.php', { action: 'check_email', email: email }, function(res) {
                            if (res.status === 'taken') {
                                el.addClass('is-invalid');
                                Swal.fire('อีเมลซ้ำ', 'อีเมลนี้ถูกใช้งานแล้ว', 'warning');
                                $('#btnSendOTP').hide();
                            } else {
                                el.removeClass('is-invalid');
                                $('#btnSendOTP').show();
                            }
                        }, 'json');
                    }
                }
            });

            // ปุ่มส่ง OTP 
            $('#btnSendOTP').on('click', function() {
                const email = $('#emp_email').val();
                if ($('#emp_email').hasClass('is-invalid') || !email) return;

                const btn = $(this);
                btn.prop('disabled', true).text('กำลังส่ง...');

                fetch('send_otp.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ email: email }) // ใช้ email key ให้ตรงกับ send_otp
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire('สำเร็จ', 'รหัส OTP ถูกส่งไปยังอีเมลของคุณแล้ว', 'success');
                            $('#otpSection').fadeIn();
                        } else {
                            Swal.fire('ผิดพลาด', data.message, 'error');
                        }
                    })
                    .catch(err => Swal.fire('ผิดพลาด', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error'))
                    .finally(() => btn.prop('disabled', false).text('ส่งรหัส OTP'));
            });

            // ปุ่มยืนยัน OTP
            $('#btnVerifyOTP').on('click', function() {
                const otp = $('#otp_code').val();
                if (otp.length !== 6) return Swal.fire('คำเตือน', 'กรุณากรอกรหัส OTP 6 หลัก', 'warning');

                fetch('verify_otp.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ otp: otp })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire('สำเร็จ', 'ยืนยันอีเมลสำเร็จ', 'success');
                            $('#otp_code').addClass('is-valid').prop('readonly', true);
                            $('#btnVerifyOTP').prop('disabled', true).text('ยืนยันแล้ว');
                            $('#btnSendOTP').hide();
                            $('#emp_email').prop('readonly', true);
                            isEmailVerified = true; // ยืนยันผ่านแล้ว
                        } else {
                            Swal.fire('ผิดพลาด', data.message, 'error');
                        }
                    });
            });

            // ------------------------------------------------------------------
            // ระบบที่อยู่
            // ------------------------------------------------------------------
            $('#provinceSelect').on('change', function() {
                const pId = $(this).val();
                const $distSelect = $('#districtSelect'); 
                $distSelect.empty().append('<option value="">-- เลือกอำเภอ --</option>').prop('disabled', !pId);
                $('#subdistrictSelect').empty().append('<option value="">-- เลือกตำบล --</option>').prop('disabled', true);

                if (pId) {
                    const districts = [...new Set(allLocations.filter(l => l.province_id == pId).map(l => l.district_name_th))];
                    districts.forEach(d => $distSelect.append(new Option(d, d)));
                }
                $distSelect.trigger('change');
            });

            $('#districtSelect').on('change', function() {
                const dName = $(this).val();
                const $subSelect = $('#subdistrictSelect');
                $subSelect.empty().append('<option value="">-- เลือกตำบล --</option>').prop('disabled', !dName);

                if (dName) {
                    const filtered = allLocations.filter(l => l.district_name_th === dName);
                    filtered.forEach(l => {
                        const opt = new Option(l.subdistrict_name_th, l.subdistrict_id);
                        $(opt).data('zip', l.zip_code);
                        $subSelect.append(opt);
                    });
                }
                $subSelect.trigger('change');
            });

            // ------------------------------------------------------------------
            // บันทึกข้อมูล
            // ------------------------------------------------------------------
            $('#addEmpForm').on('submit', function(e) {
                e.preventDefault();

                // เช็คว่ามีช่องไหนติด Error (ขอบแดง) อยู่หรือไม่
                if ($('.is-invalid').length > 0) {
                    Swal.fire('ข้อมูลไม่ถูกต้อง', 'กรุณาแก้ไขข้อมูลที่มีขอบสีแดงให้ถูกต้อง', 'warning');
                    return;
                }

                // เช็คสถานะการยืนยัน OTP (กรณีมีการกรอกอีเมล)
                if (!isEmailVerified) {
                    Swal.fire('รอสักครู่', 'คุณกรอกอีเมลไว้ กรุณากดส่งและยืนยันรหัส OTP ให้เสร็จสิ้นก่อน', 'warning');
                    return;
                }

                // ตรวจสอบข้อมูล HTML5 Required
                if (!this.checkValidity()) {
                    e.stopPropagation();
                    $(this).addClass('was-validated');
                    return;
                }

                const formData = new FormData(this);
                Swal.fire({
                    title: 'กำลังบันทึก...',
                    didOpen: () => Swal.showLoading()
                });

                fetch('add_employee_process.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire('สำเร็จ', data.message, 'success').then(() => window.location.href = 'employee.php');
                        } else {
                            Swal.fire('ผิดพลาด', data.message, 'error');
                        }
                    });
            });
        });

        window.previewFile = function() {
            const file = document.querySelector('input[type=file]').files[0];
            const reader = new FileReader();
            reader.onloadend = () => document.getElementById('previewImg').src = reader.result;
            if (file) reader.readAsDataURL(file);
        };
    </script>
</body>

</html>