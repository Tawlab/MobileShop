<?php
session_start();

// เรียกใช้ PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require '../config/config.php';
require '../vendor/autoload.php';

checkPageAccess($conn, 'add_sale');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "ไม่พบรหัสบิล";
    exit;
}

$bill_id = (int)$_GET['id'];

// ดึงข้อมูลหัวบิล (VAT, Discount) เพื่อคำนวณยอดเงิน
$stmt = $conn->prepare("SELECT * FROM bill_headers WHERE bill_id = ?");
$stmt->bind_param("i", $bill_id);
$stmt->execute();
$header = $stmt->get_result()->fetch_assoc();

if (!$header) {
    echo "ไม่พบข้อมูลบิล";
    exit;
}

// คำนวณยอดรวมสินค้า
$stmt_sum = $conn->prepare("SELECT SUM(price * amount) as subtotal FROM bill_details WHERE bill_headers_bill_id = ?");
$stmt_sum->bind_param("i", $bill_id);
$stmt_sum->execute();
$sum_row = $stmt_sum->get_result()->fetch_assoc();
$subtotal = $sum_row['subtotal'] ?? 0;

// คำนวณยอดสุทธิ
$vat_rate = $header['vat'];
$discount = $header['discount'];
$vat_amount = $subtotal * ($vat_rate / 100);
$grand_total = $subtotal + $vat_amount - $discount;
if ($grand_total < 0) $grand_total = 0;

