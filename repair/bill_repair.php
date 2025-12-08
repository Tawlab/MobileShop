<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'bill_repair');

//  ตรวจสอบ ID งานซ่อม
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ไม่พบรหัสงานซ่อม";
    header('Location: repair_list.php');
    exit;
}

$repair_id = (int)$_GET['id'];

// ดึงข้อมูลงานซ่อม + บิล
$sql = "SELECT r.*, bh.bill_id, bh.bill_status, 
        c.firstname_th, c.lastname_th, 
        p.prod_name, ps.serial_no
        FROM repairs r
        JOIN bill_headers bh ON r.bill_headers_bill_id = bh.bill_id
        JOIN customers c ON r.customers_cs_id = c.cs_id
        JOIN prod_stocks ps ON r.prod_stocks_stock_id = ps.stock_id
        JOIN products p ON ps.products_prod_id = p.prod_id
        WHERE r.repair_id = $repair_id";
$result = mysqli_query($conn, $sql);
$repair = mysqli_fetch_assoc($result);

if (!$repair) {
    $_SESSION['error'] = "ไม่พบข้อมูล หรือ งานซ่อมนี้ยังไม่ได้เปิดบิล";
    header('Location: repair_list.php');
    exit;
}

$bill_id = $repair['bill_id'];

// ดึงรายการสินค้าในบิลปัจจุบัน
$sql_details = "SELECT bd.*, p.prod_name, p.model_name, ps.serial_no 
                FROM bill_details bd
                LEFT JOIN prod_stocks ps ON bd.prod_stocks_stock_id = ps.stock_id
                JOIN products p ON bd.products_prod_id = p.prod_id
                WHERE bd.bill_headers_bill_id = $bill_id";
$res_details = mysqli_query($conn, $sql_details);

// เตรียมข้อมูลสำหรับ Dropdown เลือกสินค้า
$sql_stock = "SELECT ps.stock_id, p.prod_name, p.model_name, ps.price, ps.serial_no 
              FROM prod_stocks ps
              JOIN products p ON ps.products_prod_id = p.prod_id
              WHERE ps.stock_status = 'In Stock' 
              AND p.prod_types_type_id = 3 
              ORDER BY p.prod_name";
$res_stock = mysqli_query($conn, $sql_stock);

// ดึงค่าบริการ (Services) -> Type ID = 4
$sql_service = "SELECT prod_id, prod_name, 0.00 AS price 
                FROM products 
                WHERE prod_types_type_id = 4
                ORDER BY prod_name";
$res_service = mysqli_query($conn, $sql_service);

// -----------------------------------------------------------------------------
// เพิ่มรายการ
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $item_value = $_POST['item_select'];
    $price = (float)$_POST['price'];
    // รับค่าประกัน
    $warranty_months = !empty($_POST['warranty_months']) ? (int)$_POST['warranty_months'] : NULL;
    $warranty_note = !empty($_POST['warranty_note']) ? mysqli_real_escape_string($conn, $_POST['warranty_note']) : NULL;

    if (!empty($item_value)) {
        list($type, $id) = explode('_', $item_value);
        $id = (int)$id;

        // เตรียม SQL Insert (ใช้ร่วมกัน)
        $sql_insert = "INSERT INTO bill_details (
            amount, price, bill_headers_bill_id, products_prod_id, prod_stocks_stock_id, 
            warranty_duration_months, warranty_note, create_at, update_at
        ) VALUES (1, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $conn->prepare($sql_insert);

        if ($type === 'stock') {
            // --- กรณีอะไหล่ (มีสต็อก) ---
            $stock_id = $id;
            $chk = $conn->query("SELECT products_prod_id FROM prod_stocks WHERE stock_id=$stock_id")->fetch_assoc();
            $prod_id = $chk['products_prod_id'];
            $stmt->bind_param("diiiis", $price, $bill_id, $prod_id, $stock_id, $warranty_months, $warranty_note);
            
            if ($stmt->execute()) {
                // ตัดสต็อก -> เปลี่ยนสถานะเป็น 'Sold'
                $conn->query("UPDATE prod_stocks SET stock_status='Sold' WHERE stock_id=$stock_id");
                
                // บันทึก Movement (OUT)
                $sql_max = "SELECT IFNULL(MAX(movement_id), 0) + 1 as next_id FROM stock_movements";
                $move_id = mysqli_fetch_assoc(mysqli_query($conn, $sql_max))['next_id'];
                $conn->query("INSERT INTO stock_movements (movement_id, movement_type, ref_table, ref_id, create_at, prod_stocks_stock_id) VALUES ($move_id, 'OUT', 'bill_repair', $bill_id, NOW(), $stock_id)");
            }

        } elseif ($type === 'service') {
            // --- กรณีค่าบริการ (ไม่มีสต็อก) ---
            $prod_id = $id; 
            $stock_id = NULL; // ค่าบริการไม่มี stock_id
            $stmt->bind_param("diiiis", $price, $bill_id, $prod_id, $stock_id, $warranty_months, $warranty_note);
            $stmt->execute();
        }

        $stmt->close();
        $_SESSION['success'] = "เพิ่มรายการเรียบร้อย";
        header("Location: bill_repair.php?id=$repair_id");
        exit;
    }
}

