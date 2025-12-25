<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_product');
require '../config/load_theme.php';

// [1] รับค่าพื้นฐานจาก Session
$shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// [2] ตรวจสอบสิทธิ์ Admin เพื่อกำหนดปลายทางข้อมูล (Target Shop ID)
$is_super_admin = false;
$check_admin_sql = "SELECT r.role_name FROM roles r 
                    JOIN user_roles ur ON r.role_id = ur.roles_role_id 
                    WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt_admin = $conn->prepare($check_admin_sql)) {
    $stmt_admin->bind_param("i", $current_user_id);
    $stmt_admin->execute();
    if ($stmt_admin->get_result()->num_rows > 0) {
        $is_super_admin = true;
    }
    $stmt_admin->close();
}

// *** หัวใจสำคัญ: ถ้าเป็น Admin ให้บันทึกเข้า '0' (ส่วนกลาง), ถ้าไม่ใช่ให้บันทึกเข้า Shop ID ตัวเอง ***
$save_as_shop_id = $is_super_admin ? 0 : $shop_id;

// ฟังก์ชันหา prod_id ถัดไป (Manual Increment)
function getNextProductId($conn) {
    $sql = "SELECT IFNULL(MAX(prod_id), 100000) + 1 as next_id FROM products";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['next_id'];
}

// [3] ดึงข้อมูล Brands และ Types (ให้เห็นของตัวเอง + ของส่วนกลาง)
$brands_query = "SELECT brand_id, brand_name_th FROM prod_brands 
                 WHERE shop_info_shop_id = '$shop_id' OR shop_info_shop_id = 0 
                 ORDER BY brand_name_th";
$brands_result = mysqli_query($conn, $brands_query);

$types_query = "SELECT type_id, type_name_th FROM prod_types 
                WHERE shop_info_shop_id = '$shop_id' OR shop_info_shop_id = 0 
                ORDER BY type_name_th";
$types_result = mysqli_query($conn, $types_query);

