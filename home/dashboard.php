<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'dashboard');

// Check Access
if (!isset($_SESSION['user_id'])) {
    header("Location: ../global/login.php");
    exit;
}

// =============================================================================
// ข้อมูลสรุป Real-time 
// =============================================================================
$today = date('Y-m-d');

// ยอดขายวันนี้
$sql_sales_today = "SELECT SUM(bd.price * bd.amount) as total FROM bill_details bd JOIN bill_headers bh ON bd.bill_headers_bill_id = bh.bill_id WHERE DATE(bh.create_at) = '$today' AND bh.bill_status = 'Completed'";
$sales_today = mysqli_fetch_assoc(mysqli_query($conn, $sql_sales_today))['total'] ?? 0;

// งานซ่อมค้าง
$sql_pending = "SELECT COUNT(*) as count FROM repairs WHERE repair_status NOT IN ('ส่งมอบ', 'ยกเลิก')";
$repair_pending = mysqli_fetch_assoc(mysqli_query($conn, $sql_pending))['count'];

// สินค้าพร้อมขาย
$sql_stock = "SELECT COUNT(*) as count FROM prod_stocks WHERE stock_status = 'In Stock'";
$stock_count = mysqli_fetch_assoc(mysqli_query($conn, $sql_stock))['count'];

// ลูกค้าทั้งหมด
$sql_cust = "SELECT COUNT(*) as count FROM customers";
$cust_count = mysqli_fetch_assoc(mysqli_query($conn, $sql_cust))['count'];

// =============================================================================
// ตัวกรองช่วงเวลา
// =============================================================================
$period = $_GET['period'] ?? 'week'; 
$where_date = "";
$group_by = "";
$date_format = "";

switch ($period) {
    case 'today':
        $where_date = "DATE(bh.create_at) = CURDATE()";
        $group_by = "HOUR(bh.create_at)";
        $date_format = "H:00"; 
        $chart_title = "รายได้วันนี้ (รายชั่วโมง)";
        break;
    case 'month':
        $where_date = "MONTH(bh.create_at) = MONTH(CURDATE()) AND YEAR(bh.create_at) = YEAR(CURDATE())";
        $group_by = "DAY(bh.create_at)";
        $date_format = "d";
        $chart_title = "รายได้เดือนนี้ (รายวัน)";
        break;
    case 'quarter':
        $where_date = "QUARTER(bh.create_at) = QUARTER(CURDATE()) AND YEAR(bh.create_at) = YEAR(CURDATE())";
        $group_by = "MONTH(bh.create_at)";
        $date_format = "M";
        $chart_title = "รายได้ไตรมาสนี้ (รายเดือน)";
        break;
    case 'year':
        $where_date = "YEAR(bh.create_at) = YEAR(CURDATE())";
        $group_by = "MONTH(bh.create_at)";
        $date_format = "M"; 
        $chart_title = "รายได้ปีนี้ (รายเดือน)";
        break;
    case 'week':
    default:
        // สัปดาห์นี้
        $where_date = "YEARWEEK(bh.create_at, 1) = YEARWEEK(CURDATE(), 1)";
        $group_by = "DATE(bh.create_at)";
        $date_format = "D d";
        $chart_title = "รายได้สัปดาห์นี้ (รายวัน)";
        break;
}

// =============================================================================
// ข้อมูลกราฟตามตัวกรอง
// =============================================================================

// แนวโน้มรายได้ 
$revenue_labels = [];
$revenue_data = [];

$sql_revenue = "SELECT DATE_FORMAT(bh.create_at, '%Y-%m-%d %H:00:00') as time_slot, 
                       SUM(bd.price * bd.amount) as total 
                FROM bill_headers bh 
                JOIN bill_details bd ON bh.bill_id = bd.bill_headers_bill_id 
                WHERE bh.bill_status = 'Completed' AND $where_date 
                GROUP BY $group_by 
                ORDER BY bh.create_at ASC";
$res_rev = mysqli_query($conn, $sql_revenue);

