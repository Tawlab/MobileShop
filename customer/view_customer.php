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
               a.home_no, a.moo, a.soi, a.road, 
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

// Helper: จัดรูปแบบที่อยู่
$address_text = "บ้านเลขที่ " . $customer['home_no'];
if ($customer['moo']) $address_text .= " หมู่ " . $customer['moo'];
if ($customer['soi']) $address_text .= " ซอย " . $customer['soi'];
if ($customer['road']) $address_text .= " ถนน " . $customer['road'];
$address_text .= "\nต." . $customer['subdistrict_name_th'] . " อ." . $customer['district_name_th'];
$address_text .= "\nจ." . $customer['province_name_th'] . " " . $customer['zip_code'];

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>รายละเอียดลูกค้า - <?= $customer['firstname_th'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            color: <?= $text_color ?>;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .profile-header {
            background: linear-gradient(135deg, <?= $theme_color ?> 0%, #2c3e50 100%);
            color: white;
            padding: 30px;
            border-radius: 12px 12px 0 0;
        }

        .avatar-lg {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            border: 2px solid rgba(255, 255, 255, 0.5);
        }

        .nav-tabs .nav-link {
            color: #6c757d;
            font-weight: 600;
            border: none;
            border-bottom: 3px solid transparent;
        }

        .nav-tabs .nav-link.active {
            color: <?= $theme_color ?>;
            border-bottom-color: <?= $theme_color ?>;
            background: none;
        }

        .info-label {
            width: 140px;
            font-weight: 600;
            color: #6c757d;
        }

        /* --- CSS สำหรับการพิมพ์ (Print) --- */
        @media print {

            .no-print,
            .btn,
            .nav-tabs {
                display: none !important;
            }

            /* ซ่อนปุ่มและ Tab หัวข้อ */
            body {
                background-color: white !important;
                color: black !important;
            }

            .card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }

            .profile-header {
                background: white !important;
                color: black !important;
                border-bottom: 2px solid #000;
                padding: 10px 0 !important;
            }

            .avatar-lg {
                border: 1px solid #000 !important;
                color: #000 !important;
            }

            .text-success,
            .text-primary,
            .text-danger {
                color: black !important;
            }

            /* บังคับตัวหนังสือดำ */
            a {
                text-decoration: none !important;
                color: black !important;
            }

            /* ให้แสดงเฉพาะ Tab ที่เปิดอยู่ หรือจัดเรียงใหม่ (ในที่นี้จะแสดง Tab ที่ Active อยู่) */
            .tab-content {
                border: none !important;
            }
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container py-5">

                    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                        <a href="customer_list.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> กลับหน้ารายการ</a>
                        <div>
                            <a href="edit_customer.php?id=<?= $cs_id ?>" class="btn btn-warning"><i class="fas fa-edit me-1"></i> แก้ไขข้อมูล</a>
                            <button onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print me-1"></i> พิมพ์</button>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="profile-header d-flex align-items-center">
                            <div class="avatar-lg me-4">
                                <?= mb_substr($customer['firstname_th'], 0, 1) ?>
                            </div>
                            <div>
                                <h3 class="mb-1 fw-bold"><?= $customer['prefix_th'] . $customer['firstname_th'] . ' ' . $customer['lastname_th'] ?></h3>
                                <div class="opacity-75">
                                    <i class="fas fa-id-card me-1"></i> รหัสลูกค้า: <?= str_pad($cs_id, 6, '0', STR_PAD_LEFT) ?> |
                                    <i class="fas fa-calendar-alt me-1"></i> สมาชิกเมื่อ: <?= date('d/m/Y', strtotime($customer['create_at'])) ?>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <ul class="nav nav-tabs mb-4 no-print" id="myTab" role="tablist">
                                <li class="nav-item"><button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button"><i class="fas fa-user me-2"></i>ข้อมูลทั่วไป</button></li>
                                <li class="nav-item"><button class="nav-link" id="repair-tab" data-bs-toggle="tab" data-bs-target="#repair" type="button"><i class="fas fa-tools me-2"></i>ประวัติการซ่อม</button></li>
                                <li class="nav-item"><button class="nav-link" id="sale-tab" data-bs-toggle="tab" data-bs-target="#sale" type="button"><i class="fas fa-shopping-bag me-2"></i>ประวัติการซื้อ</button></li>
                            </ul>

                            <div class="tab-content" id="myTabContent">
                                <div class="tab-pane fade show active" id="info">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5 class="text-primary fw-bold mb-3">ข้อมูลส่วนตัว</h5>
                                            <div class="d-flex mb-2"><span class="info-label">ชื่อ (อังกฤษ):</span> <span><?= $customer['firstname_en'] ? $customer['firstname_en'] . ' ' . $customer['lastname_en'] : '-' ?></span></div>
                                            <div class="d-flex mb-2"><span class="info-label">เลขบัตร ปชช.:</span> <span><?= $customer['cs_national_id'] ?: '-' ?></span></div>
                                            <div class="d-flex mb-2"><span class="info-label">เบอร์โทรศัพท์:</span> <span class="fw-bold"><?= $customer['cs_phone_no'] ?></span></div>
                                            <div class="d-flex mb-2"><span class="info-label">อีเมล:</span> <span><?= $customer['cs_email'] ?: '-' ?></span></div>
                                            <div class="d-flex mb-2"><span class="info-label">Line ID:</span> <span class="text-success"><?= $customer['cs_line_id'] ?: '-' ?></span></div>
                                        </div>
                                        <div class="col-md-6">
                                            <h5 class="text-success fw-bold mb-3">ที่อยู่จัดส่ง/ออกบิล</h5>
                                            <div class="p-3 bg-light rounded border">
                                                <i class="fas fa-map-marker-alt text-danger me-2"></i>
                                                <?= nl2br($address_text) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="repair">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Job ID</th>
                                                    <th>วันที่รับ</th>
                                                    <th>อาการเสีย</th>
                                                    <th>สถานะ</th>
                                                    <th class="no-print">ดู</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (mysqli_num_rows($res_repairs) > 0): ?>
                                                    <?php while ($job = mysqli_fetch_assoc($res_repairs)): ?>
                                                        <tr>
                                                            <td class="fw-bold text-primary">#<?= $job['repair_id'] ?></td>
                                                            <td><?= date('d/m/Y', strtotime($job['create_at'])) ?></td>
                                                            <td><?= mb_substr($job['device_description'], 0, 50) ?>...</td>
                                                            <td><span class="badge bg-secondary"><?= $job['repair_status'] ?></span></td>
                                                            <td class="no-print"><a href="../repair/view_repair.php?id=<?= $job['repair_id'] ?>" class="btn btn-sm btn-info text-white"><i class="fas fa-eye"></i></a></td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted py-4">ไม่พบประวัติการซ่อม</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="sale">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Bill ID</th>
                                                    <th>วันที่ซื้อ</th>
                                                    <th class="text-end">ยอดสุทธิ</th>
                                                    <th class="text-center">สถานะ</th>
                                                    <th class="no-print">ดู</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (mysqli_num_rows($res_bills) > 0): ?>
                                                    <?php while ($bill = mysqli_fetch_assoc($res_bills)): ?>
                                                        <tr>
                                                            <td class="fw-bold text-success">#<?= $bill['bill_id'] ?></td>
                                                            <td><?= date('d/m/Y', strtotime($bill['bill_date'])) ?></td>
                                                            <td class="text-end"><?= number_format($bill['total'], 2) ?></td>
                                                            <td class="text-center">
                                                                <span class="badge bg-<?= $bill['bill_status'] == 'Completed' ? 'success' : 'warning' ?>">
                                                                    <?= $bill['bill_status'] ?>
                                                                </span>
                                                            </td>
                                                            <td class="no-print"><a href="../sales/view_sale.php?id=<?= $bill['bill_id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a></td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted py-4">ไม่พบประวัติการซื้อ</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>