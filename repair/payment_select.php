<?php
session_start();
require '../config/config.php';
require '../vendor/autoload.php';
checkPageAccess($conn, 'payment_select');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "ไม่พบรหัสบิล";
    exit;
}

$bill_id = (int)$_GET['id'];

// 1. ดึงข้อมูลหัวบิล
$stmt = $conn->prepare("SELECT * FROM bill_headers WHERE bill_id = ?");
$stmt->bind_param("i", $bill_id);
$stmt->execute();
$header = $stmt->get_result()->fetch_assoc();

if (!$header) {
    echo "ไม่พบข้อมูลบิล";
    exit;
}

// 2. คำนวณยอดรวมสินค้า (Subtotal)
$stmt_sum = $conn->prepare("SELECT SUM(price * amount) as subtotal FROM bill_details WHERE bill_headers_bill_id = ?");
$stmt_sum->bind_param("i", $bill_id);
$stmt_sum->execute();
$sum_row = $stmt_sum->get_result()->fetch_assoc();
$subtotal = $sum_row['subtotal'] ?? 0;

// 3. คำนวณยอดสุทธิ
$vat_rate = $header['vat'];
$discount = $header['discount'];
$vat_amount = $subtotal * ($vat_rate / 100);
$grand_total = $subtotal + $vat_amount - $discount;
if ($grand_total < 0) $grand_total = 0;

// 4. กำหนดเส้นทางย้อนกลับ
$redirect_url = "view_sale.php?id=$bill_id";
$back_btn_url = "sale_list.php";
$repair_id = 0; // ตัวแปรเก็บ repair_id

if ($header['bill_type'] === 'Repair') {
    $r_res = mysqli_query($conn, "SELECT repair_id, prod_stocks_stock_id FROM repairs WHERE bill_headers_bill_id = $bill_id LIMIT 1");
    if ($r_row = mysqli_fetch_assoc($r_res)) {
        $repair_id = $r_row['repair_id'];
        $stock_id = $r_row['prod_stocks_stock_id']; // ID เครื่องลูกค้า
        $redirect_url = "view_repair.php?id=" . $repair_id;
        $back_btn_url = "bill_repair.php?id=" . $repair_id;
    }
} else {
    $back_btn_url = "add_sale.php?id=$bill_id";
}

