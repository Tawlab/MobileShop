<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'edit_purchase_order');

// -----------------------------------------------------------------------------
// VALIDATE & GET PO ID
// -----------------------------------------------------------------------------
$po_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($po_id <= 0) {
    $_SESSION['error'] = 'ไม่พบรหัสใบสั่งซื้อ (PO) ที่ต้องการแก้ไข';
    header('Location: purchase_order.php');
    exit;
}

// -----------------------------------------------------------------------------
// บันทึกการแก้ไข)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['po_id'])) {

    $po_id = (int)$_POST['po_id'];
    $purchase_date = mysqli_real_escape_string($conn, $_POST['purchase_date']);
    $supplier_id = (int)$_POST['supplier_id'];
    $branch_id = (int)$_POST['branch_id'];
    $employee_id = (int)$_POST['employee_id'];

    $items_data = $_POST['items'] ?? []; 

    // Validation
    if (empty($items_data)) {
        $_SESSION['error'] = 'ไม่สามารถบันทึก PO ที่ไม่มีรายการสินค้า (อย่างน้อย 1 รายการ)';
        header('Location: edit_purchase_order.php?id=' . $po_id);
        exit;
    }

    mysqli_autocommit($conn, false); 

    try {
        $sql_header = "UPDATE purchase_orders SET 
                        purchase_date = ?, 
                        suppliers_supplier_id = ?, 
                        branches_branch_id = ?, 
                        employees_emp_id = ?
                       WHERE purchase_id = ?";
        $stmt_header = $conn->prepare($sql_header);
        $stmt_header->bind_param("siiii", $purchase_date, $supplier_id, $branch_id, $employee_id, $po_id);
        if (!$stmt_header->execute()) {
            throw new Exception("ไม่สามารถอัปเดต PO Header: " . $stmt_header->error);
        }
        $stmt_header->close();

        // ดึง ID รายการสินค้าเก่าทั้งหมด 
        $old_item_ids_sql = "SELECT order_id FROM order_details WHERE purchase_orders_purchase_id = $po_id";
        $old_item_result = mysqli_query($conn, $old_item_ids_sql);
        $old_item_ids = [];
        while ($row = mysqli_fetch_assoc($old_item_result)) {
            $old_item_ids[$row['order_id']] = $row['order_id']; 
        }

        // วนลูปรายการสินค้าที่ส่งมา
        foreach ($items_data as $item_key => $item) {
            $order_detail_id = (int)$item['order_detail_id'];
            $product_id = (int)$item['product_id'];
            $amount = (int)$item['amount'];
            $price = floatval($item['price']);

            if ($product_id <= 0 || $amount <= 0 || $price < 0) {
                throw new Exception("ข้อมูลสินค้า (ID: $product_id) ไม่ถูกต้อง (จำนวนหรือราคา)");
            }

            // ตรวจสอบว่าสินค้านี้เคยถูกรับเข้าสต็อกไปแล้วหรือยัง
            $received_count = 0;
            if ($order_detail_id > 0) {
                $check_received_sql = "SELECT COUNT(*) as cnt FROM stock_movements WHERE ref_table = 'order_details' AND ref_id = ?";
                $stmt_check = $conn->prepare($check_received_sql);
                $stmt_check->bind_param("i", $order_detail_id);
                $stmt_check->execute();
                $received_count = (int)$stmt_check->get_result()->fetch_assoc()['cnt'];
                $stmt_check->close();
            }

            // กรณี "รายการใหม่" 
            if ($order_detail_id == 0) {
                $sql_insert = "INSERT INTO order_details (purchase_orders_purchase_id, products_prod_id, amount, price, create_at, update_at)
                               VALUES (?, ?, ?, ?, NOW(), NOW())";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("iiid", $po_id, $product_id, $amount, $price);
                if (!$stmt_insert->execute()) {
                    throw new Exception("ไม่สามารถเพิ่มรายการสินค้าใหม่: " . $stmt_insert->error);
                }
                $stmt_insert->close();
            }
            // กรณี "รายการเดิม" 
            else {
                // ถ้าเคยรับของแล้ว
                if ($received_count > 0) {
                    if ($amount < $received_count) {
                        throw new Exception("ไม่สามารถลดจำนวนสินค้า (ID: $product_id) ให้น้อยกว่าจำนวนที่รับไปแล้ว ($received_count)");
                    }
                    // ห้ามเปลี่ยนตัวสินค้า ถ้าเคยรับไปแล้ว
                    $sql_update = "UPDATE order_details SET amount = ?, price = ? WHERE order_id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("idi", $amount, $price, $order_detail_id);
                }
                // ถ้ายังไม่เคยรับของเลย 
                else {
                    $sql_update = "UPDATE order_details SET products_prod_id = ?, amount = ?, price = ? WHERE order_id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("iidi", $product_id, $amount, $price, $order_detail_id);
                }

                if (!$stmt_update->execute()) {
                    throw new Exception("ไม่สามารถอัปเดตรายการสินค้า (ID: $product_id): " . $stmt_update->error);
                }
                $stmt_update->close();

                //  ลบ ID นี้ออกจาก $old_item_ids (เพราะเราเจอมันในฟอร์มแล้ว)
                unset($old_item_ids[$order_detail_id]);
            }
        }

        //  ลบรายการสินค้าที่หายไป 
        if (!empty($old_item_ids)) {
            foreach ($old_item_ids as $id_to_delete) {
                // ตรวจสอบครั้งสุดท้ายว่าลบได้จริง
                $check_received_sql = "SELECT COUNT(*) as cnt FROM stock_movements WHERE ref_table = 'order_details' AND ref_id = ?";
                $stmt_check = $conn->prepare($check_received_sql);
                $stmt_check->bind_param("i", $id_to_delete);
                $stmt_check->execute();
                $received_count = (int)$stmt_check->get_result()->fetch_assoc()['cnt'];
                $stmt_check->close();

                if ($received_count > 0) {
                    throw new Exception("ไม่สามารถลบรายการสินค้า (ID: $id_to_delete) เพราะมีการรับสินค้าเข้าระบบไปแล้ว");
                }

                // ลบ
                $sql_delete = "DELETE FROM order_details WHERE order_id = ?";
                $stmt_delete = $conn->prepare($sql_delete);
                $stmt_delete->bind_param("i", $id_to_delete);
                if (!$stmt_delete->execute()) {
                    throw new Exception("ไม่สามารถลบรายการสินค้า (ID: $id_to_delete): " . $stmt_delete->error);
                }
                $stmt_delete->close();
            }
        }

        //  ถ้าทุกอย่างสำเร็จ
        mysqli_commit($conn);
        $_SESSION['success'] = "แก้ไขใบสั่งซื้อ (PO #$po_id) สำเร็จ";
        header('Location: purchase_order.php');
        exit;
    } catch (Exception $e) {
        //  ถ้ามีปัญหา
        mysqli_rollback($conn);
        $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        header('Location: edit_purchase_order.php?id=' . $po_id);
        exit;
    }

    mysqli_autocommit($conn, true);
}


