<?php
ob_start(); // ป้องกัน Error แทรกใน JSON
session_start();
require '../config/config.php';
require '../config/load_theme.php';

// ตรวจสอบสิทธิ์เข้าใช้งาน
checkPageAccess($conn, 'add_religion');

$current_user_id = $_SESSION['user_id'];

// --------------------------------------------------------------------------
// [1] Security Check: ตรวจสอบว่าเป็น Admin หรือไม่? (เฉพาะ Admin เพิ่มได้)
// --------------------------------------------------------------------------
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

// ถ้าไม่ใช่ Admin ให้เด้งออกทันที
if (!$is_admin) {
    $_SESSION['error'] = "คุณไม่มีสิทธิ์เข้าถึงหน้านี้ (เฉพาะผู้ดูแลระบบ)";
    header("Location: religion.php");
    exit();
}

// --------------------------------------------------------------------------
// [2] Function: หา ID ถัดไป (Manual Increment)
// --------------------------------------------------------------------------
function getNextReligionId($conn) {
    // หาค่ามากสุดจากตาราง religions
    $sql = "SELECT MAX(religion_id) as max_id FROM religions";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    // ถ้ามีค่า ให้บวก 1, ถ้าไม่มี ให้เริ่มที่ 1
    return ($row['max_id']) ? $row['max_id'] + 1 : 1;
}

// ==========================================================================================
// [3] FORM SUBMISSION: บันทึกข้อมูล
// ==========================================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    ob_clean(); 
    header('Content-Type: application/json');
    
    try {
        $religion_name_th = trim($_POST['religion_name_th']);
        $religion_name_en = trim($_POST['religion_name_en']);

        // Validation
        if (empty($religion_name_th)) throw new Exception("กรุณากรอกชื่อศาสนา (ภาษาไทย)");
        if (mb_strlen($religion_name_th) > 30) throw new Exception("ชื่อศาสนาต้องไม่เกิน 30 ตัวอักษร"); // ตามขนาด varchar(30)
        if (mb_strlen($religion_name_en) > 30) throw new Exception("ชื่อภาษาอังกฤษต้องไม่เกิน 30 ตัวอักษร");

        // ตรวจสอบชื่อซ้ำ
        $chk_sql = "SELECT religion_id FROM religions WHERE religion_name_th = ? OR (religion_name_en != '' AND religion_name_en = ?)";
        $stmt = $conn->prepare($chk_sql);
        $stmt->bind_param("ss", $religion_name_th, $religion_name_en);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("ชื่อศาสนานี้มีอยู่แล้วในระบบ");
        }
        $stmt->close();

        // เริ่ม Transaction
        $conn->begin_transaction();

        // หา ID ใหม่
        $new_id = getNextReligionId($conn);

        // บันทึกข้อมูล (ตัด create_at, update_at ออก เพราะไม่มีในตาราง)
        // กำหนด is_active = 1 (ใช้งาน), shop_info_shop_id = 0 (ส่วนกลาง)
        $sql = "INSERT INTO religions (religion_id, religion_name_th, religion_name_en, is_active, shop_info_shop_id) 
                VALUES (?, ?, ?, 1, 0)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $new_id, $religion_name_th, $religion_name_en);
        
        if (!$stmt->execute()) {
             if ($conn->errno == 1062) throw new Exception("รหัสศาสนาซ้ำ ($new_id) กรุณาลองใหม่");
            throw new Exception("บันทึกไม่สำเร็จ: " . $stmt->error);
        }
        $stmt->close();

        // Commit
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'บันทึกศาสนาใหม่เรียบร้อยแล้ว']);

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
    <title>เพิ่มศาสนาใหม่ - Mobile Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body { background-color: <?= $background_color ?>; font-family: '<?= $font_style ?>', sans-serif; color: <?= $text_color ?>; }
        
        /* Custom Header: สีขาวตาม Theme */
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
                    <div class="col-lg-6"> 
                        
                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header-custom d-flex justify-content-between align-items-center">
                                <h4 class="mb-0 fw-bold"><i class="bi bi-star-fill me-2"></i>เพิ่มศาสนาใหม่</h4>
                            </div>

                            <div class="card-body p-4 p-md-5">
                                <form id="addReligionForm" class="needs-validation" novalidate>
                                    
                                    <div class="alert alert-info border-0 bg-opacity-10 d-flex align-items-center mb-4">
                                        <i class="bi bi-globe2 fs-4 me-3 text-primary"></i>
                                        <div>
                                            <span class="fw-bold text-primary">ข้อมูลส่วนกลาง (Central Data)</span>
                                            <div class="small text-muted" style="font-size: 0.85rem;">
                                                ข้อมูลที่เพิ่มจะถูกใช้งานร่วมกันทุกสาขาในระบบ
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-section-title">รายละเอียด</div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">ชื่อศาสนา (ภาษาไทย) <span class="required-star">*</span></label>
                                        <input type="text" class="form-control" name="religion_name_th" required maxlength="30" placeholder="เช่น ศาสนาพุทธ">
                                        <div class="invalid-feedback">กรุณากรอกชื่อศาสนา</div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-bold">ชื่อศาสนา (ภาษาอังกฤษ)</label>
                                        <input type="text" class="form-control" name="religion_name_en" maxlength="30" placeholder="เช่น Buddhism">
                                    </div>

                                    <hr>

                                    <div class="d-flex justify-content-between align-items-center mt-4">
                                        <a href="religion.php" class="btn btn-light rounded-pill px-4"><i class="bi bi-arrow-left me-2"></i>ย้อนกลับ</a>
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
            $('#addReligionForm').on('submit', function(e) {
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

                fetch('add_religion.php', {
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
                        }).then(() => window.location.href = 'religion.php');
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