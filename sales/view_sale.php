<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'view_sale');

// ตรวจสอบ ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "ไม่พบรหัสบิล";
    exit;
}
$bill_id = (int)$_GET['id'];

// ดึงข้อมูลหัวบิล (Header) + ร้านค้า + ลูกค้า + พนักงาน
$sql = "
    SELECT bh.*, 
           c.firstname_th AS cus_fname, c.lastname_th AS cus_lname, c.cs_phone_no,
           e.firstname_th AS emp_fname, e.lastname_th AS emp_lname,
           s.shop_name, s.shop_phone, s.tax_id, s.shop_email,
           s.home_no, s.moo, s.soi, s.road, s.village, d.district_name_th, sd.subdistrict_name_th, p.province_name_th, sd.zip_code
    FROM bill_headers bh
    LEFT JOIN customers c ON c.cs_id = bh.customers_cs_id
    LEFT JOIN employees e ON e.emp_id = bh.employees_emp_id
    LEFT JOIN shop_info s ON s.shop_id = bh.shop_info_shop_id -- (ถ้าไม่มี col นี้ใน bill ให้แก้เป็น JOIN ธรรมดา)
    -- หรือถ้าใน bill ไม่ได้เก็บ shop_id ให้ดึง shop แยกต่างหาก
    LEFT JOIN addresses a ON s.Addresses_address_id = a.address_id
    LEFT JOIN subdistricts sd ON a.subdistricts_subdistrict_id = sd.subdistrict_id
    LEFT JOIN districts d ON sd.districts_district_id = d.district_id
    LEFT JOIN provinces p ON d.provinces_province_id = p.province_id
    WHERE bh.bill_id = ?
";

$shop_sql = "SELECT * FROM shop_info s 
             JOIN addresses a ON s.Addresses_address_id = a.address_id
             JOIN subdistricts sd ON a.subdistricts_subdistrict_id = sd.subdistrict_id
             JOIN districts d ON sd.districts_district_id = d.district_id
             JOIN provinces p ON d.provinces_province_id = p.province_id
             LIMIT 1";
$shop_res = mysqli_query($conn, $shop_sql);
$shop = mysqli_fetch_assoc($shop_res);

// Query บิลหลัก
$sql_bill = "
    SELECT bh.*, 
           c.firstname_th AS cus_fname, c.lastname_th AS cus_lname, c.cs_phone_no,
           e.firstname_th AS emp_fname, e.lastname_th AS emp_lname
    FROM bill_headers bh
    LEFT JOIN customers c ON c.cs_id = bh.customers_cs_id
    LEFT JOIN employees e ON e.emp_id = bh.employees_emp_id
    WHERE bh.bill_id = ?
";
$stmt = $conn->prepare($sql_bill);
$stmt->bind_param("i", $bill_id);
$stmt->execute();
$bill = $stmt->get_result()->fetch_assoc();

if (!$bill) die("ไม่พบข้อมูลบิล");

// ดึงรายการสินค้า (Items)
$sql_items = "
    SELECT bd.*, 
           ps.serial_no, 
           p.prod_name, p.model_name
    FROM bill_details bd
    JOIN prod_stocks ps ON bd.prod_stocks_stock_id = ps.stock_id
    JOIN products p ON bd.products_prod_id = p.prod_id
    WHERE bd.bill_headers_bill_id = ?
";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $bill_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();

//  คำนวณยอดเงิน
$subtotal = 0;
$items = [];
while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
    $subtotal += ($row['price'] * $row['amount']);
}

$vat_rate = $bill['vat'];
$discount = $bill['discount'];
// ถ้าราคาสินค้ายังไม่รวม VAT
$vat_amount = $subtotal * ($vat_rate / 100);
$grand_total = $subtotal + $vat_amount - $discount;

