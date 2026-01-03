<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'edit_product');
require '../config/load_theme.php';

//  ตรวจสอบว่ามี ID ส่งมาหรือไม่ 
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ไม่พบรหัสสินค้าที่ต้องการแก้ไข";
    header('Location: product.php');
    exit();
}

$prod_id_to_edit = $_GET['id']; 

// การจัดการการแก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_prod_id = (int)$_POST['prod_id'];
    $original_prod_id = $_POST['original_prod_id']; 

    $prod_name = trim($_POST['prod_name']);
    $prod_brands_brand_id = (int)$_POST['prod_brands_brand_id'];
    $prod_types_type_id = (int)$_POST['prod_types_type_id'];
    $model_name = trim($_POST['model_name']);
    $model_no = trim($_POST['model_no']);
    $prod_desc = trim($_POST['prod_desc']);
    $prod_price = (float)$_POST['prod_price'];

    // (5) ตรวจสอบข้อมูล
    $errors = [];
    if (empty($new_prod_id)) $errors[] = "กรุณากรอกรหัสสินค้า";
    if (empty($prod_name)) $errors[] = "กรุณากรอกชื่อสินค้า";
    if (empty($prod_brands_brand_id)) $errors[] = "กรุณาเลือกยี่ห้อ";
    if (empty($prod_types_type_id)) $errors[] = "กรุณาเลือกประเภทสินค้า";
    if (empty($model_name)) $errors[] = "กรุณากรอกชื่อรุ่น";
    if (empty($model_no)) $errors[] = "กรุณากรอกรหัสรุ่น";
    if ($prod_price <= 0) $errors[] = "ราคาสินค้าต้องมากกว่า 0";

    //  ตรวจสอบรหัสซ้ำ (ถ้าเปลี่ยนรหัส)
    if ($new_prod_id != $original_prod_id) {
        $check_id_sql = "SELECT prod_id FROM products WHERE prod_id = ?";
        $check_id_stmt = $conn->prepare($check_id_sql);
        $check_id_stmt->bind_param("i", $new_prod_id);
        $check_id_stmt->execute();
        $check_id_result = $check_id_stmt->get_result();
        if ($check_id_result->num_rows > 0) {
            $errors[] = "รหัสสินค้า '$new_prod_id' นี้มีอยู่แล้วในระบบ";
        }
        $check_id_stmt->close();
    }

    //  ตรวจสอบว่ารหัสรุ่นซ้ำกับสินค้าอื่นหรือไม่ (ยกเว้นตัวเอง)
    $check_model_sql = "SELECT prod_id FROM products WHERE model_no = ? AND prod_id != ?";
    if ($stmt_model = $conn->prepare($check_model_sql)) {
        $stmt_model->bind_param("si", $model_no, $original_prod_id);
        $stmt_model->execute();
        $check_result = $stmt_model->get_result();
        if (mysqli_num_rows($check_result) > 0) {
            $errors[] = "รหัสรุ่น '$model_no' นี้มีอยู่แล้วในระบบ";
        }
        $stmt_model->close();
    }

    //  หากไม่มีข้อผิดพลาด ให้ทำการอัปเดต
    if (empty($errors)) {
        $update_sql = "UPDATE products SET 
                      prod_id = ?, 
                      prod_name = ?, 
                      prod_brands_brand_id = ?, 
                      prod_types_type_id = ?, 
                      model_name = ?, 
                      model_no = ?, 
                      prod_desc = ?, 
                      prod_price = ? 
                      WHERE prod_id = ?";

        if ($stmt = $conn->prepare($update_sql)) {
            $stmt->bind_param(
                "isiisssdi",
                $new_prod_id,
                $prod_name,
                $prod_brands_brand_id,
                $prod_types_type_id,
                $model_name,
                $model_no,
                $prod_desc,
                $prod_price,
                $original_prod_id
            );

            if ($stmt->execute()) {
                $_SESSION['success'] = "แก้ไขข้อมูลสินค้าสำเร็จ";
                header('Location: product.php');
                exit();
            } else {
                // ตรวจจับ Foreign Key Error
                if (mysqli_errno($conn) == 1451 || mysqli_errno($conn) == 1452) {
                    $errors[] = "เกิดข้อผิดพลาด Foreign Key: ไม่สามารถเปลี่ยนรหัสสินค้าที่มีการใช้งานอยู่ (เช่น ในสต็อก หรือ บิล)";
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการแก้ไขข้อมูล: " . $stmt->error;
                }
            }
            $stmt->close();
        }
    }

    //  เก็บข้อผิดพลาดไว้แสดง
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
    }
    // ส่งกลับไปหน้าเดิม (กรณีมี Error)
    header("Location: edit_product.php?id=" . $original_prod_id);
    exit();
}

