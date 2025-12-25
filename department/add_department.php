<?php
ob_start(); // ป้องกัน Error แทรกใน JSON
session_start();
require '../config/config.php';
require '../config/load_theme.php';

// ตรวจสอบสิทธิ์
checkPageAccess($conn, 'add_department');

// ดึงค่า Shop ID จาก Session โดยตรง
$current_shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// --------------------------------------------------------------------------
// [PHP Logic] ฟังก์ชันหา ID ถัดไป (Manual Increment)
// --------------------------------------------------------------------------
function getNextId($conn, $table, $column) {
    $sql = "SELECT MAX($column) as max_id FROM $table";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    // ถ้ามีค่า ให้บวก 1, ถ้าไม่มี ให้เริ่มที่ 1
    return ($row['max_id']) ? $row['max_id'] + 1 : 1;
}
// --------------------------------------------------------------------------

// ==========================================================================================
// [1] FORM SUBMISSION: บันทึกข้อมูล
// ==========================================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    ob_clean(); 
    header('Content-Type: application/json');
    
    try {
        // 1. กำหนด Shop ID จากผู้ใช้งานปัจจุบันทันที (ไม่ต้องรับจาก POST)
        $shop_id = $current_shop_id;

        $dept_name = trim($_POST['dept_name']);
        $dept_desc = trim($_POST['dept_desc']);

        // Validation
        if (empty($dept_name)) throw new Exception("กรุณากรอกชื่อแผนก");
        if (mb_strlen($dept_name) > 50) throw new Exception("ชื่อแผนกต้องไม่เกิน 50 ตัวอักษร");

        // ตรวจสอบชื่อซ้ำ (เฉพาะใน Shop เดียวกัน)
        $chk_sql = "SELECT dept_id FROM departments WHERE dept_name = ? AND shop_info_shop_id = ?";
        $stmt = $conn->prepare($chk_sql);
        $stmt->bind_param("si", $dept_name, $shop_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("ชื่อแผนก '$dept_name' มีอยู่แล้วในระบบ");
        }
        $stmt->close();

        // 2. เริ่ม Transaction
        $conn->begin_transaction();

        // 3. หา ID ใหม่ (Manual Increment)
        $new_dept_id = getNextId($conn, 'departments', 'dept_id');

        // 4. บันทึกข้อมูล
        $sql = "INSERT INTO departments (dept_id, shop_info_shop_id, dept_name, dept_desc, create_at, update_at) 
                VALUES (?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $conn->prepare($sql);
        // Bind Params: i(id), i(shop), s(name), s(desc)
        $stmt->bind_param("iiss", $new_dept_id, $shop_id, $dept_name, $dept_desc);
        
        if (!$stmt->execute()) {
             // เช็ค Error ซ้ำเผื่อ Race Condition
            if ($conn->errno == 1062) throw new Exception("รหัสแผนกซ้ำ ($new_dept_id) กรุณาลองใหม่อีกครั้ง");
            throw new Exception("บันทึกข้อมูลไม่สำเร็จ: " . $stmt->error);
        }
        $stmt->close();

        // 5. Commit
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'บันทึกแผนกใหม่เรียบร้อยแล้ว']);

    } catch (Exception $e) {
        if ($conn->connect_errno == 0) $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เพิ่มแผนกใหม่ - Mobile Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body { background-color: <?= $background_color ?>; font-family: '<?= $font_style ?>', sans-serif; color: <?= $text_color ?>; }
        
        /* Custom Header: สีขาวตามที่ขอ */
        .card-header-custom { 
            background: linear-gradient(135deg, <?= $theme_color ?>, #0f5132); 
            color: #ffffff !important; 
            padding: 1.5rem; 
            border-radius: 15px 15px 0 0; 
        }
        .card-header-custom h4, .card-header-custom i {
            color: #ffffff !important;
        }

        .form-section-title { font-weight: 700; color: <?= $theme_color ?>; border-left: 5px solid <?= $theme_color ?>; padding-left: 10px; margin: 25px 0 15px 0; background: #f8f9fa; padding: 10px; border-radius: 0 5px 5px 0; }
        .required-star { color: #dc3545; }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-lg-8"> <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header-custom d-flex justify-content-between align-items-center">
                                <h4 class="mb-0 fw-bold"><i class="bi bi-diagram-3-fill me-2"></i>เพิ่มแผนกใหม่</h4>
                            </div>

                            <div class="card-body p-4 p-md-5">
                                <form id="addDeptForm" class="needs-validation" novalidate>
                                    
                                    <div class="form-section-title">ข้อมูลแผนก (Department Info)</div>
                                    
                                    <div class="alert alert-light border border-secondary border-opacity-25 d-flex align-items-center mb-4">
                                        <i class="bi bi-shop fs-4 me-3 text-secondary"></i>
                                        <div>
                                            <small class="text-muted d-block">คุณกำลังเพิ่มแผนกให้กับ:</small>
                                            <span class="fw-bold text-dark"><?= $_SESSION['shop_name'] ?? 'ไม่ระบุ' ?></span>
                                            <?php if($current_shop_id == 0): ?>
                                                <span class="badge bg-secondary ms-2">ส่วนกลาง (Central)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">ชื่อแผนก <span class="required-star">*</span></label>
                                        <input type="text" class="form-control" name="dept_name" required placeholder="เช่น ฝ่ายบัญชี, ฝ่ายซ่อมบำรุง">
                                        <div class="invalid-feedback">กรุณากรอกชื่อแผนก</div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-bold">รายละเอียด (เพิ่มเติม)</label>
                                        <textarea class="form-control" name="dept_desc" rows="3" placeholder="ระบุหน้าที่ความรับผิดชอบ หรือรายละเอียดสั้นๆ (ถ้ามี)"></textarea>
                                    </div>

                                    <hr>

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

    <script>
        $(document).ready(function() {
            // Handle Submit
            $('#addDeptForm').on('submit', function(e) {
                e.preventDefault();
                
                if (!this.checkValidity()) {
                    e.stopPropagation();
                    $(this).addClass('was-validated');
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
                    Swal.fire('System Error', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
                });
            });
        });
    </script>
</body>
</html>