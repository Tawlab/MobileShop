<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_prodStock');

// 1. รับค่า Session
$branch_id = $_SESSION['branch_id'];
$shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// -----------------------------------------------------------------------------
// ตรวจสอบสิทธิ์ Admin
// -----------------------------------------------------------------------------
$is_admin = false;
$chk_sql = "SELECT r.role_name FROM roles r 
            JOIN user_roles ur ON r.role_id = ur.roles_role_id 
            WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt = $conn->prepare($chk_sql)) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $is_admin = true;
    $stmt->close();
}

// -----------------------------------------------------------------------------
// INITIALIZE VARIABLES
// -----------------------------------------------------------------------------
$page_title = "เพิ่มสต็อก (กรณีพิเศษ/ของแถม)";
$page_icon = "fa-gift";

// -----------------------------------------------------------------------------
// ดึงข้อมูลสินค้า
// -----------------------------------------------------------------------------
$sql_products = "SELECT 
                    p.prod_id, p.prod_name, p.model_name, p.prod_price, p.shop_info_shop_id,
                    pb.brand_name_th as brand_name, 
                    pt.type_name_th as type_name,
                    s.shop_name 
                 FROM products p 
                 LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id 
                 LEFT JOIN prod_types pt ON p.prod_types_type_id = pt.type_id 
                 LEFT JOIN shop_info s ON p.shop_info_shop_id = s.shop_id";

$conditions = [];
// ไม่แสดงสินค้าประเภทบริการ (Type ID != 4)
$conditions[] = "p.prod_types_type_id != 4";

// ถ้าไม่ใช่ Admin ให้กรองเฉพาะของร้านตัวเอง หรือ ของส่วนกลาง (ID=0)
if (!$is_admin) {
    $conditions[] = "(p.shop_info_shop_id = '$shop_id' OR p.shop_info_shop_id = 0)";
}

if (count($conditions) > 0) {
    $sql_products .= " WHERE " . implode(' AND ', $conditions);
}

$sql_products .= " ORDER BY p.prod_name ASC";
$products_result = mysqli_query($conn, $sql_products);