// -----------------------------------------------------------------------------
// สำหรับแสดงฟอร์ม
// -----------------------------------------------------------------------------

//  ดึง PO Header
$po_sql = "SELECT * FROM purchase_orders WHERE purchase_id = $po_id";
$po_result = mysqli_query($conn, $po_sql);
$po_data = mysqli_fetch_assoc($po_result);

if (!$po_data) {
    $_SESSION['error'] = 'ไม่พบ PO ที่ต้องการแก้ไข';
    header('Location: purchase_order.php');
    exit;
}

// ตรวจสอบสถานะ 
if ($po_data['po_status'] != 'Pending') {
    $_SESSION['error'] = "PO นี้ (สถานะ: {$po_data['po_status']}) ไม่สามารถแก้ไขได้";
    header('Location: purchase_order.php');
    exit;
}

// ดึง PO Items (พร้อมเช็กว่าเคยรับของหรือยัง)
$items_sql = "SELECT 
                od.*, 
                p.prod_name, 
                p.model_name, 
                pb.brand_name_th,
                (SELECT COUNT(*) FROM stock_movements sm WHERE sm.ref_table = 'order_details' AND sm.ref_id = od.order_id) as received_count
              FROM order_details od
              LEFT JOIN products p ON od.products_prod_id = p.prod_id
              LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id
              WHERE od.purchase_orders_purchase_id = $po_id
              ORDER BY od.order_id ASC";