while ($row = mysqli_fetch_assoc($res_rev)) {
    $dt = strtotime($row['time_slot']);
    $revenue_labels[] = date($date_format, $dt);
    $revenue_data[] = $row['total'];
}

// สัดส่วนรายได้ ขาย vs ซ่อม 
$income_labels = ['ขายสินค้า', 'บริการซ่อม'];
$income_values = [0, 0];

$sql_income = "SELECT bh.bill_type, SUM(bd.price * bd.amount) as total 
               FROM bill_headers bh 
               JOIN bill_details bd ON bh.bill_id = bd.bill_headers_bill_id 
               WHERE bh.bill_status = 'Completed' AND $where_date 
               GROUP BY bh.bill_type";
$res_inc = mysqli_query($conn, $sql_income);

while ($row = mysqli_fetch_assoc($res_inc)) {
    if ($row['bill_type'] == 'Sale') $income_values[0] = $row['total'];
    elseif ($row['bill_type'] == 'Repair') $income_values[1] = $row['total'];
}

// Top 5 สินค้า
$top_prod_labels = [];
$top_prod_values = [];
$res_top = mysqli_query($conn, "SELECT p.prod_name, SUM(bd.amount) as qty FROM bill_details bd JOIN products p ON bd.products_prod_id = p.prod_id JOIN bill_headers bh ON bd.bill_headers_bill_id = bh.bill_id WHERE bh.bill_type = 'Sale' AND bh.bill_status = 'Completed' GROUP BY p.prod_id ORDER BY qty DESC LIMIT 5");
while ($row = mysqli_fetch_assoc($res_top)) {
    $top_prod_labels[] = mb_substr($row['prod_name'], 0, 15) . '...';
    $top_prod_values[] = $row['qty'];
}

// Top 5 อาการเสีย
$top_sym_labels = [];
$top_sym_values = [];
$res_sym = mysqli_query($conn, "SELECT s.symptom_name, COUNT(*) as c FROM repair_symptoms rs JOIN symptoms s ON rs.symptoms_symptom_id = s.symptom_id GROUP BY s.symptom_id ORDER BY c DESC LIMIT 5");
while ($row = mysqli_fetch_assoc($res_sym)) {
    $top_sym_labels[] = $row['symptom_name'];
    $top_sym_values[] = $row['c'];
}

// สถานะงานซ่อม
$repair_status_labels = [];
$repair_status_values = [];
$res_rep = mysqli_query($conn, "SELECT repair_status, COUNT(*) as c FROM repairs GROUP BY repair_status");
while ($row = mysqli_fetch_assoc($res_rep)) {
    $repair_status_labels[] = $row['repair_status'];
    $repair_status_values[] = $row['c'];
}

