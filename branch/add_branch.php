<?php
ob_start(); // ป้องกัน Error แทรกใน JSON
session_start();
require '../config/config.php';
require '../config/load_theme.php';

// ตรวจสอบสิทธิ์
checkPageAccess($conn, 'branch');

$current_shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// --------------------------------------------------------------------------
// [PHP Logic] ฟังก์ชันหา ID ถัดไป (Manual Increment)
// --------------------------------------------------------------------------
function getNextId($conn, $table, $column) {
    $sql = "SELECT MAX($column) as max_id FROM $table";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return ($row['max_id']) ? $row['max_id'] + 1 : 1;
}

// ตรวจสอบว่าเป็น Admin หรือไม่
$is_super_admin = false;
$chk_sql = "SELECT r.role_name FROM roles r 
            JOIN user_roles ur ON r.role_id = ur.roles_role_id 
            WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt = $conn->prepare($chk_sql)) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $is_super_admin = true;
    $stmt->close();
}

// ==========================================================================================
// [1] FORM SUBMISSION: บันทึกข้อมูล (AJAX)
// ==========================================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    ob_clean(); 
    header('Content-Type: application/json');
    
    try {
        // 1. รับค่าและ Validate
        // ถ้าเป็น Admin ให้ใช้ค่าจาก Post, ถ้าไม่ใช่ให้ใช้ ID ร้านตัวเอง
        $shop_id = ($is_super_admin && !empty($_POST['shop_id'])) ? intval($_POST['shop_id']) : $current_shop_id;
        
        $branch_name = trim($_POST['branch_name']);
        $branch_code = trim($_POST['branch_code']);
        $branch_phone = trim($_POST['branch_phone']);
        
        $home_no = trim($_POST['home_no']);
        $moo = trim($_POST['moo']);
        $soi = trim($_POST['soi']);
        $road = trim($_POST['road']);
        
        $subdistrict_id = isset($_POST['subdistrict_id']) ? intval($_POST['subdistrict_id']) : 0;

        if (empty($branch_name)) throw new Exception("กรุณากรอกชื่อสาขา");
        if ($subdistrict_id <= 0) throw new Exception("กรุณาเลือก ตำบล/แขวง ให้ถูกต้อง");

        // ตรวจสอบชื่อสาขาซ้ำ (ในร้านเดียวกัน)
        $chk_sql = "SELECT branch_id FROM branches WHERE branch_name = ? AND shop_info_shop_id = ?";
        $stmt = $conn->prepare($chk_sql);
        $stmt->bind_param("si", $branch_name, $shop_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("ชื่อสาขา '$branch_name' มีอยู่แล้วในร้านนี้");
        }
        $stmt->close();

        // 2. เริ่ม Transaction
        $conn->begin_transaction();

        // Step 1: บันทึกที่อยู่ (Addresses)
        $new_address_id = getNextId($conn, 'addresses', 'address_id'); 
        $sql_addr = "INSERT INTO addresses (address_id, home_no, moo, soi, road, subdistricts_subdistrict_id) 
                     VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql_addr);
        $stmt->bind_param("issssi", $new_address_id, $home_no, $moo, $soi, $road, $subdistrict_id);
        
        if (!$stmt->execute()) throw new Exception("บันทึกที่อยู่ไม่สำเร็จ: " . $stmt->error);
        $stmt->close();

        // Step 2: บันทึกสาขา (Branches)
        $new_branch_id = getNextId($conn, 'branches', 'branch_id');
        $sql_branch = "INSERT INTO branches (branch_id, branch_code, branch_name, branch_phone, Addresses_address_id, shop_info_shop_id, create_at, update_at) 
                       VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $conn->prepare($sql_branch);
        $stmt->bind_param("isssii", $new_branch_id, $branch_code, $branch_name, $branch_phone, $new_address_id, $shop_id);
        
        if (!$stmt->execute()) {
            if ($conn->errno == 1062) throw new Exception("รหัสสาขาซ้ำ ($new_branch_id) กรุณาลองใหม่อีกครั้ง");
            throw new Exception("บันทึกสาขาไม่สำเร็จ: " . $stmt->error);
        }
        $stmt->close();

        // 3. Commit
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'บันทึกสำเร็จ รหัสสาขา: ' . $new_branch_id]);

    } catch (Exception $e) {
        if ($conn->connect_errno == 0) $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================================================================
// [2] PRE-FETCH DATA
// ==========================================================================================
$shops = ($is_super_admin) ? $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name") : null;
$provinces_res = $conn->query("SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th");

$districts_res = $conn->query("SELECT district_id, district_name_th, provinces_province_id FROM districts ORDER BY district_name_th");
$all_districts = [];
while ($row = $districts_res->fetch_assoc()) $all_districts[] = $row;

$subdistricts_res = $conn->query("SELECT subdistrict_id, subdistrict_name_th, districts_district_id, zip_code FROM subdistricts ORDER BY subdistrict_name_th");
$all_subdistricts = [];
while ($row = $subdistricts_res->fetch_assoc()) $all_subdistricts[] = $row;

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เพิ่มสาขาใหม่ - Mobile Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        body { background-color: <?= $background_color ?>; font-family: '<?= $font_style ?>', sans-serif; color: <?= $text_color ?>; }
        
        .card-header-custom { 
            background: linear-gradient(135deg, <?= $theme_color ?>, #0f5132); 
            color: #ffffff !important; 
            padding: 1.5rem; 
            border-radius: 15px 15px 0 0; 
        }
        .card-header-custom h4, .card-header-custom i { color: #ffffff !important; }

        .form-section-title { font-weight: 700; color: <?= $theme_color ?>; border-left: 5px solid <?= $theme_color ?>; padding-left: 10px; margin: 25px 0 15px 0; background: #f8f9fa; padding: 10px; border-radius: 0 5px 5px 0; }
        .required-star { color: #dc3545; }
        .select2-container--bootstrap-5 .select2-selection { border-radius: 0.375rem; }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <div class="card shadow-sm border-0 rounded-4">
                            
                            <div class="card-header-custom d-flex justify-content-between align-items-center">
                                <h4 class="mb-0 fw-bold"><i class="bi bi-shop-window me-2"></i>เพิ่มสาขาใหม่</h4>
                            </div>

                            <div class="card-body p-4 p-md-5">
                                <form id="addBranchForm" class="needs-validation" novalidate>
                                    
                                    <div class="form-section-title">ข้อมูลพื้นฐาน</div>
                                    <div class="row g-3">
                                        
                                        <?php if ($is_super_admin): ?>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">สังกัดร้านค้า</label>
                                                <select class="form-select select2" name="shop_id" required>
                                                    <option value="">-- ค้นหาร้านค้า --</option>
                                                    <?php while($s = $shops->fetch_assoc()): ?>
                                                        <option value="<?= $s['shop_id'] ?>"><?= $s['shop_name'] ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6"></div> <?php endif; ?>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">ชื่อสาขา <span class="required-star">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-white"><i class="bi bi-tag"></i></span>
                                                <input type="text" class="form-control" name="branch_name" required placeholder="เช่น สาขานคร">
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">รหัสสาขา (Code)</label>
                                            <input type="text" class="form-control" name="branch_code" placeholder="เช่น BR-001">
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">เบอร์โทรศัพท์</label>
                                            <input type="text" class="form-control" name="branch_phone" placeholder="02-xxx-xxxx">
                                        </div>
                                    </div>

                                    <div class="form-section-title">ที่ตั้งสาขา</div>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">เลขที่บ้าน</label>
                                            <input type="text" class="form-control" name="home_no">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">หมู่ที่</label>
                                            <input type="text" class="form-control" name="moo">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">ซอย</label>
                                            <input type="text" class="form-control" name="soi">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">ถนน</label>
                                            <input type="text" class="form-control" name="road">
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">จังหวัด <span class="required-star">*</span></label>
                                            <select class="form-select select2" id="provinceSelect" required>
                                                <option value="">-- ค้นหาจังหวัด --</option>
                                                <?php while($p = $provinces_res->fetch_assoc()): ?>
                                                    <option value="<?= $p['province_id'] ?>"><?= $p['province_name_th'] ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">อำเภอ/เขต <span class="required-star">*</span></label>
                                            <select class="form-select select2" id="districtSelect" required disabled>
                                                <option value="">-- เลือกจังหวัดก่อน --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">ตำบล/แขวง <span class="required-star">*</span></label>
                                            <select class="form-select select2" name="subdistrict_id" id="subdistrictSelect" required disabled>
                                                <option value="">-- เลือกอำเภอก่อน --</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-12 text-end">
                                            <span class="text-muted small me-2">รหัสไปรษณีย์:</span>
                                            <span id="zipcodeDisplay" class="fw-bold text-primary">-</span>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-5 pt-3 border-top">
                                        <a href="branch.php" class="btn btn-light rounded-pill px-4"><i class="bi bi-arrow-left me-2"></i>ย้อนกลับ</a>
                                        <button type="submit" class="btn btn-success rounded-pill px-5 fw-bold shadow-sm">
                                            <i class="bi bi-save2-fill me-2"></i>บันทึกข้อมูล
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
        // ข้อมูลสำหรับ Filter Address
        const allDistricts = <?= json_encode($all_districts, JSON_UNESCAPED_UNICODE) ?>;
        const allSubdistricts = <?= json_encode($all_subdistricts, JSON_UNESCAPED_UNICODE) ?>;

        $(document).ready(function() {
            // Init Select2
            $('.select2').select2({ theme: 'bootstrap-5', width: '100%', placeholder: 'กรุณาเลือก' });

            // 1. เปลี่ยนจังหวัด -> อัปเดตอำเภอ
            $('#provinceSelect').on('change', function() {
                const pId = $(this).val();
                const $dSelect = $('#districtSelect');
                const $sSelect = $('#subdistrictSelect');

                $dSelect.empty().append('<option value="">-- ค้นหาอำเภอ --</option>').val(null).trigger('change').prop('disabled', true);
                $sSelect.empty().append('<option value="">-- เลือกอำเภอก่อน --</option>').val(null).trigger('change').prop('disabled', true);
                $('#zipcodeDisplay').text('-');

                if (pId) {
                    const filtered = allDistricts.filter(d => d.provinces_province_id == pId);
                    filtered.forEach(d => $dSelect.append(new Option(d.district_name_th, d.district_id)));
                    $dSelect.prop('disabled', false).trigger('change');
                }
            });

            // 2. เปลี่ยนอำเภอ -> อัปเดตตำบล
            $('#districtSelect').on('change', function() {
                const dId = $(this).val();
                const $sSelect = $('#subdistrictSelect');

                $sSelect.empty().append('<option value="">-- ค้นหาตำบล --</option>').val(null).trigger('change').prop('disabled', true);
                $('#zipcodeDisplay').text('-');

                if (dId) {
                    const filtered = allSubdistricts.filter(s => s.districts_district_id == dId);
                    filtered.forEach(s => {
                        const opt = new Option(s.subdistrict_name_th, s.subdistrict_id);
                        $(opt).attr('data-zip', s.zip_code);
                        $sSelect.append(opt);
                    });
                    $sSelect.prop('disabled', false).trigger('change');
                }
            });

            // 3. เปลี่ยนตำบล -> Zipcode
            $('#subdistrictSelect').on('change', function() {
                const zip = $(this).find(':selected').data('zip');
                $('#zipcodeDisplay').text(zip ? zip : '-');
            });

            // 4. Submit Form via AJAX
            $('#addBranchForm').on('submit', function(e) {
                e.preventDefault();
                
                if (!this.checkValidity()) {
                    e.stopPropagation();
                    $(this).addClass('was-validated');
                    return;
                }
                if (!$('#subdistrictSelect').val()) {
                    Swal.fire('ข้อมูลไม่ครบ', 'กรุณาเลือกที่อยู่ให้ครบถ้วน', 'warning');
                    return;
                }

                Swal.fire({
                    title: 'กำลังบันทึก...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                fetch('add_branch.php', {
                    method: 'POST',
                    body: new FormData(this)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ!',
                            text: data.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => window.location.href = 'branch.php');
                    } else {
                        Swal.fire('บันทึกไม่สำเร็จ', data.message, 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('System Error', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
                });
            });
        });
    </script>
</body>
</html>