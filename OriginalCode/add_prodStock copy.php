<?php
session_start();
require '../config/config.php';

// ดึงข้อมูล dropdown
$products_result = mysqli_query($conn, "SELECT p.*, pb.name_th as brand_name, pt.name_th as type_name 
                                      FROM products p 
                                      LEFT JOIN prod_brands pb ON p.prod_brands_id = pb.id 
                                      LEFT JOIN prod_types pt ON p.prod_types_id = pt.id 
                                      ORDER BY p.name");

$suppliers_result = mysqli_query($conn, "SELECT id, sp_name FROM suppliers ORDER BY sp_name");

// ฟังก์ชันสร้างรหัสสต็อกถัดไป (รหัสปัจจุบัน + 1)
function getNextStockId($conn) {
    $sql = "SELECT IFNULL(MAX(id), 100000) + 1 as next_id FROM prod_stocks";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['next_id'];
}

// ฟังก์ชันสร้างรหัสประกันถัดไป (รหัสปัจจุบัน + 1)
function getNextWarrantyId($conn) {
    $sql = "SELECT IFNULL(MAX(id), 0) + 1 as next_id FROM prod_warranty";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['next_id'];
}

// ฟังก์ชันสร้างบาร์โค้ดไม่ซ้ำ (เบื้องหลัง)
function generateUniqueBarcode($conn) {
    do {
        $barcode = time() . rand(1000, 9999);
        $check_sql = "SELECT id FROM prod_stocks WHERE barcode = '$barcode'";
        $check_result = mysqli_query($conn, $check_sql);
    } while (mysqli_num_rows($check_result) > 0);
    
    return $barcode;
}

// ฟังก์ชันตรวจสอบ IMEI ซ้ำ
function checkImeiExists($conn, $imei) {
    $sql = "SELECT id FROM prod_stocks WHERE imei = '" . mysqli_real_escape_string($conn, $imei) . "'";
    $result = mysqli_query($conn, $sql);
    return mysqli_num_rows($result) > 0;
}

// จัดการ AJAX Request
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'check_imei':
            $imei = mysqli_real_escape_string($conn, $_POST['imei']);
            echo json_encode([
                'success' => true,
                'exists' => checkImeiExists($conn, $imei)
            ]);
            exit;
    }
}

