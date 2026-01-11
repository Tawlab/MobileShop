<?php
session_start();
require '../config/config.php';
require '../vendor/autoload.php';

// ตรวจสอบสิทธิ์
checkPageAccess($conn, 'report_sales');

$current_user_id = $_SESSION['user_id'];
$current_branch_id = $_SESSION['branch_id'];

// --- 1. ตรวจสอบสถานะ Admin ---
$is_admin = false;
$chk_sql = "SELECT r.role_name FROM roles r 
            JOIN user_roles ur ON r.role_id = ur.roles_role_id 
            WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt = $conn->prepare($chk_sql)) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $is_admin = true;
    $stmt->close();
}

// --- 2. รับค่าตัวกรอง (GET) ---
$report_type = $_GET['report_type'] ?? 'employee';
$start_date_raw = $_GET['start_date'] ?? date('Y-m-01');
$end_date_raw = $_GET['end_date'] ?? date('Y-m-d');
$start_date = $start_date_raw . " 00:00:00";
$end_date = $end_date_raw . " 23:59:59";

$min_amt = !empty($_GET['min_amt']) ? (float)$_GET['min_amt'] : 0;
$max_amt = !empty($_GET['max_amt']) ? (float)$_GET['max_amt'] : 999999999;
$filter_id = !empty($_GET['filter_id']) ? (int)$_GET['filter_id'] : 0;

// จัดการสาขา (Branch Logic)
$target_branch_id = 0;
$branch_name_display = "ทุกสาขา";
$shop_name_display = "Mobile Shop Management System"; // Default

