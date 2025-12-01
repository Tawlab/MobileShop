<?php
session_start();
// (ตั้งค่า Path ให้ถูกต้องตามโครงสร้างโฟลเดอร์ของคุณ: ไฟล์นี้อยู่ใน /symptom/)
require '../config/config.php';
checkPageAccess($conn, 'symptoms');

// 1. ดึงข้อมูลทั้งหมดจากตารางอาการเสีย
$result = mysqli_query($conn, "SELECT * FROM symptoms ORDER BY symptom_id ASC");

// 2. จัดการการลบอาการเสีย (POST Handler)
if (isset($_POST['delete_symptom'])) {
    $symptom_id = (int)$_POST['symptom_id'];

    // (A) ตรวจสอบว่ามีรายการซ่อม (repair_symptoms) อ้างอิงอยู่หรือไม่
    $check_sql = "SELECT COUNT(*) FROM repair_symptoms WHERE symptoms_symptom_id = $symptom_id";
    $count = mysqli_fetch_assoc(mysqli_query($conn, $check_sql))['COUNT(*)'];

    if ($count > 0) {
        $_SESSION['error'] = 'ไม่สามารถลบได้ เนื่องจากมีงานซ่อม (' . $count . ') ที่ใช้อาการนี้อยู่';
    } else {
        mysqli_autocommit($conn, false);
        try {
            // (B) ลบ
            $delete_sql = "DELETE FROM symptoms WHERE symptom_id = $symptom_id";
            if (!mysqli_query($conn, $delete_sql)) {
                throw new Exception('ไม่สามารถลบอาการเสียได้');
            }

            mysqli_commit($conn);
            $_SESSION['success'] = 'ลบอาการเสียรหัส ' . $symptom_id . ' สำเร็จ';
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = 'เกิดข้อผิดพลาดในการลบ: ' . $e->getMessage();
        }
    }

    mysqli_autocommit($conn, true);
    header('Location: symptoms.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการอาการเสีย (Symptoms)</title>
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
        }

        .table th {
            background-color: <?= $header_bg_color ?>;
            color: <?= $header_text_color ?>;
            text-align: center;
        }

        .btn-add {
            background-color: <?= $btn_add_color ?>;
            color: white;
        }

        .btn-edit {
            background-color: <?= $btn_edit_color ?>;
            color: white;
        }

        .btn-delete {
            background-color: <?= $btn_delete_color ?>;
            color: white;
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
                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class="mb-0" style="color: <?= $theme_color ?>;">
                                    <i class="fas fa-list-check me-2"></i>
                                    รายการอาการเสียทั้งหมด
                                </h4>
                                <a href="add_symptom.php" class="btn btn-add">
                                    <i class="fas fa-plus me-1"></i> เพิ่มอาการใหม่
                                </a>
                            </div>
                        </div>

                        <div class="card-body">
                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo $_SESSION['success'];
                                    unset($_SESSION['success']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo $_SESSION['error'];
                                    unset($_SESSION['error']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>ชื่ออาการเสีย</th>
                                            <th>คำอธิบาย</th>
                                            <th>งานซ่อมที่ใช้</th>
                                            <th>วันที่สร้าง</th>
                                            <th>จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($result && $result->num_rows > 0): ?>
                                            <?php mysqli_data_seek($result, 0); // (ย้อนกลับไปอ่านใหม่หลังใช้ COUNT) 
                                            ?>
                                            <?php while ($row = $result->fetch_assoc()):
                                                // (Subquery สำหรับนับจำนวนงานซ่อมที่ใช้อาการนี้)
                                                $count_repair_sql = "SELECT COUNT(*) FROM repair_symptoms WHERE symptoms_symptom_id = " . $row['symptom_id'];
                                                $count_repair_result = mysqli_query($conn, $count_repair_sql);
                                                $repair_count = mysqli_fetch_assoc($count_repair_result)['COUNT(*)'];
                                            ?>
                                                <tr>
                                                    <td class="text-center"><?= htmlspecialchars($row['symptom_id']) ?></td>
                                                    <td><?= htmlspecialchars($row['symptom_name']) ?></td>
                                                    <td><?= htmlspecialchars($row['symptom_desc']) ?: '—' ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-primary"><?= $repair_count ?></span>
                                                    </td>
                                                    <td class="text-center"><?= date('d/m/Y', strtotime($row['create_at'])) ?></td>
                                                    <td class="text-center">
                                                        <a href="edit_symptom.php?id=<?= $row['symptom_id'] ?>" class="btn btn-edit btn-sm">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-delete btn-sm delete-btn"
                                                            data-id="<?= $row['symptom_id'] ?>"
                                                            data-name="<?= htmlspecialchars($row['symptom_name']) ?>"
                                                            data-count="<?= $repair_count ?>"
                                                            <?= $repair_count > 0 ? 'disabled' : '' ?>>
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">ไม่พบข้อมูลอาการเสีย กรุณาเพิ่มอาการใหม่</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="modal fade" id="confirmDeleteModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="symptoms.php">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> ยืนยันการลบ</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>คุณต้องการลบอาการเสีย <strong id="deleteSymptomName"></strong> ใช่หรือไม่?</p>
                                    <p class="text-danger small">
                                        <i class="fas fa-exclamation-circle me-1"></i>
                                        <strong>คำเตือน:</strong> การลบจะเป็นไปได้เมื่อไม่มีงานซ่อมใดๆ ใช้อาการนี้อยู่
                                    </p>
                                    <input type="hidden" name="symptom_id" id="deleteSymptomIdInput">
                                    <input type="hidden" name="delete_symptom" value="1">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-trash me-1"></i> ยืนยันการลบ
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
        document.addEventListener('DOMContentLoaded', function() {
            const deleteModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));

            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    const count = parseInt(this.getAttribute('data-count'));

                    if (count > 0) {
                        alert(`ไม่สามารถลบได้: มีงานซ่อม ${count} รายการที่ใช้อาการ "${name}" อยู่`);
                        return;
                    }

                    document.getElementById('deleteSymptomIdInput').value = id;
                    document.getElementById('deleteSymptomName').textContent = name;

                    deleteModal.show();
                });
            });
        });
    </script>
</body>

</html>