// -----------------------------------------------------------------------------
// ลบรายการ
// -----------------------------------------------------------------------------
if (isset($_GET['remove_detail'])) {
    $detail_id = (int)$_GET['remove_detail'];
    
    $chk_sql = "SELECT prod_stocks_stock_id FROM bill_details WHERE detail_id = $detail_id";
    $chk_res = mysqli_query($conn, $chk_sql);
    $chk_row = mysqli_fetch_assoc($chk_res);
    $stock_id = $chk_row['prod_stocks_stock_id'];

    $conn->query("DELETE FROM bill_details WHERE detail_id=$detail_id");

    if (!empty($stock_id)) {
        // คืนสถานะสต็อก
        $conn->query("UPDATE prod_stocks SET stock_status='In Stock' WHERE stock_id=$stock_id");
        
        // บันทึก Movement 
        $sql_max = "SELECT IFNULL(MAX(movement_id), 0) + 1 as next_id FROM stock_movements";
        $move_id = mysqli_fetch_assoc(mysqli_query($conn, $sql_max))['next_id'];
        $conn->query("INSERT INTO stock_movements (movement_id, movement_type, ref_table, ref_id, create_at, prod_stocks_stock_id) VALUES ($move_id, 'ADJUST', 'bill_repair_remove', $bill_id, NOW(), $stock_id)");
    }

    header("Location: bill_repair.php?id=$repair_id");
    exit;
}