if ($is_admin) {
    if (!empty($_GET['branch_id'])) {
        $target_branch_id = (int)$_GET['branch_id'];
        // ดึงชื่อสาขามาแสดง
        $br_info = $conn->query("SELECT branch_name, shop_info_shop_id FROM branches WHERE branch_id = $target_branch_id")->fetch_assoc();
        $branch_name_display = $br_info['branch_name'];
        
        // ดึงชื่อร้าน
        $sh_info = $conn->query("SELECT shop_name FROM shop_info WHERE shop_id = '{$br_info['shop_info_shop_id']}'")->fetch_assoc();
        $shop_name_display = $sh_info['shop_name'];
    } else {
        // กรณีเลือกทุกสาขา (Admin) ดึงชื่อร้านแรกมาแสดงเป็นหัว (หรือจะดึงจาก Session ก็ได้ถ้ามีการจัดการ Multi-shop)
        $sh_info = $conn->query("SELECT shop_name FROM shop_info LIMIT 1")->fetch_assoc();
        if($sh_info) $shop_name_display = $sh_info['shop_name'];
    }
} else {
    $target_branch_id = $current_branch_id;
    // ดึงชื่อสาขาตัวเอง
    $br_info = $conn->query("SELECT b.branch_name, s.shop_name 
                             FROM branches b 
                             JOIN shop_info s ON b.shop_info_shop_id = s.shop_id 
                             WHERE b.branch_id = $current_branch_id")->fetch_assoc();
    $branch_name_display = $br_info['branch_name'];
    $shop_name_display = $br_info['shop_name'];
}

// --- 3. ดึงข้อมูลรายงาน (Query Logic เดียวกับหน้าหลัก) ---
$data = [];
$summary = ['total_sales' => 0, 'total_items' => 0, 'count_bill' => 0];

// ชื่อเงื่อนไขตัวกรอง (สำหรับแสดงหัวกระดาษ)
$filter_condition_text = "ทั้งหมด";

if ($report_type === 'employee') {
    $sql = "SELECT e.firstname_th, e.lastname_th, e.emp_code,
                   COUNT(DISTINCT bh.bill_id) as bill_count,
                   SUM(bd.price * bd.amount) as total_sales
            FROM bill_headers bh
            JOIN employees e ON bh.employees_emp_id = e.emp_id
            JOIN bill_details bd ON bh.bill_id = bd.bill_headers_bill_id
            WHERE bh.bill_type = 'Sale' 
              AND bh.bill_status = 'Completed'
              AND bh.bill_date BETWEEN '$start_date' AND '$end_date'";
    
    if ($target_branch_id > 0) $sql .= " AND bh.branches_branch_id = '$target_branch_id'";
    if ($filter_id > 0) {
        $sql .= " AND e.emp_id = '$filter_id'";
        $e_name = $conn->query("SELECT firstname_th, lastname_th FROM employees WHERE emp_id = $filter_id")->fetch_assoc();
        $filter_condition_text = "พนักงาน: " . $e_name['firstname_th'] . ' ' . $e_name['lastname_th'];
    }

    $sql .= " GROUP BY e.emp_id 
              HAVING total_sales BETWEEN $min_amt AND $max_amt
              ORDER BY total_sales DESC";
    $report_title = "รายงานยอดขายตามพนักงาน";

} elseif ($report_type === 'brand') {
    $sql = "SELECT pb.brand_name_th as name,
                   SUM(bd.amount) as item_count,
                   SUM(bd.price * bd.amount) as total_sales
            FROM bill_details bd
            JOIN bill_headers bh ON bd.bill_headers_bill_id = bh.bill_id
            JOIN products p ON bd.products_prod_id = p.prod_id
            JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id
            WHERE bh.bill_type = 'Sale' 
              AND bh.bill_status = 'Completed'
              AND bh.bill_date BETWEEN '$start_date' AND '$end_date'";

    if ($target_branch_id > 0) $sql .= " AND bh.branches_branch_id = '$target_branch_id'";
    if ($filter_id > 0) {
        $sql .= " AND pb.brand_id = '$filter_id'";
        $b_name = $conn->query("SELECT brand_name_th FROM prod_brands WHERE brand_id = $filter_id")->fetch_assoc();
        $filter_condition_text = "ยี่ห้อ: " . $b_name['brand_name_th'];
    }

    $sql .= " GROUP BY pb.brand_id 
              HAVING total_sales BETWEEN $min_amt AND $max_amt
              ORDER BY total_sales DESC";
    $report_title = "รายงานยอดขายตามยี่ห้อสินค้า";

} elseif ($report_type === 'type') {
    $sql = "SELECT pt.type_name as name,
                   SUM(bd.amount) as item_count,
                   SUM(bd.price * bd.amount) as total_sales
            FROM bill_details bd
            JOIN bill_headers bh ON bd.bill_headers_bill_id = bh.bill_id
            JOIN products p ON bd.products_prod_id = p.prod_id
            JOIN prod_types pt ON p.prod_types_type_id = pt.type_id
            WHERE bh.bill_type = 'Sale' 
              AND bh.bill_status = 'Completed'
              AND bh.bill_date BETWEEN '$start_date' AND '$end_date'";

    if ($target_branch_id > 0) $sql .= " AND bh.branches_branch_id = '$target_branch_id'";
    if ($filter_id > 0) {
        $sql .= " AND pt.type_id = '$filter_id'";
        $t_name = $conn->query("SELECT type_name FROM prod_types WHERE type_id = $filter_id")->fetch_assoc();
        $filter_condition_text = "ประเภท: " . $t_name['type_name'];
    }

    $sql .= " GROUP BY pt.type_id 
              HAVING total_sales BETWEEN $min_amt AND $max_amt
              ORDER BY total_sales DESC";
    $report_title = "รายงานยอดขายตามประเภทสินค้า";
}

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
        $summary['total_sales'] += $row['total_sales'];
        if (isset($row['item_count'])) $summary['total_items'] += $row['item_count'];
        if (isset($row['bill_count'])) $summary['count_bill'] += $row['bill_count'];
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title><?= $report_title ?> - Print</title>
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
            padding: 15mm 15mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }
        .report-header {
            border-bottom: 2px solid #4e73df;
            padding-bottom: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .shop-info h2 { font-size: 1.4rem; font-weight: bold; color: #4e73df; margin: 0; }
        .shop-info p { margin: 0; font-size: 0.9rem; color: #666; }
        .report-meta { text-align: right; font-size: 0.9rem; }
        .report-meta h3 { font-size: 1.2rem; font-weight: bold; margin: 0 0 5px 0; }
        
        .filter-box {
            background-color: #f8f9fa;
            border: 1px solid #e3e6f0;
            border-left: 5px solid #4e73df;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            border-radius: 4px;
        }
        
        .summary-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        .summary-card {
            flex: 1;
            background: #fff;
            border: 1px solid #ccc;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
        }
        .summary-title { font-size: 0.85rem; color: #666; font-weight: bold; text-transform: uppercase; }
        .summary-value { font-size: 1.4rem; font-weight: bold; color: #333; margin-top: 5px; }

        .table-custom { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
        .table-custom th, .table-custom td { border: 1px solid #ddd; padding: 8px; }
        .table-custom th { background-color: #4e73df; color: white; text-align: center; font-weight: 500; }
        .table-custom tr:nth-child(even) { background-color: #f8f9fa; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }

        .fab-print {
            position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px;
            background-color: #4e73df; color: white; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3); cursor: pointer; border: none; z-index: 1000;
        }
        .fab-print:hover { transform: scale(1.1); background-color: #224abe; }

        @media print {
            body { background: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .a4-page { margin: 0; box-shadow: none; width: 100%; padding: 0; border: none; }
            .fab-print { display: none; }
            .filter-box { background-color: #f8f9fa !important; border: 1px solid #ddd; }
            .table-custom th { background-color: #4e73df !important; color: white !important; }
            .summary-card { border: 1px solid #000; }
            @page { margin: 1cm; size: A4; }
        }
    </style>
</head>
<body>

    <button class="fab-print" onclick="window.print()" title="พิมพ์">
        <i class="fas fa-print fa-lg"></i>
    </button>

    <div class="a4-page">
        <div class="report-header">
            <div class="shop-info">
                <h2><?= htmlspecialchars($shop_name_display) ?></h2>
                <p><strong>สาขา:</strong> <?= htmlspecialchars($branch_name_display) ?></p>
                <p>พิมพ์โดย: <?= htmlspecialchars($_SESSION['username'] ?? '-') ?> | วันที่: <?= date('d/m/Y H:i') ?></p>
            </div>
            <div class="report-meta">
                <h3><?= $report_title ?></h3>
                <span class="badge bg-primary rounded-pill px-3 py-2" style="font-size:0.9rem; background-color: #4e73df !important;">
                    <?= date('d/m/Y', strtotime($start_date_raw)) ?> - <?= date('d/m/Y', strtotime($end_date_raw)) ?>
                </span>
            </div>
        </div>

        <div class="filter-box">
            <div class="row">
                <div class="col-6"><strong>เงื่อนไข:</strong> <?= htmlspecialchars($filter_condition_text) ?></div>
                <div class="col-6 text-end">
                    <strong>ช่วงยอดขาย:</strong> 
                    <?= ($min_amt > 0) ? number_format($min_amt) : '0' ?> - 
                    <?= ($max_amt < 999999999) ? number_format($max_amt) : 'สูงสุด' ?>
                </div>
            </div>
        </div>

        <div class="summary-row">
            <div class="summary-card">
                <div class="summary-title">ยอดขายรวมสุทธิ (Total Sales)</div>
                <div class="summary-value" style="color:#4e73df;"><?= number_format($summary['total_sales'], 2) ?> ฿</div>
            </div>
            <div class="summary-card">
                <?php if ($report_type === 'employee'): ?>
                    <div class="summary-title">จำนวนบิลที่เปิด (Bills)</div>
                    <div class="summary-value"><?= number_format($summary['count_bill']) ?></div>
                <?php else: ?>
                    <div class="summary-title">จำนวนสินค้าที่ขาย (Items)</div>
                    <div class="summary-value"><?= number_format($summary['total_items']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <table class="table-custom">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <?php if ($report_type === 'employee'): ?>
                        <th width="15%">รหัสพนักงาน</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th width="15%">จำนวนบิล</th>
                        <th width="20%">ยอดขายรวม</th>
                    <?php else: ?>
                        <th>ชื่อรายการ (<?= ($report_type == 'brand') ? 'ยี่ห้อ' : 'ประเภท' ?>)</th>
                        <th width="15%">จำนวนชิ้น</th>
                        <th width="25%">ยอดขายรวม</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (count($data) > 0): ?>
                    <?php $i=1; foreach ($data as $row): ?>
                        <tr>
                            <td class="text-center"><?= $i++ ?></td>
                            <?php if ($report_type === 'employee'): ?>
                                <td class="text-center"><?= $row['emp_code'] ?? '-' ?></td>
                                <td><?= $row['firstname_th'] . ' ' . $row['lastname_th'] ?></td>
                                <td class="text-center"><?= number_format($row['bill_count']) ?></td>
                                <td class="text-end fw-bold"><?= number_format($row['total_sales'], 2) ?></td>
                            <?php else: ?>
                                <td><?= $row['name'] ?></td>
                                <td class="text-center"><?= number_format($row['item_count']) ?></td>
                                <td class="text-end fw-bold"><?= number_format($row['total_sales'], 2) ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center" style="padding: 20px; color: #777;">ไม่พบข้อมูลตามเงื่อนไข</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="margin-top: 30px; text-align: center; font-size: 0.8rem; color: #999;">
            <hr>
            เอกสารนี้สร้างโดยระบบอัตโนมัติ (System Generated Report)
        </div>
    </div>

</body>
</html>