<?php
session_start();
require '../config/config.php';
require '../vendor/autoload.php';
checkPageAccess($conn, 'add_sale');
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "ไม่พบรหัสบิล";
    exit;
}

$bill_id = (int)$_GET['id'];

// ดึงข้อมูลหัวบิล (VAT, Discount)
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

// การเลือกวิธีชำระเงิน
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
            // เงินสด -> จบงานเลย (Completed)
            $conn->query("UPDATE bill_headers SET bill_status = 'Completed', receipt_date = NOW() WHERE bill_id = $bill_id");

            // ส่งอีเมลใบเสร็จ 
            @sendReceiptEmail($conn, $bill_id);

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
        body {
            background: #f4f6f9;
        }

        .container {
            max-width: 800px;
            margin-top: 50px;
        }

        .amount-display {
            background: linear-gradient(135deg, #198754 0%, #20c997 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(25, 135, 84, 0.3);
        }

        .amount-title {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .amount-value {
            font-size: 3.5rem;
            font-weight: bold;
        }

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

        .payment-option.selected {
            border-color: #198754;
            background-color: #f0fff4;
        }

        .icon-box {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #555;
        }

        .payment-option.selected .icon-box {
            color: #198754;
        }

        /* -------------------------------------------------------------------- */
        /* --- **[เพิ่ม]** Responsive Override สำหรับ Mobile (จอเล็กกว่า 768px) --- */
        /* -------------------------------------------------------------------- */
        @media (max-width: 767.98px) {
            .container {
                margin-top: 20px; /* ลด Margin ด้านบน */
                padding-left: 15px;
                padding-right: 15px;
            }
            
            /* 1. ปรับขนาด Amount Display */
            .amount-display {
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .amount-title {
                font-size: 1rem; /* ลดขนาดหัวข้อ */
            }

            .amount-value {
                font-size: 2.5rem; /* ลดขนาดตัวเลขยอดเงิน */
            }
            
            /* 2. ปรับขนาด Icon และ Padding ใน Payment Option Card */
            .payment-option {
                padding: 15px;
                margin-bottom: 15px; /* เพิ่มระยะห่างเมื่อ Card เรียงซ้อนกัน */
            }
            
            .icon-box {
                font-size: 2.5rem; /* ลดขนาด Icon */
                margin-bottom: 10px;
            }
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