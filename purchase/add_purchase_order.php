<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_purchase_order');

$current_user_id = $_SESSION['user_id'];
$current_shop_id = $_SESSION['shop_id'];
$current_branch_id = $_SESSION['branch_id'];

// 1. ตรวจสอบสิทธิ์ Admin
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

// 1.1 ถ้าไม่ใช่ Admin ให้ดึงชื่อสาขามาแสดง
$current_branch_name = '';
if (!$is_admin) {
    $b_sql = "SELECT branch_name FROM branches WHERE branch_id = $current_branch_id";
    $b_res = $conn->query($b_sql);
    if ($b_row = $b_res->fetch_assoc()) {
        $current_branch_name = $b_row['branch_name'];
    }
}

// ฟังก์ชันหา ID ถัดไป (Backend Auto-Increment)
function getNextPurchaseId($conn) {
    $sql = "SELECT MAX(purchase_id) as max_id FROM purchase_orders";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return ($row['max_id']) ? $row['max_id'] + 1 : 1;
}

// ฟังก์ชันหา order_id ล่าสุด (สำหรับ Order Details)
function getMaxOrderId($conn) {
    $sql = "SELECT MAX(order_id) as max_id FROM order_details";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return ($row['max_id']) ? (int)$row['max_id'] : 0;
}

