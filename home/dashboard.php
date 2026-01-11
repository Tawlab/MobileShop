<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'dashboard');

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: ../global/login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$my_branch_id = $_SESSION['branch_id'];

// =============================================================================
// 1. ตรวจสอบสิทธิ์และการเลือกสาขา (Admin Logic)
// =============================================================================
$is_admin = false;
$chk_role = $conn->query("SELECT r.role_name FROM roles r JOIN user_roles ur ON r.role_id = ur.roles_role_id WHERE ur.users_user_id = '$current_user_id' AND r.role_name = 'Admin'");
if ($chk_role->num_rows > 0) $is_admin = true;

// ค่าเริ่มต้นสาขา (ถ้าไม่ใช่ Admin ให้เป็นสาขาตัวเองเสมอ)
$target_branch_id = $is_admin ? 0 : $my_branch_id; 

// ถ้า Admin เลือกสาขามา
if ($is_admin && isset($_GET['branch_id']) && $_GET['branch_id'] != '') {
    $target_branch_id = (int)$_GET['branch_id'];
}

$sql_branch_bh = ($target_branch_id > 0) ? "AND bh.branches_branch_id = '$target_branch_id'" : "";
$sql_branch_rep = ($target_branch_id > 0) ? "AND branches_branch_id = '$target_branch_id'" : "";
$sql_branch_gen = ($target_branch_id > 0) ? "AND branches_branch_id = '$target_branch_id'" : "";

// ดึงรายชื่อสาขาสำหรับ Dropdown (Admin Only)
$branch_options = [];
if ($is_admin) {
    $res_br = $conn->query("SELECT b.branch_id, b.branch_name, s.shop_name FROM branches b JOIN shop_info s ON b.shop_info_shop_id = s.shop_id ORDER BY s.shop_id, b.branch_id");
    while ($row = $res_br->fetch_assoc()) $branch_options[] = $row;
}

// =============================================================================
// 2. ตัวกรองช่วงเวลา (Advanced Date Filter)
// =============================================================================
$period = $_GET['period'] ?? 'year';
$custom_start = $_GET['custom_start'] ?? date('Y-m-d 00:00');
$custom_end = $_GET['custom_end'] ?? date('Y-m-d 23:59');

$where_date = "";
$group_by = "";
$date_format = "";
$period_label = "";

switch ($period) {
    case 'today':
        $where_date = "DATE(bh.create_at) = CURDATE()";
        $group_by = "HOUR(bh.create_at)";
        $date_format = "H:00"; 
        $period_label = "วันนี้";
        break;
    case 'week':
        $where_date = "YEARWEEK(bh.create_at, 1) = YEARWEEK(CURDATE(), 1)";
        $group_by = "DATE(bh.create_at)";
        $date_format = "D d";
        $period_label = "สัปดาห์นี้";
        break;
    case 'month':
        $where_date = "MONTH(bh.create_at) = MONTH(CURDATE()) AND YEAR(bh.create_at) = YEAR(CURDATE())";
        $group_by = "DAY(bh.create_at)";
        $date_format = "d/m";
        $period_label = "เดือนนี้";
        break;
    case 'quarter':
        $where_date = "QUARTER(bh.create_at) = QUARTER(CURDATE()) AND YEAR(bh.create_at) = YEAR(CURDATE())";
        $group_by = "MONTH(bh.create_at)";
        $date_format = "M";
        $period_label = "ไตรมาสนี้";
        break;
    case 'year':
        $where_date = "YEAR(bh.create_at) = YEAR(CURDATE())";
        $group_by = "MONTH(bh.create_at)";
        $date_format = "M-Y"; 
        $period_label = "ปีนี้";
        break;
    case 'custom':
        $where_date = "bh.create_at BETWEEN '$custom_start' AND '$custom_end'";
        // ปรับ Group by ตามระยะห่างวัน
        $diff = strtotime($custom_end) - strtotime($custom_start);
        if($diff < 86400*2) { $group_by = "HOUR(bh.create_at)"; $date_format = "H:00"; } 
        elseif($diff < 86400*60) { $group_by = "DATE(bh.create_at)"; $date_format = "d/m"; }
        else { $group_by = "MONTH(bh.create_at)"; $date_format = "M-Y"; }
        $period_label = "กำหนดเอง";
        break;
    default:
        $where_date = "YEAR(bh.create_at) = YEAR(CURDATE())";
        $group_by = "MONTH(bh.create_at)";
        $date_format = "M";
        $period_label = "ปีนี้";
}

