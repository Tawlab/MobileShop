<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_religion');

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $religion_id = trim($_POST['religion_id']);
    $religion_name_th = trim($_POST['religion_name_th']);
    $religion_name_en = trim($_POST['religion_name_en']);

    if (empty($religion_id) || empty($religion_name_th)) {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
        header("Location: add_religion.php");
        exit();
    }

    // ตรวจสอบซ้ำ
    $sql = "SELECT COUNT(*) FROM religions WHERE religion_id = ? OR religion_name_th = ? OR religion_name_en = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $religion_id, $religion_name_th, $religion_name_en);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0) {
        $_SESSION['error'] = 'มีรหัส, ชื่อไทย, หรือชื่ออังกฤษนี้อยู่แล้ว';
        header("Location: add_religion.php");
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO religions (religion_id, religion_name_th, religion_name_en) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $religion_id, $religion_name_th, $religion_name_en);

    if ($stmt->execute()) {
        $_SESSION['success'] = 'เพิ่มศาสนาเรียบร้อยแล้ว';
        header("Location: religion.php");
    } else {
        $_SESSION['error'] = 'เกิดข้อผิดพลาดในการเพิ่มข้อมูล';
        header("Location: add_religion.php");
    }
    $stmt->close();
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>เพิ่มศาสนา</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        .container {
            max-width: 600px;
        }
    </style>
    <?php
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
                        <h4 class="mb-4"><i class="bi bi-plus-circle me-2"></i>เพิ่มศาสนา</h4>

                        <?php if (!empty($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= $_SESSION['error'];
                                unset($_SESSION['error']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="religion_id" class="form-label">รหัสศาสนา (2 หลักตัวเลข)</label>
                                <input type="text" name="religion_id" id="religion_id" class="form-control" pattern="^\d{2}$" maxlength="2" required>
                                <div class="invalid-feedback">กรุณากรอกตัวเลข 2 หลักเท่านั้น</div>
                            </div>

                            <div class="mb-3">
                                <label for="religion_name_th" class="form-label">ชื่อศาสนา (ไทย)</label>
                                <input type="text" name="religion_name_th" id="religion_name_th" class="form-control" maxlength="30" pattern="^[ก-๙\s.]+$" required>
                                <div class="invalid-feedback">กรุณากรอกเฉพาะตัวอักษรภาษาไทย ไม่เกิน 30 ตัว</div>
                            </div>

                            <div class="mb-3">
                                <label for="religion_name_en" class="form-label">ชื่อศาสนา (อังกฤษ)</label>
                                <input type="text" name="religion_name_en" id="religion_name_en" class="form-control" maxlength="30" pattern="^[A-Za-z\s.]+$">
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