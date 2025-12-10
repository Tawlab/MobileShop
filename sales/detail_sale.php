<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'detail_sale');
// ตรวจสอบว่าได้รับ id จาก URL หรือไม่
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "ไม่พบรหัสบิล";
    exit;
}

$bill_id = (int)$_GET['id'];

// หากมีการกดปุ่ม "จ่ายแล้ว" ให้ทำการอัปเดตสถานะ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    $today = date('Y-m-d');
    $method = $_POST['method'] ?? 'manual';

    $stmt = $conn->prepare("UPDATE bill_headers SET bill_status = 'completed', receipt_date = ?, payment_method = ? WHERE id = ?");
    $stmt->bind_param("ssi", $today, $method, $bill_id);
    $stmt->execute();

    // Refresh หน้า
    header("Location: detail_sale.php?id=" . $bill_id);
    exit;
}

// ดึงข้อมูลบิล + ลูกค้า + พนักงาน + ร้านค้า + ส่วนลด 
$sql = "
SELECT bh.*, 
       c.fname_th AS customer_fname, c.lname_th AS customer_lname,
       e.fname_th AS emp_fname, e.lname_th AS emp_lname,
       s.name_th AS shop_name,
       s.home_no, s.moo, s.soi, s.road, s.village, s.zip_code,
       d.discount, d.description AS discount_note
FROM bill_headers bh
JOIN customers c ON c.id = bh.customers_id
JOIN employees e ON e.id = bh.employees_id
JOIN shop_info s ON s.id = bh.shop_info_id
LEFT JOIN discounts d ON d.id = bh.discounts_id
WHERE bh.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bill_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "ไม่พบข้อมูลบิล";
    exit;
}
$bill = $result->fetch_assoc();

// ดึงรายการสินค้าในบิลนั้น 
$sql_items = "
SELECT bd.price, bd.amount,
       ps.imei,
       p.name AS product_name, p.model_name
FROM bill_details bd
JOIN prod_stocks ps ON ps.id = bd.stock_id
JOIN products p ON p.id = ps.products_id
WHERE bd.bill_headers_id = ?
";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $bill_id);
$stmt_items->execute();
$items = $stmt_items->get_result();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>รายละเอียดบิลขาย</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Sarabun', sans-serif;
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        h4 {
            color: #2e7d32;
            margin-bottom: 20px;
        }

        .table th,
        .table td {
            vertical-align: middle;
        }

        @media print {
            body {
                font-size: 12pt;
                background: white;
            }

            .btn,
            .no-print {
                display: none !important;
            }

            .container {
                box-shadow: none;
                border: none;
            }

            table,
            th,
            td {
                border: 1px solid black !important;
                border-collapse: collapse !important;
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
                    <h4><i class="fas fa-receipt me-2"></i>ใบเสร็จสินค้า <?= $bill_id ?></h4>

                    <div class="mb-3">
                        <strong>ร้านค้า:</strong> <?= htmlspecialchars($bill['shop_name']) ?><br>
                        <strong>ที่อยู่ร้านค้า:</strong>
                        <?= "บ้านเลขที่ {$bill['home_no']} หมู่ {$bill['moo']} ซ.{$bill['soi']} ถ.{$bill['road']} หมู่บ้าน{$bill['village']} {$bill['zip_code']}" ?> <br>

                        <strong>ลูกค้า:</strong> <?= htmlspecialchars($bill['customer_fname'] . ' ' . $bill['customer_lname']) ?><br>
                    </div>

                    <table class="table table-bordered">
                        <thead class="table-success">
                            <tr>
                                <th>รหัสสินค้า</th>
                                <th>สินค้า</th>
                                <th>IMEI</th>
                                <th>ราคา/ชิ้น</th>
                                <th>จำนวน</th>
                                <th>รวม</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total = 0;
                            $index = 1;
                            while ($row = $items->fetch_assoc()):
                                $sum = $row['price'] * $row['amount'];
                                $total += $sum;
                            ?>
                                <tr>
                                    <td><?= $index++ ?></td>
                                    <td><?= htmlspecialchars($row['product_name'] . ' ' . $row['model_name']) ?></td>
                                    <td><?= htmlspecialchars($row['imei']) ?></td>
                                    <td class="text-end"><?= number_format($row['price'], 2) ?></td>
                                    <td class="text-center"><?= $row['amount'] ?></td>
                                    <td class="text-end"><?= number_format($sum, 2) ?></td>
                                </tr>
                            <?php endwhile; ?>

                            <?php
                            $vat_rate    = $bill['vat'] ?? 7;
                            $discount    = $bill['discount'] ?? 0;
                            $withholding = $total * 0.03;
                            $vat_amount  = $total * ($vat_rate / 100);
                            $net         = $total + $vat_amount - $withholding - $discount;
                            ?>
                        </tbody>

                        <tfoot>
                            <tr>
                                <th colspan="5" class="text-end">รวมก่อน VAT</th>
                                <th class="text-end"><?= number_format($total, 2) ?></th>
                            </tr>
                            <tr>
                                <th colspan="5" class="text-end">VAT (<?= $vat_rate ?>%)</th>
                                <th class="text-end"><?= number_format($vat_amount, 2) ?></th>
                            </tr>
                            <tr>
                                <th colspan="5" class="text-end">ภาษีหัก ณ ที่จ่าย (3%)</th>
                                <th class="text-end">-<?= number_format($withholding, 2) ?></th>
                            </tr>
                            <tr>
                                <th colspan="5" class="text-end">หักส่วนลด</th>
                                <th class="text-end">-<?= number_format($discount, 2) ?></th>
                            </tr>
                            <tr class="table-success">
                                <th colspan="5" class="text-end">รวมสุทธิ</th>
                                <th class="text-end"><?= number_format($net, 2) ?></th>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="text-end mt-4">
                        <button onclick="window.print()" class="btn btn-success me-2">🖨️ พิมพ์ใบเสร็จ</button>
                        <a href="sale_list.php" class="btn btn-secondary me-2">กลับไปหน้ารายการขาย</a>

                        <?php if ($bill['bill_status'] !== 'completed'): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="method" value="<?= htmlspecialchars($bill['payment_method'] ?? 'manual') ?>">
                                <button type="submit" name="mark_paid" class="btn btn-primary">✅ จ่ายแล้ว</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://kit.fontawesome.com/4f2c6f7b67.js" crossorigin="anonymous"></script>
</body>

</html>