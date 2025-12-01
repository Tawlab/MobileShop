<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_prodStock');

// -----------------------------------------------------------------------------
// 1. HELPER FUNCTIONS
// -----------------------------------------------------------------------------
function getNextStockId($conn) {
    $sql = "SELECT IFNULL(MAX(stock_id), 100000) + 1 as next_id FROM prod_stocks";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result)['next_id'];
}

function getNextMovementId($conn) {
    $sql = "SELECT IFNULL(MAX(movement_id), 0) + 1 as next_id FROM stock_movements";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result)['next_id'];
}

// -----------------------------------------------------------------------------
// 2. FETCH PRODUCTS
// -----------------------------------------------------------------------------
$sql_prod = "SELECT p.prod_id, p.prod_name, p.model_name, pb.brand_name_th 
             FROM products p
             LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id
             WHERE p.prod_types_type_id != 4 
             ORDER BY p.prod_name";
$products = mysqli_query($conn, $sql_prod);

// -----------------------------------------------------------------------------
// 3. HANDLE SAVE (บันทึกข้อมูล)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_stock'])) {
    $prod_id = (int)$_POST['product_id'];
    $items = $_POST['items'] ?? []; // รับค่าเป็น Array [index][serial], [index][price]
    
    if (empty($prod_id) || empty($items)) {
        $_SESSION['error'] = "ข้อมูลไม่ครบถ้วน หรือยังไม่ได้สแกนสินค้า";
    } else {
        mysqli_autocommit($conn, false);
        try {
            // --- จัดการอัปโหลดรูปภาพ (ใช้รูปเดียวร่วมกันทั้งล็อต) ---
            $image_filename = NULL;
            if (!empty($_FILES['stock_image']['name'])) {
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($_FILES['stock_image']['type'], $allowed)) {
                    $ext = pathinfo($_FILES['stock_image']['name'], PATHINFO_EXTENSION);
                    $image_filename = "stock_" . time() . "." . $ext;
                    $target = "../uploads/stock/";
                    if (!is_dir($target)) mkdir($target, 0777, true);
                    move_uploaded_file($_FILES['stock_image']['tmp_name'], $target . $image_filename);
                }
            }

            $count = 0;
            foreach ($items as $item) {
                $sn = mysqli_real_escape_string($conn, trim($item['serial']));
                $price = floatval($item['price']); // ราคาของชิ้นนั้นๆ

                if (empty($sn)) continue;

                // 1. สร้าง Stock ID
                $stock_id = getNextStockId($conn);

                // 2. Insert prod_stocks (บันทึกราคาและรูปภาพ)
                $sql_insert = "INSERT INTO prod_stocks (stock_id, serial_no, price, stock_status, image_path, create_at, update_at, products_prod_id) 
                               VALUES (?, ?, ?, 'In Stock', ?, NOW(), NOW(), ?)";
                $stmt = $conn->prepare($sql_insert);
                $stmt->bind_param("isdsi", $stock_id, $sn, $price, $image_filename, $prod_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("บันทึก Serial: $sn ไม่สำเร็จ (อาจซ้ำ)");
                }
                $stmt->close();

                // 3. Insert Movement (IN)
                $move_id = getNextMovementId($conn);
                $sql_move = "INSERT INTO stock_movements (movement_id, movement_type, ref_table, ref_id, create_at, prod_stocks_stock_id) 
                             VALUES (?, 'IN', 'manual_scan_barcode', ?, NOW(), ?)";
                $stmt_move = $conn->prepare($sql_move);
                $ref_dummy = 0;
                $stmt_move->bind_param("iii", $move_id, $ref_dummy, $stock_id);
                $stmt_move->execute();
                $stmt_move->close();

                $count++;
            }

            mysqli_commit($conn);
            $_SESSION['success'] = "นำเข้าสินค้าสำเร็จจำนวน $count ชิ้น";
            header("Location: add_stock_barcode.php");
            exit;

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รับสินค้า (Scan & Price)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background: <?= $background_color ?>; font-family: '<?= $font_style ?>'; }
        .card-custom { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .scan-btn { height: 80px; font-size: 1.3rem; border-radius: 12px; border: 2px dashed #198754; background: #f0fff4; color: #198754; transition: all 0.3s; }
        .scan-btn:hover { background: #198754; color: #fff; border-color: #198754; }
        #reader { width: 100%; border-radius: 10px; overflow: hidden; background: #000; }
        .price-input { width: 120px; text-align: right; }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container" style="max-width: 1000px;">
                    
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle me-2"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card card-custom mb-4">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h4 class="mb-0 text-primary"><i class="fas fa-barcode me-2"></i>รับสินค้าเข้า (Scan Barcode)</h4>
                            <a href="prod_stock.php" class="btn btn-outline-secondary btn-sm">กลับหน้ารายการ</a>
                        </div>
                        
                        <form method="POST" id="mainForm" enctype="multipart/form-data">
                            <input type="hidden" name="save_stock" value="1">
                            
                            <div class="card-body p-4">
                                <div class="row g-4">
                                    
                                    <div class="col-md-4 border-end">
                                        <h6 class="fw-bold mb-3 text-secondary">1. ตั้งค่าสินค้า</h6>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">สินค้า <span class="text-danger">*</span></label>
                                            <select name="product_id" id="product_id" class="form-select" required>
                                                <option value="">-- กรุณาเลือกสินค้า --</option>
                                                <?php while($p = mysqli_fetch_assoc($products)): ?>
                                                    <option value="<?= $p['prod_id'] ?>">
                                                        <?= $p['prod_name'] ?> <?= $p['model_name'] ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">ราคาตั้งต้น (ต่อชิ้น)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">฿</span>
                                                <input type="number" id="default_price" class="form-control" placeholder="0.00" min="0" step="0.01">
                                            </div>
                                            <small class="text-muted">ราคานี้จะถูกใส่ให้อัตโนมัติเมื่อสแกน</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">รูปภาพสินค้า (ถ้ามี)</label>
                                            <input type="file" name="stock_image" class="form-control" accept="image/*">
                                            <small class="text-muted">รูปนี้จะถูกใช้กับทุกชิ้นในล็อตนี้</small>
                                        </div>

                                        <hr>
                                        
                                        <button type="button" class="btn w-100 scan-btn mb-2" onclick="openScanner()">
                                            <i class="fas fa-qrcode fa-lg"></i> เริ่มสแกน
                                        </button>
                                        <div id="scanner-container" style="display:none;">
                                            <div id="reader" class="mb-2"></div>
                                            <button type="button" class="btn btn-danger btn-sm w-100" onclick="stopScanner()">ปิดกล้อง</button>
                                        </div>

                                    </div>

                                    <div class="col-md-8">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="fw-bold mb-0 text-secondary">2. รายการที่สแกน (<span id="countDisplay">0</span>)</h6>
                                            <button type="submit" class="btn btn-success" id="btnSave" disabled>
                                                <i class="fas fa-save me-2"></i> บันทึกทั้งหมด
                                            </button>
                                        </div>

                                        <div class="table-responsive border rounded" style="max-height: 500px;">
                                            <table class="table table-hover mb-0 align-middle">
                                                <thead class="table-light sticky-top">
                                                    <tr>
                                                        <th style="width: 5%;">#</th>
                                                        <th style="width: 55%;">Serial Number / IMEI</th>
                                                        <th style="width: 30%;">ราคา (บาท)</th>
                                                        <th style="width: 10%;" class="text-center">ลบ</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="scanListBody">
                                                    <tr><td colspan="4" class="text-center text-muted py-5">ยังไม่มีรายการที่สแกน<br>กรุณาเลือกสินค้าและเริ่มสแกน</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form> </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        let html5QrcodeScanner = null;
        let scannedItems = []; // เก็บ Object: { serial: 'xxx', price: 100 }
        let isScanning = false;

        function openScanner() {
            const prodId = document.getElementById('product_id').value;
            if(!prodId) {
                Swal.fire('แจ้งเตือน', 'กรุณาเลือกสินค้าก่อนเริ่มสแกน', 'warning');
                return;
            }

            document.getElementById('scanner-container').style.display = 'block';
            if (!html5QrcodeScanner) { html5QrcodeScanner = new Html5Qrcode("reader"); }

            const config = { fps: 10, qrbox: { width: 250, height: 250 } };
            html5QrcodeScanner.start({ facingMode: "environment" }, config, onScanSuccess, onScanFailure)
            .catch(err => {
                // Fallback ถ้าเปิดกล้องหลังไม่ได้ ให้ลองเปิดกล้องหน้าหรือ Webcam
                html5QrcodeScanner.start({ facingMode: "user" }, config, onScanSuccess, onScanFailure)
                .catch(err2 => {
                    Swal.fire('เปิดกล้องไม่ได้', 'กรุณาตรวจสอบการอนุญาต หรือใช้งานผ่าน HTTPS', 'error');
                    document.getElementById('scanner-container').style.display = 'none';
                });
            });
            isScanning = true;
        }

        async function onScanSuccess(decodedText, decodedResult) {
            if (!isScanning) return;
            
            const serial = decodedText.trim();
            // เช็คซ้ำในรายการหน้าเว็บ
            if (scannedItems.some(item => item.serial === serial)) {
                Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'ซ้ำ! สแกนไปแล้ว', showConfirmButton: false, timer: 1000 });
                return; 
            }

            // หยุดสแกนชั่วคราวเพื่อถาม
            // (ถ้าไม่ต้องการถาม ให้ comment ส่วน Swal.fire นี้ แล้วเรียก addItem() เลย)
            /*
            html5QrcodeScanner.pause();
            const { isConfirmed } = await Swal.fire({
                title: 'สแกนสำเร็จ: ' + serial,
                text: 'ต้องการบันทึกและสแกนต่อหรือไม่?',
                showCancelButton: true,
                confirmButtonText: 'บันทึก & ต่อ',
                cancelButtonText: 'ยกเลิก'
            });
            if(isConfirmed) {
                addItem(serial);
                html5QrcodeScanner.resume();
            } else {
                html5QrcodeScanner.resume();
            }
            */
           
           // แบบไม่ถาม (Scan Loop เร็วๆ)
           addItem(serial);
           Swal.fire({ toast: true, position: 'center', icon: 'success', title: 'เพิ่ม: ' + serial, showConfirmButton: false, timer: 800 });
        }

        function onScanFailure(error) {}

        function stopScanner() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.stop().then(() => {
                    document.getElementById('scanner-container').style.display = 'none';
                    isScanning = false;
                });
            }
        }

        // เพิ่มรายการลง Array และอัปเดตตาราง
        function addItem(serial) {
            const defaultPrice = document.getElementById('default_price').value || 0;
            scannedItems.push({ serial: serial, price: defaultPrice });
            renderTable();
        }

        function renderTable() {
            const tbody = document.getElementById('scanListBody');
            const countDisplay = document.getElementById('countDisplay');
            const btnSave = document.getElementById('btnSave');

            tbody.innerHTML = '';

            if (scannedItems.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-5">ยังไม่มีรายการที่สแกน<br>กรุณาเลือกสินค้าและเริ่มสแกน</td></tr>';
                btnSave.disabled = true;
            } else {
                // วนลูปสร้างแถว (ย้อนกลับเพื่อให้ล่าสุดอยู่บน)
                [...scannedItems].reverse().forEach((item, index) => {
                    // index จริงใน array (เพราะเรา reverse loop)
                    const realIndex = scannedItems.length - 1 - index;
                    
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="text-center">${scannedItems.length - index}</td>
                        <td>
                            <input type="text" name="items[${realIndex}][serial]" class="form-control form-control-sm" value="${item.serial}" readonly>
                        </td>
                        <td>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">฿</span>
                                <input type="number" name="items[${realIndex}][price]" class="form-control text-end" value="${item.price}" min="0" step="0.01">
                            </div>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem(${realIndex})"><i class="fas fa-times"></i></button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
                btnSave.disabled = false;
            }
            countDisplay.innerText = scannedItems.length;
        }

        window.removeItem = function(index) {
            scannedItems.splice(index, 1);
            renderTable();
        }

        // ป้องกันการ Submit ว่าง
        document.getElementById('mainForm').addEventListener('submit', function(e) {
            if (scannedItems.length === 0) {
                e.preventDefault();
                Swal.fire('ข้อผิดพลาด', 'กรุณาสแกนสินค้าอย่างน้อย 1 ชิ้น', 'error');
            }
        });
        
        // เตือนเมื่อเปลี่ยนสินค้า
        document.getElementById('product_id').addEventListener('change', function() {
            if(scannedItems.length > 0) {
                if(confirm('การเปลี่ยนสินค้าจะล้างรายการที่สแกนไว้ทั้งหมด ยืนยัน?')) {
                    scannedItems = [];
                    renderTable();
                } else {
                    // (คืนค่าเดิม ต้องเขียน logic เพิ่มถ้าต้องการเป๊ะๆ)
                }
            }
        });
    </script>
</body>
</html>