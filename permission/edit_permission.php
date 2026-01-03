<?php
session_start();
require '../config/config.php'; 
checkPageAccess($conn, 'edit_permission');

// --- รับ ID ที่จะแก้ไข---
$permission_id = (int)($_GET['id'] ?? 0);
if ($permission_id === 0) {
    die("ไม่พบ ID สิทธิ์ที่ต้องการแก้ไข");
}

// --- ตัวแปรสำหรับเก็บข้อมูล ---
$form_data = [];
$errors_to_display = [];

// --- ประมวลผลเมื่อมีการส่งฟอร์ม POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- ตรวจสอบ ID ว่าตรงกัน ---
    $post_id = (int)$_POST['permission_id'];
    if ($post_id !== $permission_id) {
        die("ID ที่ส่งมาไม่ตรงกัน!");
    }

    // --- รับค่าจากฟอร์ม ---
    $permission_name = trim($_POST['permission_name']);
    $permission_desc = trim($_POST['permission_desc']) ?: NULL; 

    // --- ตรวจสอบข้อมูล ---
    $errors = [];
    if (empty($permission_name)) {
        $errors[] = "กรุณากรอก 'ชื่อสิทธิ์ (Name)'";
    }

    // --- ตรวจสอบชื่อซ้ำ ---
    if (empty($errors)) {
        $stmt_check = $conn->prepare("SELECT permission_id FROM permissions WHERE permission_name = ? AND permission_id != ?");
        $stmt_check->bind_param("si", $permission_name, $permission_id);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();

        if ($check_result->num_rows > 0) {
            $errors[] = "ชื่อสิทธิ์ (Name) '$permission_name' นี้มีอยู่แล้วในระบบ";
        }
        $stmt_check->close();
    }

    if (empty($errors)) {
        try {
            $sql = "UPDATE permissions SET 
                        permission_name = ?, 
                        permission_desc = ?, 
                        update_at = NOW()
                    WHERE permission_id = ?";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare statement failed: " . $conn->error);
            }

            $stmt->bind_param("ssi", $permission_name, $permission_desc, $permission_id);

            if ($stmt->execute()) {
                $_SESSION['message'] = "แก้ไขสิทธิ์ '$permission_name' (ID: $permission_id) สำเร็จ";
                $_SESSION['message_type'] = "success";
                header("Location: permission.php"); 
                exit();
            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }
        } catch (Exception $e) {
            $errors_to_display = ["เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage()];
            $form_data = $_POST; 
            $form_data['permission_id'] = $permission_id;
        }
    } else {
        $errors_to_display = $errors;
        $form_data = $_POST; 
        $form_data['permission_id'] = $permission_id; 
    }
} else {
    if (isset($_SESSION['form_data'])) {
        $form_data = $_SESSION['form_data'];
        $errors_to_display = $_SESSION['errors'] ?? [];
        unset($_SESSION['form_data'], $_SESSION['errors']);
    } else {
        $stmt_get = $conn->prepare("SELECT * FROM permissions WHERE permission_id = ?");
        $stmt_get->bind_param("i", $permission_id);
        $stmt_get->execute();
        $result = $stmt_get->get_result();

        if ($result->num_rows === 0) {
            die("ไม่พบสิทธิ์ ID: $permission_id");
        }

        $form_data = $result->fetch_assoc();
        $stmt_get->close();
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขสิทธิ์ (ID: <?= $permission_id ?>) - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: #f0fdf4;
            color: #333;
        }

        .form-container {
            max-width: 700px;
            margin: 40px auto;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            background: linear-gradient(135deg, #2dd4bf 0%, #15803d 100%);
            color: white;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
            padding: 1.25rem 1.5rem;
            border-bottom: none;
        }

        .card-header h4 {
            font-weight: 600;
            margin-bottom: 0;
        }

        .card-body {
            padding: 2rem;
        }

        .form-label {
            font-weight: 500;
            color: #495057;
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 1px solid #ced4da;
            padding: 0.6rem 1rem;
            font-size: 0.9rem;
            background-color: #f8f9fa;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #15803d;
            box-shadow: 0 0 0 0.2rem rgba(21, 128, 61, 0.15);
            background-color: #fff;
        }

        .btn-success {
            background: linear-gradient(135deg, #2dd4bf 0%, #15803d 100%);
            border: none;
            font-weight: 500;
            padding: 0.6rem 1.5rem;
        }

        .btn-secondary {
            font-weight: 500;
            padding: 0.6rem 1.5rem;
        }

        .required {
            color: #dc3545;
            margin-left: 4px;
        }

        .form-text {
            font-size: 0.85rem;
        }

        .alert-danger ul {
            margin-bottom: 0;
            padding-left: 1.5rem;
        }

        /* (Alert Error ที่มาจาก Session) */
        .custom-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 300px;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.3s ease forwards;
            z-index: 1050;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .alert-error {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            color: white;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <?php if (isset($_SESSION['errors'])): ?>
                    <div class="custom-alert alert-error" role="alert">
                        <i class="fas fa-exclamation-circle fa-lg"></i>
                        <div> <strong>ผิดพลาด!</strong><br>
                            <?php foreach ($_SESSION['errors'] as $error): ?>
                                <?= htmlspecialchars($error); ?><br>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close" style="filter: invert(1) grayscale(100%) brightness(200%);"></button>
                    </div>
                    <?php unset($_SESSION['errors']); ?>
                <?php endif; ?>


                <div class="form-container">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0 text-white"><i class="fas fa-pencil-alt me-2"></i>แก้ไขสิทธิ์ (ID: <?= $permission_id ?>)</h4>
                        </div>

                        <form method="POST" action="edit_permission.php?id=<?= $permission_id ?>" id="editPermissionForm" novalidate>

                            <input type="hidden" name="permission_id" value="<?= $permission_id ?>">

                            <div class="card-body">

                                <?php if (!empty($errors_to_display) && !isset($_SESSION['errors'])): ?>
                                    <div class="alert alert-danger mb-4">
                                        <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>ข้อมูลไม่ถูกต้อง</h5>
                                        <ul>
                                            <?php foreach ($errors_to_display as $error): ?>
                                                <li><?= htmlspecialchars($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label for="permission_name" class="form-label">ชื่อสิทธิ์ (Name)<span class="required">*</span></label>
                                    <input type="text" class="form-control" id="permission_name" name="permission_name"
                                        value="<?= htmlspecialchars($form_data['permission_name'] ?? '') ?>"
                                        required maxlength="50"
                                        aria-describedby="nameHelp">
                                    <div id="nameHelp" class="form-text text-muted">
                                        (บังคับ) ใช้สำหรับอ้างอิงในโค้ด เช่น 'add_product', 'edit_user' (ต้องไม่ซ้ำกัน)
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="permission_desc" class="form-label">คำอธิบาย (Description)</label>
                                    <textarea class="form-control" id="permission_desc" name="permission_desc" rows="3"
                                        maxlength="100"
                                        aria-describedby="descHelp"><?= htmlspecialchars($form_data['permission_desc'] ?? '') ?></textarea>
                                    <div id="descHelp" class="form-text text-muted">
                                        (ไม่บังคับ) คำอธิบายที่เข้าใจง่าย เช่น 'สิทธิ์ในการเพิ่มสินค้า'
                                    </div>
                                </div>

                            </div>

                            <div class="card-footer text-center bg-light p-3">
                                <div class="d-flex gap-2 justify-content-center">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save me-2"></i>บันทึกการแก้ไข
                                    </button>
                                    <a href="permission.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>ยกเลิก
                                    </a>
                                </div>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- Client-side Validation ---
        document.getElementById('editPermissionForm').addEventListener('submit', function(event) {
            const nameInput = document.getElementById('permission_name');
            if (nameInput.value.trim() === '') {
                event.preventDefault(); 
                alert('กรุณากรอก "ชื่อสิทธิ์ (Name)"');
                nameInput.focus();
            }
        });

        // --- สำหรับซ่อน Alert Error จาก Session ---
        setTimeout(() => {
            document.querySelectorAll('.custom-alert').forEach(alert => {
                const bsAlert = bootstrap.Alert.getInstance(alert);
                if (bsAlert) {
                    bsAlert.close();
                } else if (alert) {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 5000); 
    </script>
</body>

</html>