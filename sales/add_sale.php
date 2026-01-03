<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_sale');

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

// -----------------------------------------------------------------------------
// HELPER FUNCTIONS
// -----------------------------------------------------------------------------
function getNextMovementId($conn) {
    $sql = "SELECT IFNULL(MAX(movement_id), 0) + 1 as next_id FROM stock_movements";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result)['next_id'];
}

// [ใหม่] ฟังก์ชันหา bill_id ถัดไป
function getNextBillId($conn) {
    $sql = "SELECT IFNULL(MAX(bill_id), 0) + 1 as next_id FROM bill_headers";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result)['next_id'];
}

// [ใหม่] ฟังก์ชันหา detail_id ล่าสุด (สำหรับรายการสินค้า)
function getNextBillDetailId($conn) {
    $sql = "SELECT IFNULL(MAX(detail_id), 0) as max_id FROM bill_details";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return (int)$row['max_id'];
}

// -----------------------------------------------------------------------------
// AJAX HANDLER (สำหรับโหลดข้อมูลตามสาขาที่เลือก)
// -----------------------------------------------------------------------------
if (isset($_GET['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax_action'];

    // 1. โหลดสาขา (ตามร้านค้า)
    if ($action == 'get_branches' && isset($_GET['shop_id'])) {
        $shop_id = (int)$_GET['shop_id'];
        if (!$is_admin && $shop_id != $current_shop_id) { echo json_encode([]); exit; }

        $sql = "SELECT branch_id, branch_name FROM branches WHERE shop_info_shop_id = $shop_id ORDER BY branch_name";
        $res = $conn->query($sql);
        $data = [];
        while($row = $res->fetch_assoc()) $data[] = $row;
        echo json_encode($data);
        exit;
    }

    // 2. โหลดทรัพยากรของสาขา (ลูกค้า, พนักงาน, สินค้า)
    if ($action == 'get_branch_resources' && isset($_GET['branch_id'])) {
        $branch_id = (int)$_GET['branch_id'];
        $bill_id_edit = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;

        // A. ดึงพนักงาน (Employees)
        $emp_sql = "SELECT emp_id, firstname_th, lastname_th FROM employees 
                    WHERE branches_branch_id = $branch_id AND emp_status = 'Active' 
                    ORDER BY firstname_th";
        $emp_res = $conn->query($emp_sql);
        $employees = [];
        while($row = $emp_res->fetch_assoc()) $employees[] = $row;

        // B. ดึงลูกค้า (Customers) - ของสาขานั้นๆ
        $cust_sql = "SELECT cs_id, firstname_th, lastname_th FROM customers 
                     WHERE branches_branch_id = $branch_id 
                     ORDER BY firstname_th";
        $cust_res = $conn->query($cust_sql);
        $customers = [];
        while($row = $cust_res->fetch_assoc()) $customers[] = $row;

        // C. ดึงสินค้า (Stocks)
        $stock_sql = "SELECT ps.stock_id, ps.serial_no, ps.price, p.prod_id, p.prod_name, p.model_name
                      FROM prod_stocks ps
                      JOIN products p ON ps.products_prod_id = p.prod_id
                      WHERE ps.branches_branch_id = $branch_id 
                      AND (ps.stock_status = 'In Stock'";
        
        if ($bill_id_edit > 0) {
            $stock_sql .= " OR ps.stock_id IN (SELECT prod_stocks_stock_id FROM bill_details WHERE bill_headers_bill_id = $bill_id_edit)";
        }
        $stock_sql .= ") ORDER BY p.prod_name, ps.serial_no";

        $stock_res = $conn->query($stock_sql);
        $stocks = [];
        while($row = $stock_res->fetch_assoc()) $stocks[] = $row;

        echo json_encode([
            'employees' => $employees, 
            'customers' => $customers,
            'stocks' => $stocks
        ]);
        exit;
    }
}

// -----------------------------------------------------------------------------
// INITIALIZE VARIABLES
// -----------------------------------------------------------------------------
$edit_mode = false;
$bill_id = 0;
$selected_shop = $is_admin ? '' : $current_shop_id;
$selected_branch = $is_admin ? '' : $current_branch_id;
$selected_customer = '';
$selected_employee = '';
$val_vat = 7.00;
$val_discount = 0.00;
$val_comment = '';
$existing_items = [];

// ตรวจสอบโหมดแก้ไข
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $bill_id = (int)$_GET['id'];
    
    $sql_head = "SELECT b.*, br.shop_info_shop_id 
                 FROM bill_headers b
                 JOIN branches br ON b.branches_branch_id = br.branch_id
                 WHERE b.bill_id = $bill_id AND b.bill_status = 'Pending'";
    
    if (!$is_admin) {
        $sql_head .= " AND b.branches_branch_id = $current_branch_id";
    }

    $res_head = mysqli_query($conn, $sql_head);
    
    if ($res_head && mysqli_num_rows($res_head) > 0) {
        $edit_mode = true;
        $head = mysqli_fetch_assoc($res_head);
        
        $selected_shop = $head['shop_info_shop_id'];
        $selected_branch = $head['branches_branch_id'];
        $selected_customer = $head['customers_cs_id'];
        $selected_employee = $head['employees_emp_id'];
        $val_vat = $head['vat'];
        $val_discount = $head['discount'];
        $val_comment = $head['comment'];

        $sql_det = "SELECT bd.*, ps.serial_no, ps.price as stock_price, p.prod_name, p.model_name
                    FROM bill_details bd
                    JOIN prod_stocks ps ON bd.prod_stocks_stock_id = ps.stock_id
                    JOIN products p ON bd.products_prod_id = p.prod_id
                    WHERE bd.bill_headers_bill_id = $bill_id";
        $res_det = mysqli_query($conn, $sql_det);
        while ($row = mysqli_fetch_assoc($res_det)) {
            $existing_items[] = [
                'stock_id' => $row['prod_stocks_stock_id'],
                'price' => $row['price'],
                'text' => $row['prod_name'] . ' ' . $row['model_name'] . ' (SN: ' . $row['serial_no'] . ')'
            ];
        }
    } else {
        echo "<script>alert('ไม่พบข้อมูลบิล หรือคุณไม่มีสิทธิ์แก้ไข'); window.location='sale_list.php';</script>";
        exit;
    }
}

// -----------------------------------------------------------------------------
// FETCH MASTER DATA (Initial PHP Load)
// -----------------------------------------------------------------------------
// กรณี Non-Admin: โหลดข้อมูลของสาขาตัวเองเลย
$filter_branch = $selected_branch ? $selected_branch : 0;

$customers_list = [];
$employees_list = [];
$stocks_list = [];

if ($filter_branch > 0) {
    // 1. Customers
    $c_sql = "SELECT cs_id, firstname_th, lastname_th FROM customers WHERE branches_branch_id = $filter_branch ORDER BY firstname_th";
    $c_res = mysqli_query($conn, $c_sql);
    while($r = mysqli_fetch_assoc($c_res)) $customers_list[] = $r;

    // 2. Employees
    $e_sql = "SELECT emp_id, firstname_th, lastname_th FROM employees WHERE branches_branch_id = $filter_branch AND emp_status = 'Active' ORDER BY firstname_th";
    $e_res = mysqli_query($conn, $e_sql);
    while($r = mysqli_fetch_assoc($e_res)) $employees_list[] = $r;

    // 3. Stocks (Initial for PHP Variable -> JS)
    $s_sql = "SELECT ps.stock_id, ps.serial_no, ps.price, p.prod_id, p.prod_name, p.model_name
              FROM prod_stocks ps
              JOIN products p ON ps.products_prod_id = p.prod_id
              WHERE ps.branches_branch_id = $filter_branch 
              AND ps.stock_status = 'In Stock'
              ORDER BY p.prod_name, ps.serial_no";
    $s_res = mysqli_query($conn, $s_sql);
    while($r = mysqli_fetch_assoc($s_res)) $stocks_list[] = $r;
}

// Shops (Only for Admin Dropdown)
$shops = [];
if ($is_admin) {
    $shop_res = $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name");
    while($row = $shop_res->fetch_assoc()) $shops[] = $row;
}

// -----------------------------------------------------------------------------
// HANDLE FORM SUBMIT
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_bill'])) {

    $target_branch_id = $is_admin ? (int)$_POST['branch_id'] : $current_branch_id;
    
    if ($is_admin && empty($target_branch_id)) {
        $error = "กรุณาเลือกสาขาที่จะทำรายการขาย";
    } else {
        $customer_id = (int)$_POST['customers_id'];
        $employee_id = (int)$_POST['employees_id'];
        $vat_rate    = isset($_POST['vat']) ? floatval($_POST['vat']) : 7.00;
        $discount    = isset($_POST['discount']) ? floatval($_POST['discount']) : 0.00;
        $comment     = mysqli_real_escape_string($conn, $_POST['comment'] ?? '');
        $bill_date   = date('Y-m-d H:i:s');
        
        $current_bill_id = !empty($_POST['bill_id']) ? (int)$_POST['bill_id'] : 0;

        mysqli_autocommit($conn, false);

        try {
            if ($current_bill_id > 0) {
                // UPDATE Logic
                $sql_old = "SELECT prod_stocks_stock_id FROM bill_details WHERE bill_headers_bill_id = $current_bill_id";
                $res_old = mysqli_query($conn, $sql_old);
                while ($old = mysqli_fetch_assoc($res_old)) {
                    $conn->query("UPDATE prod_stocks SET stock_status = 'In Stock' WHERE stock_id = " . $old['prod_stocks_stock_id']);
                    $conn->query("DELETE FROM stock_movements WHERE ref_table = 'bill_headers' AND ref_id = $current_bill_id AND prod_stocks_stock_id = " . $old['prod_stocks_stock_id']);
                }
                $conn->query("DELETE FROM bill_details WHERE bill_headers_bill_id = $current_bill_id");

                $sql_upd = "UPDATE bill_headers SET vat=?, comment=?, discount=?, customers_cs_id=?, employees_emp_id=?, branches_branch_id=?, update_at=NOW() WHERE bill_id=?";
                $stmt = $conn->prepare($sql_upd);
                $stmt->bind_param("dsdiiii", $vat_rate, $comment, $discount, $customer_id, $employee_id, $target_branch_id, $current_bill_id);
                if (!$stmt->execute()) throw new Exception("อัปเดตบิลไม่สำเร็จ");
                $stmt->close();
                $bill_id = $current_bill_id;

            } else {
                // [แก้ไข] สร้างบิลใหม่แบบ Manual ID
                $bill_id = getNextBillId($conn);

                $sql_header = "INSERT INTO bill_headers 
                    (bill_id, bill_date, receipt_date, payment_method, bill_status, vat, comment, discount, 
                     customers_cs_id, bill_type, branches_branch_id, employees_emp_id, create_at, update_at)
                    VALUES (?, ?, ?, 'Cash', 'Pending', ?, ?, ?, ?, 'Sale', ?, ?, NOW(), NOW())";

                $stmt = $conn->prepare($sql_header);
                // bind_param: i(id) s(date) s(date) d(vat) s(comment) d(discount) i(cust) i(branch) i(emp)
                $stmt->bind_param("issdsdiii", $bill_id, $bill_date, $bill_date, $vat_rate, $comment, $discount, $customer_id, $target_branch_id, $employee_id);

                if (!$stmt->execute()) throw new Exception("สร้างบิลไม่สำเร็จ: " . $stmt->error);
                $stmt->close();
            }

            // INSERT Details & Cut Stock
            $stock_ids = $_POST['stock_ids'] ?? [];
            $prices    = $_POST['prices'] ?? [];

            // [แก้ไข] เตรียม ID รายการสินค้า
            $running_detail_id = getNextBillDetailId($conn);

            for ($i = 0; $i < count($stock_ids); $i++) {
                $sid = (int)$stock_ids[$i];
                $price = (float)$prices[$i];
                $qty = 1;

                // ตรวจสอบสาขาของสินค้า
                $res_chk = $conn->query("SELECT products_prod_id, branches_branch_id, stock_status FROM prod_stocks WHERE stock_id = $sid");
                $row_chk = $res_chk->fetch_assoc();
                
                if (!$row_chk) throw new Exception("ไม่พบสินค้า Stock ID: $sid");
                if ($row_chk['branches_branch_id'] != $target_branch_id) throw new Exception("สินค้า (ID: $sid) ไม่ได้อยู่ในสาขาที่เลือก");
                if ($row_chk['stock_status'] != 'In Stock') throw new Exception("สินค้า (ID: $sid) ถูกขายไปแล้ว");

                $prod_id = $row_chk['products_prod_id'];

                // [แก้ไข] Insert Detail แบบระบุ ID
                $running_detail_id++; // บวก ID ทีละ 1
                $sql_detail = "INSERT INTO bill_details (detail_id, amount, price, bill_headers_bill_id, products_prod_id, prod_stocks_stock_id, create_at, update_at) 
                               VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $stmt2 = $conn->prepare($sql_detail);
                // bind_param: i(detail_id) i(amount) d(price) i(bill_id) i(prod_id) i(stock_id)
                $stmt2->bind_param('iidiii', $running_detail_id, $qty, $price, $bill_id, $prod_id, $sid);
                
                if (!$stmt2->execute()) throw new Exception("บันทึกรายการสินค้าไม่สำเร็จ");
                $stmt2->close();

                // Cut Stock
                $sql_stock = "UPDATE prod_stocks SET stock_status = 'Sold', update_at = NOW() WHERE stock_id = ?";
                $stmt3 = $conn->prepare($sql_stock);
                $stmt3->bind_param('i', $sid);
                $stmt3->execute();
                $stmt3->close();

                // Movement
                $move_id = getNextMovementId($conn);
                $sql_move = "INSERT INTO stock_movements (movement_id, movement_type, ref_table, ref_id, create_at, prod_stocks_stock_id) 
                             VALUES (?, 'OUT', 'bill_headers', ?, NOW(), ?)";
                $stmt_move = $conn->prepare($sql_move);
                $stmt_move->bind_param('iii', $move_id, $bill_id, $sid);
                $stmt_move->execute();
                $stmt_move->close();
            }

            mysqli_commit($conn);
            header("Location: payment_select.php?id=" . $bill_id);
            exit;

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เปิดบิลขายสินค้า</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            color: <?= $text_color ?>;
        }
        .container-box {
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-top: 30px;
        }
        .header-text { color: <?= $theme_color ?>; font-weight: bold; }
        .required-mark { color: red; }
        
        @media (max-width: 991.98px) {
            .container-box { padding: 20px; margin-top: 15px; border-radius: 0; }
            .table th, .table td { padding: 0.5rem; font-size: 0.8rem; white-space: nowrap; }
            .table .form-control, .table .form-select { padding: 0.4rem; font-size: 0.75rem; min-width: 80px; }
            .d-grid .btn { width: 100% !important; margin-bottom: 10px; }
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container container-box">
                    <h3 class="header-text mb-4">
                        <i class="fas <?= $edit_mode ? 'fa-edit' : 'fa-cash-register' ?> me-2"></i>
                        <?= $edit_mode ? 'แก้ไขบิลขายสินค้า (POS) #'.$bill_id : 'เปิดบิลขายสินค้า (POS)' ?>
                    </h3>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

                    <form action="" method="post" id="saleForm">
                        
                        <?php if($edit_mode): ?>
                            <input type="hidden" name="bill_id" value="<?= $bill_id ?>">
                        <?php endif; ?>

                        <?php if ($is_admin): ?>
                            <div class="row g-3 mb-3 p-3 bg-light rounded border">
                                <div class="col-md-12"><h6 class="text-primary"><i class="fas fa-store me-2"></i>เลือกสาขาที่ทำรายการ</h6></div>
                                <div class="col-md-6">
                                    <label class="form-label">ร้านค้า <span class="required-mark">*</span></label>
                                    <select name="shop_id" id="shopSelect" class="form-select select2" required>
                                        <option value="">-- เลือกร้านค้า --</option>
                                        <?php foreach ($shops as $shop): ?>
                                            <option value="<?= $shop['shop_id'] ?>" <?= ($shop['shop_id'] == $selected_shop) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($shop['shop_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">สาขา <span class="required-mark">*</span></label>
                                    <select name="branch_id" id="branchSelect" class="form-select select2" required>
                                        <option value="">-- กรุณาเลือกร้านค้าก่อน --</option>
                                    </select>
                                </div>
                            </div>
                        <?php else: ?>
                            <input type="hidden" id="shopSelect" value="<?= $current_shop_id ?>">
                            <input type="hidden" id="branchSelect" name="branch_id" value="<?= $current_branch_id ?>">
                        <?php endif; ?>

                        <div class="row g-3 mb-4 border-bottom pb-4">
                            <div class="col-md-4">
                                <label class="form-label">ลูกค้า <span class="required-mark">*</span></label>
                                <div class="d-flex">
                                    <div class="flex-grow-1" style="min-width: 0;">
                                        <select name="customers_id" id="customerSelect" class="form-select select2" required>
                                            <option value="">-- เลือกลูกค้า --</option>
                                            <?php foreach ($customers_list as $c): ?>
                                                <option value="<?= $c['cs_id'] ?>" <?= ($c['cs_id'] == $selected_customer) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($c['firstname_th'] . ' ' . $c['lastname_th']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <a href="../customer/add_customer.php?return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                       class="btn btn-outline-success ms-2" 
                                       target="_blank" 
                                       title="เพิ่มลูกค้าใหม่"
                                       style="width: 42px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-plus"></i>
                                    </a>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">พนักงานขาย <span class="required-mark">*</span></label>
                                <select name="employees_id" id="employeeSelect" class="form-select select2" required>
                                    <option value="">-- เลือกพนักงาน --</option>
                                    <?php foreach ($employees_list as $e): ?>
                                        <option value="<?= $e['emp_id'] ?>" <?= ($e['emp_id'] == $selected_employee) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($e['firstname_th'] . ' ' . $e['lastname_th']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">VAT (%)</label>
                                <input type="number" name="vat" id="vatInput" class="form-control" value="<?= $val_vat ?>" min="0" oninput="calcTotal()">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">ส่วนลด (บาท)</label>
                                <input type="number" name="discount" id="discountInput" class="form-control" value="<?= $val_discount ?>" min="0" oninput="calcTotal()">
                            </div>

                            <div class="col-md-12 mt-2">
                                <input type="text" name="comment" class="form-control" placeholder="หมายเหตุ (ถ้ามี)" value="<?= htmlspecialchars($val_comment) ?>">
                            </div>
                        </div>

                        <h5 class="mb-3 text-secondary"><i class="fas fa-box-open me-2"></i>รายการสินค้า</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle" id="productTable">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50%">สินค้า / Serial No.</th>
                                        <th width="20%">ราคา (บาท)</th>
                                        <th width="10%" class="text-center">ลบ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between mb-4 flex-wrap">
                            <button type="button" class="btn btn-outline-primary mb-2" onclick="addRow()"><i class="fas fa-plus"></i> เพิ่มรายการ</button>
                            <div class="text-end">
                                <h4>ยอดรวมสุทธิ: <span id="grandTotal" class="text-success">0.00</span> บาท</h4>
                                <small class="text-muted">(รวม VAT - ส่วนลด แล้ว)</small>
                            </div>
                        </div>

                        <div class="text-end">
                            <a href="<?= isset($_GET['return_to']) ? urldecode($_GET['return_to']) : 'sale_list.php' ?>" class="btn btn-secondary me-2">ยกเลิก</a>
                            <button type="submit" name="save_bill" class="btn btn-success btn-lg px-5"><i class="fas fa-save me-2"></i>บันทึกและชำระเงิน</button>
                        </div>
                    </form>
                </div>

                <div id="initData" 
                     data-selected-branch="<?= $selected_branch ?>" 
                     data-selected-employee="<?= $selected_employee ?>"
                     data-selected-customer="<?= $selected_customer ?>"
                     data-edit-bill-id="<?= $bill_id ?>"
                     style="display:none;"></div>
                <div id="stockData" style="display:none;"><?= json_encode($stocks_list) ?></div>
                <div id="existingItemsData" style="display:none;"><?= json_encode($existing_items) ?></div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        let currentBranchStocks = [];
        let existingItems = [];

        $(document).ready(function() {
            $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });

            try {
                currentBranchStocks = JSON.parse($('#stockData').html());
                existingItems = JSON.parse($('#existingItemsData').html());
            } catch(e) {}

            $('#shopSelect').change(function() {
                const shopId = $(this).val();
                loadBranches(shopId);
            });

            $('#branchSelect').change(function() {
                const branchId = $(this).val();
                loadBranchResources(branchId);
            });

            const initBranch = $('#initData').data('selected-branch');
            const initShop = $('#shopSelect').val();

            if ($('#shopSelect').is('select') && initShop) {
                loadBranches(initShop, initBranch);
            } 
            
            if (existingItems.length > 0) {
                existingItems.forEach(item => addRow(item.stock_id, item.price));
            } else {
                if(currentBranchStocks.length > 0) addRow();
            }
        });

        function loadBranches(shopId, preSelectBranch = '') {
            const branchSelect = $('#branchSelect');
            branchSelect.html('<option value="">กำลังโหลด...</option>');
            
            if (!shopId) {
                branchSelect.html('<option value="">-- กรุณาเลือกร้านค้าก่อน --</option>');
                return;
            }

            $.get('add_sale.php', { ajax_action: 'get_branches', shop_id: shopId }, function(data) {
                let options = '<option value="">-- เลือกสาขา --</option>';
                data.forEach(b => {
                    const sel = (b.branch_id == preSelectBranch) ? 'selected' : '';
                    options += `<option value="${b.branch_id}" ${sel}>${b.branch_name}</option>`;
                });
                branchSelect.html(options);
                
                if (preSelectBranch) {
                    loadBranchResources(preSelectBranch);
                }
            }, 'json');
        }

        function loadBranchResources(branchId) {
            const empSelect = $('#employeeSelect');
            const custSelect = $('#customerSelect');
            
            if (!branchId) {
                empSelect.html('<option value="">-- เลือกสาขาก่อน --</option>');
                custSelect.html('<option value="">-- เลือกสาขาก่อน --</option>');
                currentBranchStocks = [];
                return;
            }

            const billId = $('#initData').data('edit-bill-id');
            const preSelectEmp = $('#initData').data('selected-employee');
            const preSelectCust = $('#initData').data('selected-customer');

            $.get('add_sale.php', { ajax_action: 'get_branch_resources', branch_id: branchId, bill_id: billId }, function(data) {
                
                let empOptions = '<option value="">-- เลือกพนักงาน --</option>';
                data.employees.forEach(e => {
                    const sel = (e.emp_id == preSelectEmp) ? 'selected' : '';
                    empOptions += `<option value="${e.emp_id}" ${sel}>${e.firstname_th} ${e.lastname_th}</option>`;
                });
                empSelect.html(empOptions);

                let custOptions = '<option value="">-- เลือกลูกค้า --</option>';
                data.customers.forEach(c => {
                    const sel = (c.cs_id == preSelectCust) ? 'selected' : '';
                    custOptions += `<option value="${c.cs_id}" ${sel}>${c.firstname_th} ${c.lastname_th}</option>`;
                });
                custSelect.html(custOptions);

                currentBranchStocks = data.stocks;

                const tableBody = $('#productTable tbody');
                const isInitialLoad = (tableBody.children().length === 0 && existingItems.length > 0 && preSelectEmp);

                if (!isInitialLoad) {
                    tableBody.empty();
                    addRow(); 
                }

            }, 'json');
        }

        function buildStockOptions(selectedId = null) {
            let options = '<option value="">-- เลือกสินค้า --</option>';
            currentBranchStocks.forEach(s => {
                const isSelected = (selectedId == s.stock_id) ? 'selected' : '';
                options += `<option value="${s.stock_id}" data-price="${s.price}" ${isSelected}>
                    ${s.prod_name} ${s.model_name} (SN: ${s.serial_no})
                </option>`;
            });
            return options;
        }

        function addRow(stockId = null, price = null) {
            if (currentBranchStocks.length === 0 && $('#branchSelect').length > 0 && !$('#branchSelect').val()) {
                return;
            }

            const html = `
                <tr>
                    <td>
                        <select name="stock_ids[]" class="form-select stock-select" onchange="updatePrice(this)" required>
                            ${buildStockOptions(stockId)}
                        </select>
                    </td>
                    <td>
                        <input type="number" name="prices[]" class="form-control text-end price-input" 
                               value="${price !== null ? price : ''}" 
                               min="0" step="0.01" onchange="calcTotal()" oninput="calcTotal()" required>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                    </td>
                </tr>
            `;
            $('#productTable tbody').append(html);
            $('#productTable tbody tr:last .stock-select').select2({ theme: 'bootstrap-5', width: '100%' });
            calcTotal();
        }

        window.updatePrice = function(select) {
            const price = $(select).find(':selected').data('price');
            const row = $(select).closest('tr');
            const priceInput = row.find('.price-input');
            if(priceInput.val() === '' || priceInput.val() == 0) {
                priceInput.val(price || 0);
            }
            calcTotal();
        }
        
        window.removeRow = function(btn) {
            $(btn).closest('tr').remove();
            calcTotal();
        }

        window.calcTotal = function() {
            let total = 0;
            $('.price-input').each(function() {
                total += parseFloat($(this).val()) || 0;
            });

            const vatRate = parseFloat($('#vatInput').val()) || 0;
            const discount = parseFloat($('#discountInput').val()) || 0;

            const vatAmount = total * (vatRate / 100);
            let grandTotal = total + vatAmount - discount;

            if (grandTotal < 0) grandTotal = 0;

            $('#grandTotal').text(grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2 }));
        }
    </script>
</body>
</html>