$json_rev_lbl = json_encode($revenue_labels);
$json_rev_val = json_encode($revenue_data);
$json_inc_val = json_encode($income_values);
$json_prod_lbl = json_encode($top_prod_labels);
$json_prod_val = json_encode($top_prod_values);
$json_sym_lbl = json_encode($top_sym_labels);
$json_sym_val = json_encode($top_sym_values);
$json_rep_lbl = json_encode($repair_status_labels);
$json_rep_val = json_encode($repair_status_values);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Dashboard - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background-color: #f0f2f5;
        }

        .stat-card {
            border: none;
            border-radius: 12px;
            color: white;
            transition: transform 0.2s;
            overflow: hidden;
            position: relative;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .card-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 3rem;
            opacity: 0.2;
        }

        .bg-1 {
            background: linear-gradient(45deg, #2ecc71, #27ae60);
        }

        .bg-2 {
            background: linear-gradient(45deg, #f1c40f, #f39c12);
            color: #333 !important;
        }

        .bg-3 {
            background: linear-gradient(45deg, #3498db, #2980b9);
        }

        .bg-4 {
            background: linear-gradient(45deg, #9b59b6, #8e44ad);
        }

        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            height: 100%;
        }

        .chart-title {
            font-weight: bold;
            color: #555;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .chart-canvas-container {
            position: relative;
            height: 250px;
            width: 100%;
        }

        .h-300 {
            height: 300px;
        }

        .dropdown-menu-custom {
            min-width: 250px;
            padding: 10px;
        }
    </style>
</head>

<body>

    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4 px-4">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="fw-bold text-secondary"><i class="fas fa-home me-2"></i>Dashboard ภาพรวม</h3>
                        <small class="text-muted">ข้อมูล ณ วันที่ <?= date('d/m/Y H:i') ?></small>
                    </div>

                    <div class="d-flex gap-2">
                        <div class="dropdown">
                            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-calendar-alt"></i> ช่วงเวลา:
                                <?php
                                $periods = ['today' => 'วันนี้', 'week' => 'สัปดาห์นี้', 'month' => 'เดือนนี้', 'quarter' => 'ไตรมาสนี้', 'year' => 'ปีนี้'];
                                echo $periods[$period];
                                ?>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?period=today">วันนี้</a></li>
                                <li><a class="dropdown-item" href="?period=week">สัปดาห์นี้</a></li>
                                <li><a class="dropdown-item" href="?period=month">เดือนนี้</a></li>
                                <li><a class="dropdown-item" href="?period=quarter">ไตรมาสนี้</a></li>
                                <li><a class="dropdown-item" href="?period=year">ปีนี้</a></li>
                            </ul>
                        </div>

                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                                <i class="fas fa-cog"></i> ปรับแต่ง
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom shadow">
                                <li>
                                    <h6 class="dropdown-header">เลือกส่วนที่ต้องการแสดง</h6>
                                </li>
                                <li>
                                    <div class="form-check ms-2"><input class="form-check-input toggle-section" type="checkbox" value="sec-widgets" id="chk-widgets" checked><label class="form-check-label" for="chk-widgets">สรุปยอด (Widgets)</label></div>
                                </li>
                                <li>
                                    <div class="form-check ms-2"><input class="form-check-input toggle-section" type="checkbox" value="sec-financial" id="chk-financial" checked><label class="form-check-label" for="chk-financial">กราฟการเงิน</label></div>
                                </li>
                                <li>
                                    <div class="form-check ms-2"><input class="form-check-input toggle-section" type="checkbox" value="sec-operation" id="chk-operation" checked><label class="form-check-label" for="chk-operation">กราฟอื่นๆ</label></div>
                                </li>
                            </ul>
                        </div>
                        <a href="../sales/add_sale.php?return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-success shadow-sm"><i class="fas fa-cash-register"></i> ขายสินค้า</a>
                        <a href="../repair/add_repair.php?return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-warning text-dark shadow-sm"><i class="fas fa-wrench"></i> รับซ่อม</a>
                    </div>
                </div>

                <div class="row g-3 mb-4" id="sec-widgets">
                    <div class="col-md-3">
                        <a href="../sales/sale_list.php" class="text-decoration-none">
                            <div class="card stat-card bg-1 h-100">
                                <div class="card-body">
                                    <h6>ยอดขายวันนี้</h6>
                                    <h3 class="fw-bold">฿<?= number_format($sales_today, 0) ?></h3>
                                    <i class="fas fa-coins card-icon"></i>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="../repair/repair_list.php" class="text-decoration-none">
                            <div class="card stat-card bg-2 h-100">
                                <div class="card-body">
                                    <h6>งานซ่อมค้าง</h6>
                                    <h3 class="fw-bold"><?= number_format($repair_pending) ?> งาน</h3>
                                    <i class="fas fa-tools card-icon"></i>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="../prod_stock/prod_stock.php" class="text-decoration-none">
                            <div class="card stat-card bg-3 h-100">
                                <div class="card-body">
                                    <h6>สินค้าพร้อมขาย</h6>
                                    <h3 class="fw-bold"><?= number_format($stock_count) ?> ชิ้น</h3>
                                    <i class="fas fa-box card-icon"></i>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="../customer/customer_list.php?return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="text-decoration-none">
                            <div class="card stat-card bg-4 h-100">
                                <div class="card-body">
                                    <h6>ฐานลูกค้า</h6>
                                    <h3 class="fw-bold"><?= number_format($cust_count) ?> คน</h3>
                                    <i class="fas fa-users card-icon"></i>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="row g-3 mb-4" id="sec-financial">
                    <div class="col-lg-8">
                        <div class="chart-card">
                            <div class="d-flex justify-content-between align-items-center chart-title">
                                <span><i class="fas fa-chart-line text-success me-2"></i><?= $chart_title ?></span>
                                <select class="form-select form-select-sm w-auto" onchange="toggleChartType(this)">
                                    <option value="bar">กราฟแท่ง</option>
                                    <option value="line">กราฟเส้น</option>
                                </select>
                            </div>
                            <div class="chart-canvas-container h-300">
                                <canvas id="salesTrendChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="chart-card">
                            <div class="chart-title"><i class="fas fa-wallet text-primary me-2"></i>สัดส่วนรายได้ (<?= $periods[$period] ?>)</div>
                            <div class="chart-canvas-container h-300">
                                <canvas id="incomeSourceChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4" id="sec-operation">
                    <div class="col-md-4">
                        <div class="chart-card">
                            <div class="chart-title"><i class="fas fa-tasks text-warning me-2"></i>สถานะงานซ่อม (ภาพรวม)</div>
                            <div class="chart-canvas-container">
                                <canvas id="repairStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="chart-card">
                            <div class="chart-title"><i class="fas fa-crown text-warning me-2"></i>Top 5 สินค้าขายดี (ตลอดกาล)</div>
                            <div class="chart-canvas-container">
                                <canvas id="bestSellerChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="chart-card">
                            <div class="chart-title"><i class="fas fa-stethoscope text-danger me-2"></i>Top 5 อาการเสีย (ตลอดกาล)</div>
                            <div class="chart-canvas-container">
                                <canvas id="topSymptomChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const colors = ['#2ecc71', '#3498db', '#9b59b6', '#f1c40f', '#e67e22', '#e74c3c', '#34495e'];

        // --- Toggle Section ---
        document.querySelectorAll('.toggle-section').forEach(checkbox => {
            const savedState = localStorage.getItem(checkbox.value);
            if (savedState === 'hidden') {
                checkbox.checked = false;
                document.getElementById(checkbox.value).style.display = 'none';
            }
            checkbox.addEventListener('change', function() {
                const targetId = this.value;
                const element = document.getElementById(targetId);
                if (this.checked) {
                    element.style.display = 'flex';
                    localStorage.setItem(targetId, 'visible');
                } else {
                    element.style.display = 'none';
                    localStorage.setItem(targetId, 'hidden');
                }
            });
        });

        // Sales Trend
        let salesChart = new Chart(document.getElementById('salesTrendChart'), {
            type: 'bar',
            data: {
                labels: <?= $json_rev_lbl ?>,
                datasets: [{
                    label: 'ยอดขาย (บาท)',
                    data: <?= $json_rev_val ?>,
                    backgroundColor: '#2ecc71',
                    borderColor: '#27ae60',
                    borderWidth: 1,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        function toggleChartType(select) {
            salesChart.config.type = select.value;
            salesChart.update();
        }

        // Income Source (Doughnut)
        new Chart(document.getElementById('incomeSourceChart'), {
            type: 'doughnut', 
            data: {
                labels: ['ขายสินค้า', 'บริการซ่อม'],
                datasets: [{
                    data: <?= $json_inc_val ?>,
                    backgroundColor: ['#2ecc71', '#f39c12']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Repair Status
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
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });

        // Best Sellers
        new Chart(document.getElementById('bestSellerChart'), {
            type: 'bar',
            data: {
                labels: <?= $json_prod_lbl ?>,
                datasets: [{
                    label: 'จำนวนขาย',
                    data: <?= $json_prod_val ?>,
                    backgroundColor: '#3498db'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y'
            }
        });

        // Top Symptoms
        new Chart(document.getElementById('topSymptomChart'), {
            type: 'bar',
            data: {
                labels: <?= $json_sym_lbl ?>,
                datasets: [{
                    label: 'เคส',
                    data: <?= $json_sym_val ?>,
                    backgroundColor: '#e74c3c'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y'
            }
        });
    </script>

</body>

</html>