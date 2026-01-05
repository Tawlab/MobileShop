<?php
session_start();

// [เพิ่ม 1] เรียกใช้ PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require '../config/config.php';
require '../vendor/autoload.php'; // [เพิ่ม 2] โหลด Library

checkPageAccess($conn, 'add_sale');

// ตรวจสอบ ID บิล
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "ไม่พบรหัสบิล";
    exit;
}
$bill_id = (int)$_GET['id'];

// ดึงข้อมูลยอดเงินในบิล
$sql = "SELECT bh.*, 
        (SELECT SUM(price * amount) FROM bill_details WHERE bill_headers_bill_id = bh.bill_id) as subtotal 
        FROM bill_headers bh WHERE bh.bill_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bill_id);
$stmt->execute();
$bill = $stmt->get_result()->fetch_assoc();

if (!$bill) die("ไม่พบข้อมูลบิล");

// คำนวณยอดสุทธิ
$vat_amount = $bill['subtotal'] * ($bill['vat'] / 100);
$grand_total = $bill['subtotal'] + $vat_amount - $bill['discount'];

// ดึงเบอร์ PromptPay จากร้านค้า
$shop_sql = "SELECT s.promptpay_number, s.shop_name 
             FROM bill_headers bh
             JOIN branches br ON bh.branches_branch_id = br.branch_id
             JOIN shop_info s ON br.shop_info_shop_id = s.shop_id
             WHERE bh.bill_id = $bill_id";

$shop_res = mysqli_query($conn, $shop_sql);
$shop_info = mysqli_fetch_assoc($shop_res);
$promptpay_id = $shop_info['promptpay_number'] ?? '';

// =============================================================================
// PROMPTPAY GENERATOR FUNCTIONS (EMVCo Standard)
// =============================================================================
function generatePromptPayPayload($target, $amount)
{
    $target = sanitizeTarget($target);
    $amount = number_format($amount, 2, '.', '');

    //  Payload Format Indicator
    $data = ["000201"];
    // Point of Initiation Method 
    $data[] = "010212";
    // Merchant Account Information
    $merchantInfo = "0016A000000677010111"; // AID
    if (strlen($target) >= 13) {
        $merchantInfo .= "0213" . $target; // Tax ID / ID Card
    } else {
        $merchantInfo .= "011300" . $target; // Mobile (เติม 00 นำหน้า + ตัด 0)
    }
    $data[] = "29" . sprintf("%02d", strlen($merchantInfo)) . $merchantInfo;
    // Country Code
    $data[] = "5802TH";
    // Currency
    $data[] = "5303764";
    // Amount
    if ($amount > 0) {
        $data[] = "54" . sprintf("%02d", strlen($amount)) . $amount;
    }
    // Checksum ID
    $raw = implode('', $data) . "6304";
    $crc = crc16($raw);
    return $raw . $crc;
}

