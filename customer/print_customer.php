<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'view_customer');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ไม่พบรหัสลูกค้า");
}

$cs_id = (int)$_GET['id'];

// 1. ดึงข้อมูลร้านค้า (เพื่อแสดงหัวกระดาษ)
$shop_sql = "SELECT * FROM shop_info LIMIT 1";
$shop_result = mysqli_query($conn, $shop_sql);
$shop = mysqli_fetch_assoc($shop_result);

// 2. ดึงข้อมูลลูกค้า + ที่อยู่
$sql = "SELECT c.*, p.prefix_th, 
               a.home_no, a.moo, a.soi, a.road, a.village,
               sd.subdistrict_name_th, d.district_name_th, pv.province_name_th, sd.zip_code
        FROM customers c
        LEFT JOIN prefixs p ON c.prefixs_prefix_id = p.prefix_id
        LEFT JOIN addresses a ON c.Addresses_address_id = a.address_id
        LEFT JOIN subdistricts sd ON a.subdistricts_subdistrict_id = sd.subdistrict_id
        LEFT JOIN districts d ON sd.districts_district_id = d.district_id
        LEFT JOIN provinces pv ON d.provinces_province_id = pv.province_id
        WHERE c.cs_id = $cs_id";

$result = mysqli_query($conn, $sql);
$customer = mysqli_fetch_assoc($result);

if (!$customer) {
    die("ไม่พบข้อมูลลูกค้า");
}

// จัดรูปแบบที่อยู่
$addr_parts = [];
if ($customer['home_no']) $addr_parts[] = "บ้านเลขที่ " . $customer['home_no'];
if ($customer['village']) $addr_parts[] = "หมู่บ้าน/อาคาร " . $customer['village'];
if ($customer['moo']) $addr_parts[] = "หมู่ " . $customer['moo'];
if ($customer['soi']) $addr_parts[] = "ซอย " . $customer['soi'];
if ($customer['road']) $addr_parts[] = "ถนน " . $customer['road'];
$addr_parts[] = "ต." . ($customer['subdistrict_name_th'] ?? '-') . " อ." . ($customer['district_name_th'] ?? '-');
$addr_parts[] = "จ." . ($customer['province_name_th'] ?? '-') . " " . ($customer['zip_code'] ?? '');
$address_text = implode(" ", $addr_parts);

// 3. ดึงประวัติการซ่อม
$sql_repairs = "SELECT repair_id, create_at, repair_status, device_description 
                FROM repairs 
                WHERE customers_cs_id = $cs_id 
                ORDER BY create_at DESC LIMIT 20";
$res_repairs = mysqli_query($conn, $sql_repairs);

// 4. ดึงประวัติการซื้อ
$sql_bills = "SELECT bill_id, bill_date, bill_status, (SELECT SUM(price*amount) FROM bill_details WHERE bill_headers_bill_id = bill_id) as total
              FROM bill_headers 
              WHERE customers_cs_id = $cs_id AND bill_type = 'Sale'
              ORDER BY bill_date DESC LIMIT 20";
