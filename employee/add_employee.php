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

// Prepare Data Queries
// 1. Shops (Load only if Admin)
$shops_data = [];
if ($is_admin) {
    $shops_res = $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name");
    while($row = $shops_res->fetch_assoc()) $shops_data[] = $row;
}

// 2. Departments & Branches (Load ALL if Admin, Filtered if User)
// Admin: โหลดทั้งหมดมาแล้วใช้ JS Filter เอา (เพิ่ม column shop_id ใน select)
// User: โหลดเฉพาะของตัวเอง
$dept_sql = $is_admin ? "SELECT * FROM departments" : "SELECT * FROM departments WHERE shop_info_shop_id = '$current_shop_id'";
$branch_sql = $is_admin ? "SELECT * FROM branches" : "SELECT * FROM branches WHERE shop_info_shop_id = '$current_shop_id'";

$depts_res = $conn->query($dept_sql);
$depts_data = [];
while($row = $depts_res->fetch_assoc()) $depts_data[] = $row;

$branches_res = $conn->query($branch_sql);
$branches_data = [];
while($row = $branches_res->fetch_assoc()) $branches_data[] = $row;

// 3. Roles
$roles_res = $conn->query("SELECT * FROM roles"); // Admin เห็นหมด User เดี๋ยวไปซ่อนใน HTML
$roles_data = [];
while($row = $roles_res->fetch_assoc()) $roles_data[] = $row;

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
                                                    <?php foreach($shops_data as $s): ?>
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
                                                    <input type="text" class="form-control" name="emp_national_id" maxlength="13" required pattern="\d{13}">
                                                </div>
                                                <div class="col-md-5">
                                                    <label class="form-label">คำนำหน้า</label>
                                                    <select class="form-select select2" name="prefixs_prefix_id">
                                                        <?php while($p = $prefixs->fetch_assoc()): ?>
                                                            <option value="<?= $p['prefix_id'] ?>"><?= $p['prefix_th'] ?></option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6"><label class="form-label fw-bold">ชื่อ (ไทย) <span class="required-star">*</span></label><input type="text" class="form-control" name="firstname_th" required></div>
                                                <div class="col-md-6"><label class="form-label fw-bold">นามสกุล (ไทย) <span class="required-star">*</span></label><input type="text" class="form-control" name="lastname_th" required></div>
                                                <div class="col-md-6"><label class="form-label">ชื่อ (อังกฤษ)</label><input type="text" class="form-control" name="firstname_en"></div>
                                                <div class="col-md-6"><label class="form-label">นามสกุล (อังกฤษ)</label><input type="text" class="form-control" name="lastname_en"></div>
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
                                                        <?php while($r = $religions->fetch_assoc()): ?>
                                                            <option value="<?= $r['religion_id'] ?>"><?= $r['religion_name_th'] ?></option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-section-title">ข้อมูลการติดต่อ</div>
                                    <div class="row g-3">
                                        <div class="col-md-4"><label class="form-label fw-bold">เบอร์โทรศัพท์</label><input type="text" class="form-control" name="emp_phone_no"></div>
                                        <div class="col-md-4"><label class="form-label">อีเมล</label><input type="email" class="form-control" name="emp_email"></div>
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
                                            <label class="form-label fw-bold">จังหวัด</label>
                                            <select class="form-select select2" id="provinceSelect">
                                                <option value="">-- ค้นหาจังหวัด --</option>
                                                <?php while($p = $provinces->fetch_assoc()): ?>
                                                    <option value="<?= $p['province_id'] ?>"><?= $p['province_name_th'] ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">อำเภอ/ตำบล <span class="required-star">*</span></label>
                                            <select class="form-select select2" name="subdistricts_subdistrict_id" id="subdistrictSelect" disabled required>
                                                <option value="">-- เลือกจังหวัดก่อน --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">รหัสไปรษณีย์</label>
                                            <input type="text" class="form-control bg-light" id="zipcodeField" readonly>
                                        </div>
                                    </div>

                                    <div class="form-section-title">ข้อมูลการทำงาน & บัญชีผู้ใช้</div>
                                    <div class="row g-3">
                                        
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">แผนก <span class="required-star">*</span></label>
                                            <select class="form-select select2" name="departments_dept_id" id="deptSelect" required>
                                                <option value="">-- กรุณาเลือกแผนก --</option>
                                                <?php if(!$is_admin): ?>
                                                    <?php foreach($depts_data as $d): ?>
                                                        <option value="<?= $d['dept_id'] ?>"><?= $d['dept_name'] ?></option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">สาขาประจำ <span class="required-star">*</span></label>
                                            <select class="form-select select2" name="branches_branch_id" id="branchSelect" required>
                                                <option value="">-- กรุณาเลือกสาขา --</option>
                                                <?php if(!$is_admin): ?>
                                                    <?php foreach($branches_data as $b): ?>
                                                        <option value="<?= $b['branch_id'] ?>"><?= $b['branch_name'] ?></option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">สถานะพนักงาน</label>
                                            <select class="form-select" name="emp_status">
                                                <option value="Active">ทำงานอยู่ (Active)</option>
                                                <option value="Resigned">ลาออก (Resigned)</option>
                                                <option value="Suspended">พักงาน (Suspended)</option>
                                            </select>
                                        </div>

                                        <div class="col-md-12"><hr class="my-2"></div>

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
                                                    <?php foreach($roles_data as $rl): ?>
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
        // ข้อมูล JSON สำหรับ JS
        const allLocations = <?= json_encode($all_locations) ?>;
        // ส่งข้อมูล Dept/Branch ทั้งหมดให้ Admin ใช้ Filter (ถ้าเป็น User จะเป็น array ว่าง หรือมีแค่ของตัวเองก็ไม่เป็นไรเพราะ PHP จัดการแล้ว)
        const allDepts = <?= json_encode($depts_data) ?>;
        const allBranches = <?= json_encode($branches_data) ?>;
        const isAdmin = <?= json_encode($is_admin) ?>;

        $(document).ready(function() {
            $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });

            // [Admin Only] Logic เปลี่ยนร้านค้า -> กรองแผนก/สาขา
            if (isAdmin) {
                $('#shopSelect').on('change', function() {
                    const shopId = $(this).val();
                    const $dept = $('#deptSelect');
                    const $branch = $('#branchSelect');

                    // Clear
                    $dept.empty().append('<option value="">-- กรุณาเลือกแผนก --</option>');
                    $branch.empty().append('<option value="">-- กรุณาเลือกสาขา --</option>');

                    if (shopId) {
                        // Filter Departments
                        const filteredDepts = allDepts.filter(d => d.shop_info_shop_id == shopId);
                        filteredDepts.forEach(d => {
                            $dept.append(new Option(d.dept_name, d.dept_id));
                        });

                        // Filter Branches
                        const filteredBranches = allBranches.filter(b => b.shop_info_shop_id == shopId);
                        filteredBranches.forEach(b => {
                            $branch.append(new Option(b.branch_name, b.branch_id));
                        });
                        
                        // Notify if empty
                        if(filteredDepts.length === 0) $dept.append('<option disabled>-- ไม่มีข้อมูลแผนกในร้านนี้ --</option>');
                        if(filteredBranches.length === 0) $branch.append('<option disabled>-- ไม่มีข้อมูลสาขาในร้านนี้ --</option>');
                    }
                    
                    $dept.trigger('change');
                    $branch.trigger('change');
                });
            }

            // (ส่วน Preview Image, Location Filter, Submit Form เหมือนเดิม)
            window.previewFile = function() {
                const preview = document.getElementById('previewImg');
                const file = document.querySelector('input[type=file]').files[0];
                const reader = new FileReader();
                reader.onloadend = function() { preview.src = reader.result; }
                if (file) reader.readAsDataURL(file);
            }

            $('#provinceSelect').on('change', function() {
                const pId = $(this).val();
                const $subSelect = $('#subdistrictSelect');
                $subSelect.empty().append('<option value="">-- เลือกอำเภอ/ตำบล --</option>').prop('disabled', true);
                $('#zipcodeField').val('');
                if (pId) {
                    const filtered = allLocations.filter(loc => loc.province_id == pId);
                    filtered.forEach(loc => {
                        const opt = new Option(`${loc.district_name_th} > ${loc.subdistrict_name_th}`, loc.subdistrict_id);
                        $(opt).data('zip', loc.zip_code);
                        $subSelect.append(opt);
                    });
                    $subSelect.prop('disabled', false).trigger('change');
                }
            });

            $('#subdistrictSelect').on('change', function() {
                const zip = $(this).find(':selected').data('zip');
                $('#zipcodeField').val(zip || '');
            });

            $('#addEmpForm').on('submit', function(e) {
                e.preventDefault();
                if (!this.checkValidity()) {
                    e.stopPropagation();
                    $(this).addClass('was-validated');
                    Swal.fire('ข้อมูลไม่ครบ', 'กรุณากรอกข้อมูลให้ครบถ้วน', 'warning');
                    return;
                }
                
                // Extra Check for Admin
                if (isAdmin && !$('#shopSelect').val()) {
                    Swal.fire('แจ้งเตือน', 'กรุณาเลือกร้านค้า', 'warning');
                    return;
                }

                const formData = new FormData(this);
                Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                fetch('add_employee_process.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: data.message, timer: 1500, showConfirmButton: false })
                        .then(() => window.location.href = 'employee.php');
                    } else {
                        Swal.fire('ผิดพลาด', data.message, 'error');
                    }
                })
                .catch(err => Swal.fire('System Error', 'Error: ' + err, 'error'));
            });
        });
    </script>
</body>
</html>