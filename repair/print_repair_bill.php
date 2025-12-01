<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'view_repair'); // ใช้สิทธิ์เดียวกับการดูงานซ่อม

// 1. ตรวจสอบค่า ID บิล
if (!isset($_GET['id'])) {
    die("ไม่พบรหัสบิล");
}

$bill_id = (int)$_GET['id'];

// 2. ดึงข้อมูลร้านค้า (Shop Info)
$shop_sql = "SELECT * FROM shop_info LIMIT 1";
$shop_result = mysqli_query($conn, $shop_sql);
$shop = mysqli_fetch_assoc($shop_result);

// 3. ดึงข้อมูลหัวบิล (Bill Header) + ลูกค้า + งานซ่อม + รายละเอียดเครื่องลูกค้า
// [แก้ไข] JOIN ตาราง addresses, subdistricts, districts, provinces เพื่อดึงที่อยู่ลูกค้า
$sql = "SELECT bh.*, 
        c.firstname_th, c.lastname_th, c.cs_phone_no, c.cs_national_id, 
        -- ดึงที่อยู่ลูกค้า (รวมสตริง)
        CONCAT(IFNULL(addr.home_no,''), ' ม.', IFNULL(addr.moo,''), ' ', IFNULL(addr.soi,''), ' ', IFNULL(addr.road,''), ' ', IFNULL(sd.subdistrict_name_th,''), ' ', IFNULL(dt.district_name_th,''), ' ', IFNULL(pv.province_name_th,''), ' ', IFNULL(sd.zip_code,'')) AS cs_address_full,
        
        r.repair_id, r.create_at as repair_date,
        p.prod_name AS device_name, p.model_name AS device_model, ps.serial_no AS device_serial,
        e.firstname_th as emp_fname, e.lastname_th as emp_lname
        FROM bill_headers bh
        LEFT JOIN customers c ON bh.customers_cs_id = c.cs_id
        -- JOIN ที่อยู่ลูกค้า
        LEFT JOIN addresses addr ON c.Addresses_address_id = addr.address_id
        LEFT JOIN subdistricts sd ON addr.subdistricts_subdistrict_id = sd.subdistrict_id
        LEFT JOIN districts dt ON sd.districts_district_id = dt.district_id
        LEFT JOIN provinces pv ON dt.provinces_province_id = pv.province_id
        -- JOIN งานซ่อม
        LEFT JOIN repairs r ON bh.bill_id = r.bill_headers_bill_id
        LEFT JOIN prod_stocks ps ON r.prod_stocks_stock_id = ps.stock_id
        LEFT JOIN products p ON ps.products_prod_id = p.prod_id
        LEFT JOIN employees e ON bh.employees_emp_id = e.emp_id
        WHERE bh.bill_id = $bill_id";

$result = mysqli_query($conn, $sql);
$header = mysqli_fetch_assoc($result);

if (!$header) {
    die("ไม่พบข้อมูลเอกสาร หรือ เอกสารนี้ไม่มีอยู่จริง");
}

// 4. ดึงรายการในบิล (Bill Details) + ข้อมูลสินค้า + ข้อมูลการรับประกัน
$sql_detail = "SELECT bd.*, p.prod_name, p.model_name 
               FROM bill_details bd
               JOIN products p ON bd.products_prod_id = p.prod_id
               WHERE bd.bill_headers_bill_id = $bill_id";
$details = mysqli_query($conn, $sql_detail);