// =============================================================================
// 3. ดึงข้อมูล Widgets (Summary)
// =============================================================================

// 1. ยอดขายวันนี้ (Specific Date = CURDATE)
$sql = "SELECT SUM(bd.price * bd.amount) as total 
        FROM bill_details bd 
        JOIN bill_headers bh ON bd.bill_headers_bill_id = bh.bill_id 
        WHERE DATE(bh.create_at) = CURDATE() AND bh.bill_status = 'Completed' $sql_branch_bh";
$sales_today = mysqli_fetch_assoc(mysqli_query($conn, $sql))['total'] ?? 0;

// 2. งานซ่อมค้าง (Status check)
$sql = "SELECT COUNT(*) as count FROM repairs WHERE repair_status NOT IN ('ส่งมอบ', 'ยกเลิก') $sql_branch_rep";
$repair_pending = mysqli_fetch_assoc(mysqli_query($conn, $sql))['count'];

// 3. สินค้าพร้อมขาย (Stock Status)
// ตรวจสอบว่า prod_stocks มี branches_branch_id หรือไม่ หากไม่มีอาจต้องลบเงื่อนไขกรองสาขาออก
// ในที่นี้สมมติว่าต้องการนับรวม หรือมี field นี้
$sql = "SELECT COUNT(*) as count FROM prod_stocks WHERE stock_status = 'In Stock'"; 
// ถ้า Database มี field branches_branch_id ให้ uncomment บรรทัดล่าง
// if($target_branch_id > 0) $sql .= " AND branches_branch_id = '$target_branch_id'";
$stock_count = mysqli_fetch_assoc(mysqli_query($conn, $sql))['count'];

// 4. ฐานลูกค้า (All customers)
$sql = "SELECT COUNT(*) as count FROM customers"; 
$cust_count = mysqli_fetch_assoc(mysqli_query($conn, $sql))['count'];

// 5. กำไรขั้นต้น (ตามช่วงเวลาที่เลือก)
// ใช้ต้นทุนเฉลี่ยจาก order_details (ซื้อเข้า)
$sql_profit = "SELECT SUM((bd.price - IFNULL(costs.avg_cost, 0)) * bd.amount) as profit
               FROM bill_details bd
               JOIN bill_headers bh ON bd.bill_headers_bill_id = bh.bill_id
               LEFT JOIN (
                   SELECT products_prod_id, SUM(price * amount) / SUM(amount) as avg_cost 
                   FROM order_details 
                   GROUP BY products_prod_id
               ) costs ON bd.products_prod_id = costs.products_prod_id
               WHERE $where_date AND bh.bill_status = 'Completed' $sql_branch_bh";
$profit_total = mysqli_fetch_assoc(mysqli_query($conn, $sql_profit))['profit'] ?? 0;


// =============================================================================
// 4. ข้อมูลกราฟ (Charts)
// =============================================================================

// 4.1 กราฟรายได้ (ตามช่วงเวลา)
$revenue_labels = [];
$revenue_data = [];
$sql = "SELECT DATE_FORMAT(bh.create_at, '%Y-%m-%d %H:00:00') as time_slot, 
               SUM(bd.price * bd.amount) as total 
        FROM bill_headers bh 
        JOIN bill_details bd ON bh.bill_id = bd.bill_headers_bill_id 
        WHERE bh.bill_status = 'Completed' AND $where_date $sql_branch_bh
        GROUP BY $group_by 
        ORDER BY bh.create_at ASC";
$res = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($res)) {
    $dt = strtotime($row['time_slot']);
    $revenue_labels[] = date($date_format, $dt);
    $revenue_data[] = $row['total'];
}

