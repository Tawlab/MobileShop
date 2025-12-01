<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'pay_qr');
// 1. ตรวจสอบ ID บิล
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "ไม่พบรหัสบิล";
    exit;
}
$bill_id = (int)$_GET['id'];

// 2. ดึงข้อมูลยอดเงินในบิล
$sql = "SELECT bh.*, 
        (SELECT SUM(price * amount) FROM bill_details WHERE bill_headers_bill_id = bh.bill_id) as subtotal 
        FROM bill_headers bh WHERE bh.bill_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bill_id);
$stmt->execute();
$bill = $stmt->get_result()->fetch_assoc();

if (!$bill) die("ไม่พบข้อมูลบิล");

// 3. คำนวณยอดสุทธิ
$vat_amount = $bill['subtotal'] * ($bill['vat'] / 100);
$grand_total = $bill['subtotal'] + $vat_amount - $bill['discount'];

// 4. ดึงเบอร์ PromptPay จากร้านค้า
$shop_sql = "SELECT promptpay_number, shop_name FROM shop_info LIMIT 1";
$shop_res = mysqli_query($conn, $shop_sql);
$shop_info = mysqli_fetch_assoc($shop_res);
$promptpay_id = $shop_info['promptpay_number'] ?? ''; // เบอร์พร้อมเพย์

// =============================================================================
// PROMPTPAY GENERATOR FUNCTIONS (EMVCo Standard)
// =============================================================================
function generatePromptPayPayload($target, $amount)
{
    $target = sanitizeTarget($target);
    $amount = number_format($amount, 2, '.', '');

    // 1. Payload Format Indicator
    $data = ["000201"];
    // 2. Point of Initiation Method (12 = Dynamic)
    $data[] = "010212";
    // 3. Merchant Account Information
    $merchantInfo = "0016A000000677010111"; // AID
    if (strlen($target) >= 13) {
        $merchantInfo .= "0213" . $target; // Tax ID / ID Card
    } else {
        $merchantInfo .= "011300" . $target; // Mobile (เติม 00 นำหน้า + ตัด 0)
    }
    $data[] = "29" . sprintf("%02d", strlen($merchantInfo)) . $merchantInfo;
    // 4. Country Code
    $data[] = "5802TH";
    // 5. Currency
    $data[] = "5303764";
    // 6. Amount (Optional but we use it)
    if ($amount > 0) {
        $data[] = "54" . sprintf("%02d", strlen($amount)) . $amount;
    }
    // 7. Checksum ID
    $raw = implode('', $data) . "6304";
    $crc = crc16($raw);
    return $raw . $crc;
}

function sanitizeTarget($number)
{
    $number = preg_replace('/[^0-9]/', '', $number); // เอาขีดออก
    // ถ้าเป็นเบอร์มือถือ (เช่น 081...) ให้ตัด 0 ตัวแรก แล้วเติม 66
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

// สร้าง Payload
$pp_payload = "";
if (!empty($promptpay_id) && $grand_total > 0) {
    $pp_payload = generatePromptPayPayload($promptpay_id, $grand_total);
}
// =============================================================================

// 5. Handle การยืนยันการโอนเงิน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $today = date('Y-m-d H:i:s');

    $update_sql = "UPDATE bill_headers 
                   SET bill_status = 'Completed', 
                       receipt_date = ?, 
                       payment_method = 'QR' 
                   WHERE bill_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param('si', $today, $bill_id);

    if ($stmt->execute()) {
        header("Location: view_sale.php?id=" . $bill_id);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ชำระเงินผ่าน QR Code</title>
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
                            <i class="fas fa-exclamation-triangle"></i> ไม่พบข้อมูลเลขบัญชีร้านค้า กรุณาตั้งค่าในตาราง shop_info
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-info py-2 mt-2" style="font-size: 0.9rem;">
                        <small><i class="fas fa-info-circle"></i> กรุณาตรวจสอบยอดเงินและชื่อบัญชีก่อนโอน</small>
                    </div>

                    <form method="post" onsubmit="return confirm('ยืนยันว่าลูกค้าโอนเงินเรียบร้อยแล้ว?');">
                        <button type="submit" name="confirm_payment" class="btn btn-success w-100 btn-lg mt-3">
                            <i class="fas fa-check-circle me-2"></i> แจ้งโอนเงินเรียบร้อย
                        </button>
                    </form>

                    <a href="payment_select.php?id=<?= $bill_id ?>" class="btn btn-link text-secondary mt-3 no-decoration">
                        <i class="fas fa-arrow-left"></i> เปลี่ยนวิธีชำระเงิน
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>