<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'view_customer');

// ตรวจสอบ ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ไม่พบรหัสลูกค้า";
    header('Location: customer_list.php');
    exit;
}

$cs_id = (int)$_GET['id'];

// ดึงข้อมูลลูกค้า + ที่อยู่
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
    $_SESSION['error'] = "ไม่พบข้อมูลลูกค้า ID: $cs_id";
    header('Location: customer_list.php');
    exit;
}

// Helper: จัดรูปแบบที่อยู่ (แบบละเอียด)
$addr_parts = [];
if ($customer['home_no']) $addr_parts[] = "บ้านเลขที่ " . $customer['home_no'];
if ($customer['village']) $addr_parts[] = "หมู่บ้าน/อาคาร " . $customer['village'];
if ($customer['moo']) $addr_parts[] = "หมู่ " . $customer['moo'];
if ($customer['soi']) $addr_parts[] = "ซอย " . $customer['soi'];
if ($customer['road']) $addr_parts[] = "ถนน " . $customer['road'];
$addr_parts[] = "ต." . ($customer['subdistrict_name_th'] ?? '-') . " อ." . ($customer['district_name_th'] ?? '-');
$addr_parts[] = "จ." . ($customer['province_name_th'] ?? '-') . " " . ($customer['zip_code'] ?? '');
$address_text = implode(" ", $addr_parts);

// ดึงประวัติการซ่อม (Repair History)
$sql_repairs = "SELECT repair_id, create_at, repair_status, device_description 
                FROM repairs 
                WHERE customers_cs_id = $cs_id 
                ORDER BY create_at DESC LIMIT 10";
$res_repairs = mysqli_query($conn, $sql_repairs);

// ดึงประวัติการซื้อ (Purchase History)
$sql_bills = "SELECT bill_id, bill_date, bill_status, (SELECT SUM(price*amount) FROM bill_details WHERE bill_headers_bill_id = bill_id) as total
              FROM bill_headers 
              WHERE customers_cs_id = $cs_id AND bill_type = 'Sale'
              ORDER BY bill_date DESC LIMIT 10";
