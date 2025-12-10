<?php
session_start();
require '../config/config.php';

// รับ ID สต็อกที่ต้องการแก้ไข
$stock_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$stock_id) {
    $_SESSION['error'] = 'ไม่พบรหัสสต็อกที่ต้องการแก้ไข';
    header('Location: prod_stock.php');
    exit;
}

// ดึงข้อมูลสต็อกปัจจุบัน
$stock_sql = "SELECT 
                ps.id as stock_id,
                ps.imei,
                ps.barcode,
                ps.date_in,
                ps.date_out,
                ps.price as stock_price,
                ps.prod_image,
                ps.supplier_id,
                p.id as product_id,
                p.name as product_name,
                p.model_name,
                p.model_no,
                p.price as original_price,
                pb.name_th as brand_name,
                pt.name_th as type_name,
                pw.id as warranty_id,
                pw.start_date,
                pw.end_date,
                pw.total_warranty,
                pw.description as warranty_description,
                pw.warranty_status,
                s.sp_name as supplier_name,
                pot.id as proout_type_id
            FROM prod_stocks ps
            LEFT JOIN products p ON ps.products_id = p.id
            LEFT JOIN prod_brands pb ON p.prod_brands_id = pb.id
            LEFT JOIN prod_types pt ON p.prod_types_id = pt.id
            LEFT JOIN prod_warranty pw ON ps.prod_warranty_id = pw.id
            LEFT JOIN suppliers s ON ps.supplier_id = s.id
            LEFT JOIN proout_types pot ON ps.proout_types_id = pot.id
            WHERE ps.id = $stock_id";

$stock_result = mysqli_query($conn, $stock_sql);
$stock_data = mysqli_fetch_assoc($stock_result);

if (!$stock_data) {
    $_SESSION['error'] = 'ไม่พบข้อมูลสต็อกที่ต้องการแก้ไข';
    header('Location: prod_stock.php');
    exit;
}

