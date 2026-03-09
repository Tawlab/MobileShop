<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'view_repair');

//  ตรวจสอบ ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ไม่พบรหัสงานซ่อม";
    header('Location: repair_list.php');
    exit;
}

$repair_id = mysqli_real_escape_string($conn, $_GET['id']);
$current_user_id = $_SESSION['user_id'] ?? 0;
$can_edit_completed = hasPermission($conn, $current_user_id, 'edit_completed_repair');

// ดึงข้อมูลร้านค้า
$shop_sql = "SELECT * FROM shop_info LIMIT 1";
$shop_result = mysqli_query($conn, $shop_sql);
$shop = mysqli_fetch_assoc($shop_result);

//  ดึงข้อมูลงานซ่อม + สถานะบิล
$sql = "SELECT 
            r.*,
            c.firstname_th AS cus_fname, c.lastname_th AS cus_lname, c.cs_phone_no, c.cs_email,
            emp_recv.firstname_th AS recv_fname, emp_recv.lastname_th AS recv_lname,
            emp_tech.firstname_th AS tech_fname, emp_tech.lastname_th AS tech_lname,
            s.serial_no, 
            p.prod_name, p.model_name,
            b.brand_name_th,
            t.type_name_th,
            bh.bill_status, bh.bill_id 
        FROM repairs r
        LEFT JOIN customers c ON r.customers_cs_id = c.cs_id
        LEFT JOIN employees emp_recv ON r.employees_emp_id = emp_recv.emp_id
        LEFT JOIN employees emp_tech ON r.assigned_employee_id = emp_tech.emp_id
        LEFT JOIN prod_stocks s ON r.prod_stocks_stock_id = s.stock_id
        LEFT JOIN products p ON s.products_prod_id = p.prod_id
        LEFT JOIN prod_brands b ON p.prod_brands_brand_id = b.brand_id
        LEFT JOIN prod_types t ON p.prod_types_type_id = t.type_id
        LEFT JOIN bill_headers bh ON r.bill_headers_bill_id = bh.bill_id 
        WHERE r.repair_id = '$repair_id'";

$result = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    $_SESSION['error'] = "ไม่พบข้อมูลงานซ่อม";
    header('Location: repair_list.php');
    exit;
}

//  ตรวจสอบสถานะการล็อค
$is_locked = false;
if (in_array($data['repair_status'], ['ส่งมอบ', 'ยกเลิก']) && !$can_edit_completed) {
    $is_locked = true;
}

// ดึงอาการเสีย
$sql_sym = "SELECT s.symptom_name FROM repair_symptoms rs JOIN symptoms s ON rs.symptoms_symptom_id = s.symptom_id WHERE rs.repairs_repair_id = '$repair_id'";
$result_sym = mysqli_query($conn, $sql_sym);
$symptoms_arr = [];
while ($row = mysqli_fetch_assoc($result_sym)) {
    $symptoms_arr[] = $row['symptom_name'];
}

