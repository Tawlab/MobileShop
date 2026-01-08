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

// Helper: จัดรูปแบบที่อยู่
$addr_parts = [];
if ($customer['home_no']) $addr_parts[] = "บ้านเลขที่ " . $customer['home_no'];
if ($customer['village']) $addr_parts[] = "หมู่บ้าน/อาคาร " . $customer['village'];
if ($customer['moo']) $addr_parts[] = "หมู่ " . $customer['moo'];
if ($customer['soi']) $addr_parts[] = "ซอย " . $customer['soi'];
if ($customer['road']) $addr_parts[] = "ถนน " . $customer['road'];
$addr_parts[] = "ต." . ($customer['subdistrict_name_th'] ?? '-') . " อ." . ($customer['district_name_th'] ?? '-');
$addr_parts[] = "จ." . ($customer['province_name_th'] ?? '-') . " " . ($customer['zip_code'] ?? '');
$address_text = implode(" ", $addr_parts);

// ดึงประวัติการซ่อม
$sql_repairs = "SELECT repair_id, create_at, repair_status, device_description 
                FROM repairs 
                WHERE customers_cs_id = $cs_id 
                ORDER BY create_at DESC";
$res_repairs = mysqli_query($conn, $sql_repairs);

// ดึงประวัติการซื้อ
$sql_bills = "SELECT bill_id, bill_date, bill_status, (SELECT SUM(price*amount) FROM bill_details WHERE bill_headers_bill_id = bill_id) as total
              FROM bill_headers 
              WHERE customers_cs_id = $cs_id AND bill_type = 'Sale'
              ORDER BY bill_date DESC";
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
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <?php require '../config/load_theme.php'; ?>
    
    <style>
        body {
            background-color: <?= $background_color ?>;
            color: #333;
        }

        .main-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            background: #fff;
        }

        .profile-header {
            background: linear-gradient(135deg, <?= $theme_color ?> 0%, #1e293b 100%);
            color: white;
            padding: 40px 30px;
            position: relative;
        }

        /* Avatar Circle */
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
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        /* Tabs Styling */
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
            margin-top: -1px;
        }
        .nav-tabs .nav-link {
            color: #6c757d;
            font-weight: 600;
            border: none;
            border-bottom: 3px solid transparent;
            padding: 15px 25px;
            transition: all 0.3s;
        }
        .nav-tabs .nav-link:hover {
            color: <?= $theme_color ?>;
            background-color: #f8f9fa;
        }
        .nav-tabs .nav-link.active {
            color: <?= $theme_color ?>;
            border-bottom-color: <?= $theme_color ?>;
            background: none;
        }

        /* Table Styling */
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
            transform: scale(1.002);
            transition: all 0.2s;
        }
        
        .info-card {
            background-color: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e9ecef;
            transition: transform 0.3s;
        }
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-color: <?= $theme_color ?>;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container py-4">

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <a href="customer_list.php" class="btn btn-outline-secondary rounded-pill px-4">
                            <i class="fas fa-arrow-left me-2"></i> ย้อนกลับ
                        </a>
                        <div class="d-flex gap-2">
                            <a href="edit_customer.php?id=<?= $cs_id ?>" class="btn btn-warning rounded-pill px-4 shadow-sm">
                                <i class="fas fa-edit me-2"></i> แก้ไข
                            </a>
                            <button onclick="confirmPrint()" class="btn btn-success rounded-pill px-4 shadow-sm">
                                <i class="fas fa-print me-2"></i> พิมพ์ประวัติ
                            </button>
                        </div>
                    </div>

                    <div class="main-card">
                        
                        <div class="profile-header d-flex align-items-center gap-4">
                            <div class="avatar-circle">
                                <?= mb_substr($customer['firstname_th'], 0, 1) ?>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="mb-1 fw-bold text-white text-shadow"><?= $customer['prefix_th'] . $customer['firstname_th'] . ' ' . $customer['lastname_th'] ?></h3>
                                <p class="mb-0 text-white-50" style="font-size: 0.95rem;">
                                    <?= $customer['firstname_en'] ? strtoupper($customer['firstname_en'] . ' ' . $customer['lastname_en']) : '(ไม่มีชื่อภาษาอังกฤษ)' ?>
                                </p>
                                <div class="mt-3">
                                    <span class="badge bg-white text-dark bg-opacity-90 fw-normal rounded-pill px-3 py-2 me-2">
                                        <i class="fas fa-id-card me-1 text-primary"></i> ID: <?= str_pad($cs_id, 6, '0', STR_PAD_LEFT) ?>
                                    </span>
                                    <span class="badge bg-white text-dark bg-opacity-90 fw-normal rounded-pill px-3 py-2">
                                        <i class="fas fa-calendar-alt me-1 text-success"></i> เป็นสมาชิกเมื่อ: <?= date('d/m/Y', strtotime($customer['create_at'])) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-0">
                            
                            <ul class="nav nav-tabs ps-3" id="profileTab" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#info" type="button">
                                        <i class="fas fa-user-circle me-2"></i>ข้อมูลทั่วไป
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#repair" type="button">
                                        <i class="fas fa-tools me-2"></i>ประวัติการซ่อม <span class="badge bg-secondary rounded-pill ms-1"><?= mysqli_num_rows($res_repairs) ?></span>
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#sale" type="button">
                                        <i class="fas fa-shopping-bag me-2"></i>ประวัติการซื้อ <span class="badge bg-secondary rounded-pill ms-1"><?= mysqli_num_rows($res_bills) ?></span>
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content p-4" id="profileTabContent">
                                
                                <div class="tab-pane fade show active" id="info">
                                    <h5 class="text-primary fw-bold mb-4 ps-2 border-start border-4 border-primary">ข้อมูลส่วนตัวและที่อยู่</h5>
                                    
                                    <div class="row g-4">
                                        <div class="col-lg-6">
                                            <div class="info-card h-100 p-4">
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3 text-primary">
                                                        <i class="fas fa-address-book fa-lg"></i>
                                                    </div>
                                                    <h6 class="fw-bold mb-0">ช่องทางการติดต่อ</h6>
                                                </div>
                                                
                                                <div class="d-flex justify-content-between mb-2 border-bottom pb-2">
                                                    <span class="text-muted">เบอร์โทรศัพท์</span>
                                                    <span class="fw-bold fs-5 text-dark"><?= $customer['cs_phone_no'] ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between mb-2 border-bottom pb-2">
                                                    <span class="text-muted">อีเมล</span>
                                                    <span><?= $customer['cs_email'] ?: '<span class="text-muted">-</span>' ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between mb-2 border-bottom pb-2">
                                                    <span class="text-muted">Line ID</span>
                                                    <span class="text-success fw-bold"><?= $customer['cs_line_id'] ?: '<span class="text-muted">-</span>' ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-muted">เลขบัตร ปชช.</span>
                                                    <span><?= $customer['cs_national_id'] ?: '<span class="text-muted">-</span>' ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-lg-6">
                                            <div class="info-card h-100 p-4">
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="bg-success bg-opacity-10 p-2 rounded-circle me-3 text-success">
                                                        <i class="fas fa-map-marked-alt fa-lg"></i>
                                                    </div>
                                                    <h6 class="fw-bold mb-0">ที่อยู่ปัจจุบัน</h6>
                                                </div>
                                                <p class="text-secondary lh-lg mb-0">
                                                    <?= $address_text ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="repair">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="text-primary fw-bold ps-2 border-start border-4 border-primary mb-0">รายการส่งซ่อมทั้งหมด</h5>
                                    </div>
                                    
                                    <div class="table-responsive rounded-3 border">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th width="15%">Job ID</th>
                                                    <th width="20%">วันที่แจ้ง</th>
                                                    <th>อาการ/รายละเอียด</th>
                                                    <th width="15%" class="text-center">สถานะ</th>
                                                    <th width="10%" class="text-center">จัดการ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (mysqli_num_rows($res_repairs) > 0): ?>
                                                    <?php while ($job = mysqli_fetch_assoc($res_repairs)): ?>
                                                        <tr>
                                                            <td>
                                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-3">
                                                                    #<?= $job['repair_id'] ?>
                                                                </span>
                                                            </td>
                                                            <td class="text-muted"><?= date('d/m/Y H:i', strtotime($job['create_at'])) ?></td>
                                                            <td><?= mb_substr($job['device_description'], 0, 60) . (mb_strlen($job['device_description']) > 60 ? '...' : '') ?></td>
                                                            <td class="text-center">
                                                                <span class="badge rounded-pill bg-secondary"><?= $job['repair_status'] ?></span>
                                                            </td>
                                                            <td class="text-center">
                                                                <a href="../repair/view_repair.php?id=<?= $job['repair_id'] ?>" class="btn btn-sm btn-outline-primary rounded-circle" title="ดูรายละเอียด">
                                                                    <i class="fas fa-search"></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr><td colspan="5" class="text-center text-muted py-5"><i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>ไม่มีประวัติการซ่อม</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="sale">
                                    <h5 class="text-primary fw-bold ps-2 border-start border-4 border-primary mb-3">รายการสั่งซื้อสินค้า</h5>
                                    
                                    <div class="table-responsive rounded-3 border">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th width="15%">Bill ID</th>
                                                    <th width="20%">วันที่ซื้อ</th>
                                                    <th class="text-end">ยอดรวม</th>
                                                    <th width="15%" class="text-center">สถานะ</th>
                                                    <th width="10%" class="text-center">จัดการ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (mysqli_num_rows($res_bills) > 0): ?>
                                                    <?php while ($bill = mysqli_fetch_assoc($res_bills)): ?>
                                                        <tr>
                                                            <td class="fw-bold text-success">#<?= $bill['bill_id'] ?></td>
                                                            <td class="text-muted"><?= date('d/m/Y H:i', strtotime($bill['bill_date'])) ?></td>
                                                            <td class="text-end fw-bold text-dark"><?= number_format($bill['total'], 2) ?> ฿</td>
                                                            <td class="text-center">
                                                                <?php if($bill['bill_status'] == 'Completed'): ?>
                                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill">สำเร็จ</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-warning text-dark rounded-pill"><?= $bill['bill_status'] ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <a href="../sales/view_sale.php?id=<?= $bill['bill_id'] ?>" class="btn btn-sm btn-outline-success rounded-circle" title="ดูบิล">
                                                                    <i class="fas fa-file-invoice"></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr><td colspan="5" class="text-center text-muted py-5"><i class="fas fa-shopping-basket fa-3x mb-3 d-block opacity-25"></i>ไม่มีประวัติการซื้อ</td></tr>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // ฟังก์ชันยืนยันการพิมพ์ด้วย SweetAlert
        function confirmPrint() {
            Swal.fire({
                title: 'พิมพ์ประวัติลูกค้า?',
                text: "ระบบจะเปิดหน้าต่างใหม่สำหรับพิมพ์เอกสาร",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754', // สีเขียวธีม
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-print"></i> ใช่, พิมพ์เลย',
                cancelButtonText: 'ยกเลิก',
                customClass: {
                    popup: 'rounded-4'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // เปิดหน้า print_customer.php ใน Tab ใหม่
                    window.open('print_customer.php?id=<?= $cs_id ?>', '_blank');
                }
            });
        }
    </script>
</body>
</html>