// 4.2 สัดส่วนรายได้ (ปีนี้ - Fixed 'This Year')
$income_values = [0, 0]; // [Sale, Repair]
$sql = "SELECT bh.bill_type, SUM(bd.price * bd.amount) as total 
        FROM bill_headers bh 
        JOIN bill_details bd ON bh.bill_id = bd.bill_headers_bill_id 
        WHERE bh.bill_status = 'Completed' AND YEAR(bh.create_at) = YEAR(CURDATE()) $sql_branch_bh
        GROUP BY bh.bill_type";
$res = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($res)) {
    if ($row['bill_type'] == 'Sale') $income_values[0] = $row['total'];
    elseif ($row['bill_type'] == 'Repair') $income_values[1] = $row['total'];
}

// 4.3 สถานะงานซ่อม (ภาพรวมตลอดกาล - All Time)
$rep_labels = [];
$rep_values = [];
$sql = "SELECT repair_status, COUNT(*) as c FROM repairs WHERE 1=1 $sql_branch_rep GROUP BY repair_status";
$res = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($res)) {
    $rep_labels[] = $row['repair_status'];
    $rep_values[] = $row['c'];
}

// 4.4 Top 5 สินค้าขายดี (ตลอดกาล - All Time)
$top_prod_lbl = [];
$top_prod_val = [];
$sql = "SELECT p.prod_name, SUM(bd.amount) as qty 
        FROM bill_details bd 
        JOIN products p ON bd.products_prod_id = p.prod_id 
        JOIN bill_headers bh ON bd.bill_headers_bill_id = bh.bill_id 
        WHERE bh.bill_type = 'Sale' AND bh.bill_status = 'Completed' $sql_branch_bh
        GROUP BY p.prod_id ORDER BY qty DESC LIMIT 5";
$res = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($res)) {
    $top_prod_lbl[] = mb_substr($row['prod_name'], 0, 15).'...';
    $top_prod_val[] = $row['qty'];
}

// 4.5 Top 5 อาการเสีย (ตลอดกาล - All Time)
$top_sym_lbl = [];
$top_sym_val = [];
$sql = "SELECT s.symptom_name, COUNT(*) as c 
        FROM repair_symptoms rs 
        JOIN symptoms s ON rs.symptoms_symptom_id = s.symptom_id 
        JOIN repairs r ON rs.repairs_repair_id = r.repair_id
        WHERE 1=1 $sql_branch_rep
        GROUP BY s.symptom_id ORDER BY c DESC LIMIT 5";
$res = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($res)) {
    $top_sym_lbl[] = mb_substr($row['symptom_name'], 0, 15);
    $top_sym_val[] = $row['c'];
}

// 4.6 ประสิทธิภาพพนักงาน (ตลอดกาล - All Time)
$top_emp_data = [];
$top_emp_lbl = [];
$top_emp_val = [];
$sql = "SELECT e.firstname_th, e.lastname_th, COUNT(DISTINCT bh.bill_id) as bill_count, SUM(bd.price * bd.amount) as total_sales
        FROM bill_headers bh
        JOIN bill_details bd ON bh.bill_id = bd.bill_headers_bill_id
        JOIN employees e ON bh.employees_emp_id = e.emp_id
        WHERE bh.bill_status = 'Completed' $sql_branch_bh
        GROUP BY e.emp_id ORDER BY total_sales DESC LIMIT 5";
$res = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($res)) {
    $top_emp_data[] = $row;
    $top_emp_lbl[] = $row['firstname_th'];
    $top_emp_val[] = $row['total_sales'];
}