$res_bills = mysqli_query($conn, $sql_bills);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ประวัติลูกค้า - <?= htmlspecialchars($customer['firstname_th']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .a4-page {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: white;
            padding: 15mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }

        .header-section {
            border-bottom: 2px solid #198754; /* สีธีมหลัก */
            padding-bottom: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .shop-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: #198754;
        }

        .doc-title {
            text-align: right;
        }
        
        .doc-title h3 {
            margin: 0;
            color: #333;
            font-weight: bold;
        }

        .section-box {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 5px solid #198754;
        }

        .section-header {
            font-weight: bold;
            font-size: 1.1rem;
            color: #198754;
            margin-bottom: 10px;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 5px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .info-item span {
            font-weight: bold;
            color: #555;
            min-width: 100px;
            display: inline-block;
        }

        .table-custom th {
            background-color: #e9ecef !important;
            color: #333;
            font-weight: bold;
            border-bottom: 2px solid #ccc;
        }

        .badge-print {
            border: 1px solid #333;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: normal;
        }

        /* ปุ่มพิมพ์ลอย */
        .fab-print {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background-color: #198754;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            cursor: pointer;
            z-index: 1000;
            transition: transform 0.2s;
            border: none;
        }
        .fab-print:hover { transform: scale(1.1); background-color: #146c43; }

        @media print {
            body { background: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .a4-page { margin: 0; box-shadow: none; width: 100%; border: none; padding: 0; }
            .fab-print { display: none; }
            /* บังคับสีพื้นหลัง */
            .section-box { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; }
            .header-section { border-bottom: 2px solid #198754 !important; }
            th { background-color: #e9ecef !important; }
        }
    </style>
</head>
<body>

    <button class="fab-print" onclick="window.print()" title="พิมพ์หน้านี้">
        <i class="fas fa-print fa-lg"></i>
    </button>

    <div class="a4-page">
        <div class="header-section">
            <div class="shop-info">
                <div class="shop-name"><?= htmlspecialchars($shop['shop_name'] ?? 'Mobile Shop') ?></div>
                <small>
                    <?= htmlspecialchars($shop['shop_address'] ?? '') ?><br>
                    โทร: <?= htmlspecialchars($shop['shop_phone'] ?? '-') ?> | Email: <?= htmlspecialchars($shop['shop_email'] ?? '-') ?>
                </small>
            </div>
            <div class="doc-title">
                <h3>ประวัติลูกค้า</h3>
                <small>CUSTOMER PROFILE</small><br>
                <small class="text-muted">พิมพ์เมื่อ: <?= date('d/m/Y H:i') ?></small>
            </div>
        </div>

        <div class="section-box">
            <div class="section-header"><i class="fas fa-user-circle me-2"></i>ข้อมูลส่วนตัว (Personal Info)</div>
            <div class="info-grid">
                <div class="info-item"><span>รหัสลูกค้า:</span> <?= str_pad($customer['cs_id'], 6, '0', STR_PAD_LEFT) ?></div>
                <div class="info-item"><span>วันที่สมัคร:</span> <?= date('d/m/Y', strtotime($customer['create_at'])) ?></div>
                <div class="info-item"><span>ชื่อ-นามสกุล:</span> <?= $customer['prefix_th'] . $customer['firstname_th'] . ' ' . $customer['lastname_th'] ?></div>
                <div class="info-item"><span>เลขบัตร ปชช.:</span> <?= $customer['cs_national_id'] ?: '-' ?></div>
                <div class="info-item"><span>เบอร์โทร:</span> <?= $customer['cs_phone_no'] ?></div>
                <div class="info-item"><span>Line ID:</span> <?= $customer['cs_line_id'] ?: '-' ?></div>
                <div class="info-item" style="grid-column: span 2;"><span>ที่อยู่:</span> <?= $address_text ?></div>
            </div>
        </div>

        <div class="mt-4">
            <div class="section-header text-dark"><i class="fas fa-tools me-2"></i>ประวัติการซ่อมล่าสุด (Repair History)</div>
            <table class="table table-bordered table-sm table-custom">
                <thead>
                    <tr>
                        <th width="15%">Job ID</th>
                        <th width="20%">วันที่แจ้ง</th>
                        <th>อาการ/รายละเอียด</th>
                        <th width="15%" class="text-center">สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($res_repairs) > 0): ?>
                        <?php while ($job = mysqli_fetch_assoc($res_repairs)): ?>
                            <tr>
                                <td class="fw-bold text-center">#<?= $job['repair_id'] ?></td>
                                <td><?= date('d/m/Y', strtotime($job['create_at'])) ?></td>
                                <td><?= htmlspecialchars($job['device_description']) ?></td>
                                <td class="text-center"><?= $job['repair_status'] ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center text-muted">-- ไม่มีประวัติการซ่อม --</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            <div class="section-header text-dark"><i class="fas fa-shopping-cart me-2"></i>ประวัติการซื้อล่าสุด (Purchase History)</div>
            <table class="table table-bordered table-sm table-custom">
                <thead>
                    <tr>
                        <th width="15%">Bill ID</th>
                        <th width="20%">วันที่ซื้อ</th>
                        <th class="text-end">ยอดรวม</th>
                        <th width="15%" class="text-center">สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($res_bills) > 0): ?>
                        <?php while ($bill = mysqli_fetch_assoc($res_bills)): ?>
                            <tr>
                                <td class="fw-bold text-center">#<?= $bill['bill_id'] ?></td>
                                <td><?= date('d/m/Y', strtotime($bill['bill_date'])) ?></td>
                                <td class="text-end"><?= number_format($bill['total'], 2) ?> ฿</td>
                                <td class="text-center"><?= $bill['bill_status'] ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center text-muted">-- ไม่มีประวัติการซื้อ --</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 50px; text-align: center; color: #777; font-size: 0.8rem; border-top: 1px solid #ddd; padding-top: 10px;">
            เอกสารนี้ออกโดยระบบอัตโนมัติ | <?= $shop['shop_name'] ?? 'Mobile Shop Management System' ?>
        </div>
    </div>

</body>
</html>