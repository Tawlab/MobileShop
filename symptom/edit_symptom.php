<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'edit_symptom');

$error = '';

// -----------------------------------------------------------------------------
// 1. GET ID AND LOAD DATA (ต้องทำก่อน POST เพื่อให้ฟอร์มแสดงข้อมูลเดิม)
// -----------------------------------------------------------------------------
$symptom_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($symptom_id <= 0) {
    $_SESSION['error'] = 'ไม่พบรหัสอาการเสียที่ต้องการแก้ไข';
    header('Location: symptoms.php');
    exit;
}

// (A) ดึงข้อมูลอาการเสียปัจจุบัน
$load_sql = "SELECT * FROM symptoms WHERE symptom_id = $symptom_id";
$load_result = mysqli_query($conn, $load_sql);
$symptom_data = mysqli_fetch_assoc($load_result);

if (!$symptom_data) {
    $_SESSION['error'] = 'ไม่พบข้อมูลอาการเสียรหัสที่ระบุ';
    header('Location: symptoms.php');
    exit;
}

// -----------------------------------------------------------------------------
// 2. POST HANDLER: จัดการการบันทึกข้อมูล
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // (B) รับค่าและทำความสะอาดข้อมูล
    $name = isset($_POST['symptom_name']) ? trim(mysqli_real_escape_string($conn, $_POST['symptom_name'])) : '';
    $desc = isset($_POST['symptom_desc']) ? trim(mysqli_real_escape_string($conn, $_POST['symptom_desc'])) : NULL;

    // (C) ตรวจสอบความถูกต้องเบื้องต้น
    if (empty($name)) {
        $error = 'กรุณากรอกชื่ออาการเสีย (Symptom Name) ให้ครบถ้วน';
    } else {

        // (D) *** UNIQUENESS CHECK ***: ตรวจสอบว่าชื่อใหม่ซ้ำกับ ID อื่นหรือไม่
        $check_sql = "SELECT symptom_id FROM symptoms WHERE symptom_name = ? AND symptom_id != ?";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("si", $name, $symptom_id);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();

        if ($check_result->num_rows > 0) {
            $error = 'ไม่สามารถบันทึกได้: ชื่ออาการเสีย "' . htmlspecialchars($name) . '" มีอยู่ในระบบแล้ว';
        } else {

            // (E) บันทึกการแก้ไขลงฐานข้อมูล
            $sql = "UPDATE symptoms SET symptom_name = ?, symptom_desc = ?, update_at = NOW() WHERE symptom_id = ?";
            $stmt = $conn->prepare($sql);

            // (bind_param "ssi" = String, String, Integer)
            $stmt->bind_param("ssi", $name, $desc, $symptom_id);

            if ($stmt->execute()) {
                $_SESSION['success'] = '✅ แก้ไขอาการเสียรหัส ' . $symptom_id . ' สำเร็จ: ' . htmlspecialchars($name);
                // (Redirect กลับไปหน้า list)
                header('Location: symptoms.php');
                exit;
            } else {
                $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $stmt->error;
            }
            $stmt->close();
        }
        $stmt_check->close();
    }

    // (ถ้าเกิด error จาก POST, ให้ฟอร์มใช้ค่าที่กรอกล่าสุด)
    if (!empty($error)) {
        $symptom_data['symptom_name'] = $name;
        $symptom_data['symptom_desc'] = $desc;
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขอาการเสีย #<?= $symptom_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            min-height: 100vh;
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            max-width: 600px;
            margin: auto;
        }

        .table th {
            background-color: <?= $header_bg_color ?>;
            color: <?= $header_text_color ?>;
        }

        .btn-success {
            background-color: <?= $btn_add_color ?>;
            color: white;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: <?= $theme_color ?>;
            box-shadow: 0 0 0 0.25rem rgba(<?= hexdec(substr($theme_color, 1, 2)) ?>, <?= hexdec(substr($theme_color, 3, 2)) ?>, <?= hexdec(substr($theme_color, 5, 2)) ?>, 0.25);
        }

        .invalid-feedback {
            display: block;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container py-5">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h4 class="mb-0" style="color: <?= $theme_color ?>;">
                                <i class="fas fa-edit me-2"></i>
                                แก้ไขอาการเสีย #<?= $symptom_id ?>
                            </h4>
                        </div>

                        <div class="card-body">
                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo $error; ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="edit_symptom.php?id=<?= $symptom_id ?>" novalidate>

                                <div class="mb-3">
                                    <label for="symptom_name" class="form-label">ชื่ออาการเสีย (จำเป็น) <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control <?= !empty($error) ? 'is-invalid' : '' ?>"
                                        id="symptom_name" name="symptom_name" maxlength="50" required
                                        value="<?= htmlspecialchars($symptom_data['symptom_name'] ?? '') ?>"
                                        placeholder="เช่น: จอแตก, เปิดไม่ติด, ชาร์จไม่เข้า">
                                    <div class="invalid-feedback">
                                        <?php echo empty($error) ? 'กรุณากรอกชื่ออาการเสีย' : $error; ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="symptom_desc" class="form-label">คำอธิบายเพิ่มเติม</label>
                                    <textarea class="form-control" id="symptom_desc" name="symptom_desc" rows="3" maxlength="100"
                                        placeholder="รายละเอียดอาการโดยย่อ..."><?= htmlspecialchars($symptom_data['symptom_desc'] ?? '') ?></textarea>
                                    <small class="text-muted">สูงสุด 100 ตัวอักษร</small>
                                </div>

                                <div class="d-flex justify-content-end mt-4">
                                    <a href="symptoms.php" class="btn btn-secondary me-2">
                                        <i class="fas fa-arrow-left me-1"></i> ย้อนกลับ
                                    </a>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save me-1"></i> บันทึกการแก้ไข
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // (*** Custom Validation Logic - เพื่อลบ class is-invalid เมื่อพิมพ์ ***)
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.getElementById('symptom_name');
            nameInput.addEventListener('input', function() {
                if (this.value.trim() !== '' && this.classList.contains('is-invalid')) {
                    this.classList.remove('is-invalid');
                }
            });
        });
    </script>
</body>

</html>