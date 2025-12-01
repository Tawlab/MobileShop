<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'view_repair');

// 1. ตรวจสอบ ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ไม่พบรหัสงานซ่อม";
    header('Location: repair_list.php');
    exit;
}

$repair_id = mysqli_real_escape_string($conn, $_GET['id']);
$current_user_id = $_SESSION['user_id'] ?? 0;
$can_edit_completed = hasPermission($conn, $current_user_id, 'edit_completed_repair');

// 2. ดึงข้อมูลร้านค้า
$shop_sql = "SELECT * FROM shop_info LIMIT 1";
$shop_result = mysqli_query($conn, $shop_sql);
$shop = mysqli_fetch_assoc($shop_result);

// 3. ดึงข้อมูลงานซ่อม + สถานะบิล
$sql = "SELECT 
            r.*,
            c.firstname_th AS cus_fname, c.lastname_th AS cus_lname, c.cs_phone_no, c.cs_email,
            emp_recv.firstname_th AS recv_fname, emp_recv.lastname_th AS recv_lname,
            emp_tech.firstname_th AS tech_fname, emp_tech.lastname_th AS tech_lname,
            s.serial_no, 
            p.prod_name, p.model_name,
            b.brand_name_th,
            t.type_name_th,
            bh.bill_status, bh.bill_id -- [เพิ่ม] ดึงสถานะบิล
        FROM repairs r
        LEFT JOIN customers c ON r.customers_cs_id = c.cs_id
        LEFT JOIN employees emp_recv ON r.employees_emp_id = emp_recv.emp_id
        LEFT JOIN employees emp_tech ON r.assigned_employee_id = emp_tech.emp_id
        LEFT JOIN prod_stocks s ON r.prod_stocks_stock_id = s.stock_id
        LEFT JOIN products p ON s.products_prod_id = p.prod_id
        LEFT JOIN prod_brands b ON p.prod_brands_brand_id = b.brand_id
        LEFT JOIN prod_types t ON p.prod_types_type_id = t.type_id
        LEFT JOIN bill_headers bh ON r.bill_headers_bill_id = bh.bill_id -- [เพิ่ม] Join บิล
        WHERE r.repair_id = '$repair_id'";

$result = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    $_SESSION['error'] = "ไม่พบข้อมูลงานซ่อม";
    header('Location: repair_list.php');
    exit;
}

// 4. ตรวจสอบสถานะการล็อค (Lock Logic)
$is_locked = false;
// ล็อคเมื่อสถานะเป็น 'ส่งมอบ' หรือ 'ยกเลิก'
if (in_array($data['repair_status'], ['ส่งมอบ', 'ยกเลิก']) && !$can_edit_completed) {
    $is_locked = true;
}

// 5. ดึงอาการเสีย
$sql_sym = "SELECT s.symptom_name FROM repair_symptoms rs JOIN symptoms s ON rs.symptoms_symptom_id = s.symptom_id WHERE rs.repairs_repair_id = '$repair_id'";
$result_sym = mysqli_query($conn, $sql_sym);
$symptoms_arr = [];
while ($row = mysqli_fetch_assoc($result_sym)) {
    $symptoms_arr[] = $row['symptom_name'];
}

// 6. ดึง Log
$sql_log = "SELECT l.*, e.firstname_th FROM repair_status_log l LEFT JOIN employees e ON l.update_by_employee_id = e.emp_id WHERE l.repairs_repair_id = '$repair_id' ORDER BY l.update_at DESC";
$result_log = mysqli_query($conn, $sql_log);

