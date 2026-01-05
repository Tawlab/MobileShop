<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'edit_purchase_order');

$current_user_id = $_SESSION['user_id'];
$current_shop_id = $_SESSION['shop_id'];

// -----------------------------------------------------------------------------
// 1. จัดการ AJAX Request (สำหรับการบันทึกข้อมูล)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_po') {
    
    // ตั้งค่า Header ให้ตอบกลับเป็น JSON
    ob_clean(); 
    header('Content-Type: application/json');

    $response = ['success' => false, 'message' => ''];

    try {
        $po_id = isset($_POST['po_id']) ? (int)$_POST['po_id'] : 0;
        $purchase_date = mysqli_real_escape_string($conn, $_POST['purchase_date']);
        $supplier_id = (int)$_POST['supplier_id'];
        $employee_id = (int)$_POST['employee_id'];
        
        // รับค่า Branch ID เดิม (ไม่ต้องให้เลือกใหม่ แต่ต้องส่งมาเพื่อความชัวร์)
        $branch_id = (int)$_POST['branch_id'];

        $items_data = $_POST['items'] ?? [];

        if ($po_id <= 0 || empty($purchase_date) || $supplier_id <= 0 || $employee_id <= 0) {
            throw new Exception("กรุณากรอกข้อมูลหลักให้ครบถ้วน (วันที่, Supplier, พนักงาน)");
        }

        if (empty($items_data)) {
            throw new Exception("ต้องมีรายการสินค้าอย่างน้อย 1 รายการ");
        }

        mysqli_autocommit($conn, false);

        // 1. อัปเดต Header
        $sql_header = "UPDATE purchase_orders SET 
                        purchase_date = ?, 
                        suppliers_supplier_id = ?, 
                        employees_emp_id = ?,
                        update_at = NOW()
                       WHERE purchase_id = ? AND branches_branch_id = ?"; // เช็ค Branch เพื่อความปลอดภัย
        $stmt_header = $conn->prepare($sql_header);
        $stmt_header->bind_param("siiii", $purchase_date, $supplier_id, $employee_id, $po_id, $branch_id);
        
        if (!$stmt_header->execute()) {
            throw new Exception("บันทึกข้อมูลหลักไม่สำเร็จ: " . $stmt_header->error);
        }
        $stmt_header->close();

        // 2. จัดการรายการสินค้า (Items)
        
        // ดึง ID รายการเดิมที่มีใน DB เพื่อเปรียบเทียบการลบ
        $old_ids = [];
        $res_old = $conn->query("SELECT order_id FROM order_details WHERE purchase_orders_purchase_id = $po_id");
        while($row = $res_old->fetch_assoc()) {
            $old_ids[] = $row['order_id'];
        }

        $submitted_ids = []; // เก็บ ID ที่ส่งมาจากฟอร์ม

        foreach ($items_data as $item) {
            $order_detail_id = (int)$item['order_detail_id'];
            $product_id = (int)$item['product_id'];
            $amount = (int)$item['amount'];
            $price = floatval($item['price']);

            if ($product_id <= 0 || $amount <= 0) continue;

            $submitted_ids[] = $order_detail_id;

            // ตรวจสอบการรับของ (Stock Movement Check)
            $received_qty = 0;
            if ($order_detail_id > 0) {
                $chk_stk = $conn->query("SELECT COUNT(*) as c FROM stock_movements WHERE ref_table = 'order_details' AND ref_id = $order_detail_id");
                $received_qty = $chk_stk->fetch_assoc()['c'];
            }

            if ($order_detail_id == 0) {
                // Insert New Item
                $stmt_ins = $conn->prepare("INSERT INTO order_details (purchase_orders_purchase_id, products_prod_id, amount, price, create_at, update_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $stmt_ins->bind_param("iiid", $po_id, $product_id, $amount, $price);
                if (!$stmt_ins->execute()) throw new Exception("เพิ่มรายการสินค้าไม่สำเร็จ");
                $stmt_ins->close();
            } else {
                // Update Existing Item
                if ($received_qty > 0) {
                    // ถ้าเคยรับของแล้ว ห้ามเปลี่ยนสินค้า และห้ามลดจำนวนต่ำกว่าที่รับไป
                    if ($amount < $received_qty) throw new Exception("สินค้า ID $product_id รับเข้าระบบไปแล้ว $received_qty ชิ้น ไม่สามารถลดจำนวนต่ำกว่านี้ได้");
                    
                    $stmt_upd = $conn->prepare("UPDATE order_details SET amount = ?, price = ?, update_at = NOW() WHERE order_id = ?");
                    $stmt_upd->bind_param("idi", $amount, $price, $order_detail_id);
                } else {
                    // ยังไม่เคยรับของ แก้ได้หมด
                    $stmt_upd = $conn->prepare("UPDATE order_details SET products_prod_id = ?, amount = ?, price = ?, update_at = NOW() WHERE order_id = ?");
                    $stmt_upd->bind_param("iidi", $product_id, $amount, $price, $order_detail_id);
                }
                
                if (!$stmt_upd->execute()) throw new Exception("อัปเดตรายการสินค้าไม่สำเร็จ");
                $stmt_upd->close();
            }
        }

        // 3. ลบรายการที่หายไป
        $ids_to_delete = array_diff($old_ids, $submitted_ids);
        foreach ($ids_to_delete as $del_id) {
            // เช็คก่อนลบว่าเคยรับของไหม
            $chk_del = $conn->query("SELECT COUNT(*) as c FROM stock_movements WHERE ref_table = 'order_details' AND ref_id = $del_id");
            if ($chk_del->fetch_assoc()['c'] > 0) {
                throw new Exception("ไม่สามารถลบรายการสินค้าบางรายการได้ เนื่องจากมีการรับของเข้าระบบไปแล้ว");
            }
            $conn->query("DELETE FROM order_details WHERE order_id = $del_id");
        }

        mysqli_commit($conn);
        $response['success'] = true;
        $response['message'] = "บันทึกการแก้ไขเรียบร้อยแล้ว";

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

// -----------------------------------------------------------------------------
// 2. ส่วน View (HTML)
// -----------------------------------------------------------------------------

// ตรวจสอบ ID
$po_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($po_id <= 0) {
    $_SESSION['error'] = 'ไม่พบรหัสใบสั่งซื้อ';
    header('Location: purchase_order.php');
    exit;
}

// ดึงข้อมูล PO Header
$sql_po = "SELECT * FROM purchase_orders WHERE purchase_id = $po_id";
$res_po = mysqli_query($conn, $sql_po);
$po_data = mysqli_fetch_assoc($res_po);

if (!$po_data) {
    $_SESSION['error'] = 'ไม่พบข้อมูลใบสั่งซื้อ';
    header('Location: purchase_order.php');
    exit;
}

// ตรวจสอบสถานะ (แก้ไขได้เฉพาะ Pending)
if ($po_data['po_status'] !== 'Pending') {
    $_SESSION['error'] = "ใบสั่งซื้อนี้สถานะ '{$po_data['po_status']}' ไม่สามารถแก้ไขได้";
    header('Location: purchase_order.php');
    exit;
}

$po_branch_id = $po_data['branches_branch_id'];

// ดึงข้อมูล PO Items
$sql_items = "SELECT od.*, p.prod_name, p.model_name, pb.brand_name_th,
              (SELECT COUNT(*) FROM stock_movements sm WHERE sm.ref_table = 'order_details' AND sm.ref_id = od.order_id) as received_count
              FROM order_details od
              LEFT JOIN products p ON od.products_prod_id = p.prod_id
              LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id
              WHERE od.purchase_orders_purchase_id = $po_id";
$res_items = mysqli_query($conn, $sql_items);
$po_items = [];
while ($row = mysqli_fetch_assoc($res_items)) {
    $po_items[] = $row;
}

// --- โหลดข้อมูล Dropdown (ตามเงื่อนไขที่ขอ) ---

// 1. Supplier: เฉพาะสาขาตัวเอง (สาขาของ PO)
$sql_sup = "SELECT supplier_id, co_name FROM suppliers WHERE branches_branch_id = '$po_branch_id' ORDER BY co_name";
$res_sup = mysqli_query($conn, $sql_sup);

// 2. Employee: เฉพาะสาขาตัวเอง (สาขาของ PO)
$sql_emp = "SELECT emp_id, firstname_th, lastname_th FROM employees WHERE branches_branch_id = '$po_branch_id' AND emp_status = 'Active' ORDER BY firstname_th";
$res_emp = mysqli_query($conn, $sql_emp);

// 3. Products: เฉพาะสินค้าของร้าน (และสินค้าส่วนกลาง) + [แก้ไข] ไม่เอาสินค้าประเภทบริการ (type_id != 4)
$sql_prod = "SELECT p.prod_id, p.prod_name, p.model_name, p.prod_price, pb.brand_name_th 
             FROM products p
             LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id
             WHERE (p.shop_info_shop_id = '$current_shop_id' OR p.shop_info_shop_id = 0)
             AND p.prod_types_type_id != 4 
             ORDER BY p.prod_name";
$res_prod = mysqli_query($conn, $sql_prod);

// เก็บสินค้าลง Array PHP เพื่อนำไปวนลูปใน JavaScript (Template Row)
$products_list = [];
while ($p = mysqli_fetch_assoc($res_prod)) {
    $products_list[] = $p;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขใบสั่งซื้อ (PO #<?= $po_id ?>)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background-color: <?= $background_color ?>; color: <?= $text_color ?>; font-family: 'Prompt', sans-serif; }
        .card { border-radius: 12px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .card-header { background-color: #fff; border-bottom: 2px solid <?= $theme_color ?>; color: <?= $theme_color ?>; font-weight: 600; padding: 1.2rem; border-radius: 12px 12px 0 0; }
        
        .form-section { background: #fff; border-radius: 10px; padding: 25px; margin-bottom: 20px; border: 1px solid #e9ecef; }
        .form-section h5 { color: <?= $theme_color ?>; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px; font-weight: 600; }

        .table th { background-color: <?= $header_bg_color ?>; color: <?= $header_text_color ?>; text-align: center; vertical-align: middle; white-space: nowrap; }
        .table td { vertical-align: middle; }
        
        /* Select2 Customization */
        .select2-container .select2-selection--single { height: 38px; border: 1px solid #dee2e6; border-radius: 0.375rem; }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered { line-height: 36px; padding-left: 12px; }
        
        .btn-add-item { background-color: <?= $theme_color ?>; color: white; }
        .btn-add-item:hover { background-color: #145c32; color: white; }
        
        .item-locked { background-color: #f8f9fa; opacity: 0.8; pointer-events: none; }
        .item-locked input { background-color: #e9ecef; }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container" style="max-width: 1400px;">
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><i class="fas fa-edit me-2"></i>แก้ไขใบสั่งซื้อ (PO #<?= $po_id ?>)</h4>
                    </div>

                    <form id="editPoForm">
                        <input type="hidden" name="action" value="update_po">
                        <input type="hidden" name="po_id" value="<?= $po_id ?>">
                        <input type="hidden" name="branch_id" value="<?= $po_branch_id ?>">

                        <div class="form-section shadow-sm">
                            <h5><i class="fas fa-info-circle me-2"></i>ข้อมูลหลัก (Header)</h5>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">วันที่สั่งซื้อ <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" name="purchase_date" 
                                           value="<?= date('Y-m-d\TH:i', strtotime($po_data['purchase_date'])) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ผู้จำหน่าย (Supplier) <span class="text-danger">*</span></label>
                                    <select class="form-select select2" name="supplier_id" required>
                                        <option value="">-- ค้นหาผู้จำหน่าย --</option>
                                        <?php while($s = mysqli_fetch_assoc($res_sup)): ?>
                                            <option value="<?= $s['supplier_id'] ?>" <?= ($s['supplier_id'] == $po_data['suppliers_supplier_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($s['co_name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">พนักงานผู้รับเรื่อง <span class="text-danger">*</span></label>
                                    <select class="form-select select2" name="employee_id" required>
                                        <option value="">-- ค้นหาพนักงาน --</option>
                                        <?php while($e = mysqli_fetch_assoc($res_emp)): ?>
                                            <option value="<?= $e['emp_id'] ?>" <?= ($e['emp_id'] == $po_data['employees_emp_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($e['firstname_th'] . ' ' . $e['lastname_th']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-section shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0 border-0"><i class="fas fa-boxes me-2"></i>รายการสินค้า (Items)</h5>
                                <button type="button" class="btn btn-add-item btn-sm shadow-sm" id="btn-add-row">
                                    <i class="fas fa-plus me-1"></i> เพิ่มสินค้า
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle" id="items-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 45%;">สินค้า</th>
                                            <th style="width: 15%;">จำนวน</th>
                                            <th style="width: 15%;">ต้นทุน/หน่วย (฿)</th>
                                            <th style="width: 15%;">รวม (฿)</th>
                                            <th style="width: 10%;">ลบ</th>
                                        </tr>
                                    </thead>
                                    <tbody id="items-body">
                                        <?php foreach ($po_items as $item): 
                                            $is_locked = $item['received_count'] > 0;
                                            $unique_key = $item['order_id'];
                                        ?>
                                        <tr class="item-row <?= $is_locked ? 'item-locked' : '' ?>" id="row-<?= $unique_key ?>">
                                            <input type="hidden" name="items[<?= $unique_key ?>][order_detail_id]" value="<?= $item['order_id'] ?>">
                                            
                                            <td>
                                                <select class="form-select product-select select2-product" name="items[<?= $unique_key ?>][product_id]" required <?= $is_locked ? 'disabled' : '' ?>>
                                                    <option value="">-- เลือกสินค้า --</option>
                                                    <?php foreach ($products_list as $p): ?>
                                                        <option value="<?= $p['prod_id'] ?>" data-price="<?= $p['prod_price'] ?>"
                                                            <?= ($p['prod_id'] == $item['products_prod_id']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($p['prod_name']) ?> (<?= htmlspecialchars($p['brand_name_th']) ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php if($is_locked): ?>
                                                    <input type="hidden" name="items[<?= $unique_key ?>][product_id]" value="<?= $item['products_prod_id'] ?>">
                                                    <small class="text-danger d-block mt-1"><i class="fas fa-lock"></i> รับของแล้ว แก้ไขไม่ได้</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control text-center amount-input" 
                                                       name="items[<?= $unique_key ?>][amount]" 
                                                       value="<?= $item['amount'] ?>" min="1" required>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control text-end price-input" 
                                                       name="items[<?= $unique_key ?>][price]" 
                                                       value="<?= $item['price'] ?>" step="0.01" min="0" required>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control text-end total-input bg-light" readonly value="<?= number_format($item['amount'] * $item['price'], 2) ?>">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-outline-danger btn-sm remove-row-btn" <?= $is_locked ? 'disabled' : '' ?>>
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-light fw-bold">
                                            <td colspan="3" class="text-end">ยอดรวมสุทธิ:</td>
                                            <td class="text-end text-success fs-5" id="grand-total">0.00</td>
                                            <td>บาท</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mb-5">
                            <a href="purchase_order.php" class="btn btn-secondary shadow-sm px-4">
                                <i class="fas fa-times me-2"></i> ยกเลิก
                            </a>
                            <button type="submit" class="btn btn-success shadow-sm px-4">
                                <i class="fas fa-save me-2"></i> บันทึกการแก้ไข
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <template id="row-template">
        <tr class="item-row">
            <input type="hidden" name="items[INDEX][order_detail_id]" value="0">
            <td>
                <select class="form-select product-select" name="items[INDEX][product_id]" required>
                    <option value="">-- เลือกสินค้า --</option>
                    <?php foreach ($products_list as $p): ?>
                        <option value="<?= $p['prod_id'] ?>" data-price="<?= $p['prod_price'] ?>">
                            <?= htmlspecialchars($p['prod_name']) ?> (<?= htmlspecialchars($p['brand_name_th']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="number" class="form-control text-center amount-input" name="items[INDEX][amount]" value="1" min="1" required></td>
            <td><input type="number" class="form-control text-end price-input" name="items[INDEX][price]" value="0.00" step="0.01" min="0" required></td>
            <td><input type="text" class="form-control text-end total-input bg-light" readonly value="0.00"></td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm remove-row-btn"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    </template>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            // 1. เริ่มต้น Select2
            $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
            initProductSelect2($('.select2-product'));

            calculateGrandTotal();

            // 2. ฟังก์ชันเพิ่มแถว
            $('#btn-add-row').click(function() {
                const timestamp = Date.now();
                const template = document.getElementById('row-template').innerHTML;
                const newRowHtml = template.replace(/INDEX/g, timestamp);
                
                $('#items-body').append(newRowHtml);
                
                // Init Select2 สำหรับแถวใหม่
                const newSelect = $('#items-body tr:last .product-select');
                initProductSelect2(newSelect);
            });

            // 3. จัดการ Event ในตาราง (Change/Input/Click)
            $('#items-body').on('change', '.product-select', function() {
                const price = $(this).find(':selected').data('price') || 0;
                const row = $(this).closest('tr');
                row.find('.price-input').val(parseFloat(price).toFixed(2));
                calculateRow(row);
            });

            $('#items-body').on('input', '.amount-input, .price-input', function() {
                calculateRow($(this).closest('tr'));
            });

            $('#items-body').on('click', '.remove-row-btn', function() {
                $(this).closest('tr').remove();
                calculateGrandTotal();
            });

            // 4. ฟังก์ชันคำนวณ
            function calculateRow(row) {
                const qty = parseFloat(row.find('.amount-input').val()) || 0;
                const price = parseFloat(row.find('.price-input').val()) || 0;
                const total = qty * price;
                row.find('.total-input').val(total.toFixed(2));
                calculateGrandTotal();
            }

            function calculateGrandTotal() {
                let grandTotal = 0;
                $('.total-input').each(function() {
                    grandTotal += parseFloat($(this).val()) || 0;
                });
                $('#grand-total').text(grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            }

            // 5. ตั้งค่า Select2 ให้ค้นหาได้ในตาราง
            function initProductSelect2(element) {
                element.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    dropdownAutoWidth: true
                });
            }

            // 6. บันทึกข้อมูลด้วย AJAX
            $('#editPoForm').on('submit', function(e) {
                e.preventDefault();

                // Validation เบื้องต้น
                if ($('.item-row').length === 0) {
                    Swal.fire('ข้อผิดพลาด', 'กรุณาเพิ่มรายการสินค้าอย่างน้อย 1 รายการ', 'warning');
                    return;
                }

                Swal.fire({
                    title: 'กำลังบันทึก...',
                    text: 'กรุณารอสักครู่',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading() }
                });

                $.ajax({
                    url: 'edit_purchase_order.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'สำเร็จ!',
                                text: res.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.href = 'purchase_order.php';
                            });
                        } else {
                            Swal.fire('เกิดข้อผิดพลาด', res.message, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error(xhr.responseText);
                        Swal.fire('Server Error', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
                    }
                });
            });
        });
    </script>
</body>
</html>