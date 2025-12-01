<?php
session_start();
// (ไฟล์นี้อยู่ใน /purchase/ จึงต้องใช้ ../)
require '../config/config.php';
checkPageAccess($conn, 'receive_po');

// -----------------------------------------------------------------------------
// 1. INITIALIZE MODE & VALIDATE PO
// -----------------------------------------------------------------------------

$po_id = null;
$po_data = null;
$po_items = [];
$page_title = "รับสินค้าเข้าจาก PO";
$page_icon = "fa-truck-loading";

// (JS-Shared Data)
// เราจะสร้างตัวแปร PHP เพื่อส่งค่า "ยอดค้างรับ" ไปให้ Javascript
$js_pending_data = [];

// ตรวจสอบว่ามี ?po_id=... ใน URL หรือไม่
if (isset($_GET['po_id']) && (int)$_GET['po_id'] > 0) {
    $po_id = (int)$_GET['po_id'];

    // -----------------------------------------------------------------------------
    // 2. ดึงข้อมูล PO และรายการสินค้าที่ "ค้างรับ"
    // -----------------------------------------------------------------------------

    // ดึงข้อมูลใบสั่งซื้อ (PO Header)
    $po_sql = "SELECT po.*, s.co_name as supplier_name 
               FROM purchase_orders po
               LEFT JOIN suppliers s ON po.suppliers_supplier_id = s.supplier_id
               WHERE po.purchase_id = $po_id";
    $po_result = mysqli_query($conn, $po_sql);
    $po_data = mysqli_fetch_assoc($po_result);

    if (!$po_data) {
        $_SESSION['error'] = "ไม่พบใบสั่งซื้อ (PO) ที่ระบุ";
        header('Location: purchase_order.php'); // (กลับไปหน้ารายการ PO)
        exit;
    }

    // ดึงรายการสินค้าใน PO (PO Details)
    // (เราต้องนับด้วยว่ารับไปแล้วเท่าไหร่ เพื่อคุมยอด)
    $items_sql = "SELECT 
                    od.order_id, 
                    od.products_prod_id, 
                    od.amount AS amount_ordered, 
                    od.price AS po_cost_price,
                    p.prod_name, 
                    p.model_name, 
                    p.prod_price as default_selling_price,
                    pb.brand_name_th as brand_name,
                    (SELECT COUNT(*) FROM stock_movements sm WHERE sm.ref_table = 'order_details' AND sm.ref_id = od.order_id) as amount_received
                  FROM order_details od
                  LEFT JOIN products p ON od.products_prod_id = p.prod_id
                  LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id
                  WHERE od.purchase_orders_purchase_id = $po_id";

    $items_result = mysqli_query($conn, $items_sql);
    while ($item = mysqli_fetch_assoc($items_result)) {
        // คำนวณยอดค้างรับ
        $item['amount_pending'] = $item['amount_ordered'] - $item['amount_received'];
        // (เฉพาะสินค้าที่ยังรับไม่ครบเท่านั้น)
        if ($item['amount_pending'] > 0) {
            $po_items[] = $item;
            // (เตรียมข้อมูลให้ Javascript)
            $js_pending_data[$item['order_id']] = $item['amount_pending'];
        }
    }

    // ถ้าไม่มีรายการค้างรับ (รับครบหมดแล้ว)
    if (empty($po_items)) {
        $_SESSION['success'] = "ใบสั่งซื้อ (PO ID: $po_id) นี้ รับสินค้าเข้าครบแล้ว";
        header('Location: purchase_order.php');
        exit;
    }
} else {
    // ถ้าเข้าหน้านี้โดยไม่มี po_id
    $_SESSION['error'] = "กรุณาระบุรหัสใบสั่งซื้อ (PO ID) ที่ต้องการรับเข้า";
    header('Location: purchase_order.php');
    exit;
}

// -----------------------------------------------------------------------------
// 3. SHARED FUNCTIONS (Helper)
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

