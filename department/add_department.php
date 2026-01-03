<?php
ob_start();
session_start();
require '../config/config.php';

checkPageAccess($conn, 'add_department');

$current_shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// --- 1. ตรวจสอบสิทธิ์ Admin ---
$is_admin = false;
$sql_role = "SELECT r.role_name FROM user_roles ur 
             JOIN roles r ON ur.roles_role_id = r.role_id 
             WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
$stmt = $conn->prepare($sql_role);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) $is_admin = true;
$stmt->close();

// หาสาขาของ User (กรณีไม่ใช่ Admin)
$current_user_branch_id = 0;
if (!$is_admin) {
    $sql_emp = "SELECT branches_branch_id FROM employees WHERE users_user_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql_emp);
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $res_emp = $stmt->get_result();
    if ($row_emp = $res_emp->fetch_assoc()) {
        $current_user_branch_id = $row_emp['branches_branch_id'];
    }
    $stmt->close();
}

// --------------------------------------------------------------------------
// AJAX HANDLER (สำหรับโหลดสาขา)
// --------------------------------------------------------------------------
if (isset($_POST['action'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_POST['action'];
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $data = [];

    if ($action === 'get_branches') {
        $sql = "SELECT branch_id, branch_name FROM branches WHERE shop_info_shop_id = $id ORDER BY branch_name";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
    }

    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

// ฟังก์ชันหา ID ถัดไป
function getNextId($conn, $table, $column) {
    $sql = "SELECT MAX($column) as max_id FROM $table";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return ($row['max_id']) ? $row['max_id'] + 1 : 1;
}

// ==========================================================================
// [POST] BUNDLE DATA
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    ob_end_clean(); 
    header('Content-Type: application/json');
    
    try {
        // กำหนด Shop/Branch เป้าหมาย
        if ($is_admin) {
            $target_shop_id = isset($_POST['shop_id']) ? (int)$_POST['shop_id'] : 0;
            $target_branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
            
            if (empty($target_shop_id) || empty($target_branch_id)) {
                throw new Exception("กรุณาเลือกร้านค้าและสาขาให้ครบถ้วน");
            }
        } else {
            $target_shop_id = $current_shop_id;
            $target_branch_id = $current_user_branch_id;
        }

        $dept_name = trim($_POST['dept_name']);
        $dept_desc = trim($_POST['dept_desc']);

        // Validation
        if (empty($dept_name)) throw new Exception("กรุณากรอกชื่อแผนก");
        if (mb_strlen($dept_name) > 50) throw new Exception("ชื่อแผนกต้องไม่เกิน 50 ตัวอักษร");

        // ตรวจสอบชื่อซ้ำ (ในสาขาเดียวกัน)
        $chk_sql = "SELECT dept_id FROM departments WHERE dept_name = ? AND branches_branch_id = ?";
        $stmt = $conn->prepare($chk_sql);
        $stmt->bind_param("si", $dept_name, $target_branch_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("ชื่อแผนก '$dept_name' มีอยู่แล้วในสาขานี้");
        }
        $stmt->close();

        // Transaction
        $conn->begin_transaction();

        $new_dept_id = getNextId($conn, 'departments', 'dept_id');

        // Insert (เพิ่ม branches_branch_id)
        // ** ต้องแน่ใจว่าตาราง departments มีคอลัมน์ branches_branch_id แล้ว **
        $sql = "INSERT INTO departments (dept_id, shop_info_shop_id, branches_branch_id, dept_name, dept_desc, create_at, update_at) 
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiss", $new_dept_id, $target_shop_id, $target_branch_id, $dept_name, $dept_desc);
        
        if (!$stmt->execute()) {
            throw new Exception("บันทึกข้อมูลไม่สำเร็จ: " . $stmt->error);
        }
        $stmt->close();

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'บันทึกแผนกใหม่เรียบร้อยแล้ว']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ดึงข้อมูลร้านค้า (สำหรับ Admin)
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
    <title>เพิ่มแผนกใหม่</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>

    <style>
        body { background-color: <?= $background_color ?>; font-family: '<?= $font_style ?>', sans-serif; color: <?= $text_color ?>; }
        .card-header-custom { 
            background: linear-gradient(135deg, <?= $theme_color ?>, #0f5132); 
            color: #ffffff; 
            padding: 1.5rem; 
            border-radius: 15px 15px 0 0; 
        }
        .form-section-title { font-weight: 700; color: <?= $theme_color ?>; border-left: 5px solid <?= $theme_color ?>; padding-left: 10px; margin: 20px 0 15px 0; background: #f8f9fa; padding: 10px; border-radius: 0 5px 5px 0; }
        .required-star { color: #dc3545; margin-left: 3px; }
        .admin-box { background-color: #e7f1ff; border: 1px solid #b6d4fe; border-radius: 10px; padding: 15px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header-custom d-flex justify-content-between align-items-center">
                                <h4 class="mb-0 fw-bold text-white"><i class="bi bi-diagram-3-fill me-2"></i>เพิ่มแผนกใหม่</h4>
                            </div>

                            <div class="card-body p-4 p-md-5">
                                <form id="addDeptForm" class="needs-validation" novalidate>
                                    
                                    <?php if ($is_admin): ?>
                                    <div class="admin-box">
                                        <h6 class="text-primary fw-bold mb-3"><i class="bi bi-shop-window me-2"></i>เลือกสาขาปลายทาง (Admin)</h6>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">ร้านค้า <span class="required-star">*</span></label>
                                                <select id="shopSelect" name="shop_id" class="form-select select2" required>
                                                    <option value="">-- เลือกร้านค้า --</option>
                                                    <?php foreach ($shops as $s): ?>
                                                        <option value="<?= $s['shop_id'] ?>"><?= htmlspecialchars($s['shop_name']) ?></option>
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

                                    <div class="form-section-title">ข้อมูลแผนก (Department Info)</div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">ชื่อแผนก <span class="required-star">*</span></label>
                                        <input type="text" class="form-control" name="dept_name" required placeholder="เช่น ฝ่ายบัญชี, ฝ่ายซ่อมบำรุง">
                                        <div class="invalid-feedback">กรุณากรอกชื่อแผนก</div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-bold">รายละเอียด (เพิ่มเติม)</label>
                                        <textarea class="form-control" name="dept_desc" rows="3" placeholder="ระบุหน้าที่ความรับผิดชอบ หรือรายละเอียดสั้นๆ (ถ้ามี)"></textarea>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-4">
                                        <a href="department.php" class="btn btn-light rounded-pill px-4"><i class="bi bi-arrow-left me-2"></i>ย้อนกลับ</a>
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
        $(document).ready(function() {
            $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });

            // Admin: Load Branches
            $('#shopSelect').on('change', function() {
                const shopId = $(this).val();
                const $branch = $('#branchSelect');
                
                $branch.empty().append('<option value="">-- เลือกสาขา --</option>').prop('disabled', true);

                if (shopId) {
                    $.post('add_department.php', { action: 'get_branches', id: shopId }, function(res) {
                        if (res.status === 'success') {
                            res.data.forEach(function(b) {
                                $branch.append(new Option(b.branch_name, b.branch_id));
                            });
                            $branch.prop('disabled', false);
                        }
                    }, 'json');
                }
            });

            // Submit Form
            $('#addDeptForm').on('submit', function(e) {
                e.preventDefault();
                
                if (!this.checkValidity()) {
                    e.stopPropagation();
                    $(this).addClass('was-validated');
                    // ถ้าเป็น Admin แล้วยังไม่เลือก Select2 ต้องบังคับโชว์แดง (Select2 ซ่อน input เดิม)
                    if ($('#shopSelect').length && !$('#shopSelect').val()) {
                        $('#shopSelect').next('.select2-container').find('.select2-selection').addClass('border-danger');
                    }
                    return;
                }

                Swal.fire({
                    title: 'กำลังบันทึก...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                fetch('add_department.php', {
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
                        }).then(() => window.location.href = 'department.php');
                    } else {
                        Swal.fire('บันทึกไม่สำเร็จ', data.message, 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('System Error', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้: ' + err, 'error');
                });
            });
            
            // ลบขอบแดง Select2 เมื่อมีการเลือก
            $('.select2').on('change', function() {
                if($(this).val()) {
                    $(this).next('.select2-container').find('.select2-selection').removeClass('border-danger');
                }
            });
        });
    </script>
</body>
</html>