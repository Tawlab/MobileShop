<?php
session_start();
require '../config/config.php';
require '../vendor/autoload.php';

// เรียกใช้ PHPMailer Namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// ตรวจสอบสิทธิ์ (ถ้า checkPageAccess อยู่ใน functions.php ให้ include มาด้วย)
if (file_exists('../functions.php')) {
    require_once '../functions.php';
} elseif (file_exists('../includes/functions.php')) {
    require_once '../includes/functions.php';
}

checkPageAccess($conn, 'payment_select');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Error: ไม่พบรหัสบิล (Invalid Bill ID)");
}

$bill_id = (int)$_GET['id'];

// ดึงข้อมูลหัวบิลพื้นฐาน
$stmt = $conn->prepare("SELECT * FROM bill_headers WHERE bill_id = ?");
$stmt->bind_param("i", $bill_id);
$stmt->execute();
$header = $stmt->get_result()->fetch_assoc();

if (!$header) {
    die("Error: ไม่พบข้อมูลบิลในระบบ");
}

// คำนวณยอดเงินรวม
$stmt_sum = $conn->prepare("SELECT SUM(price * amount) as subtotal FROM bill_details WHERE bill_headers_bill_id = ?");
$stmt_sum->bind_param("i", $bill_id);
$stmt_sum->execute();
$sum_row = $stmt_sum->get_result()->fetch_assoc();
$subtotal = $sum_row['subtotal'] ?? 0;

$vat_rate = $header['vat'];
$discount = $header['discount'];
$vat_amount = $subtotal * ($vat_rate / 100);
$grand_total = $subtotal + $vat_amount - $discount;
if ($grand_total < 0) $grand_total = 0;

// เตรียมข้อมูล ID สำหรับงานซ่อม
$repair_id = 0;
$stock_id = 0;
$back_btn_url = "sale_list.php"; 

if ($header['bill_type'] === 'Repair') {
    $sql_find_repair = "SELECT repair_id, prod_stocks_stock_id FROM repairs WHERE bill_headers_bill_id = ? LIMIT 1";
    $stmt_r = $conn->prepare($sql_find_repair);
    $stmt_r->bind_param("i", $bill_id);
    $stmt_r->execute();
    $r_res = $stmt_r->get_result();

    if ($r_row = $r_res->fetch_assoc()) {
        $repair_id = $r_row['repair_id'];
        $stock_id = $r_row['prod_stocks_stock_id'];
        $back_btn_url = "bill_repair.php?id=" . $repair_id;
    } else {
        $back_btn_url = "repair_list.php";
    }
} else {
    $back_btn_url = "add_sale.php?id=$bill_id";
}