// ==========================================================================================
// [AJAX HANDLER] สำหรับโหลดข้อมูลและบันทึก
// ==========================================================================================
if (isset($_GET['ajax_action']) || isset($_POST['ajax_action'])) {
    ob_clean();
    header('Content-Type: application/json');

    $action = $_REQUEST['ajax_action'];

    // --- Action 1: โหลดข้อมูลตาม Shop ID และ Branch ID ---
    if ($action == 'get_shop_data') {
        $target_shop_id = intval($_REQUEST['shop_id']);
        // [แก้ไข] รับค่า Branch ID เข้ามาด้วย เพื่อกรอง Supplier/Employee
        $target_branch_id = isset($_REQUEST['branch_id']) ? intval($_REQUEST['branch_id']) : 0;

        if (!$is_admin && $target_shop_id != $current_shop_id) {
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $response = [];

        // 1. Branches (โหลดเฉพาะถ้าเป็น Admin เพื่อนำไปสร้าง Dropdown)
        $branches = [];
        if ($is_admin) {
            $sql = "SELECT branch_id, branch_name FROM branches WHERE shop_info_shop_id = $target_shop_id ORDER BY branch_name";
            $res = $conn->query($sql);
            while ($row = $res->fetch_assoc()) $branches[] = $row;
        }
        $response['branches'] = $branches;

        // 2. Suppliers ([แก้ไข] กรองตาม Branch ID)
        $suppliers = [];
        if ($target_branch_id > 0) {
            $sql = "SELECT supplier_id, co_name FROM suppliers WHERE branches_branch_id = $target_branch_id ORDER BY co_name";
            $res = $conn->query($sql);
            while ($row = $res->fetch_assoc()) $suppliers[] = $row;
        }
        $response['suppliers'] = $suppliers;

        // 3. Employees ([แก้ไข] กรองตาม Branch ID)
        $employees = [];
        if ($target_branch_id > 0) {
            $sql = "SELECT emp_id, firstname_th, lastname_th, emp_code
                    FROM employees 
                    WHERE branches_branch_id = $target_branch_id AND emp_status = 'Active' 
                    ORDER BY firstname_th";
            $res = $conn->query($sql);
            while ($row = $res->fetch_assoc()) $employees[] = $row;
        }
        $response['employees'] = $employees;

        // 4. Products (รวมสินค้าส่วนกลาง shop_id=0) -> [คงเดิม] อิงตาม Shop
        $products = [];
        $sql = "SELECT p.prod_id, p.prod_name, p.model_name, p.prod_price, pb.brand_name_th 
                FROM products p
                LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id
                WHERE (p.shop_info_shop_id = $target_shop_id OR p.shop_info_shop_id = 0) 
                AND p.prod_types_type_id != 4
                ORDER BY p.prod_name";
        $res = $conn->query($sql);
        while ($row = $res->fetch_assoc()) $products[] = $row;
        $response['products'] = $products;

        echo json_encode($response);
        exit;
    }

    // --- Action 2: บันทึก PO ---
    if ($action == 'save_po' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $response = ['success' => false, 'message' => ''];

        $purchase_date = $_POST['purchase_date'];
        
        // รับค่า ID และป้องกันการปลอมแปลงถ้าไม่ใช่ Admin
        if ($is_admin) {
            $suppliers_supplier_id = intval($_POST['suppliers_supplier_id']);
            $employees_emp_id = intval($_POST['employees_emp_id']);
            $branches_branch_id = intval($_POST['branches_branch_id']);
        } else {
            $suppliers_supplier_id = intval($_POST['suppliers_supplier_id']);
            $employees_emp_id = intval($_POST['employees_emp_id']);
            // บังคับใช้ Branch ID ของตัวเอง
            $branches_branch_id = $current_branch_id;
        }
        
        $product_ids = $_POST['product_ids'] ?? [];
        $amounts = $_POST['amounts'] ?? [];
        $prices = $_POST['prices'] ?? [];

        if (empty($purchase_date) || empty($suppliers_supplier_id) || empty($employees_emp_id) || empty($branches_branch_id)) {
            $response['message'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
            echo json_encode($response); exit;
        }
        if (empty($product_ids)) {
            $response['message'] = 'กรุณาเพิ่มรายการสินค้าอย่างน้อย 1 รายการ';
            echo json_encode($response); exit;
        }

        mysqli_autocommit($conn, false);
        try {
            $new_po_id = getNextPurchaseId($conn);

            // Insert Header
            $sql_head = "INSERT INTO purchase_orders (purchase_id, purchase_date, create_at, update_at, suppliers_supplier_id, branches_branch_id, employees_emp_id, po_status) 
                         VALUES (?, ?, NOW(), NOW(), ?, ?, ?, 'Pending')";
            $stmt = $conn->prepare($sql_head);
            $stmt->bind_param("isiii", $new_po_id, $purchase_date, $suppliers_supplier_id, $branches_branch_id, $employees_emp_id);
            
            if (!$stmt->execute()) throw new Exception("บันทึกส่วนหัวไม่สำเร็จ: " . $stmt->error);
            $stmt->close();

            // Insert Details
            $running_order_id = getMaxOrderId($conn);

            $sql_det = "INSERT INTO order_details (order_id, amount, price, create_at, update_at, purchase_orders_purchase_id, products_prod_id) 
                        VALUES (?, ?, ?, NOW(), NOW(), ?, ?)";
            $stmt = $conn->prepare($sql_det);

            $count_items = 0;
            foreach ($product_ids as $idx => $prod_id) {
                $qty = intval($amounts[$idx]);
                $cost = floatval($prices[$idx]);

                if ($qty > 0) {
                    $running_order_id++;
                    $stmt->bind_param("iidii", $running_order_id, $qty, $cost, $new_po_id, $prod_id);
                    
                    if (!$stmt->execute()) throw new Exception("บันทึกรายการสินค้าไม่สำเร็จ (ID: $prod_id) Error: " . $stmt->error);
                    $count_items++;
                }
            }
            $stmt->close();

            if ($count_items == 0) throw new Exception("จำนวนสินค้าต้องมากกว่า 0");

            mysqli_commit($conn);
            $response['success'] = true;
            $response['po_id'] = $new_po_id;
            $response['message'] = "บันทึกใบสั่งซื้อเลขที่ #$new_po_id เรียบร้อยแล้ว";

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $response['message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }

        echo json_encode($response);
        exit;
    }
}

// ข้อมูลสำหรับ Dropdown ร้านค้า (เฉพาะ Admin)
$shops_list = [];
if ($is_admin) {
    $s_res = $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name");
    while ($row = $s_res->fetch_assoc()) $shops_list[] = $row;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>สร้างใบรับเข้าสินค้า (PO)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background-color: <?= $background_color ?>; color: <?= $text_color ?>; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); margin-bottom: 2rem; }
        .card-header { background-color: #fff; border-bottom: 2px solid <?= $theme_color ?>; padding: 1.5rem; border-radius: 15px 15px 0 0; font-size: 1.25rem; font-weight: 600; color: <?= $theme_color ?>; }
        .table th { background-color: <?= $header_bg_color ?>; color: <?= $header_text_color ?>; font-weight: 600; text-align: center; vertical-align: middle; }
        .table td { vertical-align: middle; }
        .btn-success { background-color: <?= $btn_add_color ?>; border-color: <?= $btn_add_color ?>; color: white; }
        .required-label::after { content: " *"; color: red; }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container py-5">

                    <form id="poForm" novalidate>
                        <input type="hidden" name="ajax_action" value="save_po">

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="mb-0 text-primary">
                                <i class="fas fa-cart-plus me-2"></i>สร้างใบสั่งซื้อ / รับเข้าสินค้าใหม่
                            </h4>
                            <div>
                                <button type="submit" class="btn btn-success btn-lg shadow-sm" id="btnSubmit">
                                    <i class="fas fa-save me-2"></i>บันทึกใบรับเข้า
                                </button>
                                <a href="purchase_order.php" class="btn btn-secondary btn-lg shadow-sm">
                                    <i class="fas fa-times me-2"></i>ยกเลิก
                                </a>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-info-circle me-2"></i>ข้อมูลทั่วไป
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    
                                    <div class="col-md-6">
                                        <label class="form-label required-label">ร้านค้า (Shop)</label>
                                        <?php if ($is_admin): ?>
                                            <select class="form-select select2" name="shop_id" id="shopSelect" onchange="loadShopData(this.value, 0)">
                                                <option value="">-- เลือกร้านค้า --</option>
                                                <?php foreach ($shops_list as $shop): ?>
                                                    <option value="<?= $shop['shop_id'] ?>"><?= htmlspecialchars($shop['shop_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else: ?>
                                            <input type="text" class="form-control bg-light" value="<?= $_SESSION['shop_name'] ?? 'ระบบเลือกร้านของคุณอัตโนมัติ' ?>" readonly>
                                            <input type="hidden" name="shop_id" id="shopSelect" value="<?= $current_shop_id ?>">
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label required-label">สาขา (Branch)</label>
                                        <?php if ($is_admin): ?>
                                            <select class="form-select select2" name="branches_branch_id" id="branchSelect" required>
                                                <option value="">-- กรุณาเลือกร้านค้าก่อน --</option>
                                            </select>
                                        <?php else: ?>
                                            <div class="input-group">
                                                <span class="input-group-text bg-success text-white"><i class="fas fa-store"></i></span>
                                                <input type="text" class="form-control bg-light fw-bold text-success" value="<?= htmlspecialchars($current_branch_name) ?> (ระบบเลือกสาขาของคุณอัตโนมัติ)" readonly>
                                            </div>
                                            <input type="hidden" name="branches_branch_id" value="<?= $current_branch_id ?>">
                                            <div class="form-text text-success"><i class="fas fa-check-circle me-1"></i>ระบบจะเพิ่มข้อมูลไปยังสาขาของท่านอัตโนมัติ</div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label required-label">Supplier (ผู้จำหน่าย)</label>
                                        <select class="form-select select2" name="suppliers_supplier_id" id="supplierSelect" required>
                                            <option value="">-- กรุณาเลือกสาขาก่อน --</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label required-label">พนักงานผู้รับเข้า</label>
                                        <select class="form-select select2" name="employees_emp_id" id="employeeSelect" required>
                                            <option value="">-- กรุณาเลือกสาขาก่อน --</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label required-label">วันที่รับเข้า</label>
                                        <input type="datetime-local" class="form-control" name="purchase_date" value="<?= date('Y-m-d\TH:i') ?>" required>
                                    </div>
                                    <div class="col-md-6"></div> </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-boxes me-2"></i>
                                รายการสินค้า
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th width="40%">สินค้า</th>
                                                <th width="15%">จำนวน</th>
                                                <th width="20%">ราคา/หน่วย (บาท)</th>
                                                <th width="20%">ราคารวม (บาท)</th>
                                                <th width="5%">ลบ</th>
                                            </tr>
                                        </thead>
                                        <tbody id="product-list-container">
                                            </tbody>
                                    </table>
                                </div>

                                <button type="button" class="btn btn-outline-success mt-2" id="add-product-btn">
                                    <i class="fas fa-plus me-1"></i> เพิ่มรายการสินค้า
                                </button>

                                <hr>

                                <div class="row justify-content-end">
                                    <div class="col-md-4">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="fw-bold">จำนวนรวม (ชิ้น):</span>
                                            <span id="total-quantity" class="fw-bold">0</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="fw-bold fs-5">ยอดรวมสุทธิ (บาท):</span>
                                            <span id="total-price" class="fw-bold fs-5 text-success">0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let currentProducts = [];
        const userShopId = "<?= $is_admin ? '' : $current_shop_id ?>";
        const userBranchId = "<?= $current_branch_id ?>";
        const currentUserId = "<?= $current_user_id ?>";
        const isAdmin = <?= $is_admin ? 'true' : 'false' ?>;

        $(document).ready(function() {
            $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });

            if (!isAdmin) {
                // User: โหลดข้อมูลโดยใช้ Shop ID และ Branch ID ของตัวเอง
                loadShopData(userShopId, userBranchId);
            }

            // Admin: เมื่อเลือกสาขา ให้โหลดข้อมูล Supplier/Employee
            if(isAdmin) {
                $('#branchSelect').on('change', function() {
                    loadShopData($('#shopSelect').val(), $(this).val());
                });
            }

            // Bind Add Button
            $('#add-product-btn').click(function() {
                addProductRow();
            });

            // Bind Form Submit
            $('#poForm').on('submit', function(e) {
                e.preventDefault();
                
                // Validate form
                if (!this.checkValidity()) {
                    e.stopPropagation();
                    $(this).addClass('was-validated');
                    return;
                }

                // Additional check for product count
                if ($('#product-list-container tr').length === 0) {
                    Swal.fire('แจ้งเตือน', 'กรุณาเพิ่มรายการสินค้าอย่างน้อย 1 รายการ', 'warning');
                    return;
                }

                Swal.fire({
                    title: 'กำลังบันทึก...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading() }
                });

                $.ajax({
                    url: 'add_purchase_order.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'บันทึกสำเร็จ!',
                                text: res.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.href = `view_purchase_order.php?id=${res.po_id}`;
                            });
                        } else {
                            Swal.fire('เกิดข้อผิดพลาด', res.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Server Error', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
                    }
                });
            });
        });

        // [แก้ไข] ฟังก์ชันรับ branchId เพิ่มเข้ามา
        function loadShopData(shopId, branchId = 0) {
            if (!shopId) return;

            // ไม่เคลียร์รายการสินค้าหากเป็นการเปลี่ยนสาขา (เพราะสินค้าผูกกับ Shop)
            // แต่ถ้าเป็นการเปลี่ยน Shop (branchId == 0) ควรเคลียร์
            if(isAdmin && branchId == 0) {
                $('#product-list-container').empty();
                calculateTotals();
            } else if (!isAdmin && $('#product-list-container').children().length == 0) {
                // User ทั่วไป โหลดครั้งแรก
            }

            const btn = document.getElementById('btnSubmit');
            const originalBtnText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            btn.disabled = true;

            $.ajax({
                url: 'add_purchase_order.php',
                type: 'GET',
                data: { ajax_action: 'get_shop_data', shop_id: shopId, branch_id: branchId },
                dataType: 'json',
                success: function(res) {
                    if (res.error) {
                        Swal.fire('Error', res.error, 'error');
                        return;
                    }

                    // Branches (เฉพาะ Admin และเป็นการโหลดครั้งแรก/เปลี่ยน Shop)
                    if (isAdmin && branchId == 0) {
                        let branchOpts = '<option value="">-- เลือกสาขา --</option>';
                        res.branches.forEach(b => {
                            branchOpts += `<option value="${b.branch_id}">${b.branch_name}</option>`;
                        });
                        $('#branchSelect').html(branchOpts).trigger('change');
                        
                        // เคลียร์ Supplier/Employee รอเลือกสาขา
                        $('#supplierSelect').html('<option value="">-- กรุณาเลือกสาขาก่อน --</option>').trigger('change');
                        $('#employeeSelect').html('<option value="">-- กรุณาเลือกสาขาก่อน --</option>').trigger('change');
                    }

                    // Suppliers & Employees (โหลดเมื่อมี Branch ID)
                    if (branchId > 0) {
                        let supOpts = '<option value="">-- เลือก Supplier --</option>';
                        res.suppliers.forEach(s => {
                            supOpts += `<option value="${s.supplier_id}">${s.co_name}</option>`;
                        });
                        $('#supplierSelect').html(supOpts).trigger('change');

                        let empOpts = '<option value="">-- เลือกพนักงาน --</option>';
                        res.employees.forEach(e => {
                            let selected = ''; 
                            empOpts += `<option value="${e.emp_id}" ${selected}>${e.firstname_th} ${e.lastname_th} (${e.emp_code})</option>`;
                        });
                        $('#employeeSelect').html(empOpts).trigger('change');
                    }

                    // Products (Always update as it depends on Shop)
                    currentProducts = res.products;

                    // Add first row if empty
                    if ($('#product-list-container').children().length == 0) {
                        addProductRow();
                    }

                    btn.innerHTML = originalBtnText;
                    btn.disabled = false;
                },
                error: function() {
                    Swal.fire('Error', 'ไม่สามารถโหลดข้อมูลร้านค้าได้', 'error');
                    btn.innerHTML = originalBtnText;
                    btn.disabled = false;
                }
            });
        }

        function addProductRow() {
            if (currentProducts.length === 0) {
                Swal.fire('แจ้งเตือน', 'กรุณาเลือกร้านค้าก่อน หรือร้านค้านี้ยังไม่มีสินค้า', 'warning');
                return;
            }

            const rowIndex = Date.now();
            
            let prodOpts = '<option value="">-- เลือกสินค้า --</option>';
            currentProducts.forEach(p => {
                prodOpts += `<option value="${p.prod_id}" data-price="${p.prod_price}">
                                ${p.prod_name} ${p.model_name ? '('+p.model_name+')' : ''} [${p.brand_name_th}]
                             </option>`;
            });

            const html = `
                <tr class="product-row">
                    <td>
                        <select class="form-select product-select" name="product_ids[]" id="prod_${rowIndex}" required>
                            ${prodOpts}
                        </select>
                    </td>
                    <td>
                        <input type="number" class="form-control text-center amount-input" name="amounts[]" value="1" min="1" required>
                    </td>
                    <td>
                        <input type="number" class="form-control text-end price-input" name="prices[]" value="0.00" min="0" step="0.01" required>
                    </td>
                    <td>
                        <input type="text" class="form-control text-end total-input bg-light" value="0.00" readonly>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-danger btn-sm remove-btn"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;

            $('#product-list-container').append(html);

            $(`#prod_${rowIndex}`).select2({ theme: 'bootstrap-5', width: '100%' });

            const newRow = $('#product-list-container tr').last();
            bindRowEvents(newRow);
            calculateTotals();
        }

        function bindRowEvents(row) {
            const select = row.find('.product-select');
            const qtyInput = row.find('.amount-input');
            const priceInput = row.find('.price-input');
            const totalInput = row.find('.total-input');
            const removeBtn = row.find('.remove-btn');

            select.on('change', function() {
                const price = $(this).find(':selected').data('price') || 0;
                priceInput.val(parseFloat(price).toFixed(2));
                calcRowTotal();
            });

            qtyInput.on('input', calcRowTotal);
            priceInput.on('input', calcRowTotal);

            removeBtn.on('click', function() {
                row.remove();
                calculateTotals();
            });

            function calcRowTotal() {
                const qty = parseInt(qtyInput.val()) || 0;
                const price = parseFloat(priceInput.val()) || 0;
                const total = qty * price;
                totalInput.val(total.toFixed(2));
                calculateTotals();
            }
        }

        function calculateTotals() {
            let totalQty = 0;
            let totalPrice = 0;

            $('.product-row').each(function() {
                const qty = parseInt($(this).find('.amount-input').val()) || 0;
                const total = parseFloat($(this).find('.total-input').val()) || 0;
                totalQty += qty;
                totalPrice += total;
            });

            $('#total-quantity').text(totalQty);
            $('#total-price').text(totalPrice.toFixed(2));
        }
    </script>
</body>
</html>