$res_bills = mysqli_query($conn, $sql_bills);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลลูกค้า - <?= htmlspecialchars($customer['firstname_th']) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <?php require '../config/load_theme.php'; ?>
    
    <style>
        body {
            background-color: <?= $background_color ?>;
            color: #333;
        }

        /* --- Screen Style (หน้าจอปกติ) --- */
        .main-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .profile-header {
            background: linear-gradient(135deg, <?= $theme_color ?> 0%, #1e293b 100%);
            color: white;
            padding: 40px 30px;
            position: relative;
        }

        .avatar-circle {
            width: 90px;
            height: 90px;
            background-color: rgba(255,255,255,0.2);
            border: 3px solid rgba(255,255,255,0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.2);
        }

        .nav-tabs .nav-link {
            color: #666;
            font-weight: 500;
            border: none;
            border-bottom: 3px solid transparent;
            padding: 12px 20px;
        }
        .nav-tabs .nav-link.active {
            color: <?= $theme_color ?>;
            border-bottom-color: <?= $theme_color ?>;
            background: none;
            font-weight: 700;
        }

        /* --- Print Style (ตั้งค่าการพิมพ์) --- */
        @media print {
            @page { size: A4; margin: 1cm; }
            
            body {
                background-color: #fff !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact !important; /* บังคับพิมพ์สี */
                print-color-adjust: exact !important;
            }

            /* ซ่อนองค์ประกอบที่ไม่จำเป็น */
            .no-print, #sidebar, .navbar, .btn, footer, .card-footer {
                display: none !important;
            }

            /* ปรับ Layout ให้เต็มหน้ากระดาษ */
            .container, .container-fluid, .main-content {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .main-card {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
            }

            /* ปรับ Header สำหรับพิมพ์ */
            .profile-header {
                padding: 20px 0 !important;
                background: none !important; /* เอาพื้นหลัง Gradient ออกเพื่อประหยัดหมึก หรือจะเก็บไว้ก็ได้ */
                border-bottom: 2px solid <?= $theme_color ?>;
                color: #000 !important;
                margin-bottom: 20px;
            }
            .profile-header .text-white-50, .profile-header h3 {
                color: #000 !important;
            }
            .avatar-circle {
                border-color: <?= $theme_color ?> !important;
                color: <?= $theme_color ?> !important;
                background: none !important;
            }

            /* แสดงเนื้อหาทุก Tab เรียงต่อกัน */
            .nav-tabs { display: none !important; }
            .tab-content > .tab-pane {
                display: block !important;
                opacity: 1 !important;
                margin-bottom: 30px;
                page-break-inside: avoid; /* พยายามไม่ตัดหน้า */
            }

            /* ปรับแต่งหัวข้อ Section ในการพิมพ์ */
            .tab-pane h5 {
                background-color: #f0f0f0;
                color: #000 !important;
                padding: 8px 15px;
                border-left: 5px solid <?= $theme_color ?>;
                font-size: 16px;
                font-weight: bold;
                margin-bottom: 15px;
                -webkit-print-color-adjust: exact;
            }

            /* ปรับ Grid ข้อมูลให้ดูเป็นระเบียบ */
            .info-row {
                border-bottom: 1px dotted #ccc;
                padding: 5px 0;
                display: flex;
            }
            .info-label {
                width: 150px;
                font-weight: bold;
                color: #555;
            }
            .info-value {
                flex: 1;
                color: #000;
            }

            /* ปรับตาราง */
            .table {
                border: 1px solid #ddd !important;
            }
            .table th {
                background-color: #f8f9fa !important;
                color: #000 !important;
            }
            
            /* ลิงก์ไม่ขีดเส้นใต้และสีดำ */
            a { text-decoration: none !important; color: #000 !important; }
        }

        /* Custom Table & Grid styles shared */
        .info-label { font-weight: 600; color: #6c757d; }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container py-4">

                    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                        <a href="customer_list.php" class="btn btn-outline-secondary rounded-pill px-4">
                            <i class="fas fa-arrow-left me-2"></i> ย้อนกลับ
                        </a>
                        <div class="d-flex gap-2">
                            <a href="edit_customer.php?id=<?= $cs_id ?>" class="btn btn-warning rounded-pill px-4">
                                <i class="fas fa-edit me-2"></i> แก้ไข
                            </a>
                            <button onclick="window.print()" class="btn btn-success rounded-pill px-4 shadow-sm">
                                <i class="fas fa-print me-2"></i> พิมพ์ข้อมูล
                            </button>
                        </div>
                    </div>

                    <div class="main-card bg-white">
                        
                        <div class="profile-header d-flex align-items-center gap-4">
                            <div class="avatar-circle">
                                <?= mb_substr($customer['firstname_th'], 0, 1) ?>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="mb-1 fw-bold text-white"><?= $customer['prefix_th'] . $customer['firstname_th'] . ' ' . $customer['lastname_th'] ?></h3>
                                <p class="mb-0 text-white-50" style="font-size: 0.95rem;">
                                    <?= $customer['firstname_en'] ? strtoupper($customer['firstname_en'] . ' ' . $customer['lastname_en']) : '' ?>
                                </p>
                                <div class="mt-2 badge bg-white text-dark bg-opacity-75 fw-normal">
                                    <i class="fas fa-id-card me-1"></i> ID: <?= str_pad($cs_id, 6, '0', STR_PAD_LEFT) ?>
                                </div>
                                <div class="mt-2 badge bg-white text-dark bg-opacity-75 fw-normal ms-1">
                                    <i class="fas fa-calendar me-1"></i> Member Since: <?= date('d/m/Y', strtotime($customer['create_at'])) ?>
                                </div>
                            </div>
                            <div class="d-none d-print-block text-end">
                                <h4 class="m-0 text-primary fw-bold">แบบฟอร์มข้อมูลลูกค้า</h4>
                                <small class="text-muted">พิมพ์เมื่อ: <?= date('d/m/Y H:i') ?></small>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            
                            <ul class="nav nav-tabs mb-4 no-print" id="profileTab" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#info" type="button">
                                        <i class="fas fa-user-circle me-2"></i>ข้อมูลทั่วไป
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#repair" type="button">
                                        <i class="fas fa-tools me-2"></i>ประวัติการซ่อม
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#sale" type="button">
                                        <i class="fas fa-shopping-bag me-2"></i>ประวัติการซื้อ
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content" id="profileTabContent">
                                
                                <div class="tab-pane fade show active" id="info">
                                    <h5 class="d-none d-print-block text-primary"><i class="fas fa-user me-2"></i>ข้อมูลทั่วไป (General Information)</h5>
                                    
                                    <div class="row g-4">
                                        <div class="col-lg-6">
                                            <div class="card h-100 border-0 shadow-sm bg-light d-print-none"> <div class="card-body">
                                                    <h6 class="text-primary fw-bold mb-3 border-bottom pb-2">ข้อมูลติดต่อ</h6>
                                                    <div class="mb-2 row"><div class="col-4 text-muted">เบอร์โทรศัพท์</div><div class="col-8 fw-bold text-dark"><?= $customer['cs_phone_no'] ?></div></div>
                                                    <div class="mb-2 row"><div class="col-4 text-muted">อีเมล</div><div class="col-8"><?= $customer['cs_email'] ?: '-' ?></div></div>
                                                    <div class="mb-2 row"><div class="col-4 text-muted">Line ID</div><div class="col-8 text-success"><?= $customer['cs_line_id'] ?: '-' ?></div></div>
                                                    <div class="mb-2 row"><div class="col-4 text-muted">เลขบัตร ปชช.</div><div class="col-8"><?= $customer['cs_national_id'] ?: '-' ?></div></div>
                                                </div>
                                            </div>

                                            <div class="d-none d-print-block">
                                                <div class="info-row"><div class="info-label">เบอร์โทรศัพท์</div><div class="info-value"><?= $customer['cs_phone_no'] ?></div></div>
                                                <div class="info-row"><div class="info-label">อีเมล</div><div class="info-value"><?= $customer['cs_email'] ?: '-' ?></div></div>
                                                <div class="info-row"><div class="info-label">Line ID</div><div class="info-value"><?= $customer['cs_line_id'] ?: '-' ?></div></div>
                                                <div class="info-row"><div class="info-label">เลขบัตร ปชช.</div><div class="info-value"><?= $customer['cs_national_id'] ?: '-' ?></div></div>
                                            </div>
                                        </div>

                                        <div class="col-lg-6">
                                            <div class="card h-100 border-0 shadow-sm bg-light d-print-none">
                                                <div class="card-body">
                                                    <h6 class="text-success fw-bold mb-3 border-bottom pb-2">ที่อยู่ (Address)</h6>
                                                    <p class="card-text text-secondary lh-lg">
                                                        <i class="fas fa-map-marker-alt text-danger me-2"></i>
                                                        <?= $address_text ?>
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="d-none d-print-block mt-3">
                                                <div class="section-title" style="background:none; border:none; padding:0; margin-bottom:5px; font-weight:bold;">ที่อยู่ปัจจุบัน</div>
                                                <div style="border: 1px solid #ccc; padding: 10px; border-radius: 5px;">
                                                    <?= $address_text ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="repair">
                                    <h5 class="d-none d-print-block text-primary mt-4"><i class="fas fa-tools me-2"></i>ประวัติการซ่อม (Repair History)</h5>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle border">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Job ID</th>
                                                    <th>วันที่แจ้ง</th>
                                                    <th>อาการ/รายละเอียด</th>
                                                    <th class="text-center">สถานะ</th>
                                                    <th class="no-print">จัดการ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (mysqli_num_rows($res_repairs) > 0): ?>
                                                    <?php while ($job = mysqli_fetch_assoc($res_repairs)): ?>
                                                        <tr>
                                                            <td class="fw-bold text-primary">#<?= $job['repair_id'] ?></td>
                                                            <td><?= date('d/m/Y', strtotime($job['create_at'])) ?></td>
                                                            <td><?= mb_substr($job['device_description'], 0, 60) ?>...</td>
                                                            <td class="text-center">
                                                                <span class="badge rounded-pill bg-secondary text-white d-print-none"><?= $job['repair_status'] ?></span>
                                                                <span class="d-none d-print-inline border px-2 py-1 rounded small"><?= $job['repair_status'] ?></span>
                                                            </td>
                                                            <td class="no-print">
                                                                <a href="../repair/view_repair.php?id=<?= $job['repair_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-search"></i></a>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr><td colspan="5" class="text-center text-muted py-3">ไม่มีประวัติการซ่อม</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="sale">
                                    <h5 class="d-none d-print-block text-primary mt-4"><i class="fas fa-shopping-cart me-2"></i>ประวัติการซื้อ (Purchase History)</h5>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle border">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Bill ID</th>
                                                    <th>วันที่ซื้อ</th>
                                                    <th class="text-end">ยอดรวม</th>
                                                    <th class="text-center">สถานะ</th>
                                                    <th class="no-print">จัดการ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (mysqli_num_rows($res_bills) > 0): ?>
                                                    <?php while ($bill = mysqli_fetch_assoc($res_bills)): ?>
                                                        <tr>
                                                            <td class="fw-bold text-success">#<?= $bill['bill_id'] ?></td>
                                                            <td><?= date('d/m/Y', strtotime($bill['bill_date'])) ?></td>
                                                            <td class="text-end fw-bold"><?= number_format($bill['total'], 2) ?></td>
                                                            <td class="text-center">
                                                                <span class="badge rounded-pill bg-<?= $bill['bill_status'] == 'Completed' ? 'success' : 'warning' ?> d-print-none">
                                                                    <?= $bill['bill_status'] ?>
                                                                </span>
                                                                <span class="d-none d-print-inline small"><?= $bill['bill_status'] ?></span>
                                                            </td>
                                                            <td class="no-print">
                                                                <a href="../sales/view_sale.php?id=<?= $bill['bill_id'] ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-file-invoice"></i></a>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr><td colspan="5" class="text-center text-muted py-3">ไม่มีประวัติการซื้อ</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            </div> </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>