//  ดึง Log
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
            return 'secondary';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>

    <style>
        :root {
            --theme-color: <?= $theme_color ?>;
            --bg-color: <?= $background_color ?>;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg-color);
            color: #333;
            margin: 0;
            overflow-x: hidden;
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

        /* Responsive Mobile */
        @media (max-width: 767.98px) {
            .container {
                max-width: 100%;
                padding: 0 15px !important;
            }

            .card-custom {
                border-radius: 0;
                box-shadow: none;
                margin-top: 10px;
                margin-bottom: 10px;
            }

            .card-body {
                padding: 15px;
            }

            .card-header-custom {
                font-size: 1rem;
                padding: 10px 15px;
            }

            .row > div[class*='col-'] {
                margin-bottom: 5px;
            }

            .info-label {
                font-size: 0.8rem;
            }

            .info-value {
                font-size: 0.9rem;
            }

            .timeline {
                margin-left: 0;
                padding-left: 15px;
            }

            .timeline-item {
                margin-bottom: 15px;
            }

            .timeline-item::before {
                left: -17px;
            }

            .d-flex.justify-content-end.no-print {
                flex-direction: column;
                gap: 10px;
                margin-top: 10px !important;
            }

            .d-flex.justify-content-end.no-print .btn {
                width: 100%;
            }
        }

        /* =====================================================
           PRINT CSS — ใบรับซ่อม (A4) — ออกแบบใหม่ สวยงาม
        ====================================================== */
        @media print {
            @page {
                size: A4;
                margin: 10mm 12mm;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body {
                background: #fff !important;
                font-size: 10pt;
                line-height: 1.5;
                color: #1a1a1a;
                font-family: 'Sarabun', 'TH SarabunPSK', Arial, sans-serif;
            }

            .no-print,
            .btn,
            .navbar,
            .sidebar,
            .card-header-custom,
            .timeline,
            #wrapper > .main-content > .container-fluid > .container.py-4.no-print {
                display: none !important;
            }

            .container,
            .container-fluid {
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }

            /* ซ่อน card ปกติทั้งหมด เหลือแค่ print-only */
            .card-custom,
            .row.g-4 {
                display: none !important;
            }

            /* แสดง print-only */
            .print-only {
                display: block !important;
            }
        }

        .print-only {
            display: none;
        }

        /* =====================================================
           PRINT RECEIPT LAYOUT STYLES
        ====================================================== */
        @media print {

            /* --- กรอบหลักทั้งใบ --- */
            .receipt-wrapper {
                width: 100%;
                max-width: 190mm;
                margin: 0 auto;
                font-size: 10pt;
            }

            /* --- HEADER BANNER --- */
            .receipt-header {
                background: #1a3c5e !important;
                color: #fff !important;
                padding: 12px 16px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-radius: 6px 6px 0 0;
                margin-bottom: 0;
            }

            .receipt-header .shop-name {
                font-size: 18pt;
                font-weight: bold;
                letter-spacing: 1px;
                color: #fff !important;
            }

            .receipt-header .shop-contact {
                font-size: 9pt;
                color: #cce3ff !important;
                margin-top: 3px;
            }

            .receipt-header .job-badge {
                text-align: right;
            }

            .receipt-header .job-badge .doc-title {
                background: #f59e0b !important;
                color: #1a1a1a !important;
                font-size: 13pt;
                font-weight: bold;
                padding: 4px 14px;
                border-radius: 4px;
                display: inline-block;
                letter-spacing: 1px;
                text-transform: uppercase;
            }

            .receipt-header .job-badge .doc-meta {
                font-size: 9pt;
                color: #cce3ff !important;
                margin-top: 5px;
                text-align: right;
            }

            /* --- สายสี accent ใต้ header --- */
            .receipt-accent-bar {
                height: 4px;
                background: linear-gradient(to right, #f59e0b, #ef4444, #3b82f6, #10b981) !important;
                margin-bottom: 12px;
            }

            /* --- STATUS BADGE แถบสถานะ --- */
            .receipt-status-bar {
                display: flex;
                justify-content: flex-end;
                align-items: center;
                margin-bottom: 10px;
            }

            .receipt-status-bar .status-label {
                font-size: 9pt;
                color: #666;
                margin-right: 6px;
            }

            .receipt-status-bar .status-pill {
                background: #1a3c5e !important;
                color: #fff !important;
                font-size: 9pt;
                font-weight: bold;
                padding: 3px 12px;
                border-radius: 20px;
                display: inline-block;
            }

            /* --- SECTION ROW แบบ 2 คอลัมน์ --- */
            .receipt-row {
                display: flex;
                gap: 10px;
                margin-bottom: 10px;
            }

            .receipt-col-6 {
                width: 50%;
                flex-shrink: 0;
            }

            .receipt-col-12 {
                width: 100%;
            }

            /* --- กล่องข้อมูล (Section Box) --- */
            .info-section {
                border: 1px solid #c8d8e8;
                border-radius: 5px;
                overflow: hidden;
                height: 100%;
            }

            .info-section-title {
                background: #1a3c5e !important;
                color: #fff !important;
                font-size: 9pt;
                font-weight: bold;
                padding: 5px 10px;
                letter-spacing: 0.5px;
                text-transform: uppercase;
            }

            .info-section-body {
                padding: 8px 10px;
                background: #fff !important;
            }

            /* ตารางข้อมูลภายใน section */
            .info-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 10pt;
            }

            .info-table td {
                padding: 3px 4px;
                vertical-align: top;
            }

            .info-table td.field-label {
                font-weight: bold;
                color: #4a5568;
                white-space: nowrap;
                width: 38%;
            }

            .info-table td.field-value {
                color: #1a1a1a;
            }

            /* --- กล่องอาการ/รายละเอียด --- */
            .detail-section {
                border: 1px solid #c8d8e8;
                border-radius: 5px;
                overflow: hidden;
                margin-bottom: 10px;
            }

            .detail-section-title {
                background: #2563eb !important;
                color: #fff !important;
                font-size: 9pt;
                font-weight: bold;
                padding: 5px 10px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .detail-section-body {
                padding: 8px 12px;
                background: #f8fbff !important;
            }

            .symptom-list {
                margin: 0;
                padding-left: 18px;
            }

            .symptom-list li {
                margin-bottom: 2px;
                font-size: 10pt;
            }

            /* --- กล่องต้นทุน/ราคา --- */
            .cost-row {
                display: flex;
                gap: 10px;
                margin-bottom: 10px;
                align-items: stretch;
            }

            .cost-box {
                width: 50%;
                border: 1.5px solid #1a3c5e;
                border-radius: 5px;
                overflow: hidden;
            }

            .cost-box-title {
                background: #1a3c5e !important;
                color: #fff !important;
                font-size: 9pt;
                font-weight: bold;
                padding: 5px 10px;
                text-transform: uppercase;
            }

            .cost-box-value {
                background: #f0f7ff !important;
                padding: 10px 14px;
                font-size: 16pt;
                font-weight: bold;
                color: #1a3c5e !important;
                text-align: center;
            }

            .cost-box-value span.currency {
                font-size: 10pt;
                color: #555 !important;
                margin-left: 4px;
            }

            /* กล่องผู้รับผิดชอบ */
            .staff-box {
                width: 50%;
                border: 1px solid #c8d8e8;
                border-radius: 5px;
                overflow: hidden;
            }

            .staff-box-title {
                background: #059669 !important;
                color: #fff !important;
                font-size: 9pt;
                font-weight: bold;
                padding: 5px 10px;
                text-transform: uppercase;
            }

            .staff-box-body {
                padding: 8px 12px;
                background: #f0fdf4 !important;
                font-size: 10pt;
            }

            .staff-box-body .staff-row {
                margin-bottom: 4px;
            }

            .staff-box-body .staff-label {
                font-size: 8pt;
                color: #6b7280;
                display: block;
            }

            .staff-box-body .staff-name {
                font-weight: bold;
                font-size: 10pt;
                color: #064e3b;
            }

            /* --- เส้นแบ่ง Divider --- */
            .section-divider {
                border: none;
                border-top: 1px dashed #b0c4de;
                margin: 10px 0;
            }

            /* --- เงื่อนไขการรับบริการ --- */
            .terms-section {
                border: 1px dashed #b0c4de;
                border-radius: 5px;
                padding: 8px 12px;
                margin-bottom: 12px;
                background: #fffbeb !important;
            }

            .terms-section .terms-title {
                font-weight: bold;
                font-size: 9pt;
                color: #92400e;
                margin-bottom: 4px;
            }

            .terms-section ol {
                margin: 0;
                padding-left: 18px;
                font-size: 8.5pt;
                color: #57534e;
            }

            .terms-section ol li {
                margin-bottom: 2px;
                text-align: justify;
            }

            /* --- ลายเซ็น --- */
            .signature-row {
                display: flex;
                justify-content: space-between;
                margin-top: 16px;
                margin-bottom: 12px;
            }

            .sign-block {
                width: 42%;
                text-align: center;
            }

            .sign-block .sign-line {
                border-bottom: 1.5px solid #1a3c5e;
                width: 80%;
                margin: 0 auto 6px auto;
                padding-bottom: 28px;
            }

            .sign-block .sign-name {
                font-weight: bold;
                font-size: 10pt;
                color: #1a1a1a;
            }

            .sign-block .sign-role {
                font-size: 8.5pt;
                color: #555;
            }

            .sign-block .sign-date {
                font-size: 8.5pt;
                color: #555;
                margin-top: 2px;
            }

            /* --- Footer --- */
            .receipt-footer {
                background: #1a3c5e !important;
                color: #cce3ff !important;
                text-align: center;
                padding: 6px 10px;
                font-size: 9pt;
                border-radius: 0 0 6px 6px;
                font-style: italic;
            }

            /* --- Job ID ขนาดใหญ่ใน header --- */
            .job-id-number {
                font-size: 15pt;
                font-weight: bold;
                color: #f59e0b !important;
            }

        }
        /* end @media print */

    </style>
</head>


<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <!-- =================== SCREEN VIEW (no-print) =================== -->
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
                <!-- =================== END SCREEN VIEW =================== -->


                <!-- =================== PRINT ONLY — ใบรับซ่อมใหม่ =================== -->
                <div class="print-only">
                    <div class="receipt-wrapper">

                        <!-- HEADER BANNER -->
                        <div class="receipt-header">
                            <div class="shop-left">
                                <div class="shop-name"><?= htmlspecialchars($shop['shop_name'] ?? 'Mobile Shop') ?></div>
                                <div class="shop-contact">
                                    <?php if (!empty($shop['shop_address'])): ?>
                                        <?= htmlspecialchars($shop['shop_address']) ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($shop['shop_phone'])): ?>
                                        <i>โทร:</i> <?= htmlspecialchars($shop['shop_phone']) ?>
                                        <?php if (!empty($shop['tax_id'])): ?> &nbsp;|&nbsp; <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($shop['tax_id'])): ?>
                                        เลขผู้เสียภาษี: <?= htmlspecialchars($shop['tax_id']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="job-badge">
                                <div class="doc-title">ใบรับซ่อม / JOB ORDER</div>
                                <div class="doc-meta">
                                    เลขที่: <span class="job-id-number">JOB-<?= $data['repair_id'] ?></span><br>
                                    วันที่รับ: <?= date('d/m/Y', strtotime($data['create_at'])) ?>
                                    &nbsp;เวลา: <?= date('H:i', strtotime($data['create_at'])) ?> น.
                                </div>
                            </div>
                        </div>

                        <!-- Accent Color Bar -->
                        <div class="receipt-accent-bar"></div>

                        <!-- STATUS BAR -->
                        <div class="receipt-status-bar">
                            <span class="status-label">สถานะงาน:</span>
                            <span class="status-pill"><?= htmlspecialchars($data['repair_status']) ?></span>
                        </div>

                        <!-- ROW 1: ข้อมูลลูกค้า + ข้อมูลเครื่อง -->
                        <div class="receipt-row">
                            <!-- ข้อมูลลูกค้า -->
                            <div class="receipt-col-6">
                                <div class="info-section">
                                    <div class="info-section-title">&#128100; ข้อมูลลูกค้า (Customer)</div>
                                    <div class="info-section-body">
                                        <table class="info-table">
                                            <tr>
                                                <td class="field-label">ชื่อ-นามสกุล:</td>
                                                <td class="field-value"><?= htmlspecialchars($data['cus_fname'] . ' ' . $data['cus_lname']) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="field-label">โทรศัพท์:</td>
                                                <td class="field-value"><?= htmlspecialchars($data['cs_phone_no']) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="field-label">อีเมล:</td>
                                                <td class="field-value"><?= htmlspecialchars($data['cs_email'] ?: '-') ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- ข้อมูลเครื่อง -->
                            <div class="receipt-col-6">
                                <div class="info-section">
                                    <div class="info-section-title">&#128241; ข้อมูลเครื่อง (Device)</div>
                                    <div class="info-section-body">
                                        <table class="info-table">
                                            <tr>
                                                <td class="field-label">ยี่ห้อ / รุ่น:</td>
                                                <td class="field-value"><?= htmlspecialchars($data['brand_name_th'] . ' ' . $data['prod_name']) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="field-label">Serial / IMEI:</td>
                                                <td class="field-value"><?= htmlspecialchars($data['serial_no'] ?: '-') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="field-label">ประเภท:</td>
                                                <td class="field-value"><?= htmlspecialchars($data['type_name_th'] ?: '-') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="field-label">อุปกรณ์ที่ให้มา:</td>
                                                <td class="field-value"><?= htmlspecialchars($data['accessories_list'] ?: 'เครื่องเปล่า') ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ROW 2: รายละเอียดการซ่อม -->
                        <div class="detail-section">
                            <div class="detail-section-title">&#128295; รายละเอียดการซ่อม (Service Details)</div>
                            <div class="detail-section-body">
                                <table class="info-table">
                                    <tr>
                                        <td class="field-label" style="width:20%; vertical-align:top;">อาการเสียที่แจ้ง:</td>
                                        <td class="field-value">
                                            <?php if (!empty($symptoms_arr)): ?>
                                                <ul class="symptom-list">
                                                    <?php foreach ($symptoms_arr as $sym): ?>
                                                        <li><?= htmlspecialchars($sym) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="field-label" style="vertical-align:top;">รายละเอียด / Note:</td>
                                        <td class="field-value"><?= nl2br(htmlspecialchars($data['repair_desc'] ?: '-')) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="field-label" style="vertical-align:top;">สภาพเครื่องภายนอก:</td>
                                        <td class="field-value"><?= htmlspecialchars($data['device_description'] ?: '-') ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- ROW 3: ราคาประเมิน + ผู้รับผิดชอบ -->
                        <div class="cost-row">
                            <!-- ราคาประเมิน -->
                            <div class="cost-box">
                                <div class="cost-box-title">&#128176; ราคาประเมิน (Estimated Cost)</div>
                                <div class="cost-box-value">
                                    <?= number_format($data['estimated_cost'], 2) ?>
                                    <span class="currency">บาท (THB)</span>
                                </div>
                            </div>

                            <!-- ผู้รับผิดชอบ -->
                            <div class="staff-box">
                                <div class="staff-box-title">&#128100; ผู้รับผิดชอบ</div>
                                <div class="staff-box-body">
                                    <div class="staff-row">
                                        <span class="staff-label">ผู้รับเรื่อง / Received By</span>
                                        <span class="staff-name"><?= htmlspecialchars($data['recv_fname'] . ' ' . $data['recv_lname']) ?></span>
                                    </div>
                                    <div class="staff-row" style="margin-top:6px;">
                                        <span class="staff-label">ช่างผู้รับผิดชอบ / Technician</span>
                                        <span class="staff-name">
                                            <?= $data['tech_fname'] ? htmlspecialchars($data['tech_fname'] . ' ' . $data['tech_lname']) : '— รอจ่ายงาน —' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- เงื่อนไขการรับบริการ -->
                        <div class="terms-section">
                            <div class="terms-title">&#9888;&#65039; เงื่อนไขการรับบริการ (Terms &amp; Conditions)</div>
                            <ol>
                                <li>ใบนัดรับเครื่องนี้เป็นหลักฐานสำคัญ โปรดนำมาแสดงทุกครั้งเมื่อมารับเครื่อง หากสูญหายต้องมีใบแจ้งความ</li>
                                <li>ทางร้านรับประกันเฉพาะอาการเดิมที่ซ่อม และอะไหล่ชิ้นเดิม ภายในระยะเวลาที่กำหนด</li>
                                <li>หากพ้นกำหนด 30 วัน นับจากวันที่แจ้งให้มารับเครื่อง ทางร้านขอสงวนสิทธิ์ไม่รับผิดชอบต่อความเสียหายหรือสูญหาย</li>
                            </ol>
                        </div>

                        <!-- ลายเซ็น -->
                        <div class="signature-row">
                            <div class="sign-block">
                                <div class="sign-line"></div>
                                <div class="sign-name">( <?= htmlspecialchars($data['cus_fname'] . ' ' . $data['cus_lname']) ?> )</div>
                                <div class="sign-role">ลูกค้า / Customer</div>
                                <div class="sign-date">วันที่: ______ / ______ / ______</div>
                            </div>
                            <div class="sign-block">
                                <div class="sign-line"></div>
                                <div class="sign-name">( <?= htmlspecialchars($data['recv_fname'] . ' ' . $data['recv_lname']) ?> )</div>
                                <div class="sign-role">พนักงานรับเครื่อง / Staff</div>
                                <div class="sign-date">วันที่: <?= date('d/m/Y', strtotime($data['create_at'])) ?></div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="receipt-footer">
                            ขอบคุณที่ใช้บริการ <?= htmlspecialchars($shop['shop_name'] ?? '') ?>
                            &nbsp;|&nbsp; พิมพ์เมื่อ: <?= date('d/m/Y H:i') ?> น.
                        </div>

                    </div><!-- /.receipt-wrapper -->
                </div>
                <!-- =================== END PRINT ONLY =================== -->

            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>