<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'edit_stock');

// -----------------------------------------------------------------------------
// 1. VALIDATE & GET STOCK ID
// -----------------------------------------------------------------------------
$stock_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$stock_id) {
    $_SESSION['error'] = 'ไม่พบรหัสสต็อกที่ต้องการแก้ไข';
    header('Location: prod_stock.php');
    exit;
}

// -----------------------------------------------------------------------------
// 2. GET DATA (สำหรับแสดงฟอร์ม)
// -----------------------------------------------------------------------------
$stock_sql = "SELECT 
                ps.stock_id,
                ps.serial_no,
                ps.price as stock_price,
                ps.stock_status,
                ps.image_path,
                ps.create_at as date_in,
                ps.products_prod_id,
                p.prod_name,
                p.model_name,
                pb.brand_name_th as brand_name
            FROM prod_stocks ps
            LEFT JOIN products p ON ps.products_prod_id = p.prod_id
            LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id
            WHERE ps.stock_id = $stock_id";

$stock_result = mysqli_query($conn, $stock_sql);
$stock_data = mysqli_fetch_assoc($stock_result);

if (!$stock_data) {
    $_SESSION['error'] = 'ไม่พบข้อมูลสต็อกที่ต้องการแก้ไข';
    header('Location: prod_stock.php');
    exit;
}

// (สำคัญ!) ตรวจสอบว่า "ขายแล้ว" หรือยัง
$is_sold = ($stock_data['stock_status'] === 'Sold');

// (ดึงข้อมูล Dropdown สถานะ - ไม่รวม Sold)
$status_options = ['In Stock', 'Damage', 'Reserved', 'Repair'];

// -----------------------------------------------------------------------------
// 3. SHARED FUNCTIONS
// -----------------------------------------------------------------------------

function checkSerialExists($conn, $serial, $exclude_id)
{
    $sql = "SELECT stock_id FROM prod_stocks WHERE serial_no = ? AND stock_id != ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $serial, $exclude_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

function getNextMovementId($conn)
{
    $move_sql = "SELECT IFNULL(MAX(movement_id), 0) + 1 as next_move_id FROM stock_movements";
    $move_result = mysqli_query($conn, $move_sql);
    return mysqli_fetch_assoc($move_result)['next_move_id'];
}

// -----------------------------------------------------------------------------
// 4. AJAX HANDLER (เช็ค Serial ซ้ำ)
// -----------------------------------------------------------------------------
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'check_serial':
            $serial = mysqli_real_escape_string($conn, $_POST['serial_no']);
            $exclude_id = (int)$_POST['stock_id'];
            echo json_encode([
                'success' => true,
                'exists' => checkSerialExists($conn, $serial, $exclude_id)
            ]);
            exit;
    }
}