// -----------------------------------------------------------------------------
// HANDLE PAYMENT SUBMISSION
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $_POST['payment_method'] ?? '';
    $valid_methods = ['Cash', 'QR', 'Credit'];

    if (in_array($method, $valid_methods)) {
        // อัปเดตวิธีชำระเงิน
        $update_sql = "UPDATE bill_headers SET payment_method = ? WHERE bill_id = ?";
        $stmt_up = $conn->prepare($update_sql);
        $stmt_up->bind_param("si", $method, $bill_id);
        $stmt_up->execute();

        // Redirect ตามประเภท
        if ($method === 'Cash') {
            // 1. อัปเดตสถานะบิลเป็น Completed ทันที
            $conn->query("UPDATE bill_headers SET bill_status = 'Completed', receipt_date = NOW() WHERE bill_id = $bill_id");

            // -------------------------------------------------------------------------
            // 2. ส่วนการส่งอีเมลใบเสร็จ
            // -------------------------------------------------------------------------
            try {
                // [แก้ไข 1] เพิ่มการดึง shop_email และ shop_app_password ใน SQL
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

                // [แก้ไข 2] ตรวจสอบความครบถ้วนของข้อมูล (ต้องมี เมลลูกค้า + เมลร้าน + รหัสผ่านร้าน)
                if ($info && !empty($info['cs_email']) && !empty($info['shop_email']) && !empty($info['shop_app_password'])) {
                    
                    // ดึงรายการสินค้า
                    $sql_items = "SELECT bd.amount, bd.price, p.prod_name, p.model_name
                                  FROM bill_details bd
                                  JOIN products p ON bd.products_prod_id = p.prod_id
                                  WHERE bd.bill_headers_bill_id = ?";
                    $stmt_items = $conn->prepare($sql_items);
                    $stmt_items->bind_param("i", $bill_id);
                    $stmt_items->execute();
                    $res_items = $stmt_items->get_result();

                    // เตรียม HTML
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

                    // คำนวณท้ายบิล
                    $cal_vat = $calc_subtotal * ($info['vat'] / 100);
                    $cal_net = $calc_subtotal + $cal_vat - $info['discount'];

                    // HTML Template
                    $email_content = "
                    <div style='background-color: #f4f6f9; padding: 20px; font-family: sans-serif;'>
                        <div style='max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>
                            <div style='text-align: center; border-bottom: 2px solid #198754; padding-bottom: 20px; margin-bottom: 20px;'>
                                <h2 style='color: #198754; margin: 0;'>ใบเสร็จรับเงิน / Receipt</h2>
                                <p style='color: #666; font-size: 14px;'>ขอบคุณที่ใช้บริการ</p>
                            </div>
                            
                            <table style='width: 100%; margin-bottom: 20px;'>
                                <tr>
                                    <td>
                                        <strong>ร้านค้า:</strong> {$info['shop_name']}<br>
                                        <strong>สาขา:</strong> {$info['branch_name']}<br>
                                        <strong>วันที่:</strong> " . date('d/m/Y H:i', strtotime($info['bill_date'])) . "
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
                                        <th style='padding: 10px; text-align: right; border-bottom: 2px solid #dee2e6;'>ราคาต่อหน่วย</th>
                                        <th style='padding: 10px; text-align: right; border-bottom: 2px solid #dee2e6;'>รวม</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {$items_html}
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan='3' style='padding: 8px; text-align: right;'><strong>รวมเป็นเงิน:</strong></td>
                                        <td style='padding: 8px; text-align: right;'>" . number_format($calc_subtotal, 2) . "</td>
                                    </tr>
                                    <tr>
                                        <td colspan='3' style='padding: 8px; text-align: right;'>VAT ({$info['vat']}%):</td>
                                        <td style='padding: 8px; text-align: right;'>" . number_format($cal_vat, 2) . "</td>
                                    </tr>
                                    <tr>
                                        <td colspan='3' style='padding: 8px; text-align: right; color: red;'>ส่วนลด:</td>
                                        <td style='padding: 8px; text-align: right; color: red;'>-" . number_format($info['discount'], 2) . "</td>
                                    </tr>
                                    <tr style='background-color: #e8f5e9;'>
                                        <td colspan='3' style='padding: 12px; text-align: right; color: #198754; font-size: 1.2em;'><strong>ยอดสุทธิ:</strong></td>
                                        <td style='padding: 12px; text-align: right; color: #198754; font-size: 1.2em;'><strong>฿" . number_format($cal_net, 2) . "</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                            <div style='text-align: center; color: #999; font-size: 12px; margin-top: 30px;'>
                                <p>อีเมลฉบับนี้เป็นการแจ้งเตือนอัตโนมัติ กรุณาอย่าตอบกลับ</p>
                            </div>
                        </div>
                    </div>";

                    // ตั้งค่า SMTP (ดึงจากฐานข้อมูลร้านค้า)
                    $mail = new PHPMailer(true);
                    
                    $mail->isSMTP();                                            
                    $mail->Host       = 'smtp.gmail.com';                       
                    $mail->SMTPAuth   = true;                                   
                    $mail->Username   = $info['shop_email'];              // <--- [แก้ไข] ใช้เมลจาก DB ร้านค้า
                    $mail->Password   = $info['shop_app_password'];       // <--- [แก้ไข] ใช้รหัสจาก DB ร้านค้า
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
                    $mail->Port       = 587;                                    
                    $mail->CharSet    = 'UTF-8';                                

                    $mail->setFrom($info['shop_email'], $info['shop_name']); // <--- [แก้ไข] ใช้เมลจาก DB ร้านค้า
                    $mail->addAddress($info['cs_email'], $info['firstname_th']); 
                    $mail->isHTML(true);
                    $mail->Subject = "ใบเสร็จรับเงินอิเล็กทรอนิกส์ (E-Receipt) #{$bill_id} - {$info['shop_name']}";
                    $mail->Body    = $email_content;

                    $mail->send();
                } 
                
            } catch (Exception $e) {
                // error_log("Mail Error: " . $mail->ErrorInfo);
            }

            // Redirect ไปหน้า view_sale
            header("Location: view_sale.php?id=$bill_id");

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background: #f4f6f9; }
        .container { max-width: 800px; margin-top: 50px; }
        .amount-display {
            background: linear-gradient(135deg, #198754 0%, #20c997 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(25, 135, 84, 0.3);
        }
        .amount-title { font-size: 1.2rem; opacity: 0.9; }
        .amount-value { font-size: 3.5rem; font-weight: bold; }
        .payment-option {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
            height: 100%;
        }
        .payment-option:hover {
            border-color: #198754;
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .payment-option.selected { border-color: #198754; background-color: #f0fff4; }
        .icon-box { font-size: 3rem; margin-bottom: 15px; color: #555; }
        .payment-option.selected .icon-box { color: #198754; }

        @media (max-width: 767.98px) {
            .container { margin-top: 20px; padding-left: 15px; padding-right: 15px; }
            .amount-display { padding: 20px; margin-bottom: 20px; }
            .amount-title { font-size: 1rem; }
            .amount-value { font-size: 2.5rem; }
            .payment-option { padding: 15px; margin-bottom: 15px; }
            .icon-box { font-size: 2.5rem; margin-bottom: 10px; }
        }
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
                            <a href="../sales/sale_list.php" class="btn btn-outline-secondary btn-lg me-3">
                                <i class="fas fa-home me-1"></i> กลับหน้าหลัก
                            </a>

                            <a href="../sales/add_sale.php?id=<?= $bill_id ?>" class="btn btn-secondary btn-lg">
                                <i class="fas fa-arrow-left me-1"></i> ยกเลิก / แก้ไข
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
            document.getElementById('selectedMethod').value = method;
            document.getElementById('paymentForm').submit();
        }
    </script>
</body>
</html>