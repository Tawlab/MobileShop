<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_product');
// (1) โหลดธีม
require '../config/load_theme.php';

// (2) ดึงข้อมูล brands และ types (แก้คอลัมน์)
$brands_query = "SELECT brand_id, brand_name_th FROM prod_brands ORDER BY brand_name_th";
$brands_result = mysqli_query($conn, $brands_query);

$types_query = "SELECT type_id, type_name_th FROM prod_types ORDER BY type_name_th";
$types_result = mysqli_query($conn, $types_query);

// (3) ประมวลผลฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // (4) แก้ไขชื่อคอลัมน์ที่รับค่า
    $prod_id = (int)$_POST['prod_id']; // <-- รับรหัสสินค้า
    $prod_name = trim($_POST['prod_name']);
    $prod_brands_brand_id = (int)$_POST['prod_brands_brand_id'];
    $prod_types_type_id = (int)$_POST['prod_types_type_id'];
    $model_name = trim($_POST['model_name']);
    $model_no = trim($_POST['model_no']);
    $prod_desc = trim($_POST['prod_desc']);
    $prod_price = (float)$_POST['prod_price'];

    // (5) การตรวจสอบข้อมูล (Validation)
    $errors = [];
    if (empty($prod_id)) $errors[] = "กรุณากรอกรหัสสินค้า";
    if (empty($prod_name)) $errors[] = "กรุณากรอกชื่อสินค้า";
    if (empty($prod_brands_brand_id)) $errors[] = "กรุณาเลือกยี่ห้อ";
    if (empty($prod_types_type_id)) $errors[] = "กรุณาเลือกประเภทสินค้า";
    if (empty($model_name)) $errors[] = "กรุณากรอกชื่อรุ่น";
    if (empty($model_no)) $errors[] = "กรุณากรอกรหัสรุ่น";
    if ($prod_price <= 0) $errors[] = "ราคาสินค้าต้องมากกว่า 0";

    // (6) ตรวจสอบรหัสสินค้า (prod_id) และ รหัสรุ่น (model_no) ซ้ำ
    if (empty($errors)) {
        // เช็ค prod_id
        $check_id_sql = "SELECT prod_id FROM products WHERE prod_id = ?";
        $check_id_stmt = $conn->prepare($check_id_sql);
        $check_id_stmt->bind_param("i", $prod_id);
        $check_id_stmt->execute();
        $check_id_result = $check_id_stmt->get_result();
        if ($check_id_result->num_rows > 0) {
            $errors[] = "รหัสสินค้า '$prod_id' นี้มีอยู่แล้วในระบบ";
        }
        $check_id_stmt->close();

        // เช็ค model_no
        $check_model_sql = "SELECT prod_id FROM products WHERE model_no = ?";
        $check_model_stmt = $conn->prepare($check_model_sql);
        $check_model_stmt->bind_param("s", $model_no);
        $check_model_stmt->execute();
        $check_model_result = $check_model_stmt->get_result();
        if ($check_model_result->num_rows > 0) {
            $errors[] = "รหัสรุ่น (Model No.) '$model_no' นี้มีอยู่แล้วในระบบ";
        }
        $check_model_stmt->close();
    }

    if (empty($errors)) {
        // (7) ใช้ Prepared Statement เพื่อความปลอดภัย
        $sql = "INSERT INTO products (prod_id, prod_name, prod_brands_brand_id, prod_types_type_id, model_name, model_no, prod_desc, prod_price) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        // (8) แก้ไข bind_param (i = int, s = string, d = double)
        // prod_id (i), prod_name (s), brand_id (i), type_id (i), model_name (s), model_no (s), prod_desc (s), prod_price (d)
        $stmt->bind_param(
            "isiisssd",
            $prod_id,
            $prod_name,
            $prod_brands_brand_id,
            $prod_types_type_id,
            $model_name,
            $model_no,
            $prod_desc,
            $prod_price
        );

        if ($stmt->execute()) {
            $_SESSION['success'] = "เพิ่มสินค้า '$prod_name' (รหัส: $prod_id) สำเร็จ";
            header('Location: product.php');
            exit();
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการบันทึก: " . $stmt->error;
        }
        $stmt->close();
    } else {
        // หากมี errors
        $_SESSION['errors'] = $errors;
    }
    // ส่งกลับไปหน้าเดิม (กรณีมี Error)
    header('Location: add_product.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มสินค้า - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- (9) เปลี่ยนเป็น Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            color: <?= $text_color ?>;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: <?= $theme_color ?>;
            /* Theme */
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }

        .btn-success {
            background: <?= $btn_add_color ?>;
            /* Theme */
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 500;
        }

        .btn-success:hover {
            filter: brightness(90%);
        }

        .btn-secondary {
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 500;
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 1px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: <?= $theme_color ?>;
            /* Theme */
            box-shadow: 0 0 0 0.2rem <?= $theme_color ?>40;
        }

        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container mt-4">
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="mb-0 text-light">
                                        <i class="bi bi-plus-circle-fill me-2"></i>เพิ่มสินค้าใหม่
                                    </h4>
                                </div>
                                <div class="card-body p-4">
                                    <!-- (10) แสดง Error จาก Session -->
                                    <?php if (isset($_SESSION['errors'])): ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <strong>เกิดข้อผิดพลาด!</strong>
                                            <ul>
                                                <?php foreach ($_SESSION['errors'] as $error): ?>
                                                    <li><?php echo htmlspecialchars($error); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                        <?php unset($_SESSION['errors']); ?>
                                    <?php endif; ?>

                                    <?php if (isset($_SESSION['error'])): ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                            <?php echo htmlspecialchars($_SESSION['error']);
                                            unset($_SESSION['error']); ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    <?php endif; ?>


                                    <form method="POST" action="add_product.php" class="needs-validation" novalidate>
                                        <div class="row">
                                            <!-- (11) เพิ่มช่องกรอกรหัสสินค้า -->
                                            <div class="col-md-6 mb-3">
                                                <label for="prod_id" class="form-label">
                                                    <i class="bi bi-key-fill me-1"></i>รหัสสินค้า (1-6 หลัก)
                                                </label>
                                                <input type="text" class="form-control border-secondary" id="prod_id" name="prod_id"
                                                    required pattern="\d{1,6}" maxlength="6">
                                                <div class="invalid-feedback">กรุณากรอกรหัสสินค้าเป็นตัวเลข 1-6 หลัก (ห้ามซ้ำ)</div>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label for="prod_name" class="form-label">
                                                    <i class="bi bi-tag-fill me-1"></i>ชื่อสินค้า
                                                </label>
                                                <!-- (12) แก้ name -->
                                                <input type="text" class="form-control border-secondary" id="prod_name" name="prod_name" required>
                                                <div class="invalid-feedback">กรุณากรอกชื่อสินค้า</div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="prod_brands_brand_id" class="form-label">
                                                    <i class="bi bi-building me-1"></i>ยี่ห้อ
                                                </label>
                                                <!-- (13) แก้ name, id, value, text -->
                                                <select class="form-select border-secondary" id="prod_brands_brand_id" name="prod_brands_brand_id" required>
                                                    <option value="">-- เลือกยี่ห้อ --</option>
                                                    <?php while ($brand = mysqli_fetch_assoc($brands_result)): ?>
                                                        <option value="<?php echo $brand['brand_id']; ?>">
                                                            <?php echo htmlspecialchars($brand['brand_name_th']); ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                                <div class="invalid-feedback">กรุณาเลือกยี่ห้อ</div>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label for="prod_types_type_id" class="form-label">
                                                    <i class="bi bi-diagram-3-fill me-1"></i>ประเภทสินค้า
                                                </label>
                                                <!-- (14) แก้ name, id, value, text -->
                                                <select class="form-select border-secondary" id="prod_types_type_id" name="prod_types_type_id" required>
                                                    <option value="">-- เลือกประเภท --</option>
                                                    <?php while ($type = mysqli_fetch_assoc($types_result)): ?>
                                                        <option value="<?php echo $type['type_id']; ?>">
                                                            <?php echo htmlspecialchars($type['type_name_th']); ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                                <div class="invalid-feedback">กรุณาเลือกประเภทสินค้า</div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="model_name" class="form-label">
                                                    <i class="bi bi-upc me-1"></i>ชื่อรุ่น
                                                </label>
                                                <input type="text" class="form-control border-secondary" id="model_name" name="model_name" required>
                                                <div class="invalid-feedback">กรุณากรอกชื่อรุ่น</div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="model_no" class="form-label">
                                                    <i class="bi bi-qr-code me-1"></i>รหัสรุ่น (Model No.)
                                                </label>
                                                <input type="text" class="form-control border-secondary" id="model_no" name="model_no" required>
                                                <div class="invalid-feedback">กรุณากรอกรหัสรุ่น (ต้องไม่ซ้ำ)</div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="prod_price" class="form-label">
                                                    <i class="bi bi-cash-coin me-1"></i>ราคา (บาท)
                                                </label>
                                                <!-- (15) แก้ name -->
                                                <input type="number" class="form-control border-secondary" id="prod_price" name="prod_price" step="0.01" min="0.01" required>
                                                <div class="invalid-feedback">กรุณากรอกราคาที่ถูกต้อง (มากกว่า 0)</div>
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label for="prod_desc" class="form-label">
                                                <i class="bi bi-file-text-fill me-1"></i>รายละเอียด
                                            </label>
                                            <!-- (16) แก้ name -->
                                            <textarea class="form-control border-secondary" id="prod_desc" name="prod_desc" rows="3"
                                                placeholder="รายละเอียดเพิ่มเติมของสินค้า (ไม่บังคับ)"></textarea>
                                        </div>

                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-success">
                                                <i class="bi bi-save-fill me-2"></i>บันทึก
                                            </button>
                                            <a href="product.php" class="btn btn-secondary">
                                                <i class="bi bi-x-circle-fill me-2"></i>ยกเลิก
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // (17) เพิ่ม Bootstrap Validation
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

        // ตรวจสอบราคาไม่ให้เป็นค่าลบ
        document.getElementById('prod_price').addEventListener('input', function() {
            if (this.value < 0) {
                this.value = 0;
            }
        });
    </script>
</body>

</html>