$items_result = mysqli_query($conn, $items_sql);
$po_items = [];
while ($row = mysqli_fetch_assoc($items_result)) {
    $po_items[] = $row;
}

// ดึงข้อมูล Dropdowns
$suppliers_result = mysqli_query($conn, "SELECT supplier_id, co_name FROM suppliers ORDER BY co_name");
$branches_result = mysqli_query($conn, "SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
$employees_result = mysqli_query($conn, "SELECT emp_id, firstname_th, lastname_th FROM employees WHERE emp_status = 'Active' ORDER BY firstname_th");
$products_result = mysqli_query($conn, "SELECT p.prod_id, p.prod_name, p.model_name, p.prod_price, pb.brand_name_th 
                                        FROM products p 
                                        LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id 
                                        ORDER BY p.prod_name");
$products_js_array = [];
while ($row = mysqli_fetch_assoc($products_result)) {
    $products_js_array[] = $row;
}
mysqli_data_seek($products_result, 0); 

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขใบสั่งซื้อ (PO #<?= $po_id ?>)</title>
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
            max-width: 1400px;
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

        .form-control:disabled {
            background-color: #f8f9fa;
            border-style: dashed;
        }

        .btn-success {
            background-color: <?= $btn_add_color ?>;
            border-color: <?= $btn_add_color ?>;
        }

        .btn-add-item {
            background-color: <?= $theme_color ?>;
            color: white;
        }

        .btn-remove-item {
            background-color: <?= $btn_delete_color ?>;
            color: white;
        }

        .table th {
            background-color: <?= $header_bg_color ?>;
            color: <?= $header_text_color ?>;
            vertical-align: middle;
            text-align: center;
        }

        .table td {
            vertical-align: middle;
        }

        .item-row.locked {
            background-color: #f8f9fa;
            opacity: 0.7;
        }

        .item-row.locked .form-control,
        .item-row.locked .form-select,
        .item-row.locked .btn-remove-item {
            pointer-events: none;
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

                    <h4 class="mb-4"><i class="fas fa-edit me-2"></i>แก้ไขใบสั่งซื้อ (PO #<?= $po_id ?>)</h4>

                    <form method="POST" id="editPoForm">
                        <input type="hidden" name="po_id" value="<?= $po_id ?>">

                        <div class="form-section">
                            <h5>ข้อมูลหลัก (Header)</h5>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">วันที่สั่ง <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" name="purchase_date"
                                        value="<?= date('Y-m-d\TH:i', strtotime($po_data['purchase_date'])) ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">ผู้จำหน่าย (Supplier) <span class="text-danger">*</span></label>
                                    <select class="form-select" name="supplier_id" required>
                                        <option value="">-- เลือกผู้จำหน่าย --</option>
                                        <?php mysqli_data_seek($suppliers_result, 0); ?>
                                        <?php while ($row = mysqli_fetch_assoc($suppliers_result)): ?>
                                            <option value="<?= $row['supplier_id'] ?>" <?= ($row['supplier_id'] == $po_data['suppliers_supplier_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($row['co_name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">สาขา <span class="text-danger">*</span></label>
                                    <select class="form-select" name="branch_id" required>
                                        <option value="">-- เลือกสาขา --</option>
                                        <?php mysqli_data_seek($branches_result, 0); ?>
                                        <?php while ($row = mysqli_fetch_assoc($branches_result)): ?>
                                            <option value="<?= $row['branch_id'] ?>" <?= ($row['branch_id'] == $po_data['branches_branch_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($row['branch_name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">พนักงาน <span class="text-danger">*</span></label>
                                    <select class="form-select" name="employee_id" required>
                                        <option value="">-- เลือกพนักงาน --</option>
                                        <?php mysqli_data_seek($employees_result, 0); ?>
                                        <?php while ($row = mysqli_fetch_assoc($employees_result)): ?>
                                            <option value="<?= $row['emp_id'] ?>" <?= ($row['emp_id'] == $po_data['employees_emp_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($row['firstname_th'] . ' ' . $row['lastname_th']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h5>รายการสินค้า (Items)</h5>

                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th style="width: 40%;">สินค้า</th>
                                            <th style="width: 15%;">จำนวน <span class="text-danger">*</span></th>
                                            <th style="width: 20%;">ราคา/หน่วย (ต้นทุน) <span class="text-danger">*</span></th>
                                            <th style="width: 20%;">ยอดรวม</th>
                                            <th style="width: 5%;">ลบ</th>
                                        </tr>
                                    </thead>
                                    <tbody id="item-list-body">

                                        <?php foreach ($po_items as $item): ?>
                                            <?php
                                            // ตรวจสอบว่าแถวนี้ควรถูกล็อคหรือไม่
                                            $is_locked = $item['received_count'] > 0;
                                            $min_qty = $is_locked ? $item['received_count'] : 1;
                                            ?>
                                            <tr class="item-row <?= $is_locked ? 'locked' : '' ?>" data-key="<?= $item['order_id'] ?>">
                                                <input type="hidden" name="items[<?= $item['order_id'] ?>][order_detail_id]" value="<?= $item['order_id'] ?>">

                                                <td>
                                                    <select class="form-select product-select" name="items[<?= $item['order_id'] ?>][product_id]" required <?= $is_locked ? 'disabled' : '' ?>>
                                                        <option value="">-- เลือกสินค้า --</option>
                                                        <?php mysqli_data_seek($products_result, 0); ?>
                                                        <?php while ($p = mysqli_fetch_assoc($products_result)): ?>
                                                            <option value="<?= $p['prod_id'] ?>"
                                                                data-price="<?= $p['prod_price'] ?>"
                                                                <?= ($p['prod_id'] == $item['products_prod_id']) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($p['prod_name']) ?> (<?= htmlspecialchars($p['brand_name_th']) ?>)
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                    <?php if ($is_locked): ?>
                                                        <small class="text-danger">
                                                            <i class="fas fa-lock me-1"></i> ล็อค (รับของแล้ว <?= $item['received_count'] ?> ชิ้น)
                                                        </small>
                                                        <input type="hidden" name="items[<?= $item['order_id'] ?>][product_id]" value="<?= $item['products_prod_id'] ?>">
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control item-amount"
                                                        name="items[<?= $item['order_id'] ?>][amount]"
                                                        value="<?= $item['amount'] ?>" min="<?= $min_qty ?>" required>
                                                </td>
                                                <td>
                                                    <div class="input-group">
                                                        <span class="input-group-text">฿</span>
                                                        <input type="number" class="form-control item-price"
                                                            name="items[<?= $item['order_id'] ?>][price]"
                                                            value="<?= number_format($item['price'], 2, '.', '') ?>" step="0.01" min="0" required>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control item-total"
                                                        value="<?= number_format($item['amount'] * $item['price'], 2) ?>" readonly>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-remove-item btn-sm" <?= $is_locked ? 'disabled' : '' ?>>
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>

                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3">
                                                <button type="button" class="btn btn-add-item btn-sm" id="add-item-btn">
                                                    <i class="fas fa-plus me-1"></i> เพิ่มรายการสินค้า
                                                </button>
                                            </td>
                                            <td colspan="2" class="text-end">
                                                <h5 class="mb-0">ยอดรวมสุทธิ: <span id="grand-total" style="color: <?= $theme_color ?>;">0.00</span></h5>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-success" id="submitBtn">
                                <i class="fas fa-save me-2"></i> บันทึกการแก้ไข
                            </button>
                            <a href="purchase_order.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i> ย้อนกลับ
                            </a>
                        </div>
                    </form>
                </div>

                <template id="new-item-row-template">
                    <tr class="item-row" data-key="NEW_KEY">
                        <input type="hidden" name="items[NEW_KEY][order_detail_id]" value="0">

                        <td>
                            <select class="form-select product-select" name="items[NEW_KEY][product_id]" required>
                                <option value="">-- เลือกสินค้า --</option>
                                <?php mysqli_data_seek($products_result, 0); ?>
                                <?php while ($p = mysqli_fetch_assoc($products_result)): ?>
                                    <option value="<?= $p['prod_id'] ?>" data-price="<?= $p['prod_price'] ?>">
                                        <?= htmlspecialchars($p['prod_name']) ?> (<?= htmlspecialchars($p['brand_name_th']) ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </td>
                        <td>
                            <input type="number" class="form-control item-amount"
                                name="items[NEW_KEY][amount]"
                                value="1" min="1" required>
                        </td>
                        <td>
                            <div class="input-group">
                                <span class="input-group-text">฿</span>
                                <input type="number" class="form-control item-price"
                                    name="items[NEW_KEY][price]"
                                    value="0.00" step="0.01" min="0" required>
                            </div>
                        </td>
                        <td>
                            <input type="text" class="form-control item-total" value="0.00" readonly>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-remove-item btn-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                </template>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const itemListBody = document.getElementById('item-list-body');
            const addItemBtn = document.getElementById('add-item-btn');

            // ฟังก์ชันสำหรับอัปเดต Event Listeners ทั้งหมด
            function attachListeners() {
                //  ปุ่มลบแถว
                document.querySelectorAll('.btn-remove-item').forEach(btn => {
                    // ลบ event เก่ากันซ้ำ
                    btn.removeEventListener('click', removeRow);
                    // เพิ่ม event ใหม่
                    btn.addEventListener('click', removeRow);
                });

                //ช่อง Product Select
                document.querySelectorAll('.product-select').forEach(select => {
                    select.removeEventListener('change', updatePrice);
                    select.addEventListener('change', updatePrice);
                });

                // ช่อง Amount/Price
                document.querySelectorAll('.item-amount, .item-price').forEach(input => {
                    input.removeEventListener('input', calculateRowTotal);
                    input.addEventListener('input', calculateRowTotal);
                });
            }

            // ฟังก์ชันลบแถว
            function removeRow(event) {
                event.target.closest('tr').remove();
                calculateGrandTotal(); 
            }

            // ฟังก์ชันอัปเดตราคาทุน เมื่อเลือกสินค้า
            function updatePrice(event) {
                const select = event.target;
                const selectedOption = select.options[select.selectedIndex];
                const price = selectedOption.dataset.price || 0;

                const row = select.closest('tr');
                row.querySelector('.item-price').value = parseFloat(price).toFixed(2);
                calculateRowTotal({
                    target: row.querySelector('.item-price')
                }); // คำนวณใหม่
            }

            // ฟังก์ชันคำนวณยอดรวม "ต่อแถว"
            function calculateRowTotal(event) {
                const input = event.target;
                const row = input.closest('tr');

                const amount = parseFloat(row.querySelector('.item-amount').value) || 0;
                const price = parseFloat(row.querySelector('.item-price').value) || 0;

                const total = amount * price;
                row.querySelector('.item-total').value = total.toFixed(2);

                calculateGrandTotal(); // อัปเดตยอดรวมสุทธิ
            }

            // ฟังก์ชันคำนวณ "ยอดรวมสุทธิ"
            function calculateGrandTotal() {
                let grandTotal = 0;
                document.querySelectorAll('.item-row').forEach(row => {
                    const total = parseFloat(row.querySelector('.item-total').value) || 0;
                    grandTotal += total;
                });

                document.getElementById('grand-total').textContent = grandTotal.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            // ฟังก์ชัน "เพิ่มแถวใหม่"
            addItemBtn.addEventListener('click', function() {
                const template = document.getElementById('new-item-row-template');
                const newRowHtml = template.innerHTML.replace(/NEW_KEY/g, `new_${Date.now()}`); 

                itemListBody.insertAdjacentHTML('beforeend', newRowHtml);
                attachListeners(); 
            });

            // ทำงานครั้งแรกเมื่อโหลดหน้า
            attachListeners();
            calculateGrandTotal();
        });
    </script>
</body>

</html>