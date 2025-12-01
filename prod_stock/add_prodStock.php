<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_prodStock');

// -----------------------------------------------------------------------------
// 1. INITIALIZE VARIABLES (Manual Mode Only)
// -----------------------------------------------------------------------------

$page_title = "เพิ่มสต็อก (กรณีพิเศษ/ของแถม)";
$page_icon = "fa-gift";

// -----------------------------------------------------------------------------
// 2. MANUAL MODE: ดึงข้อมูลสินค้าทั้งหมด (สำหรับ Dropdown)
// -----------------------------------------------------------------------------
$products_result = mysqli_query($conn, "SELECT 
                                    p.prod_id, p.prod_name, p.model_name, p.prod_price,
                                    pb.brand_name_th as brand_name, 
                                    pt.type_name_th as type_name 
                                  FROM products p 
                                  LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id 
                                  LEFT JOIN prod_types pt ON p.prod_types_type_id = pt.type_id 
                                  ORDER BY p.prod_name");

// -----------------------------------------------------------------------------
// 3. SHARED FUNCTIONS
// -----------------------------------------------------------------------------

function getNextStockId($conn)
{
    $sql = "SELECT IFNULL(MAX(stock_id), 100000) + 1 as next_id FROM prod_stocks";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['next_id'];
}

function checkSerialExists($conn, $serial)
{
    $sql = "SELECT stock_id FROM prod_stocks WHERE serial_no = '" . mysqli_real_escape_string($conn, $serial) . "'";
    $result = mysqli_query($conn, $sql);
    return mysqli_num_rows($result) > 0;
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
            echo json_encode([
                'success' => true,
                'exists' => checkSerialExists($conn, $serial)
            ]);
            exit;
    }
}

