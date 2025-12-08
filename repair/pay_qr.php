<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'pay_qr');

// ตรวจสอบ ID บิล
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "ไม่พบรหัสบิล";
    exit;
}
$bill_id = (int)$_GET['id'];

// ดึงข้อมูลบิลและยอดเงินรวม
$sql = "SELECT bh.*, 
        (SELECT SUM(price * amount) FROM bill_details WHERE bill_headers_bill_id = bh.bill_id) as subtotal 
        FROM bill_headers bh WHERE bh.bill_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bill_id);
$stmt->execute();
$bill = $stmt->get_result()->fetch_assoc();

if (!$bill) die("ไม่พบข้อมูลบิล");

// คำนวณยอดสุทธิ 
$subtotal = $bill['subtotal'] ?? 0;
$vat_amount = $subtotal * ($bill['vat'] / 100);
$grand_total = $subtotal + $vat_amount - $bill['discount'];
if ($grand_total < 0) $grand_total = 0;

// 4. ดึงเบอร์ PromptPay จากร้านค้า
$shop_sql = "SELECT promptpay_number, shop_name FROM shop_info LIMIT 1";
$shop_res = mysqli_query($conn, $shop_sql);
$shop_info = mysqli_fetch_assoc($shop_res);
$promptpay_id = $shop_info['promptpay_number'] ?? '';

// =============================================================================
// PROMPTPAY GENERATOR FUNCTIONS
// =============================================================================
function generatePromptPayPayload($target, $amount)
{
    $target = sanitizeTarget($target);
    $amount = number_format($amount, 2, '.', '');
    $data = ["000201", "010212"];
    $merchantInfo = "0016A000000677010111";
    if (strlen($target) >= 13) {
        $merchantInfo .= "0213" . $target;
    } else {
        $merchantInfo .= "011300" . $target;
    }
    $data[] = "29" . sprintf("%02d", strlen($merchantInfo)) . $merchantInfo;
    $data[] = "5802TH";
    $data[] = "5303764";
    if ($amount > 0) {
        $data[] = "54" . sprintf("%02d", strlen($amount)) . $amount;
    }
    $raw = implode('', $data) . "6304";
    $crc = crc16($raw);
    return $raw . $crc;
}

function sanitizeTarget($number)
{
    $number = preg_replace('/[^0-9]/', '', $number);
    if (strlen($number) == 10 && substr($number, 0, 1) == '0') {
        $number = '66' . substr($number, 1);
    }
    return $number;
}

function crc16($data)
{
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($data); $i++) {
        $x = (($crc >> 8) ^ ord($data[$i])) & 0xFF;
        $x ^= $x >> 4;
        $crc = (($crc << 8) ^ ($x << 12) ^ ($x << 5) ^ $x) & 0xFFFF;
    }
    return strtoupper(sprintf("%04x", $crc));
}

// สร้าง Payload สำหรับ QR Code
$pp_payload = "";
if (!empty($promptpay_id) && $grand_total > 0) {
    $pp_payload = generatePromptPayPayload($promptpay_id, $grand_total);
}
// =============================================================================