// ดึงข้อมูลสำหรับ dropdown
$products_result = mysqli_query($conn, "SELECT p.*, pb.name_th as brand_name, pt.name_th as type_name 
                                      FROM products p 
                                      LEFT JOIN prod_brands pb ON p.prod_brands_id = pb.id 
                                      LEFT JOIN prod_types pt ON p.prod_types_id = pt.id 
                                      ORDER BY p.name");

$suppliers_result = mysqli_query($conn, "SELECT id, sp_name FROM suppliers ORDER BY sp_name");

$proout_types_result = mysqli_query($conn, "SELECT id, name, description FROM proout_types ORDER BY name");

// จัดการบันทึกข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_stock_id = intval($_POST['stock_id']);
    $product_id = mysqli_real_escape_string($conn, $_POST['product_id']);
    $imei = $stock_data['imei']; // ใช้ IMEI เดิม
    $date_in = mysqli_real_escape_string($conn, $_POST['date_in']);
    $date_out = !empty($_POST['date_out']) ? mysqli_real_escape_string($conn, $_POST['date_out']) : NULL;
    $stock_status = mysqli_real_escape_string($conn, $_POST['stock_status']);
    $price = floatval($_POST['price']);
    $supplier_id = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : NULL;
    $proout_type_id = !empty($_POST['proout_type_id']) ? intval($_POST['proout_type_id']) : NULL;
    $warranty_months = intval($_POST['warranty_months']);
    $warranty_description = mysqli_real_escape_string($conn, trim($_POST['warranty_description']));

    // ตรวจสอบข้อมูลที่จำเป็น
    if (empty($product_id) || $price <= 0 || $new_stock_id <= 0) {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน และราคาต้องมากกว่า 0';
    } else {
        // ตรวจสอบรหัสสต็อกซ้ำ (ยกเว้นตัวเอง)
        $check_stock_id_sql = "SELECT id FROM prod_stocks WHERE id = $new_stock_id AND id != $stock_id";
        $check_stock_result = mysqli_query($conn, $check_stock_id_sql);

        if (mysqli_num_rows($check_stock_result) > 0) {
            $_SESSION['error'] = 'รหัสสต็อกนี้มีอยู่ในระบบแล้ว';
        } else {
            // จัดการอัปโหลดรูปภาพใหม่
            $image_names = json_decode($stock_data['prod_image'], true) ?: [];

            // ลบรูปภาพที่เลือกลบ
            if (isset($_POST['removed_images'])) {
                foreach ($_POST['removed_images'] as $removed_image) {
                    $image_names = array_filter($image_names, function ($img) use ($removed_image) {
                        return $img !== $removed_image;
                    });
                    $file_path = '../uploads/products/' . $removed_image;
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                $image_names = array_values($image_names);
            }

            // เพิ่มรูปภาพใหม่
            if (isset($_FILES['prod_images'])) {
                $upload_dir = '../uploads/products/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $max_files = 6;
                $uploaded_count = count($image_names);

                foreach ($_FILES['prod_images']['tmp_name'] as $key => $tmp_name) {
                    if ($uploaded_count >= $max_files) break;

                    if ($_FILES['prod_images']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_extension = pathinfo($_FILES['prod_images']['name'][$key], PATHINFO_EXTENSION);
                        $new_filename = time() . '_' . $key . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;

                        if (move_uploaded_file($tmp_name, $upload_path)) {
                            $image_names[] = $new_filename;
                            $uploaded_count++;
                        }
                    }
                }
            }

            // เริ่ม Transaction
            mysqli_autocommit($conn, false);

            try {
                // อัปเดตข้อมูลการรับประกัน
                $warranty_desc_full = empty($warranty_description)
                    ? "การรับประกัน " . $warranty_months . " เดือน สำหรับสินค้า Stock ID: " . str_pad($new_stock_id, 6, '0', STR_PAD_LEFT)
                    : $warranty_description;

                // ตรวจสอบสถานะจาก date_out
                $is_sold = ($stock_status === 'sold' || !empty($date_out));

                if ($is_sold) {
                    // ถ้าขายแล้ว เริ่มการรับประกันทันที
                    $warranty_start = $date_out ?: date('Y-m-d');
                    $warranty_end = date('Y-m-d', strtotime($warranty_start . ' + ' . $warranty_months . ' months'));
                    $warranty_status = 'active';

                    $warranty_update_sql = "UPDATE prod_warranty SET 
                                           start_date = '$warranty_start',
                                           end_date = '$warranty_end',
                                           total_warranty = $warranty_months,
                                           description = '" . mysqli_real_escape_string($conn, $warranty_desc_full) . "',
                                           warranty_status = '$warranty_status'
                                           WHERE id = " . $stock_data['warranty_id'];
                } else {
                    // ถ้ายังไม่ขาย
                    $warranty_update_sql = "UPDATE prod_warranty SET 
                                           start_date = NULL,
                                           end_date = NULL,
                                           total_warranty = $warranty_months,
                                           description = '" . mysqli_real_escape_string($conn, $warranty_desc_full) . "',
                                           warranty_status = 'pending'
                                           WHERE id = " . $stock_data['warranty_id'];
                }

                if (!mysqli_query($conn, $warranty_update_sql)) {
                    throw new Exception('ไม่สามารถอัปเดตข้อมูลการรับประกันได้: ' . mysqli_error($conn));
                }

                // อัปเดตข้อมูลสต็อก
                $images_json = json_encode($image_names);
                $date_out_sql = $date_out ? "'$date_out'" : 'NULL';
                $supplier_sql = $supplier_id ? $supplier_id : 'NULL';
                $proout_type_sql = $proout_type_id ? $proout_type_id : 'NULL';

                $stock_update_sql = "UPDATE prod_stocks SET 
                                    id = $new_stock_id,
                                    products_id = $product_id,
                                    date_in = '$date_in',
                                    date_out = $date_out_sql,
                                    price = $price,
                                    supplier_id = $supplier_sql,
                                    proout_types_id = $proout_type_sql,
                                    prod_image = '$images_json'
                                    WHERE id = $stock_id";

                if (!mysqli_query($conn, $stock_update_sql)) {
                    throw new Exception('ไม่สามารถอัปเดตข้อมูลสต็อกได้: ' . mysqli_error($conn));
                }

                mysqli_commit($conn);

                // สร้างข้อความแจ้งเตือน
                if ($is_sold) {
                    $_SESSION['success'] = 'อัปเดตข้อมูลสต็อกสำเร็จ - การรับประกันเริ่มต้นแล้ว';
                } else {
                    $_SESSION['success'] = 'อัปเดตข้อมูลสต็อกสำเร็จ - การรับประกันยังไม่เริ่ม (รอการขาย)';
                }

                header('Location: prod_stock.php');
                exit;
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $_SESSION['error'] = $e->getMessage();
            }

            mysqli_autocommit($conn, true);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขสินค้าในสต็อก - ระบบจัดการร้านค้ามือถือ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Prompt', sans-serif;
            min-height: 100vh;
        }

        .main-header {
            background-color: #198754;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .main-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="50" height="50" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        }

        .main-header h1 {
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            font-weight: 700;
            margin-bottom: 0;
        }

        .form-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            padding: 25px;
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
        }

        .form-control:read-only {
            background-color: #f8f9fa;
            opacity: 0.8;
            border-color: #dee2e6;
        }

        .btn-success {
            background-color: #198754;
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            background-color: #157347;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(25, 135, 84, 0.3);
        }

        .btn-secondary {
            background-color: #6c757d;
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background-color: #5c636a;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
        }

        .btn-danger {
            background-color: #dc3545;
            border: none;
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-danger:hover {
            background-color: #c82333;
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }

        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .label-col {
            width: 150px;
            font-weight: 500;
            vertical-align: top;
            padding-top: 8px;
            color: #444;
        }

        .current-images {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .image-item {
            position: relative;
            display: inline-block;
        }

        .image-item img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e9ecef;
        }

        .remove-image {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            font-size: 12px;
            cursor: pointer;
        }

        .image-preview {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            min-height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .image-preview:hover {
            border-color: #198754;
            background-color: #f8f9fa;
        }

        .warranty-info {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 10px;
            margin-top: 5px;
            font-size: 0.9em;
            color: #856404;
        }

        .warranty-active {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .warranty-pending {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }

        @media (max-width: 768px) {
            .label-col {
                display: block;
                width: 100% !important;
                margin-bottom: 5px;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="main-header">
        <div class="container">
            <h1>
                <i class="fas fa-edit me-3"></i>
                แก้ไขสินค้าในสต็อก
                <small class="fs-6 opacity-75 d-block">รหัสสต็อก: <?= str_pad($stock_data['stock_id'], 6, '0', STR_PAD_LEFT) ?></small>
            </h1>
        </div>
    </div>

    <div class="container">
        <!-- แสดงข้อความแจ้งเตือน -->
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

        <form method="POST" enctype="multipart/form-data" id="editStockForm">
            <!-- ข้อมูลพื้นฐาน -->
            <div class="form-section">
                <h5><i class="fas fa-box me-2"></i>ข้อมูลพื้นฐาน</h5>
                <table style="width: 100%;">
                    <tr>
                        <td class="label-col">รหัสสต็อก <span class="text-danger">*</span></td>
                        <td>
                            <input type="number" class="form-control" name="stock_id" id="stock_id"
                                value="<?= $stock_data['stock_id'] ?>"
                                min="1" max="999999" required style="width: 200px;">
                            <small class="text-muted">รหัสสต็อกต้องไม่ซ้ำกับที่มีอยู่ในระบบ</small>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-col">สินค้า <span class="text-danger">*</span></td>
                        <td>
                            <?php
                            mysqli_data_seek($products_result, 0);
                            while ($product = mysqli_fetch_assoc($products_result)) {
                                if ($product['id'] == $stock_data['product_id']) {
                                    echo '<div class="form-control" readonly style="width: 300px;">' .
                                        htmlspecialchars($product['name']) . ' ' .
                                        htmlspecialchars($product['brand_name']) . ' (' .
                                        htmlspecialchars($product['model_name']) . ') 
                                        </div>';
                                    break;
                                }
                            }
                            ?>
                            <input type="hidden" name="product_id" value="<?= $stock_data['product_id'] ?>">
                            <small class="text-muted">สินค้าไม่สามารถแก้ไขได้</small>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-col">IMEI</td>
                        <td>
                            <input type="text" class="form-control" name="imei" id="imei"
                                value="<?= htmlspecialchars($stock_data['imei']) ?>"
                                maxlength="25" readonly style="width: 300px;">
                            <small class="text-muted">IMEI ไม่สามารถแก้ไขได้</small>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-col">บาร์โค้ด</td>
                        <td>
                            <input type="text" class="form-control" name="barcode" id="barcode"
                                value="<?= htmlspecialchars($stock_data['barcode']) ?>"
                                maxlength="25" readonly style="width: 300px;">
                            <small class="text-muted">บาร์โค้ดไม่สามารถแก้ไขได้</small>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-col">วันที่เข้าสต็อก <span class="text-danger">*</span></td>
                        <td>
                            <input type="date" class="form-control" name="date_in" id="date_in"
                                value="<?= $stock_data['date_in'] ?>" required style="width: 200px;">
                        </td>
                    </tr>
                    <tr>
                        <td class="label-col">วันที่นำออก</td>
                        <td>
                            <input type="date" class="form-control" name="date_out" id="date_out"
                                value="<?= $stock_data['date_out'] ?>" style="width: 200px;">
                            <small class="text-muted">เฉพาะเมื่อสินค้าขายแล้วหรือนำออกจากสต็อก</small>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-col">สถานะ <span class="text-danger">*</span></td>
                        <td>
                            <select class="form-select" name="stock_status" id="stock_status" required style="width: 200px;">
                                <option value="available" <?= empty($stock_data['date_out']) ? 'selected' : '' ?>>พร้อมขาย</option>
                                <option value="sold" <?= !empty($stock_data['date_out']) ? 'selected' : '' ?>>ขายแล้ว</option>
                            </select>
                            <small class="text-muted">การเปลี่ยนสถานะจะส่งผลต่อการรับประกัน</small>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-col">ราคาขาย <span class="text-danger">*</span></td>
                        <td>
                            <div class="input-group" style="width: 250px;">
                                <span class="input-group-text">฿</span>
                                <input type="number" class="form-control" name="price" id="price"
                                    step="0.01" min="0.01" value="<?= $stock_data['stock_price'] ?>" required>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-col">ผู้จำหน่าย</td>
                        <td>
                            <select class="form-select" name="supplier_id" id="supplier_id" style="width: 300px;">
                                <option value="">-- เลือกผู้จำหน่าย --</option>
                                <?php mysqli_data_seek($suppliers_result, 0); ?>
                                <?php while ($supplier = mysqli_fetch_assoc($suppliers_result)): ?>
                                    <option value="<?= $supplier['id'] ?>"
                                        <?= $supplier['id'] == $stock_data['supplier_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($supplier['sp_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-col">ประเภทการนำออก</td>
                        <td>
                            <select class="form-select" name="proout_type_id" id="proout_type_id" style="width: 300px;">
                                <option value="">-- เลือกประเภทการนำออก --</option>
                                <?php while ($proout_type = mysqli_fetch_assoc($proout_types_result)): ?>
                                    <option value="<?= $proout_type['id'] ?>"
                                        <?= $proout_type['id'] == $stock_data['proout_type_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($proout_type['name']) ?>
                                        <?= !empty($proout_type['description']) ? ' - ' . htmlspecialchars($proout_type['description']) : '' ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ข้อมูลการรับประกัน -->
            <div class="form-section">
                <h5><i class="fas fa-shield-alt me-2"></i>ข้อมูลการรับประกัน</h5>
                <table style="width: 100%;">
                    <tr>
                        <td class="label-col">ระยะเวลาการรับประกัน <span class="text-danger">*</span></td>
                        <td>
                            <div class="input-group" style="width: 200px;">
                                <input type="number" class="form-control" name="warranty_months" id="warranty_months"
                                    min="0" max="60" value="<?= $stock_data['total_warranty'] ?>" required>
                                <span class="input-group-text">เดือน</span>
                            </div>
                            <div class="warranty-info" id="warrantyInfo">
                                <?php if ($stock_data['warranty_status'] === 'active'): ?>
                                    <div class="warranty-active">
                                        <i class="fas fa-check-circle me-1"></i>
                                        การรับประกัน <?= $stock_data['total_warranty'] ?> เดือน -
                                        เริ่ม: <?= date('d/m/Y', strtotime($stock_data['start_date'])) ?>
                                        หมดอายุ: <?= date('d/m/Y', strtotime($stock_data['end_date'])) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="warranty-pending">
                                        <i class="fas fa-clock me-1"></i>
                                        การรับประกัน <?= $stock_data['total_warranty'] ?> เดือน -
                                        <strong>รอการขาย (ยังไม่เริ่มการรับประกัน)</strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-col">รายละเอียดการรับประกัน</td>
                        <td>
                            <textarea class="form-control" name="warranty_description" id="warranty_description"
                                rows="4" placeholder="กรอกรายละเอียดเงื่อนไขการรับประกัน..."
                                style="width: 500px;" maxlength="500"><?= htmlspecialchars($stock_data['warranty_description']) ?></textarea>
                            <small class="text-muted">
                                <span id="descriptionCount"><?= strlen($stock_data['warranty_description']) ?></span>/500 ตัวอักษร
                            </small>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- รูปภาพสินค้า -->
            <div class="form-section">
                <h5><i class="fas fa-camera me-2"></i>รูปภาพสินค้า</h5>

                <!-- รูปภาพปัจจุบัน -->
                <?php
                $current_images = json_decode($stock_data['prod_image'], true) ?: [];
                if (!empty($current_images)):
                ?>
                    <div class="mb-3">
                        <label class="form-label">รูปภาพปัจจุบัน:</label>
                        <div class="current-images" id="currentImages">
                            <?php foreach ($current_images as $index => $image): ?>
                                <div class="image-item" data-image="<?= htmlspecialchars($image) ?>">
                                    <img src="../uploads/products/<?= htmlspecialchars($image) ?>" alt="Product Image">
                                    <button type="button" class="remove-image" onclick="removeCurrentImage(this, '<?= htmlspecialchars($image) ?>')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- เพิ่มรูปภาพใหม่ -->
                <div class="mb-3">
                    <label class="form-label">เพิ่มรูปภาพใหม่ (สูงสุด 6 รูปรวม):</label>
                    <div class="image-preview" onclick="document.getElementById('prod_images').click()">
                        <div id="imagePreview">
                            <i class="fas fa-camera fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">คลิกเพื่อเลือกรูปภาพ</p>
                            <small class="text-muted">รองรับไฟล์: JPG, PNG, GIF</small>
                        </div>
                        <div id="selectedImages" class="current-images"></div>
                    </div>
                    <input type="file" class="form-control d-none" name="prod_images[]" id="prod_images" accept="image/*" multiple onchange="previewNewImages(this)">
                </div>
            </div>

            <!-- ปุ่มบันทึก -->
            <div class="text-end mb-4">
                <button type="submit" class="btn btn-success me-2" id="submitBtn">
                    <i class="fas fa-save me-2"></i>บันทึกการแก้ไข
                </button>
                <a href="prod_stock.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>ย้อนกลับ
                </a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let removedImages = [];

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateWarrantyDescription();

            // เพิ่ม event listener
            document.getElementById('warranty_months').addEventListener('input', updateWarrantyInfo);
            document.getElementById('warranty_description').addEventListener('input', updateWarrantyDescription);
            document.getElementById('stock_status').addEventListener('change', updateWarrantyAndDate);
        });

        // อัปเดตข้อมูลการรับประกันตามสถานะ
        function updateWarrantyInfo() {
            const months = parseInt(document.getElementById('warranty_months').value) || 0;
            const stockStatus = document.getElementById('stock_status').value;
            const info = document.getElementById('warrantyInfo');

            if (months > 0) {
                if (stockStatus === 'sold') {
                    info.innerHTML = `
                        <div class="warranty-active">
                            <i class="fas fa-check-circle me-1"></i>
                            การรับประกัน ${months} เดือน - <strong>เริ่มการรับประกันเมื่อบันทึก</strong>
                        </div>
                    `;
                } else {
                    info.innerHTML = `
                        <div class="warranty-pending">
                            <i class="fas fa-clock me-1"></i>
                            การรับประกัน ${months} เดือน - <strong>รอการขาย (ยังไม่เริ่มการรับประกัน)</strong>
                        </div>
                    `;
                }
            } else {
                info.innerHTML = `
                    <div class="warranty-info">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        ไม่มีการรับประกัน
                    </div>
                `;
            }
        }

        // อัปเดตรายละเอียดการรับประกัน
        function updateWarrantyDescription() {
            const textarea = document.getElementById('warranty_description');
            const counter = document.getElementById('descriptionCount');

            const currentLength = textarea.value.length;
            counter.textContent = currentLength;

            if (currentLength > 400) {
                counter.style.color = '#dc3545';
            } else if (currentLength > 300) {
                counter.style.color = '#ffc107';
            } else {
                counter.style.color = '#6c757d';
            }
        }

        // อัปเดตวันที่นำออกและการรับประกันตามสถานะ
        function updateWarrantyAndDate() {
            const status = document.getElementById('stock_status').value;
            const dateOut = document.getElementById('date_out');

            if (status === 'sold' && !dateOut.value) {
                dateOut.value = new Date().toISOString().split('T')[0];
            } else if (status === 'available') {
                dateOut.value = '';
            }

            updateWarrantyInfo();
        }

        // ลบรูปภาพปัจจุบัน
        function removeCurrentImage(button, imageName) {
            const imageItem = button.parentElement;
            imageItem.style.opacity = '0';
            imageItem.style.transform = 'scale(0.8)';

            setTimeout(() => {
                imageItem.remove();
                removedImages.push(imageName);

                const form = document.getElementById('editStockForm');
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'removed_images[]';
                hiddenInput.value = imageName;
                form.appendChild(hiddenInput);
            }, 300);
        }

        // Preview รูปภาพใหม่
        function previewNewImages(input) {
            const files = Array.from(input.files);
            const currentImagesCount = document.querySelectorAll('#currentImages .image-item').length;
            const maxFiles = 6 - currentImagesCount;

            if (files.length > maxFiles) {
                alert(`สามารถเพิ่มได้สูงสุด ${maxFiles} รูป (รวมรูปเดิม ${currentImagesCount} รูป)`);
                const dt = new DataTransfer();
                files.slice(0, maxFiles).forEach(file => dt.items.add(file));
                input.files = dt.files;
            }

            const container = document.getElementById('selectedImages');
            const preview = document.getElementById('imagePreview');

            container.innerHTML = '';
            const finalFiles = Array.from(input.files);
            preview.style.display = finalFiles.length > 0 ? 'none' : 'block';

            finalFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imageItem = document.createElement('div');
                    imageItem.className = 'image-item';
                    imageItem.innerHTML = `
                        <img src="${e.target.result}" alt="New Image">
                        <button type="button" class="remove-image" onclick="removeNewImage(this, ${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    container.appendChild(imageItem);
                }
                reader.readAsDataURL(file);
            });
        }

        // ลบรูปภาพใหม่ที่เลือก
        function removeNewImage(button, index) {
            const imageItem = button.parentElement;
            const input = document.getElementById('prod_images');
            const dt = new DataTransfer();

            Array.from(input.files).forEach((file, i) => {
                if (i !== index) dt.items.add(file);
            });

            input.files = dt.files;
            imageItem.remove();

            previewNewImages(input);
        }

        // Form validation และ submission
        document.getElementById('editStockForm').addEventListener('submit', function(e) {
            let isValid = true;

            const requiredFields = ['stock_id', 'product_id', 'date_in', 'stock_status', 'warranty_months', 'price'];
            requiredFields.forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (!field.value.trim() ||
                    (fieldName === 'price' && parseFloat(field.value) <= 0) ||
                    (fieldName === 'stock_id' && parseInt(field.value) <= 0)) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            if (!isValid) {
                e.preventDefault();
                const firstError = document.querySelector('.is-invalid');
                if (firstError) {
                    firstError.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    firstError.focus();
                }
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            const stockId = document.getElementById('stock_id').value;
            const stockStatus = document.getElementById('stock_status').value;
            const productName = document.getElementById('product_id').options[document.getElementById('product_id').selectedIndex].text;
            const imei = document.getElementById('imei').value;
            const warrantyMonths = document.getElementById('warranty_months').value;

            let warrantyStatusText = '';
            if (stockStatus === 'sold') {
                warrantyStatusText = 'จะเริ่มการรับประกันทันที';
            } else {
                warrantyStatusText = 'ยังไม่เริ่มการรับประกัน (รอการขาย)';
            }

            const confirmation = confirm(
                `ยืนยันการแก้ไขข้อมูลสต็อก:\n` +
                `รหัสสต็อกใหม่: ${stockId.padStart(6, '0')}\n` +
                `สินค้า: ${productName.split(' - ')[0]}\n` +
                `IMEI: ${imei} (ไม่เปลี่ยนแปลง)\n` +
                `สถานะ: ${stockStatus === 'available' ? 'พร้อมขาย' : 'ขายแล้ว'}\n` +
                `ราคา: ฿${parseFloat(document.getElementById('price').value).toLocaleString()}\n` +
                `การรับประกัน: ${warrantyMonths} เดือน (${warrantyStatusText})\n\n` +
                `ต้องการบันทึกการแก้ไขหรือไม่?`
            );

            if (!confirmation) {
                e.preventDefault();
                return;
            }

            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังบันทึก...';
            submitBtn.disabled = true;
        });

        // ลบ error class เมื่อผู้ใช้เริ่มกรอกข้อมูลใหม่
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('is-invalid')) {
                e.target.classList.remove('is-invalid');
            }
        });

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('is-invalid')) {
                e.target.classList.remove('is-invalid');
            }
        });
    </script>
</body>

</html>