// -----------------------------------------------------------------------------
// ไม่มีค่าใช้จ่าย
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['skip_payment'])) {
    $skip_reason = mysqli_real_escape_string($conn, trim($_POST['skip_reason']));
    
    // ลบรายการสินค้าทั้งหมดในบิลนี้ออก (คืนสต็อกอะไหล่ก่อน)
    $sql_details_chk = "SELECT detail_id, prod_stocks_stock_id FROM bill_details WHERE bill_headers_bill_id = $bill_id";
    $res_details_chk = mysqli_query($conn, $sql_details_chk);
    
    while($row = mysqli_fetch_assoc($res_details_chk)) {
        if(!empty($row['prod_stocks_stock_id'])) {
            // คืนสต็อก
            $conn->query("UPDATE prod_stocks SET stock_status='In Stock' WHERE stock_id={$row['prod_stocks_stock_id']}");
            
            // บันทึก Movement ADJUST
            $sql_max = "SELECT IFNULL(MAX(movement_id), 0) + 1 as next_id FROM stock_movements";
            $move_id = mysqli_fetch_assoc(mysqli_query($conn, $sql_max))['next_id'];
            $conn->query("INSERT INTO stock_movements (movement_id, movement_type, ref_table, ref_id, create_at, prod_stocks_stock_id) VALUES ($move_id, 'ADJUST', 'bill_repair_skip', $bill_id, NOW(), {$row['prod_stocks_stock_id']})");
        }
        $conn->query("DELETE FROM bill_details WHERE detail_id={$row['detail_id']}");
    }

    // ปิดบิลเป็น Completed ยอด 0
    $comment = "ไม่มีค่าใช้จ่าย/ข้ามขั้นตอน: " . $skip_reason;
    $sql_up = "UPDATE bill_headers SET bill_status = 'Completed', payment_method = 'Waived', receipt_date = NOW(), comment = ? WHERE bill_id = ?";
    $stmt = $conn->prepare($sql_up);
    $stmt->bind_param("si", $comment, $bill_id);
    $stmt->execute();

    $_SESSION['success'] = "ปิดยอดบิลเรียบร้อยแล้ว (ไม่มีค่าใช้จ่าย)";
    header("Location: view_repair.php?id=$repair_id");
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>จัดการค่าซ่อม - Job #<?= $repair_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>';
        }
        .card-custom {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
        }
        optgroup { font-weight: bold; color: #555; }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container py-5">

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold text-primary"><i class="fas fa-file-invoice-dollar me-2"></i>จัดการค่าใช้จ่ายงานซ่อม</h4>
                        <a href="view_repair.php?id=<?= $repair_id ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> กลับไปดูงานซ่อม</a>
                    </div>

                    <div class="card card-custom mb-4">
                        <div class="card-body bg-light">
                            <div class="row">
                                <div class="col-md-4">
                                    <small class="text-muted">Job Order:</small>
                                    <div class="fw-bold">#<?= $repair_id ?> (Bill: #<?= $bill_id ?>)</div>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">ลูกค้า:</small>
                                    <div class="fw-bold"><?= $repair['firstname_th'] . ' ' . $repair['lastname_th'] ?></div>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">อุปกรณ์:</small>
                                    <div class="fw-bold"><?= $repair['prod_name'] ?> (<?= $repair['serial_no'] ?>)</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-5">
                            <div class="card card-custom h-100">
                                <div class="card-header bg-white fw-bold py-3"><i class="fas fa-plus-circle me-2"></i>เพิ่มรายการ (อะไหล่/ค่าบริการ)</div>
                                <div class="card-body">
                                    <?php if ($repair['bill_status'] == 'Completed'): ?>
                                        <div class="alert alert-success text-center mt-4">
                                            <i class="fas fa-check-circle fa-3x mb-3"></i><br>
                                            <strong>บิลนี้ชำระเงินเรียบร้อยแล้ว</strong><br>
                                            ไม่สามารถเพิ่มหรือแก้ไขรายการได้
                                        </div>
                                    <?php else: ?>
                                        <form method="POST">
                                            <div class="mb-3">
                                                <label class="form-label">เลือกรายการ</label>
                                                <select name="item_select" class="form-select" id="itemSelect" required onchange="updatePrice()">
                                                    <option value="" data-price="0">-- กรุณาเลือก --</option>
                                                    
                                                    <optgroup label="🛠️ ค่าบริการ (Service)">
                                                        <?php 
                                                        if(mysqli_num_rows($res_service) > 0) {
                                                            while ($svc = mysqli_fetch_assoc($res_service)): ?>
                                                                <option value="service_<?= $svc['prod_id'] ?>" data-price="<?= $svc['price'] ?>">
                                                                    <?= $svc['prod_name'] ?>
                                                                </option>
                                                            <?php endwhile; 
                                                        } else { ?>
                                                            <option value="" disabled>-- ไม่มีข้อมูลบริการ --</option>
                                                        <?php } ?>
                                                    </optgroup>

                                                    <optgroup label="📦 อะไหล่ (Parts)">
                                                        <?php 
                                                        if(mysqli_num_rows($res_stock) > 0) {
                                                            while ($s = mysqli_fetch_assoc($res_stock)): ?>
                                                                <option value="stock_<?= $s['stock_id'] ?>" data-price="<?= $s['price'] ?>">
                                                                    <?= $s['prod_name'] ?> <?= $s['model_name'] ?> (SN: <?= $s['serial_no'] ?>)
                                                                </option>
                                                            <?php endwhile; 
                                                        } else { ?>
                                                            <option value="" disabled>-- ไม่มีอะไหล่ในสต็อก --</option>
                                                        <?php } ?>
                                                    </optgroup>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">ราคา (บาท)</label>
                                                <input type="number" name="price" id="priceInput" class="form-control" step="0.01" min="0" required>
                                                <div class="form-text text-muted">สามารถแก้ไขราคาได้ตามจริง</div>
                                            </div>

                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label">ประกัน (เดือน)</label>
                                                    <input type="number" name="warranty_months" class="form-control" placeholder="0">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label">หมายเหตุประกัน</label>
                                                    <input type="text" name="warranty_note" class="form-control" placeholder="เช่น เฉพาะจอ">
                                                </div>
                                            </div>

                                            <button type="submit" name="add_item" class="btn btn-primary w-100 py-2">
                                                <i class="fas fa-plus me-1"></i> เพิ่มลงบิล
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-7">
                            <div class="card card-custom h-100">
                                <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-list me-2"></i>รายการในบิล #<?= $bill_id ?></span>
                                    <span class="badge bg-<?= $repair['bill_status'] == 'Pending' ? 'warning' : 'success' ?>">
                                        สถานะ: <?= $repair['bill_status'] ?>
                                    </span>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="ps-3">รายการ</th>
                                                    <th class="text-center">ประกัน</th>
                                                    <th class="text-end">ราคา</th>
                                                    <th class="text-center">ลบ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $total = 0;
                                                if (mysqli_num_rows($res_details) > 0):
                                                    foreach ($res_details as $item):
                                                        $total += $item['price'];
                                                        $is_stock = !empty($item['prod_stocks_stock_id']);
                                                ?>
                                                        <tr>
                                                            <td class="ps-3">
                                                                <?= htmlspecialchars($item['prod_name']) ?> <?= htmlspecialchars($item['model_name']) ?>
                                                                <?php if($is_stock): ?>
                                                                    <br><small class="text-muted" style="font-size: 0.8em;">SN: <?= $item['serial_no'] ?></small>
                                                                <?php else: ?>
                                                                    <span class="badge bg-info text-dark" style="font-size: 0.7em;">Service</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-center small text-muted">
                                                                <?php 
                                                                    if($item['warranty_duration_months']) echo $item['warranty_duration_months'] . " ด.";
                                                                    else echo "-";
                                                                    
                                                                    if($item['warranty_note']) echo "<br>(".$item['warranty_note'].")";
                                                                ?>
                                                            </td>
                                                            <td class="text-end"><?= number_format($item['price'], 2) ?></td>
                                                            <td class="text-center">
                                                                <?php if ($repair['bill_status'] != 'Completed'): ?>
                                                                    <a href="bill_repair.php?id=<?= $repair_id ?>&remove_detail=<?= $item['detail_id'] ?>"
                                                                        class="btn btn-sm btn-outline-danger" 
                                                                        onclick="return confirm('ต้องการลบรายการนี้ <?= $is_stock ? '(ระบบจะคืนสต็อก)' : '' ?>?')">
                                                                        <i class="fas fa-times"></i>
                                                                    </a>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php
                                                    endforeach;
                                                else:
                                                    ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center py-5 text-muted">
                                                            <i class="fas fa-box-open fa-2x mb-2 opacity-50"></i><br>
                                                            ยังไม่มีรายการค่าใช้จ่าย
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                            <tfoot class="table-light">
                                                <tr>
                                                    <td colspan="2" class="text-end fw-bold">รวมสุทธิ:</td>
                                                    <td class="text-end fw-bold text-success fs-5"><?= number_format($total, 2) ?></td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                                <div class="card-footer bg-white p-3 d-flex justify-content-between align-items-center">
                                    
                                    <?php if ($repair['bill_status'] != 'Completed'): ?>
                                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#skipPaymentModal">
                                            <i class="fas fa-forward me-1"></i> ไม่มีค่าใช้จ่าย / ข้ามการชำระเงิน
                                        </button>
                                    <?php else: ?>
                                        <div></div> <?php endif; ?>

                                    <?php if ($repair['bill_status'] == 'Completed'): ?>
                                        <a href="view_repair.php?id=<?= $repair_id ?>" class="btn btn-secondary">
                                            <i class="fas fa-check-circle me-2"></i> กลับหน้าหลัก
                                        </a>
                                    <?php elseif ($total > 0): ?>
                                        <a href="payment_select.php?id=<?= $bill_id ?>" class="btn btn-success btn-lg shadow-sm">
                                            <i class="fas fa-money-bill-wave me-2"></i> ไปหน้าชำระเงิน
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" disabled>กรุณาเพิ่มรายการก่อนชำระเงิน</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="skipPaymentModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <div class="modal-header">
                                    <h5 class="modal-title">ยืนยันไม่มีค่าใช้จ่าย</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-danger"><i class="fas fa-exclamation-circle"></i> คำเตือน: ระบบจะลบรายการอะไหล่/ค่าแรงทั้งหมดในบิลนี้ และปิดบิลเป็นยอด 0 บาททันที เพื่อให้สามารถเปลี่ยนสถานะเป็น "ส่งมอบ" ได้</p>
                                    <div class="mb-3">
                                        <label class="form-label">เหตุผล / หมายเหตุ:</label>
                                        <input type="text" name="skip_reason" class="form-control" required placeholder="เช่น เคลมประกัน, ลูกค้ายกเลิกซ่อม">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                    <button type="submit" name="skip_payment" class="btn btn-primary">ยืนยัน</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <script>
        function updatePrice() {
            const select = document.getElementById('itemSelect');
            const price = select.options[select.selectedIndex].getAttribute('data-price');
            document.getElementById('priceInput').value = price || 0;
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>