//  Handle การยืนยันการโอนเงิน (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $today = date('Y-m-d H:i:s');

    //  อัปเดตสถานะบิล -> Completed
    $update_sql = "UPDATE bill_headers 
                   SET bill_status = 'Completed', 
                       receipt_date = ?, 
                       payment_method = 'QR',
                       update_at = NOW()
                   WHERE bill_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param('si', $today, $bill_id);
    $stmt->execute();

    //  ตรวจสอบประเภทบิลเพื่อจัดการสต็อกและสถานะงานซ่อม
    if ($bill['bill_type'] === 'Repair') {
        // หาข้อมูลงานซ่อมที่ผูกกับบิลนี้
        $r_res = mysqli_query($conn, "SELECT repair_id, prod_stocks_stock_id FROM repairs WHERE bill_headers_bill_id = $bill_id LIMIT 1");

        if ($r_row = mysqli_fetch_assoc($r_res)) {
            $repair_id = $r_row['repair_id'];
            $stock_id = $r_row['prod_stocks_stock_id'];

            // เปลี่ยนสถานะงานซ่อมเป็น 'ส่งมอบ'
            $conn->query("UPDATE repairs SET repair_status = 'ส่งมอบ', update_at = NOW() WHERE repair_id = $repair_id");

            // บันทึก Log การเปลี่ยนสถานะ
            $emp_id = $_SESSION['emp_id'] ?? 1;
            $conn->query("INSERT INTO repair_status_log (repairs_repair_id, old_status, new_status, update_by_employee_id, comment, update_at) 
                          VALUES ($repair_id, 'ซ่อมเสร็จ', 'ส่งมอบ', $emp_id, 'ชำระเงินผ่าน QR และส่งมอบอัตโนมัติ', NOW())");

            // ตัดสต็อกเครื่องซ่อมออก (Movement: OUT)
            if ($stock_id > 0) {
                // เปลี่ยนสถานะสต็อกเป็น Sold
                $conn->query("UPDATE prod_stocks SET stock_status = 'Sold', update_at = NOW() WHERE stock_id = $stock_id");

                // สร้าง Movement ID
                $sql_move_id = "SELECT IFNULL(MAX(movement_id), 0) + 1 as next_id FROM stock_movements";
                $move_res = mysqli_query($conn, $sql_move_id);
                $move_id = mysqli_fetch_assoc($move_res)['next_id'];

                // บันทึก Movement
                $move_sql = "INSERT INTO stock_movements (movement_id, movement_type, ref_table, ref_id, create_at, prod_stocks_stock_id) 
                             VALUES ($move_id, 'OUT', 'repairs_return', $repair_id, NOW(), $stock_id)";
                $conn->query($move_sql);
            }

            // กลับไปหน้างานซ่อม
            header("Location: view_repair.php?id=" . $repair_id);
            exit;
        } else {
            // Fallback กรณีไม่เจองานซ่อม
            header("Location: repair_list.php");
            exit;
        }
    } else {
        // ถ้าเป็นการขายปกติ กลับไปหน้าบิลขาย
        header("Location: view_sale.php?id=" . $bill_id);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ชำระเงินผ่าน QR Code - Bill #<?= $bill_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background: #f0f2f5;
        }

        .qr-container {
            max-width: 500px;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .amount-text {
            font-size: 2.5rem;
            color: #198754;
            font-weight: bold;
            margin: 15px 0;
        }

        .qr-image {
            max-width: 300px;
            border: 1px solid #eee;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .pp-id {
            font-size: 1.1rem;
            color: #555;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            display: inline-block;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="qr-container">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/c/c5/PromptPay-logo.png" alt="PromptPay" style="height: 40px;" class="mb-3">
                    <h4 class="mb-2">สแกนเพื่อชำระเงิน</h4>
                    <div class="badge bg-secondary mb-3">Bill ID: #<?= $bill_id ?> (<?= $bill['bill_type'] == 'Repair' ? 'ค่าซ่อม' : 'ค่าสินค้า' ?>)</div>

                    <div class="amount-text">฿<?= number_format($grand_total, 2) ?></div>

                    <?php if (!empty($pp_payload)): ?>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= urlencode($pp_payload) ?>" alt="PromptPay QR" class="qr-image">
                        <br>
                        <div class="pp-id mb-3">
                            <i class="fas fa-mobile-alt me-2"></i>
                            <?= $promptpay_id ?> (<?= htmlspecialchars($shop_info['shop_name']) ?>)
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> ไม่พบข้อมูลเลขบัญชีร้านค้า กรุณาตั้งค่าในเมนู 'ตั้งค่าร้านค้า'
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-info py-2 mt-2" style="font-size: 0.9rem;">
                        <small><i class="fas fa-info-circle"></i> กรุณาตรวจสอบยอดเงินและชื่อบัญชีก่อนโอน</small>
                    </div>

                    <form method="post" onsubmit="return confirm('ยืนยันว่าตรวจสอบยอดเงินเข้าเรียบร้อยแล้ว?\nระบบจะทำการปิดงานซ่อมและตัดสต็อกทันที');">
                        <button type="submit" name="confirm_payment" class="btn btn-success w-100 btn-lg mt-3 shadow-sm">
                            <i class="fas fa-check-circle me-2"></i> แจ้งโอนเงินเรียบร้อย (Confirm Payment)
                        </button>
                    </form>

                    <a href="payment_select.php?id=<?= $bill_id ?>" class="btn btn-link text-secondary mt-3 no-decoration">
                        <i class="fas fa-arrow-left"></i> เปลี่ยนวิธีชำระเงิน
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>