// แปลงที่อยู่ร้านเป็น String
$shop_address = "{$shop['home_no']} ";
if ($shop['moo']) $shop_address .= "ม.{$shop['moo']} ";
if ($shop['soi']) $shop_address .= "ซ.{$shop['soi']} ";
if ($shop['road']) $shop_address .= "ถ.{$shop['road']} ";
$shop_address .= "ต.{$shop['subdistrict_name_th']} อ.{$shop['district_name_th']} จ.{$shop['province_name_th']} {$shop['zip_code']}";

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
<<<<<<< HEAD
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
=======
>>>>>>> 87d2bdcaa5a9158c74359bf647e536fa344f68ca
    <title>ใบเสร็จรับเงิน #<?= $bill_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>

    <style>
        :root {
            --theme-color: <?= $theme_color ?>;
        }

        body {
            background: #f4f6f9;
            color: #333;
        }

<<<<<<< HEAD
        /* Screen View (Desktop/Tablet) */
=======
        /* Screen View */
>>>>>>> 87d2bdcaa5a9158c74359bf647e536fa344f68ca
        .card-bill {
            max-width: 850px;
            margin: 40px auto;
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--theme-color);
        }

        .table th {
            background-color: #f8f9fa !important;
        }
<<<<<<< HEAD
        
        /* -------------------------------------------------------------------- */
        /* --- **[เพิ่ม]** Responsive Override สำหรับ Mobile (จอเล็กกว่า 768px) --- */
        /* -------------------------------------------------------------------- */
        @media (max-width: 767.98px) {
            .card-bill {
                margin: 20px auto; /* ลด Margin บน Mobile */
                padding: 20px 15px; /* ลด Padding ด้านข้างเพื่อให้มีพื้นที่มากขึ้น */
                border-radius: 12px; /* คงขอบมนไว้ */
                box-shadow: none; /* ลบเงา */
            }
            
            .header-title {
                font-size: 1.2rem; /* ลดขนาดหัวข้อ */
            }
            
            /* ปรับขนาด Font ในตาราง */
            .table th, .table td {
                font-size: 0.8rem;
                padding: 0.6rem;
            }

            /* จัดการสถานะ */
            .bill-status {
                font-size: 0.7rem;
                padding: 4px 8px;
            }
        }


        /* Print View (A4) - ไม่มีการแก้ไขเพื่อคง Layout การพิมพ์ไว้ */
=======

        /* Print View (A4) */
>>>>>>> 87d2bdcaa5a9158c74359bf647e536fa344f68ca
        @media print {
            @page {
                size: A4;
                margin: 10mm;
            }

            body {
                background: #fff;
            }

            .no-print,
            .btn {
                display: none !important;
            }

            .card-bill {
                box-shadow: none;
                margin: 0;
                padding: 0;
                width: 100%;
                max-width: 100%;
            }

            .table th,
            .table td {
                border: 1px solid #ddd !important;
            }

            .bg-light {
                background-color: #f8f9fa !important;
                -webkit-print-color-adjust: exact;
            }
        }

        .bill-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: bold;
            text-transform: uppercase;
            border: 1px solid;
        }

        .status-Pending {
            color: #ffc107;
            border-color: #ffc107;
            background: #fff3cd;
        }

        .status-Completed {
            color: #198754;
            border-color: #198754;
            background: #d1e7dd;
        }

        .status-Cancelled {
            color: #dc3545;
            border-color: #dc3545;
            background: #f8d7da;
        }
    </style>
</head>
<<<<<<< HEAD
=======