// ============================================================================
// HANDLE PAYMENT SUBMISSION
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $_POST['payment_method'] ?? '';
    $valid_methods = ['Cash', 'QR', 'Credit'];

    if (in_array($method, $valid_methods)) {
        // อัปเดตวิธีชำระเงิน
        $stmt_up = $conn->prepare("UPDATE bill_headers SET payment_method = ? WHERE bill_id = ?");
        $stmt_up->bind_param("si", $method, $bill_id);
        $stmt_up->execute();

        // --------------------------------------------------------------------
        // กรณีชำระเงินสด (Cash)
        // --------------------------------------------------------------------
        if ($method === 'Cash') {
            
            // ปิดบิล
            $conn->query("UPDATE bill_headers SET bill_status = 'Completed', receipt_date = NOW() WHERE bill_id = $bill_id");

            // จัดการงานซ่อมและสต็อก (ถ้ามี)
            if ($header['bill_type'] === 'Repair' && $repair_id > 0) {
                // อัปเดตสถานะงานซ่อม
                $conn->query("UPDATE repairs SET repair_status = 'ส่งมอบ', update_at = NOW() WHERE repair_id = $repair_id");
                
                // บันทึก Log
                $emp_id = $_SESSION['emp_id'] ?? 1;
                $conn->query("INSERT INTO repair_status_log (repairs_repair_id, old_status, new_status, update_by_employee_id, comment, update_at) 
                              VALUES ($repair_id, 'ซ่อมเสร็จ', 'ส่งมอบ', $emp_id, 'ชำระเงินสด (Cash)', NOW())");

                // ตัดสต็อก
                if ($stock_id > 0) {
                    $conn->query("UPDATE prod_stocks SET stock_status = 'Sold', update_at = NOW() WHERE stock_id = $stock_id");
                    
                    $sql_move_id = "SELECT IFNULL(MAX(movement_id), 0) + 1 as next_id FROM stock_movements";
                    $move_res = mysqli_query($conn, $sql_move_id);
                    $move_id = mysqli_fetch_assoc($move_res)['next_id'];
                    
                    $conn->query("INSERT INTO stock_movements (movement_id, movement_type, ref_table, ref_id, create_at, prod_stocks_stock_id) 
                                 VALUES ($move_id, 'OUT', 'repairs_return', $repair_id, NOW(), $stock_id)");
                }
            }

            // เริ่มกระบวนการส่งอีเมล (Embedded Logic)
            // ------------------------------------------------------------------
            $redirect_url = "";
            $swal_msg = "ชำระเงินเรียบร้อยแล้ว";
            $email_sent_status = false;

            // ดึงข้อมูลครบชุดเพื่อส่งเมล (ร้านค้า + ลูกค้า)
            $sql_full_info = "SELECT bh.*, 
                                     c.firstname_th, c.lastname_th, c.cs_email,
                                     s.shop_name, s.shop_email, s.shop_app_password
                              FROM bill_headers bh
                              LEFT JOIN customers c ON bh.customers_cs_id = c.cs_id
                              LEFT JOIN branches br ON bh.branches_branch_id = br.branch_id
                              LEFT JOIN shop_info s ON br.shop_info_shop_id = s.shop_id
                              WHERE bh.bill_id = ?";
            
            $stmt_info = $conn->prepare($sql_full_info);
            $stmt_info->bind_param("i", $bill_id);
            $stmt_info->execute();
            $bill_data = $stmt_info->get_result()->fetch_assoc();

            // ตรวจสอบว่ามีอีเมลลูกค้า และข้อมูลร้านค้าครบถ้วนหรือไม่
            if ($bill_data && !empty($bill_data['cs_email']) && !empty($bill_data['shop_email']) && !empty($bill_data['shop_app_password'])) {
                
                try {
                    // 2. ดึงรายการสินค้าในบิล
                    $sql_items = "SELECT bd.price, bd.amount, p.prod_name, p.model_name 
                                  FROM bill_details bd 
                                  LEFT JOIN products p ON bd.products_prod_id = p.prod_id 
                                  WHERE bd.bill_headers_bill_id = ?";
                    $stmt_items = $conn->prepare($sql_items);
                    $stmt_items->bind_param("i", $bill_id);
                    $stmt_items->execute();
                    $res_items = $stmt_items->get_result();

                    // สร้าง HTML ตารางสินค้า
                    $rows_html = "";
                    while ($row = $res_items->fetch_assoc()) {
                        $item_name = $row['prod_name'] . " " . $row['model_name'];
                        $rows_html .= "
                            <tr>
                                <td style='padding:8px; border-bottom:1px solid #eee;'>{$item_name}</td>
                                <td style='padding:8px; border-bottom:1px solid #eee; text-align:center;'>{$row['amount']}</td>
                                <td style='padding:8px; border-bottom:1px solid #eee; text-align:right;'>" . number_format($row['price'], 2) . "</td>
                            </tr>";
                    }

                    // สร้างเนื้อหาอีเมล (HTML Body)
                    $customer_name = $bill_data['firstname_th'] . " " . $bill_data['lastname_th'];
                    $bill_title = ($header['bill_type'] === 'Repair') ? "ใบเสร็จค่าซ่อม" : "ใบเสร็จรับเงิน";
                    
                    $bodyContent = "
                    <div style='font-family: sans-serif; max-width: 600px; margin: auto; border: 1px solid #ddd; padding: 20px;'>
                        <h2 style='color:#198754; text-align:center;'>{$bill_data['shop_name']}</h2>
                        <h4 style='text-align:center;'>$bill_title #INV-".str_pad($bill_id, 6, '0', STR_PAD_LEFT)."</h4>
                        <p>เรียนคุณ $customer_name,</p>
                        <p>ขอบคุณที่ใช้บริการ ทางร้านขอส่งรายละเอียดใบเสร็จรับเงินดังนี้:</p>
                        <table width='100%' cellspacing='0' style='margin-top:15px;'>
                            <tr style='background:#f8f9fa;'>
                                <th style='padding:8px; text-align:left;'>รายการ</th>
                                <th style='padding:8px;'>จำนวน</th>
                                <th style='padding:8px; text-align:right;'>ราคา</th>
                            </tr>
                            $rows_html
                            <tr>
                                <td colspan='2' style='padding:10px; text-align:right;'><strong>รวมเป็นเงิน:</strong></td>
                                <td style='padding:10px; text-align:right;'>" . number_format($subtotal, 2) . "</td>
                            </tr>
                            <tr>
                                <td colspan='2' style='padding:5px 10px; text-align:right;'>VAT ({$vat_rate}%):</td>
                                <td style='padding:5px 10px; text-align:right;'>" . number_format($vat_amount, 2) . "</td>
                            </tr>
                            <tr>
                                <td colspan='2' style='padding:10px; text-align:right; color:#198754;'><strong>ยอดสุทธิ:</strong></td>
                                <td style='padding:10px; text-align:right; color:#198754;'><strong>" . number_format($grand_total, 2) . " ฿</strong></td>
                            </tr>
                        </table>
                        <div style='margin-top:30px; text-align:center; font-size:12px; color:#777;'>
                            <p>เอกสารฉบับนี้จัดทำโดยระบบอัตโนมัติ</p>
                        </div>
                    </div>";

                    // ตั้งค่า PHPMailer และส่ง
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $bill_data['shop_email'];
                    $mail->Password   = $bill_data['shop_app_password'];
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;
                    $mail->CharSet    = 'UTF-8';

                    $mail->setFrom($bill_data['shop_email'], $bill_data['shop_name']);
                    $mail->addAddress($bill_data['cs_email'], $customer_name);

                    $mail->isHTML(true);
                    $mail->Subject = "$bill_title #INV-" . str_pad($bill_id, 6, '0', STR_PAD_LEFT);
                    $mail->Body    = $bodyContent;

                    $mail->send();
                    $email_sent_status = true;

                } catch (Exception $e) {
                    // ส่งไม่ผ่าน (อาจจะบันทึก Log ไว้ถ้าต้องการ)
                    $email_sent_status = false;
                }
            }

            // D. กำหนดการ Redirect ตามเงื่อนไข (Repair vs Sale และ Email Status)
            if ($header['bill_type'] === 'Repair') {
                if ($email_sent_status) {
                    // กรณี 1: เป็นงานซ่อม และส่งเมลสำเร็จ -> ไปหน้า View
                    $swal_msg .= " และส่งใบเสร็จทางอีเมลเรียบร้อยแล้ว";
                    $redirect_url = "view_repair.php?id=$repair_id";
                } else {
                    // กรณี 2: เป็นงานซ่อม แต่ส่งเมลไม่ได้ (หรือไม่มีเมล) -> ไปหน้า Print
                    if (empty($bill_data['cs_email'])) {
                        $swal_msg .= " (ลูกค้าไม่มีอีเมล นำทางไปหน้าพิมพ์ใบเสร็จ)";
                    } else {
                        $swal_msg .= " (แต่ระบบส่งอีเมลไม่สำเร็จ กรุณาพิมพ์ใบเสร็จแทน)";
                    }
                    $redirect_url = "print_repair_bill.php?id=$repair_id";
                }
            } else {
                // กรณีงานขาย (Sale) -> ไปหน้า View เสมอ
                if ($email_sent_status) {
                    $swal_msg .= " และส่งใบเสร็จทางอีเมลแล้ว";
                }
                $redirect_url = "view_sale.php?id=$bill_id";
            }

            $_SESSION['success'] = "✅ " . $swal_msg;
            header("Location: " . $redirect_url);
            exit;

        } elseif ($method === 'QR') {
            header("Location: pay_qr.php?id=$bill_id");
            exit;
        } elseif ($method === 'Credit') {
            header("Location: pay_credit.php?id=$bill_id");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>เลือกช่องทางชำระเงิน - #<?= $bill_id ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            color: <?= $text_color ?>;
        }

        .container {
            max-width: 850px;
            margin-top: 40px;
            margin-bottom: 40px;
        }

        /* Card แสดงยอดเงิน */
        .amount-display {
            background: linear-gradient(135deg, <?= $theme_color ?> 0%, #198754 100%);
            color: white;
            padding: 40px 20px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 40px;
            box-shadow: 0 10px 25px rgba(25, 135, 84, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .amount-display::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(30deg);
            pointer-events: none;
        }

        .amount-title {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .amount-value {
            font-size: 4rem;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            line-height: 1;
        }

        .bill-type-badge {
            margin-top: 15px;
            font-size: 0.9rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 50px;
            display: inline-block;
            backdrop-filter: blur(5px);
        }

        /* การ์ดเลือกการชำระเงิน */
        .payment-option {
            border: 2px solid transparent;
            border-radius: 16px;
            padding: 25px 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            background: white;
            height: 100%;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .payment-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.1);
            border-color: <?= $theme_color ?>;
        }

        .payment-option:active {
            transform: scale(0.98);
        }

        .icon-box {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #6c757d;
            transition: color 0.3s;
        }

        .payment-option:hover .icon-box {
            color: <?= $theme_color ?>;
        }

        .payment-option h5 {
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .payment-option small {
            color: #777;
        }

        /* Hover Effect Decoration */
        .payment-option::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: <?= $theme_color ?>;
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.3s ease-out;
        }

        .payment-option:hover::after {
            transform: scaleX(1);
            transform-origin: left;
        }

        .btn-back {
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background-color: #5a6268;
            border-color: #545b62;
            transform: translateX(-3px);
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
                        <div class="bill-type-badge">
                            <i class="fas <?= ($header['bill_type'] == 'Repair') ? 'fa-tools' : 'fa-shopping-cart' ?> me-1"></i>
                            <?= ($header['bill_type'] == 'Repair') ? 'ชำระค่าบริการซ่อม (INV-'.str_pad($bill_id, 6, '0', STR_PAD_LEFT).')' : 'ชำระค่าสินค้า (INV-'.str_pad($bill_id, 6, '0', STR_PAD_LEFT).')' ?>
                        </div>
                    </div>

                    <h5 class="mb-4 text-center text-secondary fw-bold">
                        <i class="fas fa-wallet me-2"></i>เลือกวิธีการชำระเงิน
                    </h5>

                    <form method="POST" id="paymentForm">
                        <input type="hidden" name="payment_method" id="selectedMethod">

                        <div class="row g-4 justify-content-center">
                            <div class="col-md-4 col-sm-6">
                                <div class="payment-option" onclick="selectPayment('Cash')">
                                    <div class="icon-box"><i class="fas fa-money-bill-wave"></i></div>
                                    <h5>เงินสด (Cash)</h5>
                                    <small>ชำระที่เคาน์เตอร์</small>
                                </div>
                            </div>
                            
                            <div class="col-md-4 col-sm-6">
                                <div class="payment-option" onclick="selectPayment('QR')">
                                    <div class="icon-box"><i class="fas fa-qrcode"></i></div>
                                    <h5>สแกนจ่าย (QR)</h5>
                                    <small>Mobile Banking / PromptPay</small>
                                </div>
                            </div>
                            
                            <div class="col-md-4 col-sm-6">
                                <div class="payment-option" onclick="selectPayment('Credit')">
                                    <div class="icon-box"><i class="fas fa-credit-card"></i></div>
                                    <h5>บัตรเครดิต</h5>
                                    <small>Visa / MasterCard</small>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-5">
                            <a href="<?= $back_btn_url ?>" class="btn btn-secondary btn-back shadow-sm">
                                <i class="fas fa-arrow-left me-2"></i> ยกเลิก / กลับไปแก้ไข
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function selectPayment(method) {
            if (method === 'Credit') {
                Swal.fire({
                    icon: 'info',
                    title: 'ขออภัย',
                    text: 'ระบบชำระผ่านบัตรเครดิตยังไม่เปิดให้บริการในขณะนี้',
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#6c757d'
                });
                return;
            }

            let titleMsg = 'ยืนยันการเลือก ' + method + '?';
            let detailMsg = '';
            
            if (method === 'Cash') {
                titleMsg = 'ยืนยันรับชำระ "เงินสด"?';
                detailMsg = '<ul class="text-start mt-3" style="list-style: none;">' + 
                            '<li>✅ ระบบจะปิดบิลและตัดสต็อกทันที</li>' + 
                            '<li>📧 หากลูกค้ามีอีเมล ระบบจะส่งใบเสร็จอัตโนมัติ</li>' +
                            '<li>🖨️ หากไม่มีอีเมล ระบบจะพาไปหน้าพิมพ์ใบเสร็จ</li>' +
                            '</ul>';
            } else if (method === 'QR') {
                titleMsg = 'ไปที่หน้าสแกน QR Code?';
            }

            Swal.fire({
                title: titleMsg,
                html: detailMsg,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#d33',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'กำลังบันทึก...',
                        html: 'กรุณารอสักครู่ ระบบกำลังประมวลผล',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    document.getElementById('selectedMethod').value = method;
                    document.getElementById('paymentForm').submit();
                }
            });
        }
    </script>
</body>

</html>