// -----------------------------------------------------------------------------
// SHARED FUNCTIONS
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
// AJAX: เช็ค Serial ซ้ำ (Backend)
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
// HANDLE POST: บันทึกข้อมูล
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {

    $date_in = !empty($_POST['date_in']) ? mysqli_real_escape_string($conn, $_POST['date_in']) : date('Y-m-d');

    // จัดการอัปโหลดรูปภาพ 
    $first_image_name = NULL;
    if (isset($_FILES['prod_image']) && $_FILES['prod_image']['error'][0] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/products/';
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
        $products_prod_id = mysqli_real_escape_string($conn, $_POST['products_prod_id']);
        $price = floatval($_POST['price']);
        $serial_list = $_POST['serial_no'];
        $ref_table = mysqli_real_escape_string($conn, $_POST['manual_reason']);

        if (empty($products_prod_id) || empty($serial_list) || $price <= 0 || empty($ref_table)) {
            throw new Exception('กรุณากรอกข้อมูลโหมดพิเศษให้ครบถ้วน (สินค้า, ราคา, Serial, เหตุผล)');
        }

        // ตรวจสอบ Serial ซ้ำก่อนบันทึก
        foreach ($serial_list as $serial) {
            if (empty(trim($serial))) throw new Exception('กรุณากรอก Serial Number ให้ครบทุกชิ้น');
            if (checkSerialExists($conn, $serial)) throw new Exception("Serial Number: $serial มีอยู่ในระบบแล้ว");
        }

        // ตรวจสอบค่าซ้ำใน Input เอง
        if (count($serial_list) !== count(array_unique($serial_list))) {
            throw new Exception('Serial Number ที่กรอกต้องไม่ซ้ำกัน');
        }

        // วนลูปบันทึก
        foreach ($serial_list as $serial) {
            $stock_id = getNextStockId($conn);
            $serial_escaped = mysqli_real_escape_string($conn, trim($serial));

            $sql = "INSERT INTO prod_stocks (
                        stock_id, serial_no, price, stock_status, warranty_start_date, 
                        image_path, create_at, update_at, products_prod_id, branches_branch_id
                    ) VALUES (
                        ?, ?, ?, 'In Stock', NULL, 
                        ?, NOW(), NOW(), ?, ?
                    )";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isdsii", $stock_id, $serial_escaped, $price, $first_image_name, $products_prod_id, $branch_id);
            if (!$stmt->execute()) throw new Exception('ไม่สามารถเพิ่มสต็อกได้: ' . $stmt->error);
            $stmt->close();

            // Movement
            $move_id = getNextMovementId($conn);
            $move_stmt = $conn->prepare(
    "INSERT INTO stock_movements 
        (movement_id, movement_type, ref_table, ref_id, prod_stocks_stock_id, create_at) 
     VALUES (?, 'IN', ?, NULL, ?, NOW())"
);
            $move_stmt->bind_param("isi", $move_id, $ref_table, $stock_id);
            if (!$move_stmt->execute()) throw new Exception('ไม่สามารถบันทึก Movement ได้: ' . $move_stmt->error);
            $move_stmt->close();

            $success_count++;
            $stock_ids[] = $stock_id;
        }

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $page_title ?> - ระบบจัดการร้านค้ามือถือ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php require '../config/load_theme.php'; ?>
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            color: <?= $text_color ?>;
            margin: 0;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
        }

        h4,
        h5 {
            color: <?= $theme_color ?>;
            font-weight: 700;
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
            max-width: 100%;
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

        @media (max-width: 991.98px) {
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }

            h4 {
                font-size: 1.5rem;
            }

            h5 {
                font-size: 1.25rem;
            }

            .form-section {
                padding: 20px;
                margin-bottom: 20px;
            }

            table,
            tbody,
            tr,
            td {
                display: block;
                width: 100%;
            }

            table td {
                padding: 5px 0 !important;
            }

            .label-col {
                margin-top: 10px;
                margin-bottom: 5px;
                font-weight: 600;
            }
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
                        <script>
                            Swal.fire({
                                icon: 'success',
                                title: 'สำเร็จ!',
                                text: '<?= $_SESSION['success']; ?>',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        </script>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <script>
                            Swal.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: '<?= $_SESSION['error']; ?>'
                            });
                        </script>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><i class="fas <?= $page_icon ?> me-2"></i><?= $page_title ?></h4>
                        <a href="add_stock_barcode.php" class="btn btn-primary shadow-sm">
                            <i class="fas fa-barcode fa-lg me-2"></i> รับเข้าด้วยบาร์โค้ด
                        </a>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        หน้านี้ใช้สำหรับเพิ่มสินค้าเข้าสต็อกโดยตรง (เช่น ของแถม, ปรับสต็อก)
                    </div>

                    <form method="POST" enctype="multipart/form-data" id="addStockForm" novalidate>
                        <div class="form-section">
                            <h5>ข้อมูลพื้นฐาน</h5>
                            <table>
                                <tr>
                                    <td class="label-col">สินค้า <span class="text-danger">*</span></td>
                                    <td>
                                        <select class="form-select select2" name="products_prod_id" id="products_prod_id" required style="width: 100%;">
                                            <option value="">-- เลือกสินค้า --</option>
                                            <?php mysqli_data_seek($products_result, 0); ?>
                                            <?php while ($product = mysqli_fetch_assoc($products_result)): ?>
                                                <option value="<?= $product['prod_id'] ?>" data-price="<?= $product['prod_price'] ?>">
                                                    <?= htmlspecialchars($product['prod_name']) ?> <?= htmlspecialchars($product['brand_name']) ?>
                                                    (<?= htmlspecialchars($product['model_name']) ?>) - ฿<?= number_format($product['prod_price'], 2) ?>
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
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-arrow-up me-2"></i>กรุณาเลือกสินค้าและระบุจำนวน
                                </div>
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

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        // ประกาศตัวแปร Global
        let selectedImages = [];

        $(document).ready(function() {
            // 1. Init Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: '-- ค้นหาและเลือกสินค้า --',
                allowClear: true
            });

            // 2. ตั้งค่าวันปัจจุบัน
            document.getElementById('date_in').value = new Date().toISOString().split('T')[0];

            // 3. Event Listeners (แก้ไขบัคตรงนี้)
            // เมื่อมีการเปลี่ยนสินค้า -> อัปเดตราคา และ สร้างช่อง Serial
            $('#products_prod_id').on('change', function() {
                updatePriceFromProduct();
                updateSerialFieldsManual();
            });

            // เมื่อมีการเปลี่ยนจำนวน -> สร้างช่อง Serial ใหม่ตามจำนวน
            $('#quantity').on('input change', function() {
                updateSerialFieldsManual();
            });

            // เรียกทำงานครั้งแรก (กรณีมีค่าค้างในฟอร์มหลัง Refresh)
            if ($('#products_prod_id').val()) {
                updatePriceFromProduct();
                updateSerialFieldsManual();
            }
        });

        // --- ฟังก์ชันดึงราคาจาก Option ---
        function updatePriceFromProduct() {
            const productSelect = document.getElementById('products_prod_id');
            const priceInput = document.getElementById('price');

            // ดึงค่าจาก Option ที่เลือก (รองรับ Select2)
            const selectedOption = productSelect.options[productSelect.selectedIndex];

            if (selectedOption && selectedOption.value && selectedOption.dataset.price) {
                priceInput.value = parseFloat(selectedOption.dataset.price).toFixed(2);
            } else {
                priceInput.value = '';
            }
        }

        // --- ฟังก์ชันสร้างช่องกรอก Serial (Logic หลักที่แก้ไข) ---
        function updateSerialFieldsManual() {
            const quantity = parseInt(document.getElementById('quantity').value) || 0;
            const productId = document.getElementById('products_prod_id').value;
            const container = document.getElementById('serialContainer');

            // ถ้ายังไม่เลือกสินค้า หรือจำนวนน้อยกว่า 1 ให้เคลียร์และแสดงข้อความแจ้งเตือน
            if (!productId || quantity < 1) {
                container.innerHTML = `
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-arrow-up me-2"></i>กรุณาเลือกสินค้าและระบุจำนวนที่ถูกต้อง
                    </div>`;
                return;
            }

            // ถ้ามีสินค้าแล้ว ให้สร้าง Input ตามจำนวน
            container.innerHTML = '';
            for (let i = 1; i <= quantity; i++) {
                container.appendChild(createSerialField('serial_no[]', i));
            }
        }

        // --- Helper: สร้าง HTML สำหรับแต่ละแถว Serial ---
        function createSerialField(name, itemNumber) {
            const row = document.createElement('div');
            row.className = 'serial-row animate__animated animate__fadeIn'; // เพิ่ม Animation เล็กน้อย (ถ้ามี library)
            row.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="item-number mb-0">ชิ้นที่ ${itemNumber}</div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <label class="form-label small text-muted">Serial Number / IMEI <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                            <input type="text" class="form-control serial-input" name="${name}" placeholder="สแกนหรือพิมพ์ S/N ที่นี่..." maxlength="50" required>
                        </div>
                        <div class="error-feedback">Serial Number นี้มีในระบบแล้ว หรือข้อมูลไม่ถูกต้อง</div>
                    </div>
                </div>
            `;

            // ผูก Event Check Serial ซ้ำ (AJAX) ทันทีที่พิมพ์
            const input = row.querySelector('.serial-input');
            let timeout = null;
            input.addEventListener('input', function() {
                clearTimeout(timeout);
                // หน่วงเวลา 500ms ก่อนเช็ค เพื่อไม่ให้ยิง Request ถี่เกินไป
                timeout = setTimeout(() => checkSerial(this), 500);
            });

            return row;
        }

        // --- AJAX Check Serial ---
        async function checkSerial(inputElement) {
            const value = inputElement.value.trim();
            const errorElement = inputElement.closest('.col-md-12').querySelector('.error-feedback');

            if (value.length >= 4) { // เช็คเมื่อพิมพ์ 4 ตัวขึ้นไป
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
                        inputElement.classList.remove('is-valid');
                        errorElement.style.display = 'block';
                        errorElement.textContent = 'Serial Number นี้มีอยู่ในระบบแล้ว';
                    } else {
                        inputElement.classList.remove('is-invalid');
                        inputElement.classList.add('is-valid');
                        errorElement.style.display = 'none';
                    }
                } catch (error) {
                    console.error('Error:', error);
                }
            } else {
                inputElement.classList.remove('is-invalid', 'is-valid');
                errorElement.style.display = 'none';
            }
        }

        // --- Image Preview Functions ---
        window.previewImages = function(input) { // ทำให้เป็น Global function
            const files = Array.from(input.files);
            const maxFiles = 6;

            if (files.length > maxFiles) {
                Swal.fire('แจ้งเตือน', `สามารถเลือกรูปภาพได้สูงสุด ${maxFiles} รูป`, 'warning');
                // ตัดให้เหลือแค่ 6 รูป
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
                    imageItem.innerHTML = `
                        <img src="${e.target.result}" alt="Preview">
                        <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" 
                                style="transform: translate(30%, -30%); border-radius: 50%;" 
                                onclick="removeImage(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    container.appendChild(imageItem);
                }
                reader.readAsDataURL(file);
            });
        }

        window.removeImage = function(index) {
            const input = document.getElementById('prod_image');
            const dt = new DataTransfer();
            const files = Array.from(input.files);

            files.forEach((file, i) => {
                if (i !== index) dt.items.add(file);
            });

            input.files = dt.files;
            previewImages(input); // Render ใหม่
        }

        // --- Form Validation & Submit ---
        document.getElementById('addStockForm').addEventListener('submit', function(e) {
            e.preventDefault(); // หยุดการส่งฟอร์มไว้ก่อนเพื่อเช็ค

            let isValid = true;
            const requiredFields = ['products_prod_id', 'quantity', 'price', 'manual_reason'];

            // 1. เช็คฟิลด์หลัก
            requiredFields.forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (!field.value.trim() || (fieldName === 'price' && parseFloat(field.value) <= 0)) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            // 2. เช็ค Serial Numbers
            const serialInputs = document.querySelectorAll('.serial-input');
            const serialValues = [];

            if (serialInputs.length === 0) {
                Swal.fire('ผิดพลาด', 'กรุณาระบุจำนวนและเลือกสินค้าก่อน', 'error');
                return;
            }

            serialInputs.forEach(input => {
                const val = input.value.trim();
                if (!val) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else if (input.classList.contains('is-invalid')) {
                    // ถ้ามี class invalid ค้างอยู่ (เช่น ซ้ำใน DB)
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                    serialValues.push(val);
                }
            });

            // 3. เช็คค่าซ้ำในฟอร์มเอง
            const uniqueSerial = [...new Set(serialValues)];
            if (uniqueSerial.length !== serialValues.length && serialValues.length > 0) {
                Swal.fire('ข้อมูลซ้ำ', 'Serial Number ที่กรอกต้องไม่ซ้ำกันในรายการเดียวกัน', 'warning');
                isValid = false;
            }

            if (!isValid) {
                Swal.fire('ข้อมูลไม่ครบถ้วน', 'กรุณาตรวจสอบข้อมูลที่แจ้งเตือนสีแดง', 'error');
                // เลื่อนหน้าจอไปหาจุดผิด
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

            // ถ้าผ่านหมด ให้ Submit
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังบันทึก...';
            submitBtn.disabled = true;

            // ส่งฟอร์มจริง
            this.submit();
        });
    </script>
</body>

</html>