// ตัวแปรสำหรับคำนวณยอดรวม (Subtotal)
$subtotal = 0;
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ใบเสร็จรับเงิน - BILL #<?= $bill_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background: #e9ecef;
            font-family: 'Sarabun', sans-serif;
            font-size: 14px;
            color: #333;
        }

        .invoice-container {
            background: #fff;
            width: 210mm;
            /* ขนาด A4 */
            min-height: 297mm;
            margin: 20px auto;
            padding: 40px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .header-title {
            font-size: 24px;
            font-weight: bold;
            color: #000;
        }

        .header-subtitle {
            font-size: 14px;
            color: #555;
        }

        /* กล่องแสดงข้อมูล (ลูกค้า/เอกสาร) */
        .box-info {
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 8px;
            height: 100%;
        }

        .box-title {
            font-weight: bold;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 5px;
            margin-bottom: 10px;
            font-size: 16px;
        }

        /* ตารางรายการ */
        .table-custom th {
            background-color: #f8f9fa !important;
            border-bottom: 2px solid #dee2e6;
            text-align: center;
            font-weight: bold;
        }

        .table-custom td {
            vertical-align: middle;
        }

        /* ส่วนสรุปยอดเงิน */
        .total-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        /* การตั้งค่าสำหรับการพิมพ์ (Print) */
        @media print {
            body {
                background: #fff;
                margin: 0;
                padding: 0;
            }

            .invoice-container {
                box-shadow: none;
                margin: 0;
                width: 100%;
                border: none;
                padding: 20px;
                min-height: auto;
            }

            .no-print {
                display: none !important;
            }

            .btn {
                display: none !important;
            }

            /* บังคับให้ Browser พิมพ์สีพื้นหลัง */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>

<body>

    <div class="text-center mb-3 no-print pt-4">
        <button onclick="window.print()" class="btn btn-primary shadow-sm">
            <i class="fas fa-print me-2"></i> พิมพ์ใบเสร็จ
        </button>
        <button onclick="window.close()" class="btn btn-secondary shadow-sm">
            <i class="fas fa-times me-2"></i> ปิดหน้าต่าง
        </button>
    </div>

    <div class="invoice-container">

        <div class="row mb-4">
            <div class="col-8">
                <?php if (!empty($shop['logo'])): ?>
                    <img src="../uploads/shop/<?= $shop['logo'] ?>" alt="Logo" style="height: 60px; margin-bottom: 10px;">
                <?php endif; ?>

                <div class="header-title"><?= htmlspecialchars($shop['shop_name']) ?></div>
                <div class="header-subtitle">
                    สำนักงานใหญ่: <?= htmlspecialchars($shop['shop_address'] ?? '-') ?><br>
                    โทรศัพท์: <?= htmlspecialchars($shop['shop_phone']) ?> | เลขประจำตัวผู้เสียภาษี: <?= htmlspecialchars($shop['tax_id']) ?>
                </div>
            </div>
            <div class="col-4 text-end align-self-center">
                <h3 class="fw-bold text-uppercase mb-1" style="letter-spacing: 1px;">ใบเสร็จรับเงิน</h3>
                <div class="text-muted">RECEIPT / TAX INVOICE</div>

                <div class="mt-2 badge bg-success text-white p-2 px-3 rounded-pill" style="font-size: 14px;">
                    <?= strtoupper($header['bill_status']) == 'COMPLETED' ? 'ชำระเงินแล้ว (PAID)' : 'ยังไม่ชำระ (UNPAID)' ?>
                </div>
            </div>
        </div>

        <hr class="mb-4">

        <div class="row mb-4 g-3">
            <div class="col-6">
                <div class="box-info">
                    <div class="box-title">ลูกค้า (Customer)</div>
                    <strong>ชื่อ:</strong> <?= htmlspecialchars($header['firstname_th'] . ' ' . $header['lastname_th']) ?><br>
                    <strong>เบอร์โทร:</strong> <?= htmlspecialchars($header['cs_phone_no']) ?><br>
                    <strong>ที่อยู่:</strong> <?= htmlspecialchars(trim($header['cs_address_full']) ?: '-') ?><br>
                    <?php if (!empty($header['cs_national_id'])): ?>
                        <strong>เลขผู้เสียภาษี:</strong> <?= htmlspecialchars($header['cs_national_id']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-6">
                <div class="box-info">
                    <div class="box-title">รายละเอียดเอกสาร (Document Details)</div>
                    <div class="d-flex justify-content-between">
                        <span>เลขที่เอกสาร:</span>
                        <strong>BILL-<?= str_pad($bill_id, 6, '0', STR_PAD_LEFT) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>วันที่:</span>
                        <strong><?= date('d/m/Y H:i', strtotime($header['receipt_date'])) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>อ้างอิงใบงาน (Job Ref):</span>
                        <span>JOB-<?= sprintf("%06d", $header['repair_id']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>พนักงานขาย:</span>
                        <span><?= htmlspecialchars($header['emp_fname']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mt-2 pt-2 border-top">
                        <span>วิธีชำระเงิน:</span>
                        <strong><?= $header['payment_method'] ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-secondary mb-3 py-2 px-3 border-0">
            <strong><i class="fas fa-tools me-2"></i>รายการซ่อม (Job Reference):</strong>
            <?= htmlspecialchars($header['device_name']) ?>
            <?= !empty($header['device_model']) ? '(' . $header['device_model'] . ')' : '' ?>
            | SN: <?= htmlspecialchars($header['device_serial']) ?>
        </div>

        <table class="table table-bordered table-custom mb-0">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="45%">รายการ (Description)</th>
                    <th width="20%">การรับประกัน (Warranty)</th>
                    <th width="15%">ราคา/หน่วย</th>
                    <th width="15%">จำนวนเงิน</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $i = 1;
                $has_items = false;
                if (mysqli_num_rows($details) > 0):
                    while ($row = mysqli_fetch_assoc($details)):
                        $total_line = $row['price'] * $row['amount'];
                        $subtotal += $total_line;
                        $has_items = true;
                ?>
                        <tr>
                            <td class="text-center"><?= $i++ ?></td>
                            <td>
                                <?= htmlspecialchars($row['prod_name']) ?>
                                <small class="text-muted"><?= htmlspecialchars($row['model_name']) ?></small>
                            </td>

                            <td class="text-center">
                                <?php
                                // แสดงเดือน
                                if (!empty($row['warranty_duration_months'])) {
                                    echo htmlspecialchars($row['warranty_duration_months']) . " เดือน";
                                } else {
                                    echo "-";
                                }

                                // แสดงหมายเหตุประกัน (ถ้ามี)
                                if (!empty($row['warranty_note'])) {
                                    echo "<br><small class='text-muted'>(" . htmlspecialchars($row['warranty_note']) . ")</small>";
                                }
                                ?>
                            </td>

                            <td class="text-end"><?= number_format($row['price'], 2) ?></td>
                            <td class="text-end"><?= number_format($total_line, 2) ?></td>
                        </tr>
                    <?php
                    endwhile;
                endif;

                // ถ้าไม่มีรายการ (เช่น ซ่อมฟรี หรือยังไม่ลงข้อมูล)
                if (!$has_items):
                    ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">
                            - ไม่พบรายการค่าบริการหรืออะไหล่ -
                        </td>
                    </tr>
                <?php endif; ?>

                <?php for ($k = $i; $k <= 5; $k++): ?>
                    <tr>
                        <td class="text-center text-muted"><?= $k ?></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <div class="row mt-0">
            <div class="col-7">
                <div class="p-3 border border-top-0 rounded-bottom h-100">
                    <strong>หมายเหตุ (Remarks):</strong><br>
                    <span class="text-muted"><?= !empty($header['comment']) ? htmlspecialchars($header['comment']) : '-' ?></span>
                    <br><br>
                    <div class="small text-secondary">
                        <strong>เงื่อนไขการรับประกัน:</strong><br>
                        1. สินค้ารับประกันเฉพาะอะไหล่ที่เปลี่ยน หรืออาการเดิม ตามระยะเวลาที่ระบุ<br>
                        2. การรับประกันไม่ครอบคลุมความเสียหายจากความชื้น, ตกหล่น, หรือการใช้งานผิดวิธี<br>
                        3. โปรดเก็บใบเสร็จนี้ไว้เป็นหลักฐานในการรับประกัน
                    </div>
                </div>
            </div>

            <div class="col-5">
                <div class="total-section h-100">
                    <div class="d-flex justify-content-between mb-1">
                        <span>รวมเงิน (Subtotal):</span>
                        <span><?= number_format($subtotal, 2) ?> บาท</span>
                    </div>

                    <?php if ($header['discount'] > 0): ?>
                        <div class="d-flex justify-content-between mb-1 text-danger">
                            <span>ส่วนลด (Discount):</span>
                            <span>-<?= number_format($header['discount'], 2) ?> บาท</span>
                        </div>
                    <?php endif; ?>

                    <?php
                    // คำนวณ VAT (7%)
                    $vat_amount = $subtotal * ($header['vat'] / 100);
                    // คำนวณ Grand Total
                    $grand_total = $subtotal + $vat_amount - $header['discount'];
                    if ($grand_total < 0) $grand_total = 0;
                    ?>

                    <div class="d-flex justify-content-between mb-1">
                        <span>ภาษีมูลค่าเพิ่ม (VAT <?= $header['vat'] ?>%):</span>
                        <span><?= number_format($vat_amount, 2) ?> บาท</span>
                    </div>

                    <hr class="my-2">

                    <div class="d-flex justify-content-between align-items-center">
                        <strong style="font-size: 1.2rem;">ยอดสุทธิ (Grand Total):</strong>
                        <strong class="text-success" style="font-size: 1.4rem;"><?= number_format($grand_total, 2) ?> บาท</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="row" style="margin-top: 60px;">
            <div class="col-4 text-center">
                <p style="border-bottom: 1px solid #ccc; margin-bottom: 10px; height: 30px;"></p>
                <p>ผู้รับเงิน / Collector</p>
                <small class="text-muted">วันที่: .......................................</small>
            </div>
            <div class="col-4"></div>
            <div class="col-4 text-center">
                <p style="border-bottom: 1px solid #ccc; margin-bottom: 10px; height: 30px;"></p>
                <p>ลูกค้า / Customer</p>
                <small class="text-muted">วันที่: .......................................</small>
            </div>
        </div>

        <div class="text-center mt-5 text-muted small no-print">
            ขอบคุณที่ใช้บริการ | Thank you for your business
        </div>

    </div>

</body>

</html>