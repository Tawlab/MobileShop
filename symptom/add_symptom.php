<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_symptom');

$shop_id = $_SESSION['shop_id'];

// Function หา ID ถัดไป
function getNextSymptomId($conn)
{
    $res = $conn->query("SELECT IFNULL(MAX(symptom_id), 100000) + 1 as next_id FROM symptoms");
    return $res->fetch_assoc()['next_id'];
}

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(mysqli_real_escape_string($conn, $_POST['symptom_name']));
    $desc = trim(mysqli_real_escape_string($conn, $_POST['symptom_desc']));

    if (empty($name)) {
        $_SESSION['form_error'] = 'กรุณากรอกชื่ออาการเสีย';
    } else {
        // เช็คซ้ำในร้านตัวเอง
        $chk = $conn->prepare("SELECT symptom_id FROM symptoms WHERE symptom_name = ? AND shop_info_shop_id = ?");
        $chk->bind_param("si", $name, $shop_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $_SESSION['form_error'] = "ชื่ออาการ '$name' มีอยู่แล้วในระบบ";
        } else {
            // บันทึก
            $new_id = getNextSymptomId($conn);
            $stmt = $conn->prepare("INSERT INTO symptoms (symptom_id, symptom_name, symptom_desc, shop_info_shop_id, is_active, create_at, update_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())");
            $stmt->bind_param("issi", $new_id, $name, $desc, $shop_id);

            if ($stmt->execute()) {
                $_SESSION['success'] = "เพิ่มข้อมูลเรียบร้อยแล้ว";
                header("Location: symptoms.php");
                exit;
            } else {
                $_SESSION['form_error'] = "เกิดข้อผิดพลาด: " . $stmt->error;
            }
            $stmt->close();
        }
        $chk->close();
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>เพิ่มอาการเสีย</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-success text-white py-3">
                                <h5 class="mb-0 text-white"><i class="bi bi-plus-circle me-2"></i>เพิ่มอาการเสียใหม่</h5>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST" id="addForm" novalidate>
                                    <div class="mb-3">
                                        <label for="symptom_name" class="form-label fw-bold">ชื่ออาการเสีย <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="symptom_name" name="symptom_name" required placeholder="เช่น จอแตก, แบตเสื่อม" value="<?= htmlspecialchars($_POST['symptom_name'] ?? '') ?>">
                                        <div class="form-text">ชื่อสั้นๆ ที่ใช้แสดงในใบรับซ่อม</div>
                                    </div>

                                    <div class="mb-4">
                                        <label for="symptom_desc" class="form-label fw-bold">รายละเอียดเพิ่มเติม</label>
                                        <textarea class="form-control" id="symptom_desc" name="symptom_desc" rows="3" placeholder="ระบุรายละเอียด (ถ้ามี)"><?= htmlspecialchars($_POST['symptom_desc'] ?? '') ?></textarea>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-success btn-lg shadow-sm">
                                            <i class="bi bi-save me-2"></i>บันทึกข้อมูล
                                        </button>
                                        <a href="symptoms.php" class="btn btn-outline-secondary">ย้อนกลับ</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // SweetAlert แจ้งเตือน Error จาก PHP (เช่น ชื่อซ้ำ)
        <?php if (isset($_SESSION['form_error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'บันทึกไม่สำเร็จ',
                text: '<?= $_SESSION['form_error'] ?>'
            });
            <?php unset($_SESSION['form_error']); ?>
        <?php endif; ?>

        // Client-side Validation & SweetAlert
        document.getElementById('addForm').addEventListener('submit', function(e) {
            const name = document.getElementById('symptom_name').value.trim();
            if (name === '') {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณากรอกข้อมูล',
                    text: 'ช่อง "ชื่ออาการเสีย" จำเป็นต้องกรอก'
                });
            }
        });
    </script>
</body>

</html>