// JSON Encode for JS
$json_rev_lbl = json_encode($revenue_labels);
$json_rev_val = json_encode($revenue_data);
$json_inc_val = json_encode($income_values);
$json_rep_lbl = json_encode($rep_labels);
$json_rep_val = json_encode($rep_values);
$json_prod_lbl = json_encode($top_prod_lbl);
$json_prod_val = json_encode($top_prod_val);
$json_sym_lbl = json_encode($top_sym_lbl);
$json_sym_val = json_encode($top_sym_val);
$json_emp_lbl = json_encode($top_emp_lbl);
$json_emp_val = json_encode($top_emp_val);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <?php require '../config/load_theme.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { background-color: #f0f2f5; }

        /* กู้คืน UI เดิมตามต้นฉบับ */
        .stat-card {
            border: none;
            border-radius: 12px;
            color: white;
            transition: transform 0.2s;
            overflow: hidden;
            position: relative;
            display: block; /* ทำให้ลิงก์กดได้เต็มพื้นที่ */
            text-decoration: none;
            height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            color: white; 
        }
        .card-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 3rem;
            opacity: 0.2;
        }

        /* สีเดิมตามต้นฉบับ */
        .bg-1 { background: linear-gradient(45deg, #2ecc71, #27ae60); }
        .bg-2 { background: linear-gradient(45deg, #f1c40f, #f39c12); color: #333 !important; }
        .bg-3 { background: linear-gradient(45deg, #3498db, #2980b9); }
        .bg-4 { background: linear-gradient(45deg, #9b59b6, #8e44ad); }

        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            height: 100%;
            border: none;
        }
        .chart-title {
            font-weight: bold;
            color: #555;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chart-canvas-container {
            position: relative;
            height: 250px;
            width: 100%;
        }

        .dropdown-menu-custom {
            min-width: 250px;
            padding: 10px;
        }
        
        .control-bar {
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            margin-bottom: 20px;
        }
        
        .table-custom th { background-color: #f8f9fa; font-weight: 600; font-size: 0.9rem; }
        .table-custom td { vertical-align: middle; font-size: 0.95rem; }
    </style>
</head>

<body>

    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4 px-4">

                <div class="mb-3">
                    <h3 class="fw-bold text-secondary"><i class="fas fa-home me-2"></i>Dashboard ภาพรวม</h3>
                    <small class="text-muted">ข้อมูล ณ วันที่ <?= date('d/m/Y H:i') ?> | สาขา: <?= ($target_branch_id == 0) ? 'ทั้งหมด' : 'สาขาที่เลือก' ?></small>
                </div>

                <form method="GET" class="control-bar">
                    <div class="row g-3 align-items-end">
                        
                        <?php if ($is_admin): ?>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">เลือกร้าน/สาขา</label>
                            <select class="form-select select2" name="branch_id" onchange="this.form.submit()">
                                <option value="0">-- ดูทั้งหมด --</option>
                                <?php foreach ($branch_options as $br): ?>
                                    <option value="<?= $br['branch_id'] ?>" <?= ($target_branch_id == $br['branch_id']) ? 'selected' : '' ?>>
                                        <?= $br['shop_name'] . ' - ' . $br['branch_name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="col-md-<?= $is_admin ? '3' : '4' ?>">
                            <label class="form-label small fw-bold text-muted">ช่วงเวลา (<?= $period_label ?>)</label>
                            <select class="form-select" name="period" id="periodSelect" onchange="toggleCustomDate()">
                                <option value="today" <?= $period=='today'?'selected':'' ?>>วันนี้</option>
                                <option value="week" <?= $period=='week'?'selected':'' ?>>สัปดาห์นี้</option>
                                <option value="month" <?= $period=='month'?'selected':'' ?>>เดือนนี้</option>
                                <option value="quarter" <?= $period=='quarter'?'selected':'' ?>>ไตรมาสนี้</option>
                                <option value="year" <?= $period=='year'?'selected':'' ?>>ปีนี้</option>
                                <option value="custom" <?= $period=='custom'?'selected':'' ?>>กำหนดเอง...</option>
                            </select>
                        </div>

                        <div class="col-md-4 custom-date-box" style="display: <?= $period == 'custom' ? 'block' : 'none' ?>;">
                            <label class="form-label small fw-bold text-muted">ระบุวัน-เวลา</label>
                            <div class="input-group">
                                <input type="datetime-local" class="form-control form-control-sm" name="custom_start" value="<?= date('Y-m-d\TH:i', strtotime($custom_start)) ?>">
                                <span class="input-group-text px-1">-</span>
                                <input type="datetime-local" class="form-control form-control-sm" name="custom_end" value="<?= date('Y-m-d\TH:i', strtotime($custom_end)) ?>">
                            </div>
                        </div>

                        <div class="col text-end">
                            <button type="submit" class="btn btn-primary btn-sm me-2"><i class="fas fa-filter"></i> กรอง</button>
                            
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                                    <i class="fas fa-eye"></i> แสดงผล
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom shadow border-0">
                                    <li><h6 class="dropdown-header">เลือกส่วนที่ต้องการแสดง</h6></li>
                                    <li><div class="form-check ms-2"><input class="form-check-input toggle-section" type="checkbox" value="sec-widgets" id="chk-widgets" checked><label class="form-check-label" for="chk-widgets">สรุปยอด (Widgets)</label></div></li>
                                    <li><div class="form-check ms-2"><input class="form-check-input toggle-section" type="checkbox" value="sec-profit" id="chk-profit" checked><label class="form-check-label" for="chk-profit">สรุปกำไรขั้นต้น</label></div></li>
                                    <li><div class="form-check ms-2"><input class="form-check-input toggle-section" type="checkbox" value="sec-financial" id="chk-financial" checked><label class="form-check-label" for="chk-financial">กราฟการเงิน</label></div></li>
                                    <li><div class="form-check ms-2"><input class="form-check-input toggle-section" type="checkbox" value="sec-repair" id="chk-repair" checked><label class="form-check-label" for="chk-repair">กราฟการซ่อม</label></div></li>
                                    <li><div class="form-check ms-2"><input class="form-check-input toggle-section" type="checkbox" value="sec-top5" id="chk-top5" checked><label class="form-check-label" for="chk-top5">กราฟ Top 5</label></div></li>
                                    <li><div class="form-check ms-2"><input class="form-check-input toggle-section" type="checkbox" value="sec-perf" id="chk-perf" checked><label class="form-check-label" for="chk-perf">กราฟประสิทธิภาพพนักงาน</label></div></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="row g-3 mb-4" id="sec-widgets">
                    <div class="col-md-3">
                        <a href="../sales/sale_list.php" class="stat-card bg-1 text-decoration-none">
                            <div class="card-body p-4"> <h6 class="text-uppercase small fw-bold opacity-75 mb-2">ยอดขายวันนี้</h6>
                                <h3 class="fw-bold mb-0">฿<?= number_format($sales_today, 0) ?></h3>
                                <i class="fas fa-coins card-icon"></i>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="../repair/repair_list.php" class="stat-card bg-2 text-decoration-none">
                            <div class="card-body p-4"> <h6 class="text-uppercase small fw-bold opacity-75 mb-2" style="color: #333 !important;">งานซ่อมค้าง</h6>
                                <h3 class="fw-bold mb-0" style="color: #333 !important;"><?= number_format($repair_pending) ?> งาน</h3>
                                <i class="fas fa-tools card-icon text-dark"></i>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="../prod_stock/prod_stock.php" class="stat-card bg-3 text-decoration-none">
                            <div class="card-body p-4"> <h6 class="text-uppercase small fw-bold opacity-75 mb-2">สินค้าพร้อมขาย</h6>
                                <h3 class="fw-bold mb-0"><?= number_format($stock_count) ?> ชิ้น</h3>
                                <i class="fas fa-box card-icon"></i>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="../customer/customer_list.php" class="stat-card bg-4 text-decoration-none">
                            <div class="card-body p-4"> <h6 class="text-uppercase small fw-bold opacity-75 mb-2">ฐานลูกค้า</h6>
                                <h3 class="fw-bold mb-0"><?= number_format($cust_count) ?> คน</h3>
                                <i class="fas fa-users card-icon"></i>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="row mb-4" id="sec-profit">
                    <div class="col-12">
                         <div class="alert alert-light border shadow-sm d-flex justify-content-between align-items-center py-2">
                             <div><i class="fas fa-chart-line text-success me-2"></i><strong>สรุปกำไรขั้นต้น (<?= $period_label ?>):</strong> <span class="text-muted small ms-2">(ยอดขาย - ต้นทุนเฉลี่ยจาก order_details)</span></div>
                             <h4 class="m-0 fw-bold text-success">฿<?= number_format($profit_total, 2) ?></h4>
                         </div>
                    </div>
                </div>
                <div class="row g-3 mb-4" id="sec-financial">
                    <div class="col-lg-8">
                        <div class="chart-card">
                            <div class="chart-title">
                                <span><i class="fas fa-chart-line text-success me-2"></i>รายได้ (<?= $period_label ?>)</span>
                                <select class="form-select form-select-sm w-auto border-0 bg-light" onchange="toggleChartType(this)">
                                    <option value="bar">กราฟแท่ง</option>
                                    <option value="line">กราฟเส้น</option>
                                </select>
                            </div>
                            <div class="chart-canvas-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="chart-card">
                            <div class="chart-title">
                                <span><i class="fas fa-wallet text-primary me-2"></i>สัดส่วนรายได้ (ปีนี้)</span>
                            </div>
                            <div class="chart-canvas-container">
                                <canvas id="incomePropChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4" id="sec-repair">
                    <div class="col-md-6">
                        <div class="chart-card">
                            <div class="chart-title"><i class="fas fa-tasks text-warning me-2"></i>สถานะงานซ่อม (ภาพรวม)</div>
                            <div class="chart-canvas-container">
                                <canvas id="repairStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="chart-card">
                            <div class="chart-title"><i class="fas fa-stethoscope text-danger me-2"></i>Top 5 อาการเสีย (ตลอดกาล)</div>
                            <div class="chart-canvas-container">
                                <canvas id="topSymptomChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4" id="sec-top5">
                    <div class="col-12">
                        <div class="chart-card">
                            <div class="chart-title"><i class="fas fa-crown text-info me-2"></i>Top 5 สินค้าขายดี (ตลอดกาล)</div>
                            <div class="chart-canvas-container" style="height: 300px;">
                                <canvas id="topProdChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4" id="sec-perf">
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <div class="chart-title"><i class="fas fa-trophy text-warning me-2"></i>Top 5 พนักงาน (ยอดขายสูงสุดตลอดกาล)</div>
                            <div class="chart-canvas-container">
                                <canvas id="topEmpChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="chart-card h-100">
                            <div class="chart-title"><i class="fas fa-list-ol text-dark me-2"></i>ประสิทธิภาพพนักงาน (ตาราง)</div>
                            <div class="table-responsive">
                                <table class="table table-hover table-custom mb-0">
                                    <thead>
                                        <tr>
                                            <th>อันดับ</th>
                                            <th>ชื่อ-นามสกุล</th>
                                            <th class="text-center">บิล</th>
                                            <th class="text-end">ยอดขาย</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(count($top_emp_data) > 0): ?>
                                            <?php foreach ($top_emp_data as $i => $emp): ?>
                                            <tr>
                                                <td class="text-center"><?= ($i==0)?'🥇':($i+1) ?></td>
                                                <td><?= $emp['firstname_th'].' '.$emp['lastname_th'] ?></td>
                                                <td class="text-center"><?= number_format($emp['bill_count']) ?></td>
                                                <td class="text-end fw-bold text-success"><?= number_format($emp['total_sales']) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="4" class="text-center">ไม่มีข้อมูล</td></tr>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        // Init jQuery & Select2
        $(document).ready(function() {
            $('.select2').select2({ theme: 'bootstrap-5' });
            toggleCustomDate(); // เช็คสถานะ Date Picker ตอนโหลด
            
            // Re-apply toggle state from localStorage
            document.querySelectorAll('.toggle-section').forEach(checkbox => {
                const targetId = checkbox.value;
                const savedState = localStorage.getItem(targetId);
                
                // ถ้าใน LocalStorage บอกว่า hidden ให้ซ่อน
                if (savedState === 'hidden') {
                    checkbox.checked = false;
                    let el = document.getElementById(targetId);
                    if(el) el.style.display = 'none';
                } else {
                    // Default คือ checked/visible
                    checkbox.checked = true;
                    let el = document.getElementById(targetId);
                    if(el) el.style.display = 'flex'; // ใช้ flex เพื่อรักษา layout row
                }
            });
        });

        // Toggle Custom Date Box
        function toggleCustomDate() {
            var val = document.getElementById('periodSelect').value;
            var box = document.querySelector('.custom-date-box');
            if(box) {
                box.style.display = (val === 'custom') ? 'block' : 'none';
            }
        }

        // Toggle Sections Logic (Event Listener)
        document.querySelectorAll('.toggle-section').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const targetId = this.value;
                const element = document.getElementById(targetId);
                if (element) {
                    if (this.checked) {
                        element.style.display = 'flex'; // เปิดแสดงผล
                        localStorage.setItem(targetId, 'visible');
                    } else {
                        element.style.display = 'none'; // ซ่อน
                        localStorage.setItem(targetId, 'hidden');
                    }
                }
            });
        });

        // --- Chart Configurations ---
        // ตั้งค่า Font และสีพื้นฐาน
        Chart.defaults.font.family = "'Sarabun', sans-serif";
        Chart.defaults.color = '#666';

        // 1. Revenue Chart (กราฟรายได้)
        let revenueChartCtx = document.getElementById('revenueChart');
        let revenueChart = new Chart(revenueChartCtx, {
            type: 'bar', // เริ่มต้นเป็นกราฟแท่ง
            data: {
                labels: <?= $json_rev_lbl ?>,
                datasets: [{
                    label: 'รายได้ (บาท)',
                    data: <?= $json_rev_val ?>,
                    backgroundColor: '#2ecc71',
                    borderColor: '#27ae60',
                    borderWidth: 2,
                    tension: 0.3, // ความโค้งของเส้น (ถ้าเป็น Line)
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [2] } },
                    x: { grid: { display: false } }
                },
                plugins: { legend: { display: false } }
            }
        });

        // ฟังก์ชันสลับประเภทกราฟ (Bar <-> Line)
        function toggleChartType(select) {
            revenueChart.config.type = select.value;
            revenueChart.update();
        }

        // 2. Income Proportion Chart (สัดส่วนรายได้)
        new Chart(document.getElementById('incomePropChart'), {
            type: 'doughnut',
            data: {
                labels: ['ขายสินค้า', 'บริการซ่อม'],
                datasets: [{
                    data: <?= $json_inc_val ?>,
                    backgroundColor: ['#2ecc71', '#f39c12'],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // 3. Repair Status Chart (สถานะงานซ่อม)
        // สร้างสีอัตโนมัติให้ครบตามจำนวนสถานะ
        const colors = ['#e74a3b', '#f6c23e', '#4e73df', '#1cc88a', '#36b9cc', '#858796', '#5a5c69'];
        
        new Chart(document.getElementById('repairStatusChart'), {
            type: 'pie',
            data: {
                labels: <?= $json_rep_lbl ?>,
                datasets: [{
                    data: <?= $json_rep_val ?>,
                    backgroundColor: colors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'right' } }
            }
        });

        // 4. Top 5 Products Chart (สินค้าขายดี)
        // ** แก้ไข ID ให้ตรงกับ HTML: topProdChart **
        new Chart(document.getElementById('topProdChart'), {
            type: 'bar',
            data: {
                labels: <?= $json_prod_lbl ?>,
                datasets: [{
                    label: 'จำนวนขาย',
                    data: <?= $json_prod_val ?>,
                    backgroundColor: '#36b9cc',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y', // กราฟแนวนอน
                plugins: { legend: { display: false } },
                scales: { x: { display: false }, y: { grid: { display: false } } }
            }
        });

        // 5. Top 5 Symptoms Chart (อาการเสีย)
        new Chart(document.getElementById('topSymptomChart'), {
            type: 'bar',
            data: {
                labels: <?= $json_sym_lbl ?>,
                datasets: [{
                    label: 'จำนวนเคส',
                    data: <?= $json_sym_val ?>,
                    backgroundColor: '#e74a3b',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y', // กราฟแนวนอน
                plugins: { legend: { display: false } },
                scales: { x: { display: false }, y: { grid: { display: false } } }
            }
        });

        // 6. Top 5 Employees Chart (พนักงานยอดขายสูงสุด)
        new Chart(document.getElementById('topEmpChart'), {
            type: 'bar',
            data: {
                labels: <?= $json_emp_lbl ?>,
                datasets: [{
                    label: 'ยอดขาย (บาท)',
                    data: <?= $json_emp_val ?>,
                    backgroundColor: '#9b59b6',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>

</body>
</html>