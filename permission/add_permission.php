<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_permission');

// --- ตัวแปรสำหรับเก็บข้อมูล ---
$form_data = [];
$errors_to_display = [];

// --- ประมวลผลเมื่อมีการส่งฟอร์ม POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- รับค่าจากฟอร์ม ---
    $permission_name = trim($_POST['permission_name']);
    $permission_desc = trim($_POST['permission_desc']) ?: NULL; // (ถ้าว่าง ให้เป็น NULL)

    // --- ตรวจสอบข้อมูล ---
    $errors = [];
    if (empty($permission_name)) {
        $errors[] = "กรุณากรอก 'ชื่อสิทธิ์ (Name)'";
    }

    // --- ตรวจสอบชื่อซ้ำหรือไม่ ---
    if (empty($errors)) {
        $stmt_check = $conn->prepare("SELECT permission_id FROM permissions WHERE permission_name = ?");
        $stmt_check->bind_param("s", $permission_name);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();

        if ($check_result->num_rows > 0) {
            $errors[] = "ชื่อสิทธิ์ (Name) '$permission_name' นี้มีอยู่แล้วในระบบ";
        }
        $stmt_check->close();
    }

    // --- (ถ้าไม่มี Error ให้บันทึก) ---
    if (empty($errors)) {
        try {

            // --- ใหม่: ค้นหา ID สูงสุด ---
            $sql_max_id = "SELECT MAX(permission_id) AS max_id FROM permissions";
            $max_result = $conn->query($sql_max_id);
            if (!$max_result) {
                throw new Exception("ล้มเหลวในการค้นหา ID สูงสุด: " . $conn->error);
            }
            $max_row = $max_result->fetch_assoc();

            // --- คำนวณ ID ใหม่ ---
            $next_permission_id = ($max_row['max_id'] ?? 0) + 1;
            $sql = "INSERT INTO permissions (permission_id, permission_name, permission_desc, create_at, update_at) 
                    VALUES (?, ?, ?, NOW(), NOW())";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare statement failed: " . $conn->error);
            }
            $stmt->bind_param("iss", $next_permission_id, $permission_name, $permission_desc);
            if ($stmt->execute()) {
                $_SESSION['message'] = "เพิ่มสิทธิ์ '$permission_name' (ID: $next_permission_id) สำเร็จ";
                $_SESSION['message_type'] = "success";
                header("Location: permission.php"); 
                exit();
            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }
        } catch (Exception $e) {
            $errors_to_display = ["เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage()];
            $form_data = $_POST; 
        }
    } else {
        // --- ถ้า Error จาก Validation ---
        $errors_to_display = $errors;
        $form_data = $_POST;
    }
} else {
    // --- ถ้าเป็นการเปิดหน้าครั้งแรก (GET) ---
    $form_data = $_SESSION['form_data'] ?? [];
    $errors_to_display = $_SESSION['errors'] ?? [];
    unset($_SESSION['form_data'], $_SESSION['errors']);
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มสิทธิ์ใหม่ - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: #f0fdf4;
            color: #333;
        }

        /* (จำกัดความกว้างฟอร์ม) */
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

        /* (Alert Error) */
        .alert-danger ul {
            margin-bottom: 0;
            padding-left: 1.5rem;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="form-container">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0 text-white"><i class="fas fa-plus-circle me-2"></i>เพิ่มสิทธิ์ใหม่ (Permission)</h4>
                        </div>

                        <form method="POST" action="add_permission.php" id="addPermissionForm" novalidate>
                            <div class="card-body">

                                <?php if (!empty($errors_to_display)): ?>
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
                                        (บังคับ) ใช้สำหรับอ้างอิงในโค้ด เช่น 'add_product', 'edit_user', 'view_report' (ต้องไม่ซ้ำกัน)
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="permission_desc" class="form-label">คำอธิบาย (Description)</label>
                                    <textarea class="form-control" id="permission_desc" name="permission_desc" rows="3"
                                        maxlength="100"
                                        aria-describedby="descHelp"><?= htmlspecialchars($form_data['permission_desc'] ?? '') ?></textarea>
                                    <div id="descHelp" class="form-text text-muted">
                                        (ไม่บังคับ) คำอธิบายที่เข้าใจง่าย เช่น 'สิทธิ์ในการเพิ่มสินค้า', 'สิทธิ์ในการแก้ไขผู้ใช้'
                                    </div>
                                </div>

                            </div>

                            <div class="card-footer text-center bg-light p-3">
                                <div class="d-flex gap-2 justify-content-center">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save me-2"></i>บันทึกข้อมูล
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
        // --- Client-side Validation  ---
        document.getElementById('addPermissionForm').addEventListener('submit', function(event) {
            const nameInput = document.getElementById('permission_name');
            if (nameInput.value.trim() === '') {
                event.preventDefault(); 
                alert('กรุณากรอก "ชื่อสิทธิ์ (Name)"');
                nameInput.focus();
            }
        });
    </script>
</body>

</html>