// -----------------------------------------------------------------------------
// 5. POST HANDLER (จัดการการบันทึกข้อมูล - Manual Mode Only)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {

    $date_in = !empty($_POST['date_in']) ? mysqli_real_escape_string($conn, $_POST['date_in']) : date('Y-m-d');

    // (A) --- จัดการรูปภาพ (ใช้ร่วมกัน) ---
    $first_image_name = NULL;
    if (isset($_FILES['prod_image']) && $_FILES['prod_image']['error'][0] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/products/'; // (ต้องสร้างโฟลเดอร์นี้)
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $tmp_name = $_FILES['prod_image']['tmp_name'][0];
        $file_extension = pathinfo($_FILES['prod_image']['name'][0], PATHINFO_EXTENSION);
        $new_filename = time() . '_0.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        if (move_uploaded_file($tmp_name, $upload_path)) {
            $first_image_name = $new_filename;
        }
    }

    mysqli_autocommit($conn, false);
    $success_count = 0;
    $stock_ids = [];

    try {

        // (B) --- บันทึกโหมด MANUAL (กรณีพิเศษ) ---

        $products_prod_id = mysqli_real_escape_string($conn, $_POST['products_prod_id']);
        $price = floatval($_POST['price']);
        $serial_list = $_POST['serial_no'];
        $ref_table = mysqli_real_escape_string($conn, $_POST['manual_reason']); // รับเหตุผล

        // (Validate)
        if (empty($products_prod_id) || empty($serial_list) || $price <= 0 || empty($ref_table)) {
            throw new Exception('กรุณากรอกข้อมูลโหมดพิเศษให้ครบถ้วน (สินค้า, ราคา, Serial, เหตุผล)');
        }

        foreach ($serial_list as $serial) {
            if (empty(trim($serial))) throw new Exception('กรุณากรอก Serial Number ให้ครบทุกชิ้น');
            if (checkSerialExists($conn, $serial)) throw new Exception("Serial Number: $serial มีอยู่ในระบบแล้ว");
        }
        if (count($serial_list) !== count(array_unique($serial_list))) {
            throw new Exception('Serial Number ที่กรอกต้องไม่ซ้ำกัน');
        }

        // (Loop Insert)
        foreach ($serial_list as $serial) {
            $stock_id = getNextStockId($conn);
            $serial_escaped = mysqli_real_escape_string($conn, trim($serial));

            // (INSERT Stock - แก้ไขแล้ว)
            $sql = "INSERT INTO prod_stocks (
                        stock_id, serial_no, price, stock_status, warranty_start_date, 
                        image_path, create_at, update_at, products_prod_id
                    ) VALUES (
                        ?, ?, ?, 'In Stock', NULL, 
                        ?, NOW(), NOW(), ?
                    )";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "isdsi",
                $stock_id,
                $serial_escaped,
                $price,
                $first_image_name,
                $products_prod_id
            );
            if (!$stmt->execute()) throw new Exception('ไม่สามารถเพิ่มสต็อกได้: ' . $stmt->error);
            $stmt->close();

            // (INSERT Movement)
            $move_id = getNextMovementId($conn);
            $move_stmt = $conn->prepare(
                "INSERT INTO stock_movements 
                    (movement_id, movement_type, ref_table, ref_id, prod_stocks_stock_id, prodout_types_outtype_id, create_at) 
                 VALUES (?, 'IN', ?, NULL, ?, NULL, NOW())"
            );
            $move_stmt->bind_param("isi", $move_id, $ref_table, $stock_id);
            if (!$move_stmt->execute()) throw new Exception('ไม่สามารถบันทึก Movement ได้: ' . $move_stmt->error);
            $move_stmt->close();

            $success_count++;
            $stock_ids[] = $stock_id;
        }

        // (C) --- Commit และ Redirect ---
        mysqli_commit($conn);
        mysqli_autocommit($conn, true);

        $stock_range = count($stock_ids) > 1 ? $stock_ids[0] . '-' . $stock_ids[count($stock_ids) - 1] : $stock_ids[0];

        $_SESSION['success'] = "เพิ่มสินค้าเข้าสต็อกสำเร็จ จำนวน $success_count ชิ้น (รหัส: $stock_range)";
        header('Location: prod_stock.php');
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
        $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - ระบบจัดการร้านค้ามือถือ</title>
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
            max-width: 1200px;
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

        .serial-row {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border: 1px solid #e9ecef;
        }

        .item-number {
            background: <?= $theme_color ?>;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 10px;
        }

        .image-preview {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            min-height: 150px;
        }

        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .images-grid img {
            max-width: 100px;
            max-height: 100px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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

        table {
            width: 100%;
        }

        .label-col {
            width: 150px;
            font-weight: 500;
            vertical-align: top;
            padding-top: 8px;
            color: #444;
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

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><i class="fas <?= $page_icon ?> me-2"></i><?= $page_title ?></h4>

                        <a href="add_stock_barcode.php" class="btn btn-primary shadow-sm">
                            <i class="fas fa-barcode fa-lg me-2"></i> รับเข้าด้วยบาร์โค้ด (Scan Mode)
                        </a>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        หน้านี้ใช้สำหรับเพิ่มสินค้าเข้าสต็อกโดยตรง (เช่น ของแถม, สินค้าตกหล่น, ปรับสต็อก)
                        <br>หากต้องการรับสินค้าจาก PO, กรุณาไปที่หน้า "ใบสั่งซื้อ" และกดปุ่ม "รับสินค้า"
                    </div>

                    <form method="POST" enctype="multipart/form-data" id="addStockForm" novalidate>
                        <div class="form-section">
                            <h5>ข้อมูลพื้นฐาน</h5>
                            <table>
                                <tr>
                                    <td class="label-col">สินค้า <span class="text-danger">*</span></td>
                                    <td>
                                        <select class="form-select" name="products_prod_id" id="products_prod_id" required style="width: 400px;">
                                            <option value="">-- เลือกสินค้า --</option>
                                            <?php mysqli_data_seek($products_result, 0); ?>
                                            <?php while ($product = mysqli_fetch_assoc($products_result)): ?>
                                                <option value="<?= $product['prod_id'] ?>" data-price="<?= $product['prod_price'] ?>">
                                                    <?= htmlspecialchars($product['prod_name']) ?>
                                                    <?= htmlspecialchars($product['brand_name']) ?>
                                                    (<?= htmlspecialchars($product['model_name']) ?>)
                                                    - ฿<?= number_format($product['prod_price'], 2) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="error-feedback">กรุณาเลือกสินค้า</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col">จำนวนสินค้า <span class="text-danger">*</span></td>
                                    <td>
                                        <div class="input-group" style="width: 200px;">
                                            <input type="number" class="form-control" name="quantity" id="quantity" min="1" max="50" value="1" required>
                                            <span class="input-group-text">ชิ้น</span>
                                        </div>
                                        <div class="error-feedback">กรุณากรอกจำนวนสินค้า</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col">ราคาขาย <span class="text-danger">*</span></td>
                                    <td>
                                        <div class="input-group" style="width: 250px;">
                                            <span class="input-group-text">฿</span>
                                            <input type="number" class="form-control" name="price" id="price" step="0.01" min="0.01" required placeholder="0.00">
                                        </div>
                                        <div class="error-feedback">กรุณากรอกราคาขาย</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col">เหตุผล <span class="text-danger">*</span></td>
                                    <td>
                                        <select class="form-select" name="manual_reason" id="manual_reason" required style="width: 250px;">
                                            <option value="">-- เลือกเหตุผล --</option>
                                            <option value="MANUAL_ENTRY">ปรับสต็อก (กรอกเอง)</option>
                                            <option value="FREEBIE">ของแถมจาก Supplier</option>
                                            <option value="RETURN">ลูกค้ารับคืน (นอกประกัน)</option>
                                            <option value="OTHER">อื่นๆ</option>
                                        </select>
                                        <div class="error-feedback">กรุณาเลือกเหตุผล</div>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="form-section">
                            <h5><i class="fas fa-barcode me-2"></i>ข้อมูล Serial Number (หรือ IMEI)</h5>
                            <div id="serialContainer">
                            </div>
                        </div>

                        <div class="form-section">
                            <h5><i class="fas fa-camera me-2"></i>รูปภาพสินค้า (ใช้รูปแรกร่วมกัน)</h5>
                            <p class="text-muted mb-3">
                                รูปภาพจะใช้ร่วมกันสำหรับสินค้าทุกชิ้นในรอบนี้ (สูงสุด 6 รูป)
                                <br><strong class="text-danger">เฉพาะ "รูปแรก" เท่านั้นที่จะถูกใช้เป็นรูปปก (บันทึกลง image_path)</strong>
                            </p>
                            <div class="image-preview" onclick="document.getElementById('prod_image').click()">
                                <div id="imagePreview">
                                    <i class="fas fa-camera fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">คลิกเพื่อเลือกรูปภาพ (สูงสุด 6 รูป)</p>
                                </div>
                                <div id="selectedImages" class="images-grid"></div>
                            </div>
                            <input type="file" class="form-control d-none" name="prod_image[]" id="prod_image" accept="image/*" multiple onchange="previewImages(this)">
                        </div>

                        <div class="form-section">
                            <h5><i class="fas fa-calendar-alt me-2"></i>ข้อมูลวันที่</h5>
                            <table>
                                <tr>
                                    <td class="label-col">วันที่เข้าสต็อก</td>
                                    <td>
                                        <input type="date" class="form-control" name="date_in" id="date_in" style="width: 200px;">
                                        <small class="text-muted">หากไม่เลือก จะใช้วันที่ปัจจุบันอัตโนมัติ</small>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-success" id="submitBtn">
                                <i class="fas fa-save me-2"></i>บันทึก
                            </button>
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
        // (เราไม่ต้องการ CURRENT_MODE อีกต่อไป)
        let selectedImages = [];

        document.addEventListener('DOMContentLoaded', function() {
            setTodayDate();

            // (เรียกใช้โหมด Manual ทันที)
            updateSerialFieldsManual();
            document.getElementById('quantity').addEventListener('change', updateSerialFieldsManual);
            document.getElementById('products_prod_id').addEventListener('change', updatePriceFromProduct);
        });

        // --- (JS: SHARED) ---
        function setTodayDate() {
            document.getElementById('date_in').value = new Date().toISOString().split('T')[0];
        }

        async function checkSerial(inputElement) {
            const value = inputElement.value.trim();
            const errorElement = inputElement.parentElement.querySelector('.error-feedback');

            if (value.length >= 5) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'check_serial');
                    formData.append('serial_no', value);

                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success && data.exists) {
                        inputElement.classList.add('is-invalid');
                        errorElement.textContent = 'Serial Number นี้มีอยู่ในระบบแล้ว';
                    } else {
                        inputElement.classList.remove('is-invalid');
                        errorElement.textContent = 'กรุณากรอก Serial Number';
                    }
                } catch (error) {
                    console.error('Error checking Serial:', error);
                }
            } else if (value.length > 0) {
                inputElement.classList.remove('is-invalid');
                errorElement.textContent = 'กรุณากรอก Serial Number';
            }
        }

        function previewImages(input) {
            // (เหมือนโค้ดเดิม)
            const files = Array.from(input.files);
            const maxFiles = 6;
            if (files.length > maxFiles) {
                alert(`สามารถเลือกได้สูงสุด ${maxFiles} รูป`);
                const dt = new DataTransfer();
                files.slice(0, maxFiles).forEach(file => dt.items.add(file));
                input.files = dt.files;
            }
            selectedImages = [];
            const container = document.getElementById('selectedImages');
            const preview = document.getElementById('imagePreview');
            container.innerHTML = '';
            const finalFiles = Array.from(input.files);
            preview.style.display = finalFiles.length > 0 ? 'none' : 'flex';
            finalFiles.forEach((file, index) => {
                selectedImages.push(file);
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imageItem = document.createElement('div');
                    imageItem.className = 'position-relative';
                    imageItem.innerHTML = `<img src="${e.target.result}" alt="Preview ${index + 1}"><button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" style="margin: -5px;" onclick="removeImage(${index})"><i class="fas fa-times"></i></button>`;
                    container.appendChild(imageItem);
                }
                reader.readAsDataURL(file);
            });
            if (finalFiles.length > 0) preview.style.display = 'none';
            else preview.style.display = 'flex';
        }

        function removeImage(index) {
            // (เหมือนโค้ดเดิม)
            selectedImages.splice(index, 1);
            const dt = new DataTransfer();
            selectedImages.forEach(file => dt.items.add(file));
            document.getElementById('prod_image').files = dt.files;
            previewImages(document.getElementById('prod_image'));
        }

        // --- (JS: MANUAL MODE) ---
        function updatePriceFromProduct() {
            const productSelect = document.getElementById('products_prod_id');
            const priceInput = document.getElementById('price');
            const selectedOption = productSelect.options[productSelect.selectedIndex];
            if (selectedOption.value && selectedOption.dataset.price) {
                priceInput.value = parseFloat(selectedOption.dataset.price).toFixed(2);
            } else {
                priceInput.value = '';
            }
        }

        function updateSerialFieldsManual() {
            const quantity = parseInt(document.getElementById('quantity').value) || 1;
            const container = document.getElementById('serialContainer');
            container.innerHTML = '';
            for (let i = 1; i <= quantity; i++) {
                container.appendChild(createSerialField('serial_no[]', i));
            }
        }

        // --- (JS: SHARED UTILITY) ---
        function createSerialField(name, itemNumber) {
            const row = document.createElement('div');
            row.className = 'serial-row';
            row.innerHTML = `
                <div class="item-number">ชิ้นที่ ${itemNumber}</div>
                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label">Serial Number (S/N) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control serial-input" name="${name}" placeholder="กรอก S/N หรือ IMEI" maxlength="50" required>
                        <div class="error-feedback">กรุณากรอก Serial Number</div>
                    </div>
                </div>
            `;
            // เพิ่ม Event Listener ให้ช่องที่เพิ่งสร้าง
            row.querySelector('.serial-input').addEventListener('input', function() {
                checkSerial(this);
            });
            return row;
        }

        // --- (JS: FORM VALIDATION) ---
        document.getElementById('addStockForm').addEventListener('submit', function(e) {
            let isValid = true;

            // (Validate Manual Mode)
            const requiredFields = ['products_prod_id', 'quantity', 'price', 'manual_reason'];
            requiredFields.forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (!field.value.trim() || (fieldName === 'price' && parseFloat(field.value) <= 0)) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            // (Validate Serials)
            const serialInputs = document.querySelectorAll('.serial-input');
            const serialValues = [];

            serialInputs.forEach(input => {
                const value = input.value.trim();
                if (!value) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else if (input.classList.contains('is-invalid')) { // เช็คว่าผ่าน AJAX
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                    serialValues.push(value);
                }
            });

            const uniqueSerial = [...new Set(serialValues)];
            if (uniqueSerial.length !== serialValues.length && serialValues.length > 0) {
                alert('Serial Number ต้องไม่ซ้ำกัน (ทั้งในฟอร์มและในระบบ)');
                isValid = false;
            }

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
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังบันทึก...';
            submitBtn.disabled = true;
        });
    </script>
</body>

</html>