// จัดการบันทึกข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    
    $products_id = mysqli_real_escape_string($conn, $_POST['product_id']);
    $date_in = !empty($_POST['date_in']) ? mysqli_real_escape_string($conn, $_POST['date_in']) : date('Y-m-d');
    $warranty_months = intval($_POST['warranty_months']);
    $warranty_description = mysqli_real_escape_string($conn, trim($_POST['warranty_description']));
    $price = floatval($_POST['price']);
    $supplier_id = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : NULL;
    $imei_list = $_POST['imei'];
    
    // ตรวจสอบข้อมูลที่จำเป็น
    if (empty($products_id) || empty($imei_list) || $price <= 0) {
        echo "<script>alert('กรุณากรอกข้อมูลให้ครบถ้วน และราคาต้องมากกว่า 0'); history.back();</script>";
        exit;
    }
    
    // ตรวจสอบ IMEI ซ้ำในฐานข้อมูล
    foreach ($imei_list as $imei) {
        if (checkImeiExists($conn, $imei)) {
            echo "<script>alert('IMEI $imei มีอยู่ในระบบแล้ว'); history.back();</script>";
            exit;
        }
    }
    
    // ตรวจสอบ IMEI ซ้ำในรายการเดียวกัน
    if (count($imei_list) !== count(array_unique($imei_list))) {
        echo "<script>alert('IMEI ต้องไม่ซ้ำกัน'); history.back();</script>";
        exit;
    }
    
    // ตรวจสอบ IMEI ว่าง
    foreach ($imei_list as $imei) {
        if (empty(trim($imei))) {
            echo "<script>alert('กรุณากรอก IMEI ให้ครบทุกชิ้น'); history.back();</script>";
            exit;
        }
    }
    
    // จัดการอัปโหลดรูปภาพ (สูงสุด 6 รูป)
    $image_names = [];
    if (isset($_FILES['prod_image'])) {
        $upload_dir = '../uploads/products/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $max_files = 6;
        $uploaded_count = 0;
        
        foreach ($_FILES['prod_image']['tmp_name'] as $key => $tmp_name) {
            if ($uploaded_count >= $max_files) break;
            
            if ($_FILES['prod_image']['error'][$key] === UPLOAD_ERR_OK) {
                $file_extension = pathinfo($_FILES['prod_image']['name'][$key], PATHINFO_EXTENSION);
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
    
    $success_count = 0;
    $images_json = json_encode($image_names);
    $stock_ids = [];
    
    // เพิ่มข้อมูลสต็อกแต่ละชิ้น (แยก row และ warranty แยกกัน)
    foreach ($imei_list as $index => $imei) {
        
        // สร้างรหัสสต็อกถัดไป (รหัสปัจจุบัน + 1)
        $stock_id = getNextStockId($conn);
        
        // สร้างรหัสประกันถัดไป (รหัสปัจจุบัน + 1)
        $warranty_id = getNextWarrantyId($conn);
        
        // สร้างบาร์โค้ดใหม่สำหรับแต่ละชิ้น  
        $barcode = generateUniqueBarcode($conn);
        
        $imei_escaped = mysqli_real_escape_string($conn, trim($imei));
        
        // สร้างข้อมูลการรับประกันสำหรับแต่ละชิ้น โดยยังไม่เริ่มประกัน (รอการขาย)
        $warranty_desc_full = empty($warranty_description) 
            ? "การรับประกัน " . $warranty_months . " เดือน "
            : $warranty_description ;
        
        $warranty_desc_escaped = mysqli_real_escape_string($conn, $warranty_desc_full);
        
        // สร้างการรับประกันแบบ pending (ยังไม่เริ่มประกัน)
        $warranty_sql = "INSERT INTO prod_warranty (id, start_date, end_date, total_warranty, description, warranty_status) 
                         VALUES ($warranty_id, NULL, NULL, $warranty_months, '$warranty_desc_escaped', 'pending')";
        
        if (!mysqli_query($conn, $warranty_sql)) {
            mysqli_rollback($conn);
            echo "<script>alert('ไม่สามารถสร้างข้อมูลการรับประกันสำหรับ IMEI: $imei ได้: " . mysqli_error($conn) . "'); history.back();</script>";
            exit;
        }
        
        echo "<!-- Debug: สร้าง Warranty ID: $warranty_id สำหรับ Stock ID: $stock_id (สถานะ: pending - รอการขาย) -->";
        
        // เพิ่มข้อมูลสต็อก โดยระบุ id เอง - สถานะเริ่มต้นเป็น available
        $sql = "INSERT INTO prod_stocks (
                    id, imei, barcode, date_in, date_out, prod_image, 
                    products_id, proout_types_id, prod_warranty_id, price, supplier_id
                ) VALUES (
                    $stock_id, '$imei_escaped', '$barcode', '$date_in', NULL, '$images_json', 
                    $products_id, ".($out_type_id ? $out_type_id : 1).", $warranty_id,$price, " . ($supplier_id ? $supplier_id : 'NULL') . "
                )";
        
        if (mysqli_query($conn, $sql)) {
            $success_count++;
            $stock_ids[] = $stock_id;
            echo "<!-- Debug: สร้างสต็อก IMEI: $imei_escaped, Stock ID: $stock_id, Barcode: $barcode, Warranty ID: $warranty_id (สถานะ: available - การรับประกันยังไม่เริ่ม) -->";
        } else {
            mysqli_rollback($conn);
            $error_msg = mysqli_error($conn);
            echo "<script>alert('ไม่สามารถเพิ่มสินค้า IMEI: $imei ได้ (Error: $error_msg)'); history.back();</script>";
            exit;
        }
    }
    
    mysqli_commit($conn);
    mysqli_autocommit($conn, true);
    
    $stock_range = count($stock_ids) > 1 ? $stock_ids[0] . '-' . $stock_ids[count($stock_ids)-1] : $stock_ids[0];
    echo "<script>
        alert('เพิ่มสินค้าเข้าสต็อกสำเร็จ จำนวน $success_count ชิ้น\\nรหัสสต็อก: $stock_range\\nราคา: ฿" . number_format($price, 2) . " ต่อชิ้น\\nการรับประกัน: $warranty_months เดือน (จะเริ่มเมื่อขาย)');
        window.location.href = 'print_barcode.php?stock_ids=" . implode(',', $stock_ids) . "';
    </script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มสินค้าเข้าสต็อก - ระบบจัดการร้านค้ามือถือ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        * {
            font-family: 'Prompt', sans-serif;
        }
        
        body {
            background-color: #f0f2f5;
            font-size: 15px;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            padding: 20px 15px;
        }
        
        h4 {
            font-weight: 700;
            color: #198754;
        }
        
        h5 {
            margin-top: 20px;
            padding-bottom: 10px;
            font-weight: 600;
            color: #198754;
        }
        
        .form-section {
            background: #fff;
            border-radius: 10px;
            padding: 20px 25px;
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }
        
        .form-control, .form-select {
            font-size: 14px;
            padding: 8px 12px;
            border-radius: 6px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.15rem rgba(25, 135, 84, 0.25);
        }
        
        .btn {
            padding: 10px 20px;
            font-weight: 500;
            font-size: 15px;
        }
        
        .btn-success {
            background-color: #198754;
            border-color: #198754;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        
        .imei-row {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border: 1px solid #e9ecef;
        }
        
        .item-number {
            background: linear-gradient(135deg, #198754 0%, #20c997 100%);
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
        
        .image-preview:hover {
            border-color: #198754;
            background-color: #f8f9fa;
        }
        
        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .image-preview img {
            max-width: 100px;
            max-height: 100px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin: 5px;
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
        
        .error-feedback {
            color: #dc3545;
            font-size: 0.875em;
            margin-top: 5px;
            display: none;
        }
        
        .is-invalid + .error-feedback {
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
        
        .auto-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 10px;
            margin-top: 5px;
            font-size: 0.9em;
            color: #0c5460;
        }
        
        .pending-warranty {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 10px;
            margin-top: 5px;
            font-size: 0.9em;
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
    <div class="container my-1">
        <h4 class="mb-4"><i class="fas fa-plus-circle me-2"></i>เพิ่มสินค้าเข้าสต็อก</h4>
        
        <form method="POST" enctype="multipart/form-data" id="addStockForm" novalidate>
            <!-- ข้อมูลพื้นฐาน -->
            <div class="form-section">
                <h5>ข้อมูลพื้นฐาน</h5>
                <table>
                    <tr>
                        <td class="label-col">สินค้า <span class="text-danger">*</span></td>
                        <td>
                            <select class="form-select" name="product_id" id="product_id" required style="width: 400px;">
                                <option value="">-- เลือกสินค้า --</option>
                                <?php while ($product = mysqli_fetch_assoc($products_result)): ?>
                                    <option value="<?= $product['id'] ?>" data-price="<?= $product['price'] ?>">
                                        <?= htmlspecialchars($product['name']) ?> 
                                        <?= htmlspecialchars($product['brand_name']) ?> 
                                        (<?= htmlspecialchars($product['model_name']) ?>) 
                                        - ฿<?= number_format($product['price'], 2) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="error-feedback">กรุณาเลือกสินค้า</div>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-col">วันที่เข้าสต็อก</td>
                        <td>
                            <input type="date" class="form-control" name="date_in" id="date_in" style="width: 200px;">
                            <small class="text-muted">หากไม่เลือก จะใช้วันที่ปัจจุบันอัตโนมัติ</small>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-col">จำนวนสินค้า <span class="text-danger">*</span></td>
                        <td>
                            <div class="input-group" style="width: 200px;">
                                <input type="number" class="form-control" name="quantity" id="quantity" min="1" max="50" value="1" required>
                                <span class="input-group-text">ชิ้น</span>
                            </div>
                            <div class="auto-info">
                                <i class="fas fa-info-circle me-1"></i>
                                แต่ละชิ้นจะได้รหัสสต็อกแยกกัน เช่น: 100001, 100002, 100003...
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
                        <td class="label-col">ผู้จำหน่าย</td>
                        <td>
                            <select class="form-select" name="supplier_id" id="supplier_id" style="width: 300px;">
                                <option value="">-- เลือกผู้จำหน่าย (ไม่จำเป็น) --</option>
                                <?php while ($supplier = mysqli_fetch_assoc($suppliers_result)): ?>
                                    <option value="<?= $supplier['id'] ?>">
                                        <?= htmlspecialchars($supplier['sp_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-muted">สามารถเลือกหรือไม่เลือกก็ได้</small>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ข้อมูลการรับประกัน -->
            <div class="form-section">
                <h5><i class="fas fa-shield-alt me-2"></i>ข้อมูลการรับประกัน</h5>
                <table>
                    <tr>
                        <td class="label-col">ระยะการรับประกัน <span class="text-danger">*</span></td>
                        <td>
                            <div class="input-group" style="width: 200px;">
                                <input type="number" class="form-control" name="warranty_months" id="warranty_months" min="0" max="60" value="12" required>
                                <span class="input-group-text">เดือน</span>
                            </div>
                            <div class="warranty-info">
                                <i class="fas fa-clock me-1"></i>
                                การรับประกัน 12 เดือน - <strong>จะเริ่มนับเมื่อขายสินค้าเท่านั้น</strong><br>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-col">รายละเอียดการรับประกัน</td>
                        <td>
                            <textarea class="form-control" name="warranty_description" id="warranty_description" 
                                      rows="4" placeholder="กรอกรายละเอียดเงื่อนไขการรับประกัน..." 
                                      style="width: 500px;" maxlength="500"></textarea>
                            <small class="text-muted">
                                <span id="descriptionCount">0</span>/500 ตัวอักษร
                                <br>
                            </small>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ข้อมูล IMEI -->
            <div class="form-section">
                <h5><i class="fas fa-mobile-alt me-2"></i>ข้อมูล IMEI</h5>
                <div id="imeiContainer">
                    <!-- Dynamic IMEI rows will be added here -->
                </div>
            </div>

            <!-- รูปภาพสินค้า -->
            <div class="form-section">
                <h5><i class="fas fa-camera me-2"></i>รูปภาพสินค้า</h5>
                <p class="text-muted mb-3">รูปภาพจะใช้ร่วมกันสำหรับสินค้าทุกชิ้นในรอบนี้ (สูงสุด 6 รูป, ไม่จำกัดขนาด)</p>
                <div class="image-preview" onclick="document.getElementById('prod_image').click()">
                    <div id="imagePreview">
                        <i class="fas fa-camera fa-3x text-muted mb-3"></i>
                        <p class="text-muted">คลิกเพื่อเลือกรูปภาพ (สูงสุด 6 รูป)</p>
                        <small class="text-muted">รองรับไฟล์: JPG, PNG, GIF (ไม่จำกัดขนาด)</small>
                    </div>
                    <div id="selectedImages" class="images-grid"></div>
                </div>
                <input type="file" class="form-control d-none" name="prod_image[]" id="prod_image" accept="image/*" multiple onchange="previewImages(this)">
            </div>

            <!-- ปุ่มบันทึก -->
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedImages = [];

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            setTodayDate();
            updateImeiFields();
            
            // เพิ่ม event listener
            document.getElementById('quantity').addEventListener('change', updateImeiFields);
            document.getElementById('warranty_months').addEventListener('input', updateWarrantyInfo);
            document.getElementById('warranty_description').addEventListener('input', updateWarrantyDescription);
            document.getElementById('product_id').addEventListener('change', updatePriceFromProduct);
        });

        // ตั้งวันที่ปัจจุบัน
        function setTodayDate() {
            document.getElementById('date_in').value = new Date().toISOString().split('T')[0];
        }

        // อัปเดตราคาจากสินค้าที่เลือก
        function updatePriceFromProduct() {
            const productSelect = document.getElementById('product_id');
            const priceInput = document.getElementById('price');
            
            const selectedOption = productSelect.options[productSelect.selectedIndex];
            if (selectedOption.value && selectedOption.dataset.price) {
                const productPrice = parseFloat(selectedOption.dataset.price);
                priceInput.value = productPrice.toFixed(2);
            } else {
                priceInput.value = '';
            }
        }

        // อัปเดตฟิลด์ IMEI ตามจำนวน
        function updateImeiFields() {
            const quantity = parseInt(document.getElementById('quantity').value) || 1;
            const container = document.getElementById('imeiContainer');
            
            container.innerHTML = '';
            
            for (let i = 1; i <= quantity; i++) {
                const row = document.createElement('div');
                row.className = 'imei-row';
                
                row.innerHTML = `
                    <div class="item-number">ชิ้นที่ ${i}</div>
                    <div class="row">
                        <div class="col-md-8">
                            <label class="form-label">IMEI <span class="text-danger">*</span></label>
                            <input type="text" class="form-control imei-input" name="imei[]" placeholder="กรอก IMEI" maxlength="25" required>
                            <div class="error-feedback">กรุณากรอก IMEI</div>
                            <small class="text-muted">
                                <i class="fas fa-cog me-1"></i>รหัสสต็อก: สร้างอัตโนมัติ | 
                                <i class="fas fa-barcode me-1"></i>บาร์โค้ด: สร้างอัตโนมัติ 
                            </small>
                        </div>
                    </div>
                `;
                
                container.appendChild(row);
            }
            
            addImeiValidation();
        }

        // เพิ่ม IMEI validation
        function addImeiValidation() {
            document.querySelectorAll('.imei-input').forEach(input => {
                input.addEventListener('input', async function() {
                    const value = this.value.trim();
                    const errorElement = this.parentElement.querySelector('.error-feedback');
                    
                    if (value && !/^\d+$/.test(value)) {
                        this.classList.add('is-invalid');
                        errorElement.textContent = 'IMEI ต้องเป็นตัวเลขเท่านั้น';
                        return;
                    }
                    
                    if (value.length >= 10) {
                        try {
                            const formData = new FormData();
                            formData.append('action', 'check_imei');
                            formData.append('imei', value);
                            
                            const response = await fetch('', {
                                method: 'POST',
                                body: formData
                            });
                            const data = await response.json();
                            
                            if (data.success && data.exists) {
                                this.classList.add('is-invalid');
                                errorElement.textContent = 'IMEI นี้มีอยู่ในระบบแล้ว';
                            } else {
                                this.classList.remove('is-invalid');
                                errorElement.textContent = '';
                            }
                        } catch (error) {
                            console.error('Error checking IMEI:', error);
                        }
                    } else {
                        this.classList.remove('is-invalid');
                        errorElement.textContent = '';
                    }
                });
            });
        }

        // อัปเดตข้อมูลการรับประกัน
        function updateWarrantyInfo() {
            const months = parseInt(document.getElementById('warranty_months').value) || 0;
            const info = document.querySelector('.warranty-info');
            
            if (months > 0) {
                info.innerHTML = `
                    <i class="fas fa-clock me-1"></i>
                    การรับประกัน ${months} เดือน - <strong>จะเริ่มนับเมื่อขายสินค้าเท่านั้น</strong><br>
                    <small>สถานะ: รอการขาย (pending) - ยังไม่เริ่มการรับประกัน</small>
                `;
            } else {
                info.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    ไม่มีการรับประกัน
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

        // Preview รูปภาพ (สูงสุด 6 รูป)
        function previewImages(input) {
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
            preview.style.display = finalFiles.length > 0 ? 'none' : 'block';
            
            finalFiles.forEach((file, index) => {
                selectedImages.push(file);
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imageItem = document.createElement('div');
                    imageItem.className = 'position-relative';
                    imageItem.innerHTML = `
                        <img src="${e.target.result}" alt="Preview ${index + 1}">
                        <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" 
                                style="margin: -5px;" onclick="removeImage(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                        <small class="d-block text-center mt-1">${file.name}</small>
                    `;
                    container.appendChild(imageItem);
                }
                reader.readAsDataURL(file);
            });
        }

        // ลบรูปภาพ
        function removeImage(index) {
            selectedImages.splice(index, 1);
            
            const dt = new DataTransfer();
            selectedImages.forEach(file => dt.items.add(file));
            document.getElementById('prod_image').files = dt.files;
            
            previewImages(document.getElementById('prod_image'));
        }

        // Form validation และ submission
        document.getElementById('addStockForm').addEventListener('submit', function(e) {
            let isValid = true;
            
            // ตรวจสอบฟิลด์ที่จำเป็น
            const requiredFields = ['product_id', 'quantity', 'warranty_months', 'price'];
            requiredFields.forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (!field.value.trim() || (fieldName === 'price' && parseFloat(field.value) <= 0)) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // ตรวจสอบ IMEI
            const imeiInputs = document.querySelectorAll('.imei-input');
            const imeiValues = [];
            
            imeiInputs.forEach(input => {
                const value = input.value.trim();
                if (!value) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else if (!/^\d+$/.test(value)) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                    imeiValues.push(value);
                }
            });
            
            // ตรวจสอบ IMEI ซ้ำ
            const uniqueImei = [...new Set(imeiValues)];
            if (uniqueImei.length !== imeiValues.length) {
                alert('IMEI ต้องไม่ซ้ำกัน');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                
                const firstError = document.querySelector('.is-invalid');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
                return;
            }
            
            // แสดง loading state และ confirmation
            const submitBtn = document.getElementById('submitBtn');
            const quantity = document.getElementById('quantity').value;
            const price = parseFloat(document.getElementById('price').value);
            const totalValue = quantity * price;
            const dateIn = document.getElementById('date_in').value || 'วันที่ปัจจุบัน';
            const warrantyMonths = document.getElementById('warranty_months').value;
            
            const confirmation = confirm(
                `ยืนยันการเพิ่มสินค้าเข้าสต็อก:\n` +
                `จำนวน: ${quantity} ชิ้น\n` +
                `ราคาต่อชิ้น: ฿${price.toLocaleString()}\n` +
                `มูลค่ารวม: ฿${totalValue.toLocaleString()}\n` +
                `วันที่เข้าสต็อก: ${dateIn}\n` +
                `การรับประกัน: ${warrantyMonths} เดือน (จะเริ่มนับเมื่อขายเท่านั้น)\n` +
                `รูปภาพ: ${selectedImages.length} รูป\n\n` +
                `แต่ละชิ้นจะได้รหัสสต็อกและการรับประกันแยกกัน\n` +
                `สถานะการรับประกัน: รอการขาย (pending)\n` +
                `ต้องการดำเนินการต่อหรือไม่?`
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