// 5. Handle Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $_POST['payment_method'] ?? '';
    $valid_methods = ['Cash', 'QR', 'Credit'];

    if (in_array($method, $valid_methods)) {
        
        // อัปเดตวิธีชำระเงินในบิล
        $update_sql = "UPDATE bill_headers SET payment_method = ? WHERE bill_id = ?";
        $stmt_up = $conn->prepare($update_sql);
        $stmt_up->bind_param("si", $method, $bill_id);
        $stmt_up->execute();

        // --------------------------------------------------------------------
        // กรณีจ่ายเงินสด (Cash) -> จบงานทันที + ตัดสต็อก
        // --------------------------------------------------------------------
        if ($method === 'Cash') {
            // 1. ปิดบิล
            $conn->query("UPDATE bill_headers SET bill_status = 'Completed', receipt_date = NOW() WHERE bill_id = $bill_id");

            // 2. ถ้าเป็นงานซ่อม -> ปิดงานซ่อม + ตัดสต็อกเครื่องลูกค้า
            if ($header['bill_type'] === 'Repair' && $repair_id > 0) {
                
                // 2.1 เปลี่ยนสถานะงานซ่อมเป็น 'ส่งมอบ'
                $conn->query("UPDATE repairs SET repair_status = 'ส่งมอบ', update_at = NOW() WHERE repair_id = $repair_id");
                
                // 2.2 บันทึก Log
                $emp_id = $_SESSION['emp_id'] ?? 1;
                $conn->query("INSERT INTO repair_status_log (repairs_repair_id, old_status, new_status, update_by_employee_id, comment, update_at) 
                              VALUES ($repair_id, 'ซ่อมเสร็จ', 'ส่งมอบ', $emp_id, 'ชำระเงินและส่งมอบอัตโนมัติ (Cash)', NOW())");

                // 2.3 ตัดสต็อกเครื่องลูกค้าออกจากระบบ (Stock Status -> Sold)
                if ($stock_id > 0) {
                    // เปลี่ยนสถานะสต็อก
                    $conn->query("UPDATE prod_stocks SET stock_status = 'Sold', update_at = NOW() WHERE stock_id = $stock_id");

                    // สร้าง Movement (OUT)
                    $sql_move_id = "SELECT IFNULL(MAX(movement_id), 0) + 1 as next_id FROM stock_movements";
                    $move_id = mysqli_fetch_assoc(mysqli_query($conn, $sql_move_id))['next_id'];
                    
                    $move_sql = "INSERT INTO stock_movements (movement_id, movement_type, ref_table, ref_id, create_at, prod_stocks_stock_id) 
                                 VALUES ($move_id, 'OUT', 'repairs_return', $repair_id, NOW(), $stock_id)";
                    $conn->query($move_sql);
                }
            }
            
            // @sendReceiptEmail($conn, $bill_id); // ส่งเมลใบเสร็จ (ถ้ามีฟังก์ชัน)

            header("Location: " . $redirect_url);
        } elseif ($method === 'QR') {
            header("Location: pay_qr.php?id=$bill_id");
        } elseif ($method === 'Credit') {
            header("Location: pay_credit.php?id=$bill_id");
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เลือกช่องทางชำระเงิน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background: #f4f6f9; }
        .container { max-width: 800px; margin-top: 50px; }
        .amount-display { background: linear-gradient(135deg, #198754 0%, #20c997 100%); color: white; padding: 30px; border-radius: 15px; text-align: center; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(25, 135, 84, 0.3); }
        .amount-title { font-size: 1.2rem; opacity: 0.9; }
        .amount-value { font-size: 3.5rem; font-weight: bold; }
        .payment-option { border: 2px solid #e9ecef; border-radius: 15px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s; background: white; height: 100%; }
        .payment-option:hover { border-color: #198754; transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); }
        .payment-option.selected { border-color: #198754; background-color: #f0fff4; }
        .icon-box { font-size: 3rem; margin-bottom: 15px; color: #555; }
        .payment-option.selected .icon-box { color: #198754; }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container">
                    <div class="amount-display">
                        <div class="amount-title">ยอดชำระสุทธิ (Net Total)</div>
                        <div class="amount-value">฿<?= number_format($grand_total, 2) ?></div>
                        <div class="mt-2 badge bg-light text-dark">
                            <?= ($header['bill_type'] == 'Repair') ? 'ชำระค่าซ่อม' : 'ชำระค่าสินค้า' ?>
                        </div>
                    </div>

                    <h4 class="mb-4 text-center text-secondary">กรุณาเลือกวิธีการชำระเงิน</h4>

                    <form method="POST" id="paymentForm">
                        <input type="hidden" name="payment_method" id="selectedMethod">

                        <div class="row g-4 justify-content-center">
                            <div class="col-md-4">
                                <div class="payment-option" onclick="selectPayment('Cash')">
                                    <div class="icon-box"><i class="fas fa-money-bill-wave"></i></div>
                                    <h5>เงินสด (Cash)</h5>
                                    <small class="text-muted">ชำระที่เคาน์เตอร์</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="payment-option" onclick="selectPayment('QR')">
                                    <div class="icon-box"><i class="fas fa-qrcode"></i></div>
                                    <h5>สแกนจ่าย (QR)</h5>
                                    <small class="text-muted">Mobile Banking</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="payment-option" onclick="selectPayment('Credit')">
                                    <div class="icon-box"><i class="fas fa-credit-card"></i></div>
                                    <h5>บัตรเครดิต</h5>
                                    <small class="text-muted">Visa / MasterCard</small>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-5">
                            <a href="<?= $back_btn_url ?>" class="btn btn-secondary btn-lg me-3">
                                <i class="fas fa-arrow-left"></i> ยกเลิก / กลับไปแก้ไข
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectPayment(method) {
            if(method === 'Credit') {
                alert('ขออภัย ระบบชำระผ่านบัตรเครดิตยังไม่เปิดให้บริการ');
                return;
            }
            
            let msg = 'ยืนยันการเลือกช่องทางชำระเงิน: ' + method + '?';
            if(method === 'Cash') {
                msg += '\n\n(ระบบจะปิดงานซ่อมและตัดสต็อกเครื่องลูกค้าทันที)';
            }

            if(confirm(msg)) {
                document.getElementById('selectedMethod').value = method;
                document.getElementById('paymentForm').submit();
            }
        }
    </script>
</body>
</html>