function sanitizeTarget($number)
{
    $number = preg_replace('/[^0-9]/', '', $number); // เอาขีดออก
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
// HANDLE CONFIRM PAYMENT (บันทึก + ส่งเมล)
// =============================================================================
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
        
        // -------------------------------------------------------------------------
        // [เพิ่มใหม่] ส่วนการส่งอีเมลใบเสร็จเมื่อชำระสำเร็จ
        // -------------------------------------------------------------------------
        try {
            // 1. ดึงข้อมูล Email ร้านค้า (SMTP) และ Email ลูกค้า
            $sql_info = "SELECT h.bill_date, h.vat, h.discount,
                                c.cs_email, c.firstname_th, c.lastname_th,
                                br.branch_name, 
                                s.shop_name, s.shop_email, s.shop_app_password
                         FROM bill_headers h
                         JOIN customers c ON h.customers_cs_id = c.cs_id
                         JOIN branches br ON h.branches_branch_id = br.branch_id
                         JOIN shop_info s ON br.shop_info_shop_id = s.shop_id
                         WHERE h.bill_id = ?";
            
            $stmt_info = $conn->prepare($sql_info);
            $stmt_info->bind_param("i", $bill_id);
            $stmt_info->execute();
            $info = $stmt_info->get_result()->fetch_assoc();

            // ตรวจสอบว่ามีข้อมูลครบถ้วน (เมลลูกค้า + เมลร้าน + รหัสแอพร้าน)
            if ($info && !empty($info['cs_email']) && !empty($info['shop_email']) && !empty($info['shop_app_password'])) {
                
                // 2. ดึงรายการสินค้า
                $sql_items = "SELECT bd.amount, bd.price, p.prod_name, p.model_name
                              FROM bill_details bd
                              JOIN products p ON bd.products_prod_id = p.prod_id
                              WHERE bd.bill_headers_bill_id = ?";
                $stmt_items = $conn->prepare($sql_items);
                $stmt_items->bind_param("i", $bill_id);
                $stmt_items->execute();
                $res_items = $stmt_items->get_result();

                // 3. เตรียม HTML รายการสินค้า
                $items_html = "";
                $calc_subtotal = 0;
                while ($item = $res_items->fetch_assoc()) {
                    $line_total = $item['price'] * $item['amount'];
                    $calc_subtotal += $line_total;
                    $items_html .= "
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$item['prod_name']} {$item['model_name']}</td>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: center;'>{$item['amount']}</td>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right;'>" . number_format($item['price'], 2) . "</td>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right;'>" . number_format($line_total, 2) . "</td>
                    </tr>";
                }

                // คำนวณท้ายบิลสำหรับแสดงในเมล
                $cal_vat = $calc_subtotal * ($info['vat'] / 100);
                $cal_net = $calc_subtotal + $cal_vat - $info['discount'];

                // 4. สร้าง HTML Body
                $email_content = "
                <div style='background-color: #f4f6f9; padding: 20px; font-family: sans-serif;'>
                    <div style='max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>
                        <div style='text-align: center; border-bottom: 2px solid #198754; padding-bottom: 20px; margin-bottom: 20px;'>
                            <h2 style='color: #198754; margin: 0;'>ใบเสร็จรับเงิน (QR Payment)</h2>
                            <p style='color: #666; font-size: 14px;'>ชำระเงินเรียบร้อยแล้ว</p>
                        </div>
                        <table style='width: 100%; margin-bottom: 20px;'>
                            <tr>
                                <td>
                                    <strong>ร้านค้า:</strong> {$info['shop_name']}<br>
                                    <strong>สาขา:</strong> {$info['branch_name']}<br>
                                    <strong>วันที่:</strong> " . date('d/m/Y H:i') . "
                                </td>
                                <td style='text-align: right; vertical-align: top;'>
                                    <strong>เลขที่บิล:</strong> #{$bill_id}<br>
                                    <strong>ลูกค้า:</strong> {$info['firstname_th']} {$info['lastname_th']}
                                </td>
                            </tr>
                        </table>
                        <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
                            <thead style='background-color: #f8f9fa;'>
                                <tr>
                                    <th style='padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;'>รายการ</th>
                                    <th style='padding: 10px; text-align: center; border-bottom: 2px solid #dee2e6;'>จำนวน</th>
                                    <th style='padding: 10px; text-align: right; border-bottom: 2px solid #dee2e6;'>ราคา</th>
                                    <th style='padding: 10px; text-align: right; border-bottom: 2px solid #dee2e6;'>รวม</th>
                                </tr>
                            </thead>
                            <tbody>{$items_html}</tbody>
                            <tfoot>
                                <tr>
                                    <td colspan='3' style='padding: 8px; text-align: right;'>ยอดรวมสินค้า:</td>
                                    <td style='padding: 8px; text-align: right;'>" . number_format($calc_subtotal, 2) . "</td>
                                </tr>
                                <tr>
                                    <td colspan='3' style='padding: 8px; text-align: right;'>VAT ({$info['vat']}%):</td>
                                    <td style='padding: 8px; text-align: right;'>" . number_format($cal_vat, 2) . "</td>
                                </tr>
                                <tr style='background-color: #e8f5e9;'>
                                    <td colspan='3' style='padding: 12px; text-align: right; color: #198754; font-size: 1.2em;'><strong>ยอดชำระ QR:</strong></td>
                                    <td style='padding: 12px; text-align: right; color: #198754; font-size: 1.2em;'><strong>฿" . number_format($cal_net, 2) . "</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                        <div style='text-align: center; color: #999; font-size: 12px; margin-top: 30px;'>
                            <p>ขอบคุณที่ใช้บริการ</p>
                        </div>
                    </div>
                </div>";

                // 5. ส่งเมลผ่าน PHPMailer
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = $info['shop_email'];        // ใช้เมลจาก DB
                $mail->Password   = $info['shop_app_password']; // ใช้รหัสจาก DB
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom($info['shop_email'], $info['shop_name']);
                $mail->addAddress($info['cs_email'], $info['firstname_th']);
                $mail->isHTML(true);
                $mail->Subject = "ใบเสร็จรับเงิน (QR Payment) #{$bill_id} - {$info['shop_name']}";
                $mail->Body    = $email_content;

                $mail->send();
            }
        } catch (Exception $e) {
            // กรณีส่งเมลไม่ผ่าน ให้ข้ามไป (ไม่แสดง Error ให้ User เห็น)
            // error_log("Mail Error: " . $e->getMessage());
        }
        // -------------------------------------------------------------------------

        header("Location: view_sale.php?id=" . $bill_id);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>เลือกช่องทางชำระเงิน</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php require '../config/load_theme.php'; ?>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { background: #f4f6f9; margin: 0; overflow-x: hidden; }
        .container { max-width: 800px; margin-top: 50px; }
        .amount-display {
            background: linear-gradient(135deg, #198754 0%, #20c997 100%);
            color: white; padding: 30px; border-radius: 15px;
            text-align: center; margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(25, 135, 84, 0.3);
        }
        .amount-title { font-size: 1.2rem; opacity: 0.9; }
        .amount-value { font-size: 3.5rem; font-weight: bold; }
        .qr-container { background: white; padding: 30px; border-radius: 15px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .qr-image { max-width: 250px; border: 1px solid #ddd; padding: 10px; border-radius: 10px; margin: 15px 0; }
        .amount-text { color: #198754; font-size: 2rem; font-weight: bold; }
        .pp-id { font-size: 1.1rem; color: #555; background: #f8f9fa; display: inline-block; padding: 10px 20px; border-radius: 50px; }

        @media (max-width: 767.98px) {
            .container { margin-top: 20px; padding-left: 15px; padding-right: 15px; }
            .amount-display { padding: 20px; margin-bottom: 20px; }
            .amount-title { font-size: 1rem; }
            .amount-value { font-size: 2.5rem; }
            .qr-image { max-width: 200px; }
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container">
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
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>