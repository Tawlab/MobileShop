<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_sale');

// -----------------------------------------------------------------------------
// HELPER FUNCTIONS
// -----------------------------------------------------------------------------
function getNextMovementId($conn) {
    $sql = "SELECT IFNULL(MAX(movement_id), 0) + 1 as next_id FROM stock_movements";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result)['next_id'];
}

// -----------------------------------------------------------------------------
// INITIALIZE VARIABLES (ค่าเริ่มต้น)
// -----------------------------------------------------------------------------
$edit_mode = false;
$bill_id = 0;
$selected_customer = '';
$selected_employee = '';
$val_vat = 7.00;
$val_discount = 0.00;
$val_comment = '';
$existing_items = [];

// --- ตรวจสอบว่าเป็นการแก้ไขบิลหรือไม่ (รับ ID จาก URL) ---
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $bill_id = (int)$_GET['id'];
    
    // ดึงข้อมูลหัวบิล (Header)
    $sql_head = "SELECT * FROM bill_headers WHERE bill_id = $bill_id AND bill_status = 'Pending'";
    $res_head = mysqli_query($conn, $sql_head);
    
    if ($res_head && mysqli_num_rows($res_head) > 0) {
        $edit_mode = true;
        $head = mysqli_fetch_assoc($res_head);
        
        $selected_customer = $head['customers_cs_id'];
        $selected_employee = $head['employees_emp_id'];
        $val_vat = $head['vat'];
        $val_discount = $head['discount'];
        $val_comment = $head['comment'];

        // ดึงรายการสินค้าเดิม (Details)
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
    }
}

// -----------------------------------------------------------------------------
// FETCH MASTER DATA
// -----------------------------------------------------------------------------
$customers = mysqli_query($conn, "SELECT cs_id, firstname_th, lastname_th FROM customers ORDER BY firstname_th");
$employees = mysqli_query($conn, "SELECT emp_id, firstname_th, lastname_th FROM employees WHERE emp_status = 'Active' ORDER BY firstname_th");

// ดึงข้อมูลสินค้า (In Stock) + สินค้าเดิมในบิลนี้ (กรณีแก้ไข ต้องดึง Sold ของบิลนี้มาด้วยเพื่อให้เลือกได้)
if ($edit_mode) {
    // ดึง In Stock OR (Sold AND อยู่ในบิลนี้)
    $stock_query = "
        SELECT ps.stock_id, ps.serial_no, ps.price, p.prod_id, p.prod_name, p.model_name
        FROM prod_stocks ps
        JOIN products p ON ps.products_prod_id = p.prod_id
        WHERE ps.stock_status = 'In Stock' 
           OR ps.stock_id IN (SELECT prod_stocks_stock_id FROM bill_details WHERE bill_headers_bill_id = $bill_id)
        ORDER BY p.prod_name, ps.serial_no
    ";
} else {
    // โหมดปกติ ดึงแค่ In Stock
    $stock_query = "
        SELECT ps.stock_id, ps.serial_no, ps.price, p.prod_id, p.prod_name, p.model_name
        FROM prod_stocks ps
        JOIN products p ON ps.products_prod_id = p.prod_id
        WHERE ps.stock_status = 'In Stock'
        ORDER BY p.prod_name, ps.serial_no
    ";
}

$stocks = mysqli_query($conn, $stock_query);
$stock_data = [];
while ($s = mysqli_fetch_assoc($stocks)) {
    $stock_data[] = $s;
}