// (ฟังก์ชันสำหรับจัดการอัปโหลดรูปภาพของ Batch)
function handleBatchImageUpload($file_key_name)
{
    if (isset($_FILES[$file_key_name]) && $_FILES[$file_key_name]['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/products/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $tmp_name = $_FILES[$file_key_name]['tmp_name'];
        $file_extension = pathinfo($_FILES[$file_key_name]['name'], PATHINFO_EXTENSION);
        // (ใช้ uniqueid() เพื่อให้ชื่อไฟล์ไม่ซ้ำกันแน่นอน)
        $new_filename = uniqid('stock_', true) . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        if (move_uploaded_file($tmp_name, $upload_path)) {
            return $new_filename; // คืนค่า "ชื่อไฟล์"
        }
    }
    return NULL; // ถ้าไม่มีไฟล์ หรืออัปโหลดไม่สำเร็จ
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
// 5. POST HANDLER (จัดการการบันทึกข้อมูล)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {

    $po_id = (int)$_POST['po_id'];
    $date_in = !empty($_POST['date_in']) ? mysqli_real_escape_string($conn, $_POST['date_in']) : date('Y-m-d');

    mysqli_autocommit($conn, false);
    $success_count = 0;
    $stock_ids = [];

    try {

        // (A) --- Pre-Validation (ตรวจสอบยอดรวมก่อน) ---
        $total_receiving_by_item = [];
        if (!isset($_POST['items'])) {
            throw new Exception('ไม่พบรายการสินค้าที่ต้องการรับเข้า (No items posted)');
        }

        $items_posted = $_POST['items'];

        // (A.1 - วนลูปนับยอดรวมที่กรอกมา)
        foreach ($items_posted as $order_detail_id => $batches) {
            $total_receiving_by_item[$order_detail_id] = 0;
            // (ตรวจสอบว่า $batches เป็น array ก่อน)
            if (is_array($batches)) {
                foreach ($batches as $batch_id => $batch_data) {
                    $total_receiving_by_item[$order_detail_id] += (int)$batch_data['quantity'];
                }
            }
        }

        // (A.2 - ดึงยอดค้างรับจริงจาก DB)
        $ids_to_check_str = implode(',', array_keys($total_receiving_by_item));
        if (empty($ids_to_check_str)) {
            throw new Exception('ไม่พบรายการสินค้าที่ต้องการรับ (Empty selection)');
        }

        $check_sql = "SELECT order_id, (od.amount - (SELECT COUNT(*) FROM stock_movements sm WHERE sm.ref_table = 'order_details' AND sm.ref_id = od.order_id)) as pending 
                      FROM order_details od WHERE od.order_id IN ($ids_to_check_str)";
        $check_result = mysqli_query($conn, $check_sql);

        while ($row = mysqli_fetch_assoc($check_result)) {
            $order_id = $row['order_id'];
            $pending_qty = (int)$row['pending'];

            // (A.3 - เปรียบเทียบ)
            if ($total_receiving_by_item[$order_id] > $pending_qty) {
                throw new Exception("รับสินค้า (Item ID: $order_id) เกินจำนวนที่สั่ง (ค้างรับ $pending_qty, พยายามรับ $total_receiving_by_item[$order_id])");
            }
        }

        // (B) --- Main Save Loop (วนลูปตาม POSTed data) ---
        foreach ($items_posted as $order_detail_id => $batches) {

            if (!is_array($batches)) continue; // ข้ามถ้าไม่มี batch

            foreach ($batches as $batch_id => $batch_data) { // (FIXED: $batch_id ไม่ใช่ $batch_index)

                $quantity_to_receive = (int)$batch_data['quantity'];
                if ($quantity_to_receive == 0) continue; // ข้าม Batch นี้ถ้ากรอก 0

                $products_prod_id = (int)$batch_data['product_id'];
                $selling_price = floatval($batch_data['selling_price']);

                // (B.1 - Validate Serials)
                if (!isset($batch_data['serial_no']) || count($batch_data['serial_no']) != $quantity_to_receive) {
                    throw new Exception("จำนวน Serial Number (Batch: $batch_id) ไม่ตรงกับจำนวนที่รับ");
                }

                $serial_list = $batch_data['serial_no'];
                $unique_serials_in_batch = [];

                foreach ($serial_list as $serial) {
                    if (empty(trim($serial))) throw new Exception('กรุณากรอก Serial Number ให้ครบทุกชิ้น');
                    if (checkSerialExists($conn, $serial)) throw new Exception("Serial Number: $serial มีอยู่ในระบบแล้ว");
                    if (in_array($serial, $unique_serials_in_batch)) throw new Exception("Serial Number: $serial ซ้ำกันใน Batch เดียว");
                    $unique_serials_in_batch[] = $serial;
                }

                // (B.2 - Handle Image Upload for THIS batch)
                $image_file_key = "batch_image_{$batch_id}"; // (FIXED: ใช้ $batch_id)
                $image_path_for_this_batch = handleBatchImageUpload($image_file_key);

                // (B.3 - Loop Insert Serials for this batch)
                foreach ($serial_list as $serial) {
                    $stock_id = getNextStockId($conn);
                    $serial_escaped = mysqli_real_escape_string($conn, trim($serial));

                    // (INSERT Stock)
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
                        $selling_price,
                        $image_path_for_this_batch, // (ใช้รูปของ Batch นี้)
                        $products_prod_id
                    );
                    if (!$stmt->execute()) throw new Exception('ไม่สามารถเพิ่มสต็อกได้: ' . $stmt->error);
                    $stmt->close();

                    // (INSERT Movement - อ้างอิง 'order_details')
                    $move_id = getNextMovementId($conn);
                    $ref_table = 'order_details';
                    $move_stmt = $conn->prepare(
                        "INSERT INTO stock_movements 
                            (movement_id, movement_type, ref_table, ref_id, prod_stocks_stock_id, prodout_types_outtype_id, create_at) 
                         VALUES (?, 'IN', ?, ?, ?, NULL, NOW())"
                    );
                    $move_stmt->bind_param(
                        "isii", // i=move_id, s=ref_table, i=ref_id, i=stock_id
                        $move_id,
                        $ref_table,
                        $order_detail_id,
                        $stock_id
                    );
                    if (!$move_stmt->execute()) throw new Exception('ไม่สามารถบันทึก Movement ได้: ' . $move_stmt->error);
                    $move_stmt->close();

                    $success_count++;
                    $stock_ids[] = $stock_id;
                }
            }
        }

        // (C) --- Commit และ Redirect ---
        mysqli_commit($conn);
        mysqli_autocommit($conn, true);

        $stock_range = count($stock_ids) > 1 ? $stock_ids[0] . '-' . $stock_ids[count($stock_ids) - 1] : (isset($stock_ids[0]) ? $stock_ids[0] : 'N/A');

        $_SESSION['success'] = "รับสินค้าเข้าสต็อกสำเร็จ จำนวน $success_count ชิ้น (รหัส: $stock_range)";
        header('Location: ../prod_stock/prod_stock.php'); // (กลับไปหน้าสต็อก)
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
        $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        // (Reload หน้ารับ PO เดิม)
        header("Location: receive_po.php?po_id=$po_id");
        exit;
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

        .btn-add-batch {
            background-color: <?= $theme_color ?>;
            color: white;
        }

        /* (CSS สำหรับ Batch) */
        .batch-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
            position: relative;
        }

        .batch-header {
            font-weight: 600;
            color: <?= $theme_color ?>;
            margin-bottom: 10px;
        }

        .batch-remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .serial-row {
            background-color: #fff;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 8px;
            border: 1px solid #dee2e6;
        }

        .item-number {
            background: #6c757d;
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 10px;
            font-size: 0.8rem;
        }

        .image-preview {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            min-height: 150px;
        }

        .batch-image-preview {
            max-width: 100px;
            max-height: 100px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-top: 10px;
            cursor: pointer;
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

        .po-item-card {
            border: 2px solid #dee2e6;
            background: #fdfdfd;
        }

        .po-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .po-item-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }

        .po-item-pending {
            font-size: 1rem;
            font-weight: 600;
            color: <?= $theme_color ?>;
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

                    <h4 class="mb-4"><i class="fas <?= $page_icon ?> me-2"></i><?= $page_title ?></h4>

                    <form method="POST" enctype="multipart/form-data" id="addStockForm" novalidate>
                        <input type="hidden" name="po_id" value="<?= $po_id ?>">

                        <div class="form-section">
                            <h5>ข้อมูลใบสั่งซื้อ (PO)</h5>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td style="width: 120px;"><strong>PO ID:</strong></td>
                                    <td><?= htmlspecialchars($po_data['purchase_id']) ?></td>
                                    <td style="width: 120px;"><strong>วันที่สั่ง:</strong></td>
                                    <td><?= date('d/m/Y', strtotime($po_data['purchase_date'])) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>ผู้จำหน่าย:</strong></td>
                                    <td colspan="3"><?= htmlspecialchars($po_data['supplier_name']) ?></td>
                                </tr>
                            </table>
                        </div>

                        <div class="form-section">
                            <h5>รายการรับสินค้าเข้า</h5>

                            <?php foreach ($po_items as $i => $item): ?>
                                <div class="form-section po-item-card" id="item-card-<?= $item['order_id'] ?>">

                                    <div class="po-item-header">
                                        <div>
                                            <div class="po-item-title"><?= htmlspecialchars($item['prod_name']) ?> (<?= htmlspecialchars($item['brand_name']) ?>)</div>
                                            <small class="text-muted">Item ID: <?= $item['order_id'] ?></small>
                                        </div>
                                        <div class="po-item-pending">
                                            ค้างรับ: <span id="pending-qty-<?= $item['order_id'] ?>"><?= $item['amount_pending'] ?></span> ชิ้น
                                        </div>
                                    </div>

                                    <div id="batches-container-<?= $item['order_id'] ?>">
                                    </div>

                                    <button type="button" class="btn btn-sm btn-add-batch mt-2"
                                        onclick="addBatch(<?= $item['order_id'] ?>, <?= $item['products_prod_id'] ?>, '<?= number_format($item['default_selling_price'], 2, '.', '') ?>')">
                                        <i class="fas fa-plus me-1"></i> เพิ่มชุดรับเข้า (Batch)
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="form-section">
                            <h5><i class="fas fa-calendar-alt me-2"></i>ข้อมูลวันที่</h5>
                            <label class="form-label">วันที่เข้าสต็อก</label>
                            <input type="date" class="form-control" name="date_in" id="date_in" style="width: 200px;">
                            <small class="text-muted">หากไม่เลือก จะใช้วันที่ปัจจุบันอัตโนมัติ</small>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-success" id="submitBtn">
                                <i class="fas fa-save me-2"></i> บันทึกการรับสินค้า
                            </button>
                            <a href="purchase_order.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i> ย้อนกลับ
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // (ส่งข้อมูลยอดค้างรับจาก PHP มาให้ Javascript)
        const g_pending_data = <?= json_encode($js_pending_data) ?>;

        // (ตัวแปร Javascript สำหรับนับยอดที่ "กำลังจะรับ" ในฟอร์ม)
        let g_allocated_data = {};
        <?php foreach ($js_pending_data as $order_id => $pending_qty): ?>
            g_allocated_data[<?= $order_id ?>] = 0;
        <?php endforeach; ?>

        // (*** FIXED ***: ตัวนับ Batch แยกตาม Order ID)
        let g_batch_counters = {};
        <?php foreach ($js_pending_data as $order_id => $pending_qty): ?>
            g_batch_counters[<?= $order_id ?>] = 0; // (เริ่มต้นที่ 0)
        <?php endforeach; ?>


        document.addEventListener('DOMContentLoaded', function() {
            setTodayDate();

            // (เมื่อโหลดหน้า ให้สร้าง Batch แรกให้ทุก Item อัตโนมัติ)
            <?php foreach ($po_items as $item): ?>
                addBatch(<?= $item['order_id'] ?>, <?= $item['products_prod_id'] ?>, '<?= number_format($item['default_selling_price'], 2, '.', '') ?>');
            <?php endforeach; ?>
        });

        function setTodayDate() {
            const dateInput = document.getElementById('date_in');
            if (!dateInput.value) {
                dateInput.value = new Date().toISOString().split('T')[0];
            }
        }

        // --- (JS: BATCH MANAGEMENT) ---

        function addBatch(order_detail_id, product_id, default_price) {

            const max_pending = g_pending_data[order_detail_id];
            const allocated = g_allocated_data[order_detail_id];

            if (allocated >= max_pending) {
                alert('คุณได้จัดสรรจำนวนรับเข้าครบตามยอดค้างรับของสินค้านี้แล้ว');
                return;
            }

            // (*** FIXED ***: ใช้ตัวนับที่แยกกัน)
            g_batch_counters[order_detail_id]++;
            const batch_count = g_batch_counters[order_detail_id];
            // (*** FIXED ***: สร้าง ID ที่ไม่ซ้ำกันแน่นอน)
            const batch_id = `${order_detail_id}_${batch_count}`; // เช่น "101_1", "101_2"

            const container = document.getElementById(`batches-container-${order_detail_id}`);

            const batchDiv = document.createElement('div');
            batchDiv.className = 'batch-box';
            batchDiv.id = `batch-${batch_id}`; // (FIXED: ใช้ ID ใหม่)

            const remaining_for_this_batch = max_pending - allocated;

            // (สร้าง HTML สำหรับ Batch)
            batchDiv.innerHTML = `
                <button type="button" class="btn-close batch-remove-btn" title="ลบชุดนี้" onclick="removeBatch(this, ${order_detail_id})"></button>
                <div class="batch-header">ชุดรับเข้า (Batch) #${batch_count}</div>
                
                <input type="hidden" name="items[${order_detail_id}][${batch_id}][product_id]" value="${product_id}">
                
                <div class="row gy-3">
                    <div class="col-md-4">
                        <label class="form-label">จำนวนที่รับ (ชุดนี้) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control batch-quantity" 
                               name="items[${order_detail_id}][${batch_id}][quantity]" 
                               min="0" max="${remaining_for_this_batch}" value="0" required
                               data-order-id="${order_detail_id}" 
                               data-batch-id="${batch_id}"> 
                        <div class="error-feedback">กรอกจำนวนไม่เกิน ${remaining_for_this_batch}</div>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">ราคาขาย (หน้าร้าน) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">฿</span>
                            <input type="number" class="form-control" 
                                   name="items[${order_detail_id}][${batch_id}][selling_price]" 
                                   value="${default_price}" step="0.01" min="0.01" required>
                        </div>
                        <div class="error-feedback">กรุณากรอกราคาขาย</div>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">รูปภาพ (สำหรับชุดนี้)</label>
                        <input type="file" class="form-control batch-image" 
                               name="batch_image_${batch_id}" 
                               accept="image/*" onchange="previewImageInBatch(this)">
                        <img src="" alt="Preview" class="batch-image-preview" style="display:none;" onclick="this.previousElementSibling.click()">
                    </div>
                </div>
                
                <div id="serials-container-${batch_id}" class="mt-3">
                    </div>
            `;

            container.appendChild(batchDiv);

            // (*** FIXED ***: เพิ่ม Event Listener ที่นี่ แทนการใช้ oninput="")
            const newQuantityInput = batchDiv.querySelector('.batch-quantity');
            newQuantityInput.addEventListener('input', function() {
                // (ลำดับสำคัญมาก)
                // 1. ปัดค่าช่องนี้ และสร้าง Serial
                generateSerialFields(this);
                // 2. อัปเดตยอดรวม และอัปเดต 'max' ของช่องอื่น
                updateAllocatedQty(this.dataset.orderId);
            });

            updateAllocatedQty(order_detail_id); // (อัปเดตยอดรวม)
        }

        function removeBatch(button, order_detail_id) {
            button.closest('.batch-box').remove();
            updateAllocatedQty(order_detail_id); // (อัปเดตยอดรวม)
        }

        function updateAllocatedQty(order_detail_id) {
            let total_allocated = 0;
            const batch_inputs = document.querySelectorAll(`#batches-container-${order_detail_id} .batch-quantity`);

            batch_inputs.forEach(input => {
                total_allocated += parseInt(input.value) || 0;
            });

            g_allocated_data[order_detail_id] = total_allocated;

            // (อัปเดต Max ของทุก Batch ใน Item นี้)
            const max_pending = g_pending_data[order_detail_id];
            batch_inputs.forEach(input => {
                const current_val = parseInt(input.value) || 0;
                const new_max = (max_pending - total_allocated) + current_val;
                input.max = new_max;
                input.nextElementSibling.textContent = `กรอกจำนวนไม่เกิน ${new_max}`; // (อัปเดต error message)
            });
        }

        // (*** FIXED ***: ฟังก์ชันนี้จะทำหน้าที่ปัดค่า (Cap) ด้วย)
        function generateSerialFields(quantityInput) {
            const batch_id = quantityInput.dataset.batchId;
            const order_detail_id = quantityInput.dataset.orderId;
            let quantity = parseInt(quantityInput.value) || 0;
            const max = parseInt(quantityInput.max);

            // (*** FIXED ***: นี่คือจุดที่ต้องปัดค่า)
            if (quantity > max) {
                quantityInput.value = max; // (บังคับค่าสูงสุดในช่องนี้)
                quantity = max;
                // (เราอาจจะ alert/warning ตรงนี้ก็ได้ แต่ user บอกให้ปัด)
            }

            const container = document.getElementById(`serials-container-${batch_id}`);
            container.innerHTML = ''; // (ล้างของเก่า)

            for (let i = 1; i <= quantity; i++) {
                // (*** FIXED ***: ใช้ batch_id ในการสร้าง name)
                const fieldName = `items[${order_detail_id}][${batch_id}][serial_no][]`;
                container.appendChild(createSerialField(fieldName, i));
            }
        }

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
            // (เพิ่ม Event Listener ที่นี่)
            row.querySelector('.serial-input').addEventListener('input', function() {
                checkSerial(this);
            });
            return row;
        }

        // --- (JS: SHARED UTILITIES) ---

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

        function previewImageInBatch(input) {
            const preview = input.nextElementSibling; // (หา <img> ที่อยู่ถัดไป)
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.src = '';
                preview.style.display = 'none';
            }
        }

        // --- (JS: FORM VALIDATION) ---
        document.getElementById('addStockForm').addEventListener('submit', function(e) {
            let isValid = true;

            // (Validate Fields)
            document.querySelectorAll('input[name*="[selling_price]"]').forEach(field => {
                if (parseFloat(field.value) <= 0) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            document.querySelectorAll('.batch-quantity').forEach(field => {
                if (parseInt(field.value) < 0) { // (อนุญาต 0, แต่ห้ามติดลบ)
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
                } else if (input.classList.contains('is-invalid')) {
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

            // (Validate Total Qty vs Pending)
            for (const order_id in g_allocated_data) {
                // (*** FIXED ***: ตรวจสอบอีกครั้งก่อนส่ง)
                if (g_allocated_data[order_id] > g_pending_data[order_id]) {
                    alert(`ยอดรับของ Item ID ${order_id} เกินจำนวนค้างรับ! (ยอดรวม ${g_allocated_data[order_id]} > ค้างรับ ${g_pending_data[order_id]})`);
                    isValid = false;
                }
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