// Helper Function สีสถานะ
function getStatusColor($status)
{
    switch ($status) {
        case 'รับเครื่อง':
            return 'secondary';
        case 'ประเมิน':
            return 'info text-dark';
        case 'รออะไหล่':
            return 'warning text-dark';
        case 'กำลังซ่อม':
            return 'primary';
        case 'ซ่อมเสร็จ':
            return 'success';
        case 'ส่งมอบ':
            return 'dark';
        case 'ยกเลิก':
            return 'secondary'; // สีเทาเข้มสำหรับยกเลิก
        default:
            return 'light text-dark border';
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Job Order #<?= $repair_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>

    <style>
        :root {
            --theme-color: <?= $theme_color ?>;
            --bg-color: <?= $background_color ?>;
        }

        body {
            background-color: var(--bg-color);
            color: #333;
        }

        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            background: #fff;
            transition: transform 0.2s;
        }

        .card-header-custom {
            background: linear-gradient(45deg, var(--theme-color), #146c43);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 15px;
            font-weight: 600;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 0.9rem;
        }

        .info-value {
            font-weight: 500;
            color: #000;
            font-size: 1rem;
        }

        .timeline {
            border-left: 2px solid #e9ecef;
            padding-left: 20px;
            margin-left: 10px;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 25px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--theme-color);
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #e9ecef;
        }

        /* --- PRINT CSS (A4 Standard) --- */
        @media print {
            @page {
                size: A4;
                margin: 10mm;
            }

            body {
                background: #fff;
                font-size: 12pt;
                line-height: 1.3;
                color: #000;
            }

            .no-print,
            .btn,
            .navbar,
            .card-header-custom,
            .timeline {
                display: none !important;
            }

            .container {
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100%;
            }

            .card-custom {
                box-shadow: none;
                border: 1px solid #000 !important;
                border-radius: 0;
                margin-bottom: 15px;
            }

            .card-body {
                padding: 10px !important;
            }

            .print-header {
                display: flex;
                justify-content: space-between;
                border-bottom: 2px solid #000;
                padding-bottom: 10px;
                margin-bottom: 15px;
            }

            .shop-info h3 {
                font-size: 18pt;
                font-weight: bold;
                margin: 0;
            }

            .job-title {
                font-size: 16pt;
                font-weight: bold;
                text-transform: uppercase;
                background: #eee;
                padding: 5px 10px;
                border: 1px solid #000;
                display: inline-block;
            }

            .print-row {
                display: flex;
                flex-wrap: wrap;
                margin: 0 -5px;
            }

            .print-col-6 {
                width: 50%;
                padding: 0 5px;
                box-sizing: border-box;
            }

            .print-col-12 {
                width: 100%;
                padding: 0 5px;
                box-sizing: border-box;
            }

            .section-box {
                border: 1px solid #000;
                padding: 10px;
                margin-bottom: 10px;
            }

            .section-title {
                font-weight: bold;
                border-bottom: 1px solid #ccc;
                padding-bottom: 5px;
                margin-bottom: 5px;
                font-size: 12pt;
            }

            .terms-box {
                font-size: 9pt;
                color: #444;
                margin-top: 15px;
                border: 1px dotted #999;
                padding: 8px;
                text-align: justify;
            }

            .signature-area {
                display: flex;
                justify-content: space-between;
                margin-top: 40px;
                margin-bottom: 20px;
            }

            .sign-box {
                width: 45%;
                text-align: center;
                border-top: 1px solid #000;
                padding-top: 5px;
            }

            .print-footer {
                border-top: 1px dashed #000;
                padding-top: 10px;
                margin-top: 10px;
                font-size: 10pt;
                text-align: center;
                font-style: italic;
            }
        }

        .print-only {
            display: none;
        }

        @media print {
            .print-only {
                display: block;
            }
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container py-4 no-print">

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show mb-4">
                            <i class="fas fa-check-circle me-2"></i> <?= $_SESSION['success'];
                                                                        unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show mb-4">
                            <i class="fas fa-exclamation-circle me-2"></i> <?= $_SESSION['error'];
                                                                            unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="mb-0 fw-bold text-primary"><i class="fas fa-file-invoice me-2"></i>รายละเอียดงานซ่อม</h4>
                            <span class="badge bg-<?= getStatusColor($data['repair_status']) ?> mt-2">สถานะ: <?= $data['repair_status'] ?></span>
                        </div>
                        <div class="gap-2 d-flex">
                            <a href="repair_list.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> กลับ</a>

                            <?php if ($data['bill_status'] == 'Completed'): ?>
                                <a href="print_repair_bill.php?id=<?= $data['bill_id'] ?>" target="_blank" class="btn btn-success text-white">
                                    <i class="fas fa-receipt"></i> ใบเสร็จรับเงิน
                                </a>
                            <?php elseif ($data['repair_status'] != 'ยกเลิก'): ?>
                                <a href="bill_repair.php?id=<?= $repair_id ?>" class="btn btn-info text-white" title="เพิ่มค่าอะไหล่/ค่าแรง และชำระเงิน">
                                    <i class="fas fa-file-invoice-dollar"></i> ค่าใช้จ่าย/ชำระเงิน
                                </a>
                            <?php endif; ?>

                            <?php if ($is_locked): ?>
                                <button type="button" class="btn btn-secondary" disabled>
                                    <i class="fas fa-lock me-1"></i> ปิดงานแล้ว
                                </button>
                            <?php else: ?>
                                <a href="update_repair_status.php?id=<?= $repair_id ?>&return_to=view" class="btn btn-warning text-white">
                                    <i class="fas fa-edit"></i> อัปเดตสถานะ
                                </a>
                            <?php endif; ?>

                            <button onclick="window.print()" class="btn btn-primary shadow-sm"><i class="fas fa-print me-2"></i> พิมพ์ใบรับซ่อม</button>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div class="card card-custom mb-4">
                                <div class="card-header card-header-custom"><i class="fas fa-info-circle me-2"></i>ข้อมูลการซ่อม (Job Info)</div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="info-label">เลขที่ใบงาน (Job ID)</div>
                                            <div class="info-value text-primary">JOB-<?= $data['repair_id'] ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="info-label">วันที่รับเครื่อง</div>
                                            <div class="info-value"><?= date('d/m/Y H:i', strtotime($data['create_at'])) ?></div>
                                        </div>
                                        <hr class="my-3 text-muted">
                                        <div class="col-md-6">
                                            <div class="info-label">ชื่อลูกค้า</div>
                                            <div class="info-value"><?= $data['cus_fname'] . ' ' . $data['cus_lname'] ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="info-label">เบอร์โทรศัพท์</div>
                                            <div class="info-value"><?= $data['cs_phone_no'] ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card card-custom">
                                <div class="card-header card-header-custom bg-secondary"><i class="fas fa-mobile-alt me-2"></i>อุปกรณ์และอาการเสีย</div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="info-label">รุ่น (Model)</div>
                                            <div class="info-value"><?= $data['brand_name_th'] . ' ' . $data['prod_name'] ?></div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="info-label">Serial No. / IMEI</div>
                                            <div class="info-value"><?= $data['serial_no'] ?></div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="info-label">อุปกรณ์ที่ให้มา</div>
                                            <div class="info-value"><?= $data['accessories_list'] ?: '-' ?></div>
                                        </div>
                                        <div class="col-12 mt-3">
                                            <div class="p-3 bg-light rounded border">
                                                <div class="info-label mb-2 text-danger">อาการเสียที่พบ:</div>
                                                <ul class="mb-2">
                                                    <?php foreach ($symptoms_arr as $sym): ?>
                                                        <li><?= $sym ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                                <div class="info-label mt-3">รายละเอียดเพิ่มเติม:</div>
                                                <div><?= nl2br($data['repair_desc']) ?: '-' ?></div>
                                                <div class="info-label mt-3">สภาพเครื่องภายนอก:</div>
                                                <div><?= $data['device_description'] ?: '-' ?></div>
                                            </div>
                                        </div>
                                        <div class="col-12 text-end mt-2">
                                            <h5 class="text-success fw-bold">ราคาประเมิน: <?= number_format($data['estimated_cost'], 2) ?> บาท</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card card-custom mb-4">
                                <div class="card-body">
                                    <h6 class="fw-bold border-bottom pb-2 mb-3">ผู้รับผิดชอบ</h6>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">ผู้รับเรื่อง</small>
                                        <strong><?= $data['recv_fname'] . ' ' . $data['recv_lname'] ?></strong>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">ช่างซ่อม</small>
                                        <?php if ($data['tech_fname']): ?>
                                            <strong class="text-primary"><?= $data['tech_fname'] . ' ' . $data['tech_lname'] ?></strong>
                                        <?php else: ?>
                                            <span class="text-danger">รอจ่ายงาน</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="card card-custom">
                                <div class="card-body">
                                    <h6 class="fw-bold border-bottom pb-2 mb-3">ประวัติสถานะ</h6>
                                    <div class="timeline">
                                        <?php while ($log = mysqli_fetch_assoc($result_log)): ?>
                                            <div class="timeline-item">
                                                <div class="fw-bold"><?= $log['new_status'] ?></div>
                                                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($log['update_at'])) ?></small>
                                                <div class="small text-secondary">โดย: <?= $log['firstname_th'] ?></div>
                                                <?php if (!empty($log['comment'])): ?>
                                                    <div class="small text-danger mt-1">Note: <?= $log['comment'] ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="print-only">
                    <div class="print-header">
                        <div class="shop-info">
                            <h3><?= $shop['shop_name'] ?? 'Mobile Shop' ?></h3>
                            <div style="font-size: 10pt;">
                                <?= $shop['shop_phone'] ? 'โทร: ' . $shop['shop_phone'] : '' ?> <br>
                                <?= $shop['tax_id'] ? 'เลขผู้เสียภาษี: ' . $shop['tax_id'] : '' ?>
                            </div>
                        </div>
                        <div class="job-info">
                            <div class="job-title">ใบรับซ่อม / JOB ORDER</div>
                            <div style="margin-top: 5px;"><b>เลขที่ใบงาน:</b> <?= $data['repair_id'] ?></div>
                            <div><b>วันที่รับ:</b> <?= date('d/m/Y H:i', strtotime($data['create_at'])) ?></div>
                        </div>
                    </div>

                    <div class="print-row">
                        <div class="print-col-6">
                            <div class="section-box">
                                <div class="section-title">ข้อมูลลูกค้า (Customer)</div>
                                <table width="100%" style="font-size: 11pt;">
                                    <tr>
                                        <td width="30%"><b>ชื่อ:</b></td>
                                        <td><?= $data['cus_fname'] . ' ' . $data['cus_lname'] ?></td>
                                    </tr>
                                    <tr>
                                        <td><b>โทร:</b></td>
                                        <td><?= $data['cs_phone_no'] ?></td>
                                    </tr>
                                    <tr>
                                        <td><b>Email:</b></td>
                                        <td><?= $data['cs_email'] ?: '-' ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="print-col-6">
                            <div class="section-box">
                                <div class="section-title">ข้อมูลเครื่อง (Device)</div>
                                <table width="100%" style="font-size: 11pt;">
                                    <tr>
                                        <td width="35%"><b>รุ่น/Model:</b></td>
                                        <td><?= $data['prod_name'] ?></td>
                                    </tr>
                                    <tr>
                                        <td><b>Serial/IMEI:</b></td>
                                        <td><?= $data['serial_no'] ?></td>
                                    </tr>
                                    <tr>
                                        <td><b>อุปกรณ์:</b></td>
                                        <td><?= $data['accessories_list'] ?: 'เครื่องเปล่า' ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="section-box">
                        <div class="section-title">รายละเอียดการซ่อม (Service Details)</div>
                        <div class="print-row">
                            <div class="print-col-12"><b>อาการเสียที่แจ้ง:</b> <?= implode(', ', $symptoms_arr) ?></div>
                            <div class="print-col-12" style="margin-top: 5px;"><b>รายละเอียด/Note:</b> <?= $data['repair_desc'] ?: '-' ?></div>
                            <div class="print-col-12" style="margin-top: 5px;"><b>สภาพเครื่องภายนอก:</b> <?= $data['device_description'] ?></div>
                        </div>
                    </div>

                    <div class="print-row">
                        <div class="print-col-6">
                            <div class="section-box" style="background: #f9f9f9;">
                                <b>ราคาประเมิน (Estimated Cost):</b>
                                <span style="float: right; font-weight: bold; font-size: 14pt;"><?= number_format($data['estimated_cost'], 2) ?> ฿</span>
                            </div>
                        </div>
                        <div class="print-col-6">
                            <div class="section-box">
                                <b>ผู้รับเรื่อง:</b> <?= $data['recv_fname'] . ' ' . $data['recv_lname'] ?> <br>
                                <b>ช่างผู้รับผิดชอบ:</b> <?= $data['tech_fname'] ? $data['tech_fname'] : '-' ?>
                            </div>
                        </div>
                    </div>

                    <div class="terms-box">
                        <b>เงื่อนไขการรับบริการ (Terms & Conditions):</b>
                        <ol style="margin: 5px 0 0 15px; padding: 0;">
                            <li>ใบนัดรับเครื่องนี้เป็นหลักฐานสำคัญ โปรดนำมาแสดงทุกครั้งเมื่อมารับเครื่อง หากสูญหายต้องมีใบแจ้งความ</li>
                            <li>ทางร้านรับประกันเฉพาะอาการเดิมที่ซ่อม และอะไหล่ชิ้นเดิม ภายในระยะเวลาที่กำหนด</li>
                            <li>หากพ้นกำหนด 30 วัน นับจากวันที่แจ้งให้มารับเครื่อง ทางร้านขอสงวนสิทธิ์ไม่รับผิดชอบต่อความเสียหายหรือสูญหาย</li>
                        </ol>
                    </div>

                    <div class="signature-area">
                        <div class="sign-box">
                            <br><br>__________________________<br>( <?= $data['cus_fname'] . ' ' . $data['cus_lname'] ?> )<br>ลูกค้า / Customer<br>วันที่: ____/____/____
                        </div>
                        <div class="sign-box">
                            <br><br>__________________________<br>( <?= $data['recv_fname'] . ' ' . $data['recv_lname'] ?> )<br>พนักงานรับเครื่อง / Staff<br>วันที่: <?= date('d/m/Y', strtotime($data['create_at'])) ?>
                        </div>
                    </div>

                    <div class="print-footer">ขอบคุณที่ใช้บริการ <?= $shop['shop_name'] ?? '' ?></div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>