// -----------------------------------------------------------------------------
// HANDLE FORM SUBMIT (SAVE / UPDATE)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_bill'])) {

    $customer_id = (int)$_POST['customers_id'];
    $employee_id = (int)$_POST['employees_id'];
    $branch_id   = 1;
    $vat_rate    = isset($_POST['vat']) ? floatval($_POST['vat']) : 7.00;
    $discount    = isset($_POST['discount']) ? floatval($_POST['discount']) : 0.00;
    $comment     = mysqli_real_escape_string($conn, $_POST['comment'] ?? '');
    $bill_date   = date('Y-m-d H:i:s');
    
    // รับ ID บิล (ถ้ามีแสดงว่าแก้ไข)
    $current_bill_id = !empty($_POST['bill_id']) ? (int)$_POST['bill_id'] : 0;

    mysqli_autocommit($conn, false);

    try {
        if ($current_bill_id > 0) {
            // --- กรณีแก้ไข (UPDATE) ---
            
            // 1. คืนสถานะสต็อกเดิมก่อน (Reset Stock)
            $sql_old_items = "SELECT prod_stocks_stock_id FROM bill_details WHERE bill_headers_bill_id = $current_bill_id";
            $res_old = mysqli_query($conn, $sql_old_items);
            while ($old = mysqli_fetch_assoc($res_old)) {
                $conn->query("UPDATE prod_stocks SET stock_status = 'In Stock' WHERE stock_id = " . $old['prod_stocks_stock_id']);
            }

            // 2. ลบรายละเอียดเดิมทิ้ง
            $conn->query("DELETE FROM bill_details WHERE bill_headers_bill_id = $current_bill_id");

            // 3. อัปเดตหัวบิล
            $stmt = $conn->prepare("UPDATE bill_headers SET vat=?, comment=?, discount=?, customers_cs_id=?, employees_emp_id=?, update_at=NOW() WHERE bill_id=?");
            $stmt->bind_param("dsdiii", $vat_rate, $comment, $discount, $customer_id, $employee_id, $current_bill_id);
            if (!$stmt->execute()) throw new Exception("อัปเดตบิลไม่สำเร็จ");
            $stmt->close();
            
            $bill_id = $current_bill_id; // ใช้ ID เดิม

        } else {
            // --- กรณีสร้างใหม่ (INSERT) ---
            $sql_header = "INSERT INTO bill_headers 
                (bill_date, receipt_date, payment_method, bill_status, vat, comment, discount, 
                 customers_cs_id, bill_type, branches_branch_id, employees_emp_id, create_at, update_at)
                VALUES (?, ?, 'Cash', 'Pending', ?, ?, ?, ?, 'Sale', ?, ?, NOW(), NOW())";

            $stmt = $conn->prepare($sql_header);
            $stmt->bind_param("ssdsdiii", $bill_date, $bill_date, $vat_rate, $comment, $discount, $customer_id, $branch_id, $employee_id);

            if (!$stmt->execute()) throw new Exception("สร้างบิลไม่สำเร็จ: " . $stmt->error);
            $bill_id = $conn->insert_id;
            $stmt->close();
        }

        // 4. บันทึกรายการสินค้าใหม่ & ตัดสต็อก (เหมือนกันทั้ง Insert/Update)
        $stock_ids = $_POST['stock_ids'] ?? [];
        $prices    = $_POST['prices'] ?? [];

        for ($i = 0; $i < count($stock_ids); $i++) {
            $sid = (int)$stock_ids[$i];
            $price = (float)$prices[$i];
            $qty = 1;

            // หา Product ID
            $res_chk = $conn->query("SELECT products_prod_id FROM prod_stocks WHERE stock_id = $sid");
            $row_chk = $res_chk->fetch_assoc();
            $prod_id = $row_chk['products_prod_id'];

            // Insert Detail
            $sql_detail = "INSERT INTO bill_details (amount, price, bill_headers_bill_id, products_prod_id, prod_stocks_stock_id, create_at, update_at) 
                           VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt2 = $conn->prepare($sql_detail);
            $stmt2->bind_param('idiii', $qty, $price, $bill_id, $prod_id, $sid);
            if (!$stmt2->execute()) throw new Exception("บันทึกรายการสินค้าไม่สำเร็จ");
            $stmt2->close();

            // Cut Stock
            $sql_stock = "UPDATE prod_stocks SET stock_status = 'Sold', update_at = NOW() WHERE stock_id = ?";
            $stmt3 = $conn->prepare($sql_stock);
            $stmt3->bind_param('i', $sid);
            $stmt3->execute();
            $stmt3->close();

            // Log Movement (OUT)
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
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title><?= $edit_mode ? 'แก้ไขบิลขาย' : 'เปิดบิลขายสินค้า' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background: <?= $background_color ?>; font-family: '<?= $font_style ?>', sans-serif; color: <?= $text_color ?>; }
        .container-box { background: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08); margin-top: 30px; }
        .header-text { color: <?= $theme_color ?>; font-weight: bold; }
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

                        <div class="row g-3 mb-4 border-bottom pb-4">
                            <div class="col-md-4">
                                <label class="form-label">ลูกค้า <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select name="customers_id" class="form-select" required>
                                        <option value="">-- เลือกลูกค้า --</option>
                                        <?php mysqli_data_seek($customers, 0); ?>
                                        <?php while ($c = mysqli_fetch_assoc($customers)): ?>
                                            <option value="<?= $c['cs_id'] ?>" <?= ($c['cs_id'] == $selected_customer) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['firstname_th'] . ' ' . $c['lastname_th']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <a href="../customer/add_customer.php?return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-outline-success" target="_blank">+</a>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">พนักงานขาย <span class="text-danger">*</span></label>
                                <select name="employees_id" class="form-select" required>
                                    <option value="">-- เลือกพนักงาน --</option>
                                    <?php mysqli_data_seek($employees, 0); ?>
                                    <?php while ($e = mysqli_fetch_assoc($employees)): ?>
                                        <option value="<?= $e['emp_id'] ?>" <?= ($e['emp_id'] == $selected_employee) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($e['firstname_th'] . ' ' . $e['lastname_th']) ?>
                                        </option>
                                    <?php endwhile; ?>
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

                        <div class="d-flex justify-content-between mb-4">
                            <button type="button" class="btn btn-outline-primary" onclick="addRow()"><i class="fas fa-plus"></i> เพิ่มรายการ</button>
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

                <div id="stockData" style="display:none;"><?= json_encode($stock_data) ?></div>
                <div id="existingItemsData" style="display:none;"><?= json_encode($existing_items) ?></div>

            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const stocks = JSON.parse(document.getElementById('stockData').innerHTML);
        const existingItems = JSON.parse(document.getElementById('existingItemsData').innerHTML);

        // ฟังก์ชันสร้างตัวเลือก <option>
        function buildOptions(selectedId = null) {
            let options = '<option value="">-- เลือกสินค้า --</option>';
            stocks.forEach(s => {
                const isSelected = (selectedId == s.stock_id) ? 'selected' : '';
                options += `<option value="${s.stock_id}" data-price="${s.price}" ${isSelected}>
                    ${s.prod_name} ${s.model_name} (SN: ${s.serial_no})
                </option>`;
            });
            return options;
        }

        function addRow(stockId = null, price = null) {
            const table = document.getElementById('productTable').querySelector('tbody');
            const row = document.createElement('tr');

            row.innerHTML = `
                <td>
                    <select name="stock_ids[]" class="form-select stock-select" onchange="updatePrice(this)" required>
                        ${buildOptions(stockId)}
                    </select>
                </td>
                <td>
                    <input type="number" name="prices[]" class="form-control text-end price-input" 
                           value="${price !== null ? price : ''}" 
                           min="0" step="0.01" onchange="calcTotal()" oninput="calcTotal()" required>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                    <input type="hidden" name="amounts[]" value="1">
                </td>
            `;
            table.appendChild(row);
            calcTotal(); // คำนวณใหม่ทุกครั้งที่เพิ่มแถว
        }

        function removeRow(btn) {
            btn.closest('tr').remove();
            calcTotal();
        }

        function updatePrice(select) {
            const price = select.options[select.selectedIndex].getAttribute('data-price');
            const row = select.closest('tr');
            // ถ้ายังไม่มีราคา หรือผู้ใช้ยังไม่ได้แก้ราคาเอง ให้ใส่ราคามาตรฐาน
            const priceInput = row.querySelector('.price-input');
            if(priceInput.value === '' || priceInput.value == 0) {
                priceInput.value = price || 0;
            }
            calcTotal();
        }

        function calcTotal() {
            let total = 0;
            // รวมราคาสินค้าทั้งหมด
            document.querySelectorAll('.price-input').forEach(input => {
                total += parseFloat(input.value) || 0;
            });

            // ดึงค่า VAT และ Discount จากช่อง Input
            const vatRate = parseFloat(document.getElementById('vatInput').value) || 0;
            const discount = parseFloat(document.getElementById('discountInput').value) || 0;

            const vatAmount = total * (vatRate / 100);
            let grandTotal = total + vatAmount - discount;

            if (grandTotal < 0) grandTotal = 0;

            document.getElementById('grandTotal').innerText = grandTotal.toLocaleString('en-US', {
                minimumFractionDigits: 2
            });
        }

        // โหลดข้อมูลเมื่อเปิดหน้า
        window.onload = function() {
            if (existingItems.length > 0) {
                // กรณีแก้ไข: วนลูปสร้างแถวตามข้อมูลเดิม
                existingItems.forEach(item => {
                    addRow(item.stock_id, item.price);
                });
            } else {
                // กรณีสร้างใหม่: เพิ่มแถวว่าง 1 แถว
                addRow();
            }
        };
    </script>
</body>
</html>