// --- ส่วนประมวลผลฟอร์ม (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $prod_code = trim($_POST['prod_code']); // รหัสที่ร้านตั้งเอง
    $prod_name = trim($_POST['prod_name']);
    $prod_brands_brand_id = (int)$_POST['prod_brands_brand_id'];
    $prod_types_type_id = (int)$_POST['prod_types_type_id'];
    $model_name = trim($_POST['model_name']);
    $model_no = trim($_POST['model_no']);
    $prod_desc = trim($_POST['prod_desc']);
    $prod_price = (float)$_POST['prod_price'];

    $errors = [];
    
    // Validation
    if (empty($prod_code)) $errors[] = "กรุณากรอกรหัสสินค้า (Product Code)";
    if (empty($prod_name)) $errors[] = "กรุณากรอกชื่อสินค้า";
    if (empty($prod_brands_brand_id)) $errors[] = "กรุณาเลือกยี่ห้อ";
    if (empty($prod_types_type_id)) $errors[] = "กรุณาเลือกประเภทสินค้า";
    if (empty($model_name)) $errors[] = "กรุณากรอกชื่อรุ่น";
    if ($prod_price < 0) $errors[] = "ราคาห้ามติดลบ";

    // [4] ตรวจสอบรหัสซ้ำ (Check Duplicate)
    if (empty($errors)) {
        // 4.1 เช็ค prod_code ซ้ำ (เฉพาะใน Scope ที่เราจะบันทึก)
        $check_code_sql = "SELECT prod_id FROM products WHERE prod_code = ? AND shop_info_shop_id = ?";
        $stmt_check = $conn->prepare($check_code_sql);
        $stmt_check->bind_param("si", $prod_code, $save_as_shop_id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $scope_msg = $is_super_admin ? "ระบบส่วนกลาง" : "ร้านของคุณ";
            $errors[] = "รหัสสินค้า '$prod_code' มีอยู่แล้วใน$scope_msg";
        }
        $stmt_check->close();
    }

    // [5] บันทึกข้อมูล (INSERT)
    if (empty($errors)) {
        // หา ID ถัดไป
        $prod_id = getNextProductId($conn);

        $sql = "INSERT INTO products (prod_id, prod_code, prod_name, prod_brands_brand_id, prod_types_type_id, model_name, model_no, prod_desc, prod_price, shop_info_shop_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        // Param types: i=int, s=string, d=double
        $stmt->bind_param(
            "issiisssdi",
            $prod_id,
            $prod_code,
            $prod_name,
            $prod_brands_brand_id,
            $prod_types_type_id,
            $model_name,
            $model_no,
            $prod_desc,
            $prod_price,
            $save_as_shop_id // <-- บันทึกเป็น 0 หรือ ID ร้าน ตามสิทธิ์
        );

        if ($stmt->execute()) {
            $_SESSION['success'] = "เพิ่มสินค้าเรียบร้อย " . ($is_super_admin ? "(บันทึกเป็นสินค้าส่วนกลาง)" : "");
            header('Location: product.php');
            exit();
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['errors'] = $errors;
    }
    // หากมี Error ให้เด้งกลับมาหน้าเดิม
    header('Location: add_product.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มสินค้าใหม่ - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: <?= $background_color ?>; font-family: '<?= $font_style ?>', sans-serif; color: <?= $text_color ?>; }
        .main-card { border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden; }
        .card-header-custom { background: linear-gradient(135deg, #198754 0%, #14532d 100%); padding: 1.5rem; color: white; }
        .form-label { font-weight: 600; color: #4b5563; }
        .form-control:focus, .form-select:focus { border-color: #198754; box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.1); }
        .badge-admin-mode { background-color: #ffc107; color: #000; font-weight: bold; padding: 0.5em 1em; border-radius: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-lg-9">
                        
                        <?php if ($is_super_admin): ?>
                        <div class="text-center mb-3">
                            <span class="badge-admin-mode">
                                <i class="bi bi-globe2 me-2"></i>คุณกำลังเพิ่มสินค้าในฐานะ "ผู้ดูแลระบบ (ส่วนกลาง)"
                            </span>
                        </div>
                        <?php endif; ?>

                        <div class="main-card card shadow-sm">
                            <div class="card-header-custom">
                                <h4 class="mb-0"><i class="bi bi-plus-circle-fill me-2"></i>เพิ่มรายการสินค้าใหม่</h4>
                            </div>
                            <div class="card-body p-4 p-md-5">
                                
                                <?php if (isset($_SESSION['errors'])): ?>
                                    <div class="alert alert-danger border-0 shadow-sm mb-4">
                                        <ul class="mb-0 ps-3">
                                            <?php foreach ($_SESSION['errors'] as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <?php unset($_SESSION['errors']); ?>
                                <?php endif; ?>

                                <form method="POST" action="add_product.php" class="needs-validation" novalidate>
                                    <div class="row g-4">
                                        
                                        <div class="col-md-6">
                                            <label for="prod_code" class="form-label">รหัสสินค้า (กำหนดเอง) <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-qr-code"></i></span>
                                                <input type="text" class="form-control border-start-0" id="prod_code" name="prod_code" required 
                                                       placeholder="เช่น IP16-PRO-BLK" value="<?= htmlspecialchars($_POST['prod_code'] ?? '') ?>">
                                            </div>
                                            <small class="text-muted">รหัสสำหรับร้านค้าใช้เรียกสินค้า (ไม่ซ้ำกันในร้าน)</small>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="prod_name" class="form-label">ชื่อสินค้า <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="prod_name" name="prod_name" required 
                                                   placeholder="ชื่อที่แสดงบนบิลขาย" value="<?= htmlspecialchars($_POST['prod_name'] ?? '') ?>">
                                        </div>

                                        <div class="col-md-6">
                                            <label for="prod_brands_brand_id" class="form-label">ยี่ห้อ</label>
                                            <select class="form-select" id="prod_brands_brand_id" name="prod_brands_brand_id" required>
                                                <option value="">-- เลือกยี่ห้อ --</option>
                                                <?php while ($b = mysqli_fetch_assoc($brands_result)): ?>
                                                    <option value="<?= $b['brand_id'] ?>"><?= htmlspecialchars($b['brand_name_th']) ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="prod_types_type_id" class="form-label">ประเภทสินค้า</label>
                                            <select class="form-select" id="prod_types_type_id" name="prod_types_type_id" required>
                                                <option value="">-- เลือกประเภท --</option>
                                                <?php while ($t = mysqli_fetch_assoc($types_result)): ?>
                                                    <option value="<?= $t['type_id'] ?>"><?= htmlspecialchars($t['type_name_th']) ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="model_name" class="form-label">ชื่อรุ่น (Model Name)</label>
                                            <input type="text" class="form-control" id="model_name" name="model_name" required 
                                                   placeholder="เช่น iPhone 16 Pro Max">
                                        </div>

                                        <div class="col-md-6">
                                            <label for="model_no" class="form-label">หมายเลขรุ่น (Model No.)</label>
                                            <input type="text" class="form-control" id="model_no" name="model_no" 
                                                   placeholder="เช่น A3296">
                                        </div>

                                        <div class="col-md-6">
                                            <label for="prod_price" class="form-label">ราคาสินค้ามาตรฐาน (บาท)</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light">฿</span>
                                                <input type="number" class="form-control" id="prod_price" name="prod_price" step="0.01" min="0" required 
                                                       placeholder="0.00">
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <label for="prod_desc" class="form-label">รายละเอียดสินค้า</label>
                                            <textarea class="form-control" id="prod_desc" name="prod_desc" rows="3" 
                                                      placeholder="รายละเอียดเพิ่มเติม..."></textarea>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-5">
                                        <a href="product.php" class="btn btn-light rounded-pill px-4">
                                            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
                                        </a>
                                        <button type="submit" class="btn btn-success rounded-pill px-5 shadow-sm fw-bold">
                                            <i class="bi bi-save2-fill me-2"></i> บันทึกข้อมูล
                                        </button>
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
    <script>
        // Validation UI
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