>>>>>>> 87d2bdcaa5a9158c74359bf647e536fa344f68ca
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container card-bill">

                    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                        <a href="sale_list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> กลับ</a>
                        <div>
                            <?php if ($bill['bill_status'] == 'Pending'): ?>
                                <a href="payment_select.php?id=<?= $bill_id ?>" class="btn btn-warning me-2"><i class="fas fa-wallet"></i> ชำระเงิน</a>
                            <?php endif; ?>
                            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> พิมพ์ใบเสร็จ</button>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-8">
                            <h3 class="header-title"><?= htmlspecialchars($shop['shop_name']) ?></h3>
                            <p class="mb-1 small"><?= $shop_address ?></p>
                            <p class="mb-1 small">โทร: <?= $shop['shop_phone'] ?> | เลขผู้เสียภาษี: <?= $shop['tax_id'] ?></p>
                        </div>
                        <div class="col-4 text-end">
                            <h4 class="fw-bold">ใบเสร็จรับเงิน</h4>
                            <div class="text-muted small">RECEIPT / TAX INVOICE</div>

                            <div class="mt-3">
                                <strong>เลขที่:</strong> INV-<?= str_pad($bill_id, 6, '0', STR_PAD_LEFT) ?><br>
                                <strong>วันที่:</strong> <?= date('d/m/Y', strtotime($bill['bill_date'])) ?>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="row mb-4">
                        <div class="col-6">
                            <h6 class="fw-bold">ลูกค้า (Customer):</h6>
                            <?= htmlspecialchars($bill['cus_fname'] . ' ' . $bill['cus_lname']) ?><br>
                            <?= $bill['cs_phone_no'] ? 'โทร: ' . $bill['cs_phone_no'] : '' ?>
                        </div>
                        <div class="col-6 text-end">
                            <div class="bill-status status-<?= $bill['bill_status'] ?>">
                                <?= $bill['bill_status'] ?>
                            </div>
                            <div class="mt-2 small">
                                พนักงานขาย: <?= htmlspecialchars($bill['emp_fname']) ?>
                            </div>
                        </div>
                    </div>

                    <table class="table table-bordered mb-4">
                        <thead class="bg-light">
                            <tr>
                                <th width="5%" class="text-center">#</th>
                                <th width="55%">รายการสินค้า (Description)</th>
                                <th width="15%" class="text-end">ราคาต่อหน่วย</th>
                                <th width="10%" class="text-center">จำนวน</th>
                                <th width="15%" class="text-end">จำนวนเงิน</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $index => $item): ?>
                                <tr>
                                    <td class="text-center"><?= $index + 1 ?></td>
                                    <td>
                                        <?= htmlspecialchars($item['prod_name'] . ' ' . $item['model_name']) ?>
                                        <div class="small text-muted">S/N: <?= $item['serial_no'] ?></div>
                                    </td>
                                    <td class="text-end"><?= number_format($item['price'], 2) ?></td>
                                    <td class="text-center"><?= $item['amount'] ?></td>
                                    <td class="text-end"><?= number_format($item['price'] * $item['amount'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php for ($i = count($items); $i < 5; $i++): ?>
                                <tr>
                                    <td colspan="5" style="height: 30px;"></td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>

                    <div class="row">
                        <div class="col-7">
                            <div class="border p-3 rounded bg-light small">
                                <strong>หมายเหตุ:</strong> <?= $bill['comment'] ?: '-' ?><br>
                                <strong>ชำระโดย:</strong> <?= $bill['payment_method'] ?><br>
                                <strong>วันที่ชำระ:</strong> <?= $bill['receipt_date'] ? date('d/m/Y H:i', strtotime($bill['receipt_date'])) : '-' ?>
                            </div>
                        </div>
                        <div class="col-5">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="text-end">รวมเป็นเงิน:</td>
                                    <td class="text-end fw-bold"><?= number_format($subtotal, 2) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-end">ภาษีมูลค่าเพิ่ม (<?= $bill['vat'] ?>%):</td>
                                    <td class="text-end"><?= number_format($vat_amount, 2) ?></td>
                                </tr>
                                <?php if ($discount > 0): ?>
                                    <tr>
                                        <td class="text-end text-danger">ส่วนลด:</td>
                                        <td class="text-end text-danger">-<?= number_format($discount, 2) ?></td>
                                    </tr>
                                <?php endif; ?>
                                <tr class="border-top border-dark">
                                    <td class="text-end fs-5 fw-bold">ยอดสุทธิ:</td>
                                    <td class="text-end fs-5 fw-bold text-success"><?= number_format($grand_total, 2) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="row mt-5 pt-3 text-center small text-muted no-print-break">
                        <div class="col-6">
                            <br><br>
                            __________________________<br>
                            ผู้รับเงิน / Collector
                        </div>
                        <div class="col-6">
                            <br><br>
                            __________________________<br>
                            ผู้รับสินค้า / Customer
                        </div>
                        <div class="col-12 mt-4">
                            ขอบคุณที่ใช้บริการ
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>