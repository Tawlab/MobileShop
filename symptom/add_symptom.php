<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_symptom');

// [1] รับค่า Shop ID จาก Session เพื่อระบุความเป็นเจ้าของข้อมูล
$shop_id = $_SESSION['shop_id'];

$error = '';
$success = '';

// -----------------------------------------------------------------------------
// HELPER FUNCTION: หา ID ถัดไป (กรณีไม่ได้ตั้ง Auto Increment ใน DB)
// -----------------------------------------------------------------------------
function getNextSymptomId($conn)
{
    $sql = "SELECT IFNULL(MAX(symptom_id), 100000) + 1 as next_id FROM symptoms";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['next_id'];
}

// -----------------------------------------------------------------------------
// จัดการการบันทึกข้อมูล (POST Method)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // รับค่าและทำความสะอาดข้อมูล
    $name = isset($_POST['symptom_name']) ? trim(mysqli_real_escape_string($conn, $_POST['symptom_name'])) : '';
    $desc = isset($_POST['symptom_desc']) ? trim(mysqli_real_escape_string($conn, $_POST['symptom_desc'])) : NULL;

    // ตรวจสอบความถูกต้อง
    if (empty($name)) {
        $error = 'กรุณากรอกชื่ออาการเสียให้ครบถ้วน';
    } else {
        // [2] ตรวจสอบว่ามีชื่ออาการเสียนี้อยู่แล้วใน "ร้านของตัวเอง" หรือไม่
        // เพื่อให้แต่ละร้านสามารถตั้งชื่อซ้ำกันได้โดยไม่กระทบกัน
        $check_sql = "SELECT symptom_id FROM symptoms WHERE symptom_name = ? AND shop_info_shop_id = ?";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("si", $name, $shop_id);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();

        if ($check_result->num_rows > 0) {
            $error = 'คุณมีชื่ออาการเสีย "' . htmlspecialchars($name) . '" ในระบบของร้านคุณอยู่แล้ว';
        } else {
            $symptom_id = getNextSymptomId($conn);

            // [3] เพิ่มข้อมูลพร้อมกับระบุ shop_info_shop_id เพื่อให้สาขาเห็นแค่ของตัวเอง
            $sql = "INSERT INTO symptoms (symptom_id, shop_info_shop_id, symptom_name, symptom_desc, create_at, update_at) 
                    VALUES (?, ?, ?, ?, NOW(), NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiss", $symptom_id, $shop_id, $name, $desc);

            if ($stmt->execute()) {
                $_SESSION['success'] = '✅ เพิ่มอาการเสียใหม่สำเร็จ: ' . htmlspecialchars($name);
                header('Location: symptoms.php');
                exit;
            } else {
                $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $stmt->error;
            }
            $stmt->close();
        }
        $stmt_check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มอาการเสียใหม่ - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: #f8fafc;
            font-family: 'Prompt', sans-serif;
        }

        .main-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            max-width: 700px;
            margin: auto;
        }

        /* หัวข้อสีขาวบน Gradient เขียวตามมาตรฐานหน้าอื่นๆ */
        .card-header-custom {
            background: linear-gradient(135deg, #198754 0%, #14532d 100%);
            padding: 1.5rem;
        }

        .card-header-custom h4 {
            color: #ffffff !important;
            font-weight: 600;
            margin-bottom: 0;
        }

        .form-label {
            font-weight: 600;
            color: #4b5563;
        }

        .form-control:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.1);
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-5">
                <div class="main-card card shadow-sm">
                    <div class="card-header-custom">
                        <h4><i class="bi bi-plus-circle-fill me-2"></i>เพิ่มข้อมูลอาการเสียใหม่</h4>
                    </div>

                    <div class="card-body p-4 p-md-5">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger border-0 shadow-sm mb-4">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="add_symptom.php" novalidate>
                            <div class="row g-4">
                                <div class="col-12">
                                    <label for="symptom_name" class="form-label">ชื่ออาการเสีย <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-activity"></i></span>
                                        <input type="text" class="form-control border-start-0 <?= !empty($error) ? 'is-invalid' : '' ?>"
                                            id="symptom_name" name="symptom_name" maxlength="50" required
                                            value="<?= htmlspecialchars($_POST['symptom_name'] ?? '') ?>"
                                            placeholder="ตัวอย่าง: จอแตก, เปิดไม่ติด, ลืมรหัสผ่าน">
                                    </div>
                                    <div class="form-text">ชื่อเรียกอาการเสียสั้นๆ สำหรับใช้ระบุในใบรับซ่อม</div>
                                </div>

                                <div class="col-12">
                                    <label for="symptom_desc" class="form-label">รายละเอียด / คำอธิบาย</label>
                                    <textarea class="form-control" id="symptom_desc" name="symptom_desc" rows="4" maxlength="100"
                                        placeholder="ระบุรายละเอียดเพิ่มเติมเกี่ยวกับอาการนี้ (ถ้ามี)..."><?= htmlspecialchars($_POST['symptom_desc'] ?? '') ?></textarea>
                                    <div class="d-flex justify-content-between mt-1">
                                        <small class="text-muted">คำอธิบายเพิ่มเติมสำหรับการจัดการภายใน</small>
                                        <small class="text-muted"><span id="charCount">0</span>/100</small>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-5">
                                <a href="symptoms.php" class="btn btn-light rounded-pill px-4">
                                    <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
                                </a>
                                <button type="submit" class="btn btn-success rounded-pill px-5 shadow-sm fw-bold">
                                    <i class="bi bi-save2-fill me-2"></i> บันทึกอาการเสีย
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.getElementById('symptom_name');
            const descArea = document.getElementById('symptom_desc');
            const charCount = document.getElementById('charCount');

            // ลบสถานะ Error เมื่อเริ่มพิมพ์ใหม่
            nameInput.addEventListener('input', function() {
                if (this.value.trim() !== '') {
                    this.classList.remove('is-invalid');
                }
            });

            // นับจำนวนตัวอักษรในคำอธิบาย
            descArea.addEventListener('input', function() {
                charCount.textContent = this.value.length;
            });
        });
    </script>
</body>

</html>