//  ดึงข้อมูลสินค้าที่ต้องการแก้ไข
$product_sql = "SELECT p.*, pb.brand_name_th as brand_name, pt.type_name_th as type_name 
                FROM products p 
                LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id 
                LEFT JOIN prod_types pt ON p.prod_types_type_id = pt.type_id 
                WHERE p.prod_id = ?";

if ($stmt = mysqli_prepare($conn, $product_sql)) {
    $stmt->bind_param("s", $prod_id_to_edit); 
    $stmt->execute();
    $product_result = $stmt->get_result();

    if (mysqli_num_rows($product_result) == 0) {
        $_SESSION['error'] = "ไม่พบข้อมูลสินค้าที่ต้องการแก้ไข (ID: $prod_id_to_edit)";
        header('Location: product.php');
        exit();
    }

    $product = mysqli_fetch_assoc($product_result);
    $stmt->close();
} else {
    $_SESSION['error'] = "เกิดข้อผิดพลาดในการดึงข้อมูล";
    header('Location: product.php');
    exit();
}

// ดึงข้อมูลยี่ห้อ 
$brands_sql = "SELECT brand_id, brand_name_th FROM prod_brands ORDER BY brand_name_th";
$brands_result = mysqli_query($conn, $brands_sql);

// ดึงข้อมูลประเภทสินค้า 
$types_sql = "SELECT type_id, type_name_th FROM prod_types ORDER BY type_name_th";
$types_result = mysqli_query($conn, $types_sql);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขสินค้า - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            color: <?= $text_color ?>;
            min-height: 100vh;
        }

        .main-header {
            background-color: <?= $theme_color ?>;
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
        }

        .main-header h1 {
            font-weight: 700;
            margin-bottom: 0;
        }

        .form-card {
            background: white;
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .form-header {
            background: <?= $theme_color ?>;
            color: white;
            padding: 1.5rem;
        }

        .form-header h4 {
            margin: 0;
            font-weight: 600;
            font-size: 1.25rem;
        }

        .form-body {
            padding: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .form-control,
        .form-select {
            border-radius: 8px;
            border: 1px solid #e9ecef;
            padding: 0.6rem 0.75rem;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: <?= $theme_color ?>;
            box-shadow: 0 0 0 0.15rem <?= $theme_color ?>40;
        }

        .btn {
            border-radius: 8px;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-success {
            background: <?= $btn_add_color ?>;
            border: none;
            color: white !important;
            box-shadow: 0 4px 15px <?= $btn_add_color ?>40;
        }

        .btn-success:hover {
            filter: brightness(90%);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            border: none;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .alert {
            border-radius: 8px;
            border: none;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
        }

        .alert-danger {
            background-color: <?= $danger_text_color ?>20;
            color: <?= $danger_text_color ?>;
        }

        /* Theme Danger */
        .alert-warning {
            background-color: <?= $warning_bg_color ?>;
            color: #856404;
        }

        /* Theme Warning */
        .required {
            color: <?= $danger_text_color ?>;
        }

        /* Theme Danger */
        .input-group {
            position: relative;
        }

        .input-group-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 10;
        }

        .price-input::before {
            content: '฿';
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: <?= $theme_color ?>;
            font-weight: 600;
            z-index: 10;
        }

        .price-input .form-control {
            padding-left: 2rem;
        }

        .btn-group-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            margin-top: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .validation-feedback {
            display: block;
            font-size: 0.875rem;
            color: <?= $danger_text_color ?>;
            margin-top: 0.25rem;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <!-- Header -->
                <div class="main-header">
                    <div class="container">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h1 class="text-light">
                                    <i class="bi bi-pencil-square me-3"></i>
                                    แก้ไขสินค้า
                                    <small class="fs-6 opacity-75 d-block"><?php echo htmlspecialchars($product['prod_name']); ?></small>
                                </h1>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="container">
                    <?php if (isset($_SESSION['errors'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show fade-in">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>เกิดข้อผิดพลาด!</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($_SESSION['errors'] as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['errors']); ?>
                    <?php endif; ?>

                    <div class="card form-card fade-in">
                        <div class="form-header">
                            <h4 class="text-light">
                                <i class="bi bi-pencil-fill me-2"></i>
                                แก้ไขข้อมูลสินค้า
                            </h4>
                        </div>

                        <div class="form-body">
                            <form method="POST" action="edit_product.php?id=<?php echo $prod_id_to_edit; ?>" id="editProductForm" novalidate>

                                <input type="hidden" name="original_prod_id" value="<?php echo htmlspecialchars($product['prod_id']); ?>">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="prod_id" class="form-label">
                                                <i class="bi bi-key-fill me-1"></i>
                                                รหัสสินค้า <span class="required">*</span>
                                            </label>
                                            <input type="text" class="form-control border-secondary" id="prod_id" name="prod_id"
                                                value="<?php echo htmlspecialchars($product['prod_id']); ?>"
                                                placeholder="กรอกรหัสสินค้า" required pattern="\d{1,6}" maxlength="6">
                                            <div class="invalid-feedback">กรุณากรอกรหัสสินค้า 1-6 หลัก (ห้ามซ้ำ)</div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="prod_name" class="form-label">
                                                <i class="bi bi-tag-fill me-1"></i>
                                                ชื่อสินค้า <span class="required">*</span>
                                            </label>
                                            <input type="text" class="form-control border-secondary" id="prod_name" name="prod_name"
                                                value="<?php echo htmlspecialchars($product['prod_name']); ?>"
                                                placeholder="กรอกชื่อสินค้า" required>
                                            <div class="invalid-feedback">กรุณากรอกชื่อสินค้า</div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="prod_brands_brand_id" class="form-label">
                                                <i class="bi bi-building me-1"></i>
                                                ยี่ห้อ <span class="required">*</span>
                                            </label>
                                            <select class="form-select border-secondary" id="prod_brands_brand_id" name="prod_brands_brand_id" required>
                                                <option value="">-- เลือกยี่ห้อ --</option>
                                                <?php while ($brand = mysqli_fetch_assoc($brands_result)): ?>
                                                    <option value="<?php echo $brand['brand_id']; ?>"
                                                        <?php echo $product['prod_brands_brand_id'] == $brand['brand_id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($brand['brand_name_th']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                            <div class="invalid-feedback">กรุณาเลือกยี่ห้อ</div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="prod_types_type_id" class="form-label">
                                                <i class="bi bi-diagram-3-fill me-1"></i>
                                                ประเภทสินค้า <span class="required">*</span>
                                            </label>
                                            <select class="form-select border-secondary" id="prod_types_type_id" name="prod_types_type_id" required>
                                                <option value="">-- เลือกประเภท --</option>
                                                <?php while ($type = mysqli_fetch_assoc($types_result)): ?>
                                                    <option value="<?php echo $type['type_id']; ?>"
                                                        <?php echo $product['prod_types_type_id'] == $type['type_id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($type['type_name_th']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                            <div class="invalid-feedback">กรุณาเลือกประเภทสินค้า</div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="prod_price" class="form-label">
                                                <i class="bi bi-cash-coin me-1"></i>
                                                ราคา (บาท) <span class="required">*</span>
                                            </label>
                                            <div class="price-input position-relative">
                                                <input type="number" class="form-control border-secondary" id="prod_price" name="prod_price"
                                                    value="<?php echo $product['prod_price']; ?>"
                                                    placeholder="0.00" min="0.01" step="0.01" required>
                                            </div>
                                            <div class="invalid-feedback">กรุณากรอกราคาที่ถูกต้อง</div>
                                        </div>
                                    </div>

                                    <!-- ชื่อรุ่น -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="model_name" class="form-label">
                                                <i class="bi bi-upc me-1"></i>
                                                ชื่อรุ่น <span class="required">*</span>
                                            </label>
                                            <input type="text" class="form-control border-secondary" id="model_name" name="model_name"
                                                value="<?php echo htmlspecialchars($product['model_name']); ?>"
                                                placeholder="เช่น iPhone 15 Pro" required>
                                            <div class="invalid-feedback">กรุณากรอกชื่อรุ่น</div>
                                        </div>
                                    </div>

                                    <!-- รหัสรุ่น -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="model_no" class="form-label">
                                                <i class="bi bi-qr-code me-1"></i>
                                                รหัสรุ่น <span class="required">*</span>
                                            </label>
                                            <input type="text" class="form-control border-secondary" id="model_no" name="model_no"
                                                value="<?php echo htmlspecialchars($product['model_no']); ?>"
                                                placeholder="เช่น IP15P-128GB" required>
                                            <div class="invalid-feedback">กรุณากรอกรหัสรุ่น (ห้ามซ้ำ)</div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="form-group">
                                            <label for="prod_desc" class="form-label">
                                                <i class="bi bi-file-text-fill me-1"></i>
                                                คำอธิบาย
                                            </label>
                                            <textarea class="form-control border-secondary" id="prod_desc" name="prod_desc"
                                                rows="4" placeholder="รายละเอียดเพิ่มเติม (ไม่บังคับ)"><?php echo htmlspecialchars($product['prod_desc']); ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-warning mt-3" role="alert">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <b>คำเตือน:</b> การแก้ไข "รหัสสินค้า" อาจล้มเหลว หากรหัสสินค้านี้ถูกใช้งานในระบบ (เช่น ในสต็อก หรือ บิล)
                                </div>

                                <div class="btn-group-actions">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-save-fill me-2"></i>
                                        บันทึกการแก้ไข
                                    </button>
                                    <a href="product.php" class="btn btn-secondary">
                                        <i class="bi bi-x-circle-fill me-2"></i>
                                        ยกเลิก
                                    </a>
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
        //  Bootstrap Validation
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