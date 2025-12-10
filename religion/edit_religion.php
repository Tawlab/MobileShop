<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'edit_religion');

//  ตรวจสอบ ID ที่ส่งมา
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ไม่ได้ระบุรหัสศาสนา";
    header("Location: religion.php");
    exit();
}

$religion_id_to_edit = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM religions WHERE religion_id = ?");
$stmt->bind_param("s", $religion_id_to_edit);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
} else {
    $_SESSION['error'] = "ไม่พบข้อมูลศาสนานี้";
    header("Location: religion.php");
    exit();
}
$stmt->close();

// ตรวจสอบการส่งฟอร์ม POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $original_religion_id = trim($_POST['original_religion_id']);
    $religion_name_th = trim($_POST['religion_name_th']);
    $religion_name_en = trim($_POST['religion_name_en']);

    if (empty($religion_name_th)) {
        $_SESSION['error'] = "กรุณากรอกชื่อศาสนา(ไทย)";
        header("Location: edit_religion.php?id=$original_religion_id");
        exit();
    }
    $stmt = $conn->prepare("UPDATE religions SET religion_name_th = ?, religion_name_en = ? WHERE religion_id = ?");
    $stmt->bind_param("sss", $religion_name_th, $religion_name_en, $original_religion_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "อัปเดตข้อมูลศาสนาเรียบร้อยแล้ว";
        header("Location: religion.php");
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปเดต";
        header("Location: edit_religion.php?id=$original_religion_id");
    }
    $stmt->close();
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
<<<<<<< HEAD
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
=======
>>>>>>> 87d2bdcaa5a9158c74359bf647e536fa344f68ca
    <title>แก้ไขศาสนา</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        .container {
            max-width: 600px;
        }
    </style>
    <?php
    // (8) โหลดธีม
    require '../config/load_theme.php';
    ?>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container py-4">
                    <div class="card shadow rounded-4 p-4">
                        <h4 class="mb-4"><i class="bi bi-pencil-square me-2"></i>แก้ไขศาสนา</h4>

                        <?php if (!empty($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= $_SESSION['error'];
                                unset($_SESSION['error']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="original_religion_id" value="<?= htmlspecialchars($row['religion_id']) ?>">

                            <div class="mb-3">
                                <label for="religion_id" class="form-label">รหัสศาสนา (2 หลักตัวเลข)</label>
                                <input type="text" name="religion_id" id="religion_id" class="form-control" pattern="^\d{2}$" maxlength="2"
                                    value="<?= htmlspecialchars($row['religion_id']) ?>" readonly
                                    title="รหัสศาสนาไม่สามารถแก้ไขได้">
                                <div class="invalid-feedback">กรุณากรอกตัวเลข 2 หลักเท่านั้น</div>
                            </div>

                            <div class="mb-3">
                                <label for="religion_name_th" class="form-label">ชื่อศาสนา (ไทย)</label>
                                <input type="text" name="religion_name_th" id="religion_name_th" class="form-control" maxlength="30" pattern="^[ก-๙\s.]+$"
                                    value="<?= htmlspecialchars($row['religion_name_th']) ?>" required>
                                <div class="invalid-feedback">กรุณากรอกเฉพาะตัวอักษรภาษาไทย ไม่เกิน 30 ตัว</div>
                            </div>

                            <div class="mb-3">
                                <label for="religion_name_en" class="form-label">ชื่อศาสนา (อังกฤษ)</label>
                                <input type="text" name="religion_name_en" id="religion_name_en" class="form-control" maxlength="30" pattern="^[A-Za-z\s.]+$"
                                    value="<?= htmlspecialchars($row['religion_name_en']) ?>">
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <a href="religion.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> ย้อนกลับ</a>
                                <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> บันทึก</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (() => {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>

</html>