// -----------------------------------------------------------------------------
// 5. POST HANDLER (บันทึกการแก้ไข)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // (ป้องกันการแก้ไขของที่ขายแล้ว)
    if ($is_sold) {
        $_SESSION['error'] = 'ไม่สามารถแก้ไขสินค้าที่ขายไปแล้ว (สถานะ Sold)';
        header('Location: edit_stock.php?id=' . $stock_id);
        exit;
    }

    // (รับค่า)
    $serial_no = mysqli_real_escape_string($conn, trim($_POST['serial_no']));
    $price = floatval($_POST['price']);
    $stock_status = mysqli_real_escape_string($conn, $_POST['stock_status']);

    $current_image = $stock_data['image_path'];
    $delete_image = isset($_POST['delete_image']) ? (int)$_POST['delete_image'] : 0;
    $new_image_name = $current_image;

    // (Validate)
    if (empty($serial_no) || $price <= 0 || empty($stock_status)) {
        $_SESSION['error'] = 'กรุณากรอกข้อมูล (Serial, ราคา, สถานะ) ให้ครบถ้วน';
        header('Location: edit_stock.php?id=' . $stock_id);
        exit;
    }
    if (checkSerialExists($conn, $serial_no, $stock_id)) {
        $_SESSION['error'] = "Serial Number: $serial_no นี้มีอยู่ในระบบแล้ว";
        header('Location: edit_stock.php?id=' . $stock_id);
        exit;
    }

    mysqli_autocommit($conn, false);

    try {
        // (A) --- จัดการรูปภาพ ---

        // (A.1 - ถ้ามีการอัปโหลดรูปใหม่)
        if (isset($_FILES['new_image']) && $_FILES['new_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // (ลบรูปเก่า ถ้ามี)
            if (!empty($current_image) && file_exists($upload_dir . $current_image)) {
                unlink($upload_dir . $current_image);
            }

            // (อัปโหลดรูปใหม่)
            $tmp_name = $_FILES['new_image']['tmp_name'];
            $file_extension = pathinfo($_FILES['new_image']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid('stock_', true) . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($tmp_name, $upload_path)) {
                $new_image_name = $new_filename;
            } else {
                throw new Exception('ไม่สามารถอัปโหลดรูปภาพใหม่ได้');
            }

            // (A.2 - ถ้าติ๊กลบรูป (โดยไม่อัปใหม่))
        } elseif ($delete_image == 1 && !empty($current_image)) {
            $upload_dir = '../uploads/products/';
            if (file_exists($upload_dir . $current_image)) {
                unlink($upload_dir . $current_image);
            }
            $new_image_name = NULL;
        }

        // (B) --- อัปเดตตาราง prod_stocks ---
        $sql_update = "UPDATE prod_stocks SET 
                        serial_no = ?,
                        price = ?,
                        stock_status = ?,
                        image_path = ?
                       WHERE stock_id = ?";

        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param(
            "sdssi",
            $serial_no,
            $price,
            $stock_status,
            $new_image_name,
            $stock_id
        );

        if (!$stmt_update->execute()) {
            throw new Exception("ไม่สามารถอัปเดตสต็อก: " . $stmt_update->error);
        }
        $stmt_update->close();

        // (C) --- บันทึกการเคลื่อนไหว (ถ้าสถานะเปลี่ยน) ---
        if ($stock_status != $stock_data['stock_status']) {
            $move_id = getNextMovementId($conn);
            $ref_table = 'EDIT_STOCK_STATUS'; // (เหตุผล)
            $comment = "เปลี่ยนสถานะจาก: {$stock_data['stock_status']} เป็น: $stock_status";

            // (เราจะใช้ ref_table เก็บเหตุผล และ ref_id เก็บ comment (แม้จะไม่ตรงหลัก DB แต่สะดวก))
            $move_stmt = $conn->prepare(
                "INSERT INTO stock_movements 
                    (movement_id, movement_type, ref_table, prod_stocks_stock_id, create_at) 
                 VALUES (?, 'ADJUST', ?, ?, NOW())"
            );
            $move_stmt->bind_param("isi", $move_id, $ref_table, $stock_id);
            if (!$move_stmt->execute()) {
                throw new Exception("ไม่สามารถบันทึก Movement การแก้ไขได้: " . $move_stmt->error);
            }
            $move_stmt->close();
        }

        // (D) --- Commit ---
        mysqli_commit($conn);
        $_SESSION['success'] = "แก้ไขข้อมูลสต็อก #$stock_id สำเร็จ";
        header('Location: prod_stock.php');
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }

    mysqli_autocommit($conn, true);
    header('Location: edit_stock.php?id=' . $stock_id);
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขสต็อก #<?= $stock_data['stock_id'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <?php require '../config/load_theme.php'; ?>

    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            color: <?= $text_color ?>;
        }

        .container {
            max-width: 900px;
        }

        h4 {
            font-weight: 700;
            color: <?= $theme_color ?>;
        }

        h5 {
            font-weight: 600;
            color: <?= $theme_color ?>;
        }

        .form-section {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .form-control,
        .form-select {
            font-size: 14px;
            padding: 8px 12px;
            border-radius: 6px;
        }

        .form-control[readonly] {
            background-color: #e9ecef;
        }

        .btn-success {
            background-color: <?= $btn_add_color ?>;
            border-color: <?= $btn_add_color ?>;
        }

        .btn-warning {
            background-color: <?= $btn_edit_color ?>;
            color: white;
        }

        table {
            width: 100%;
        }

        .label-col {
            width: 180px;
            font-weight: 500;
            vertical-align: top;
            padding-top: 8px;
            color: #444;
        }

        .image-preview-box {
            position: relative;
            width: 150px;
            height: 150px;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            overflow: hidden;
            cursor: pointer;
        }

        .image-preview-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .image-preview-text {
            color: #6c757d;
            text-align: center;
        }

        .error-feedback {
            color: #dc3545;
            font-size: 0.875em;
            margin-top: 5px;
            display: none;
        }

        .is-invalid+.error-feedback,
        .is-invalid~.error-feedback {
            display: block;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container my-4">

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?= $_SESSION['success'];
                            unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?= $_SESSION['error'];
                            unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <h4 class="mb-4"><i class="fas fa-edit me-2"></i>แก้ไขสินค้าในสต็อก</h4>

                    <?php if ($is_sold): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-lock me-2"></i>
                            <strong>ไม่สามารถแก้ไขได้:</strong> สินค้านี้ (สถานะ: Sold) ถูกขายไปแล้ว จึงไม่สามารถแก้ไขข้อมูลได้
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="editStockForm" novalidate>
                        <div class="form-section">
                            <h5>ข้อมูลสินค้า (ไม่สามารถแก้ไขได้)</h5>
                            <table>
                                <tr>
                                    <td class="label-col">รหัสสต็อก</td>
                                    <td>
                                        <input type="text" class="form-control"
                                            value="<?= htmlspecialchars($stock_data['stock_id']) ?>" readonly>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col">สินค้า</td>
                                    <td>
                                        <input type="text" class="form-control"
                                            value="<?= htmlspecialchars($stock_data['prod_name']) ?> (<?= htmlspecialchars($stock_data['brand_name']) ?>)" readonly>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="form-section">
                            <h5>ข้อมูลสต็อก (แก้ไขได้)</h5>
                            <table>
                                <tr>
                                    <td class="label-col">Serial Number <span class="text-danger">*</span></td>
                                    <td>
                                        <input type="text" class="form-control serial-input" name="serial_no"
                                            value="<?= htmlspecialchars($stock_data['serial_no']) ?>"
                                            maxlength="50" required <?= $is_sold ? 'readonly' : '' ?>>
                                        <div class="error-feedback">Serial Number นี้มีในระบบแล้ว</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col">ราคาขาย <span class="text-danger">*</span></td>
                                    <td>
                                        <div class="input-group" style="width: 250px;">
                                            <span class="input-group-text">฿</span>
                                            <input type="number" class="form-control" name="price" id="price"
                                                step="0.01" min="0.01"
                                                value="<?= htmlspecialchars($stock_data['stock_price']) ?>" required <?= $is_sold ? 'readonly' : '' ?>>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col">สถานะ <span class="text-danger">*</span></td>
                                    <td>
                                        <?php if ($is_sold): ?>
                                            <input type="text" class="form-control"
                                                value="<?= htmlspecialchars($stock_data['stock_status']) ?>" readonly style="width: 250px;">
                                            <input type="hidden" name="stock_status" value="<?= htmlspecialchars($stock_data['stock_status']) ?>">
                                        <?php else: ?>
                                            <select class="form-select" name="stock_status" id="stock_status" required style="width: 250px;">
                                                <?php foreach ($status_options as $status): ?>
                                                    <option value="<?= $status ?>" <?= ($stock_data['stock_status'] == $status) ? 'selected' : '' ?>>
                                                        <?= $status ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col">รูปภาพ</td>
                                    <td>
                                        <div class="image-preview-box" onclick="document.getElementById('new_image').click()">
                                            <img id="imagePreview" src="<?= !empty($stock_data['image_path']) ? '../uploads/products/' . htmlspecialchars($stock_data['image_path']) : '' ?>"
                                                alt="Preview" style="<?= !empty($stock_data['image_path']) ? '' : 'display:none;' ?>">
                                            <div id="imagePreviewText" class="image-preview-text" style="<?= !empty($stock_data['image_path']) ? 'display:none;' : '' ?>">
                                                <i class="fas fa-camera fa-2x"></i>
                                                <p class="mb-0">คลิกเพื่อเปลี่ยนรูป</p>
                                            </div>
                                        </div>
                                        <input type="file" class="form-control d-none" name="new_image" id="new_image" accept="image/*" <?= $is_sold ? 'disabled' : '' ?>>

                                        <?php if (!empty($stock_data['image_path']) && !$is_sold): ?>
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" name="delete_image" value="1" id="delete_image">
                                                <label class="form-check-label text-danger" for="delete_image">
                                                    ลบรูปภาพนี้ (เมื่อบันทึก)
                                                </label>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="text-end">
                            <?php if (!$is_sold): ?>
                                <button type="submit" class="btn btn-warning" id="submitBtn">
                                    <i class="fas fa-save me-2"></i>บันทึกการแก้ไข
                                </button>
                            <?php endif; ?>
                            <a href="prod_stock.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>ย้อนกลับ
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // (JS: AJAX เช็ค Serial ซ้ำ)
            const serialInput = document.querySelector('.serial-input');
            const errorFeedback = document.querySelector('.error-feedback');

            serialInput.addEventListener('input', async function() {
                const value = this.value.trim();

                if (value.length >= 5) {
                    try {
                        const formData = new FormData();
                        formData.append('action', 'check_serial');
                        formData.append('serial_no', value);
                        formData.append('stock_id', <?= $stock_id ?>); // (ส่ง ID ปัจจุบันไปด้วย)

                        const response = await fetch('', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();

                        if (data.success && data.exists) {
                            this.classList.add('is-invalid');
                            errorFeedback.style.display = 'block';
                        } else {
                            this.classList.remove('is-invalid');
                            errorFeedback.style.display = 'none';
                        }
                    } catch (error) {
                        console.error('Error checking Serial:', error);
                    }
                } else {
                    this.classList.remove('is-invalid');
                    errorFeedback.style.display = 'none';
                }
            });

            // (JS: Preview รูปใหม่)
            const newImageInput = document.getElementById('new_image');
            const imagePreview = document.getElementById('imagePreview');
            const imagePreviewText = document.getElementById('imagePreviewText');
            const deleteCheckbox = document.getElementById('delete_image');

            newImageInput.addEventListener('change', function(e) {
                if (e.target.files && e.target.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        imagePreview.src = event.target.result;
                        imagePreview.style.display = 'block';
                        imagePreviewText.style.display = 'none';
                        if (deleteCheckbox) deleteCheckbox.checked = false; // (ถ้าอัปใหม่ ให้ยกเลิกการลบ)
                    }
                    reader.readAsDataURL(e.target.files[0]);
                }
            });

            // (JS: Validation ตอน Submit)
            document.getElementById('editStockForm').addEventListener('submit', function(e) {
                const serialInput = document.querySelector('.serial-input');
                const priceInput = document.getElementById('price');
                let isValid = true;

                if (serialInput.classList.contains('is-invalid')) {
                    isValid = false;
                }
                if (!serialInput.value.trim()) {
                    serialInput.classList.add('is-invalid');
                    isValid = false;
                }
                if (parseFloat(priceInput.value) <= 0) {
                    priceInput.classList.add('is-invalid');
                    isValid = false;
                }

                if (!isValid) {
                    e.preventDefault();
                    alert('ข้อมูลไม่ถูกต้อง: กรุณาตรวจสอบ Serial Number (ห้ามว่าง/ห้ามซ้ำ) และราคา (ต้องมากกว่า 0)');
                    return;
                }

                const submitBtn = document.getElementById('submitBtn');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังบันทึก...';
                submitBtn.disabled = true;
            });

        });
    </script>
</body>

</html>