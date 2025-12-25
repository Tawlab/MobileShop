<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'repair_list');

// [แก้ไข 1] รับค่า Branch ID จาก Session
$branch_id = $_SESSION['branch_id'];

// SETTINGS
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Sorting Logic
$allowed_sorts = [
    'r.repair_id',
    'r.create_at',
    'customer_lname',
    'emp_lname',
    'r.repair_status'
];
$default_sort = 'r.create_at';

$sort_by = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sorts) ? $_GET['sort'] : $default_sort;
$order = isset($_GET['order']) && strtolower($_GET['order']) == 'asc' ? 'ASC' : 'DESC';

//  รับค่าตัวกรองและค้นหา 
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$filter_repair_id = isset($_GET['filter_repair_id']) ? mysqli_real_escape_string($conn, $_GET['filter_repair_id']) : '';
$filter_status = isset($_GET['filter_status']) ? mysqli_real_escape_string($conn, $_GET['filter_status']) : '';
$filter_employee = isset($_GET['filter_employee']) ? mysqli_real_escape_string($conn, $_GET['filter_employee']) : '';
$filter_date_min = isset($_GET['filter_date_min']) ? mysqli_real_escape_string($conn, $_GET['filter_date_min']) : '';
$filter_date_max = isset($_GET['filter_date_max']) ? mysqli_real_escape_string($conn, $_GET['filter_date_max']) : '';
$filter_customer_id = isset($_GET['filter_customer_id']) ? mysqli_real_escape_string($conn, $_GET['filter_customer_id']) : '';
$filter_customer_name = isset($_GET['filter_customer_name']) ? htmlspecialchars($_GET['filter_customer_name']) : ''; // ใช้แสดงผลเท่านั้น

// BUILD WHERE CLAUSE 
// [แก้ไข 2] บังคับกรองงานซ่อมเฉพาะสาขานี้
$where_conditions = ["r.branches_branch_id = '$branch_id'"];
$is_filtered = false; 

//  รหัสงาน
if (!empty($filter_repair_id)) {
    $where_conditions[] = "r.repair_id = '$filter_repair_id'";
    $is_filtered = true;
}

// สถานะ
if (!empty($filter_status)) {
    $where_conditions[] = "r.repair_status = '$filter_status'";
    $is_filtered = true;
}

// พนักงาน
if (!empty($filter_employee)) {
    $where_conditions[] = "r.employees_emp_id = '$filter_employee'";
    $is_filtered = true;
}

// ลูกค้า
if (!empty($filter_customer_id)) {
    $where_conditions[] = "r.customers_cs_id = '$filter_customer_id'";
    $is_filtered = true;
}

// วันที่รับ
if (!empty($filter_date_min)) {
    $where_conditions[] = "r.create_at >= '$filter_date_min 00:00:00'";
    $is_filtered = true;
}
if (!empty($filter_date_max)) {
    $where_conditions[] = "r.create_at <= '$filter_date_max 23:59:59'";
    $is_filtered = true;
}

// Search Text
if (!empty($search)) {
    $where_conditions[] = "(
        r.repair_id LIKE '%$search%' OR 
        c.firstname_th LIKE '%$search%' OR 
        c.lastname_th LIKE '%$search%' OR
        c.cs_phone_no LIKE '%$search%' OR 
        ps.serial_no LIKE '%$search%' OR
        p.prod_name LIKE '%$search%'
    )";
    $is_filtered = true;
}

$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

// ดึงข้อมูลตามคอลัมน์
$main_sql = "SELECT 
                r.repair_id, 
                r.repair_status,
                r.create_at,
                c.firstname_th as customer_fname, 
                c.lastname_th as customer_lname,
                c.cs_phone_no as customer_phone,
                ps.serial_no,
                p.prod_name,
                e.firstname_th as emp_fname,
                e.lastname_th as emp_lname
            FROM repairs r
            LEFT JOIN customers c ON r.customers_cs_id = c.cs_id
            LEFT JOIN employees e ON r.employees_emp_id = e.emp_id
            LEFT JOIN prod_stocks ps ON r.prod_stocks_stock_id = ps.stock_id
            LEFT JOIN products p ON ps.products_prod_id = p.prod_id
            $where_clause
            ORDER BY $sort_by $order";

// พนักงาน (ต้องกรองเฉพาะสาขานี้ด้วย หรือเอาทุกคนในร้านก็ได้)
// เพื่อความถูกต้อง ควรแสดงเฉพาะพนักงานในร้านเดียวกัน
$employees_filter_result = mysqli_query($conn, "SELECT e.emp_id, e.firstname_th, e.lastname_th 
                                                FROM employees e 
                                                LEFT JOIN branches b ON e.branches_branch_id = b.branch_id
                                                WHERE e.emp_status = 'Active' 
                                                AND b.shop_info_shop_id = (SELECT shop_info_shop_id FROM branches WHERE branch_id = '$branch_id')
                                                ORDER BY e.firstname_th");

// สถานะ
$status_options = ['รับเครื่อง', 'ประเมิน', 'รออะไหล่', 'กำลังซ่อม', 'ซ่อมเสร็จ', 'ส่งมอบ', 'ยกเลิก'];

// COUNT TOTAL & FETCH DATA
$count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM ($main_sql) as count_table");
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

$data_sql = $main_sql . " LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $data_sql);

// HELPER FUNCTION
function build_query_string($exclude = [])
{
    $params = $_GET;
    foreach ($exclude as $key) {
        unset($params[$key]);
    }
    return !empty($params) ? '&' . http_build_query($params) : '';
}

// Sorting Helper
function get_sort_link($column, $current_sort, $current_order)
{
    $new_order = ($current_sort == $column && $current_order == 'ASC') ? 'DESC' : 'ASC';
    $icon = '';
    if ($current_sort == $column) {
        $icon = $current_order == 'ASC' ? '<i class="fas fa-chevron-up ms-1"></i>' : '<i class="fas fa-chevron-down ms-1"></i>';
    }
    $query_string = build_query_string(['sort', 'order']);
    return "<a href=\"?sort={$column}&order={$new_order}{$query_string}\" class=\"sort-link\">{$icon}</a>";
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>รายการงานซ่อม (Repair Dashboard)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            color: <?= $text_color ?>;
        }

        .container {
            max-width: 1400px;
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .table th {
            background-color: <?= $header_bg_color ?>;
            color: <?= $header_text_color ?>;
            text-align: center;
            vertical-align: middle;
            font-size: 0.9rem;
            position: relative;
        }

        .table td {
            vertical-align: middle;
            font-size: 0.85rem;
        }

        .table td:last-child {
            display: flex;
            gap: 5px; 
            justify-content: center;
            align-items: center;
            flex-wrap: nowrap;
        }

        .btn-add {
            background-color: <?= $btn_add_color ?>;
            color: white;
        }

        .btn-info {
            background-color: #0dcaf0;
            color: white;
        }

        .btn-update-status {
            background-color: #ffc107;
            color: black;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-รับเครื่อง {
            background-color: #d1edff;
            color: #0c63e4;
        }

        .status-ประเมิน {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-รออะไหล่ {
            background-color: #f5c6cb;
            color: #721c24;
        }

        .status-กำลังซ่อม {
            background-color: #e2d9f3;
            color: #49287f;
        }

        .status-ซ่อมเสร็จ {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .status-ส่งมอบ {
            background-color: #198754;
            color: white;
        }

        .status-ยกเลิก {
            background-color: #6c757d;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .filter-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            display: none;
        }

        .filter-card.show {
            display: block;
        }

        .filter-card .form-label {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .customer-search-result {
            max-height: 150px;
            overflow-y: auto;
            position: absolute;
            z-index: 1000;
            width: 95%;
        }

        .sort-link {
            color: white;
            text-decoration: none;
            position: absolute;
            top: 0;
            right: 0;
            padding: 0.6rem 0.8rem;
        }
        
        @media (max-width: 991.98px) {
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .filter-card .row > [class*='col-'] {
                margin-bottom: 10px; 
            }

            .table th, .table td {
                padding: 0.6rem 0.5rem; /* ลด Padding ด้านข้าง */
                font-size: 0.8rem; /* ลดขนาด Font เล็กน้อย */
                white-space: nowrap; /* ป้องกันไม่ให้ข้อความยาวๆ ขึ้นบรรทัดใหม่ในตาราง Responsive */
            }

            .table td:last-child {
                flex-direction: column; /* เรียงปุ่ม Action เป็นแนวตั้งบน Mobile */
                gap: 5px;
            }
            
            .status-badge {
                font-size: 0.7rem;
                padding: 3px 6px;
            }
            
            .sort-link {
                 padding: 0.5rem 0.5rem;
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
                    <div class="card">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class="mb-0" style="color: <?= $theme_color ?>;">
                                    <i class="fas fa-tools me-2"></i>
                                    รายการงานซ่อมทั้งหมด (<?= number_format($total_records) ?> งาน)
                                </h4>
                                <a href="add_repair.php?return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-add">
                                    <i class="fas fa-plus me-1"></i> รับเครื่องซ่อมใหม่
                                </a>
                            </div>
                        </div>

                        <div class="card-body">
                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo $_SESSION['success'];
                                    unset($_SESSION['success']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?php echo $_SESSION['error'];
                                    unset($_SESSION['error']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <div class="row mb-3 align-items-center">
                                <div class="col-md-4">
                                    <form method="GET" action="repair_list.php" class="d-flex">
                                        <input type="text" name="search" class="form-control form-control-sm"
                                            placeholder="ค้นหาข้อความ..."
                                            value="<?= htmlspecialchars($search) ?>">
                                        <button type="submit" class="btn btn-primary btn-sm ms-2">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <?php if (!empty($search)): ?>
                                            <a href="repair_list.php" class="btn btn-outline-danger btn-sm ms-1">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                    </form>
                                </div>
                                <div class="col-md-8 text-end">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleFilter">
                                        <i class="fas fa-filter me-1"></i>
                                        <?= $is_filtered ? 'ปิดตัวกรอง' : 'ตัวกรอง (Filter)' ?>
                                    </button>
                                </div>
                            </div>

                            <div class="filter-card <?= $is_filtered ? 'show' : '' ?>" id="filterCard">
                                <form method="GET" action="repair_list.php">
                                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">

                                    <div class="row g-3">

                                        <div class="col-md-2">
                                            <label class="form-label">รหัสงาน</label>
                                            <input type="number" name="filter_repair_id" class="form-control form-control-sm"
                                                value="<?= htmlspecialchars($filter_repair_id) ?>" placeholder="ID">
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">วันที่รับ (ตั้งแต่)</label>
                                            <input type="date" name="filter_date_min" class="form-control form-control-sm"
                                                value="<?= htmlspecialchars($filter_date_min) ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">วันที่รับ (ถึง)</label>
                                            <input type="date" name="filter_date_max" class="form-control form-control-sm"
                                                value="<?= htmlspecialchars($filter_date_max) ?>">
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">สถานะ</label>
                                            <select name="filter_status" class="form-select form-select-sm">
                                                <option value="">-- ทุกสถานะ --</option>
                                                <?php foreach ($status_options as $status): ?>
                                                    <option value="<?= $status ?>" <?= ($filter_status == $status) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($status) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">พนักงานที่รับผิดชอบ</label>
                                            <select name="filter_employee" class="form-select form-select-sm">
                                                <option value="">-- ทั้งหมด --</option>
                                                <?php mysqli_data_seek($employees_filter_result, 0); ?>
                                                <?php while ($emp = mysqli_fetch_assoc($employees_filter_result)): ?>
                                                    <option value="<?= $emp['emp_id'] ?>" <?= ($filter_employee == $emp['emp_id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($emp['firstname_th'] . ' ' . $emp['lastname_th']) ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-3 position-relative">
                                            <label class="form-label">ลูกค้า</label>
                                            <input type="text" id="customer_filter_display" class="form-control form-control-sm"
                                                placeholder="พิมพ์ชื่อหรือเบอร์โทรลูกค้า"
                                                value="<?= htmlspecialchars($filter_customer_name) ?>">
                                            <input type="hidden" name="filter_customer_id" id="customer_filter_id" value="<?= htmlspecialchars($filter_customer_id) ?>">
                                            <div id="customer_filter_results" class="list-group customer-search-result"></div>
                                        </div>

                                        <div class="col-md-3 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary btn-sm me-2">
                                                <i class="fas fa-filter me-1"></i> กรอง
                                            </button>
                                            <a href="repair_list.php?sort=<?= $sort_by ?>&order=<?= $order ?>" class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-sync-alt me-1"></i> ล้างตัวกรอง
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th width="10%">รหัสงาน <?= get_sort_link('r.repair_id', $sort_by, $order) ?></th>
                                            <th width="15%">วันที่รับ <?= get_sort_link('r.create_at', $sort_by, $order) ?></th>
                                            <th width="20%">ลูกค้า <?= get_sort_link('customer_lname', $sort_by, $order) ?></th>
                                            <th width="20%">เครื่อง / Serial No.</th>
                                            <th width="15%">พนักงาน <?= get_sort_link('emp_lname', $sort_by, $order) ?></th>
                                            <th width="10%">สถานะ <?= get_sort_link('r.repair_status', $sort_by, $order) ?></th>
                                            <th width="10%">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($result && $result->num_rows > 0): ?>
                                            <?php while ($row = $result->fetch_assoc()):
                                                $status_css = 'status-' . str_replace(' ', '', $row['repair_status']);
                                                // เช็คว่างานจบหรือยัง (ส่งมอบ)
                                                $is_finished = ($row['repair_status'] == 'ส่งมอบ');
                                            ?>
                                                <tr>
                                                    <td class="text-center"><strong><?= htmlspecialchars($row['repair_id']) ?></strong></td>
                                                    <td class="text-center"><?= date('d/m/Y', strtotime($row['create_at'])) ?></td>
                                                    <td>
                                                        <?= htmlspecialchars($row['customer_fname'] . ' ' . $row['customer_lname']) ?>
                                                        <div class="text-muted" style="font-size: 0.8em;"><?= htmlspecialchars($row['customer_phone']) ?></div>
                                                    </td>
                                                    <td>
                                                        <?= htmlspecialchars($row['prod_name']) ?> (<?= htmlspecialchars($row['serial_no']) ?>)
                                                    </td>
                                                    <td>
                                                        <?= htmlspecialchars($row['emp_fname'] . ' ' . $row['emp_lname']) ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="status-badge <?= $status_css ?>">
                                                            <?= htmlspecialchars($row['repair_status']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="view_repair.php?id=<?= $row['repair_id'] ?>" class="btn btn-info btn-sm" title="ดูรายละเอียด">
                                                            <i class="fas fa-eye"></i>
                                                        </a>

                                                        <?php if ($is_finished): ?>
                                                            <button class="btn btn-secondary btn-sm" disabled title="งานจบแล้ว">
                                                                <i class="fas fa-clipboard-check"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <a href="update_repair_status.php?id=<?= $row['repair_id'] ?>&return_to=list" class="btn btn-update-status btn-sm" title="อัปเดตสถานะ">
                                                                <i class="fas fa-clipboard-check"></i>
                                                            </a>
                                                        <?php endif; ?>

                                                        <?php if (!$is_finished): ?>
                                                            <a href="cancel_repair.php?id=<?= $row['repair_id'] ?>"
                                                                class="btn btn-danger btn-sm"
                                                                title="ยกเลิกงานซ่อม">
                                                                <i class="fas fa-ban"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <button class="btn btn-secondary btn-sm" disabled>
                                                                <i class="fas fa-ban"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo ($page - 1); ?><?php echo build_query_string(['page', 'sort', 'order']); ?>&sort=<?= $sort_by ?>&order=<?= $order ?>">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?><?php echo build_query_string(['page', 'sort', 'order']); ?>&sort=<?= $sort_by ?>&order=<?= $order ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo ($page + 1); ?><?php echo build_query_string(['page', 'sort', 'order']); ?>&sort=<?= $sort_by ?>&order=<?= $order ?>">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- Customer Filter Logic  ---
        document.getElementById('customer_filter_display').addEventListener('input', function() {
            const query = this.value.trim();
            const resultsDiv = document.getElementById('customer_filter_results');

            if (query.length < 2) {
                resultsDiv.innerHTML = '';
                document.getElementById('customer_filter_id').value = '';
                return;
            }

            // สำหรับค้นหาลูกค้า
            fetch('add_repair.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=search_customer&query=${query}`
                })
                .then(res => res.json())
                .then(data => {
                    resultsDiv.innerHTML = '';
                    if (data.success && data.customers.length > 0) {
                        data.customers.forEach(customer => {
                            const item = document.createElement('a');
                            item.href = '#';
                            item.className = 'list-group-item list-group-item-action';
                            item.innerHTML = `
                            <strong>${customer.firstname_th} ${customer.lastname_th}</strong> 
                            <small class="text-muted ms-2">(${customer.cs_phone_no})</small>
                        `;
                            item.onclick = (e) => {
                                e.preventDefault();
                                document.getElementById('customer_filter_id').value = customer.cs_id;
                                document.getElementById('customer_filter_display').value = `${customer.firstname_th} ${customer.lastname_th} (${customer.cs_phone_no})`;
                                resultsDiv.innerHTML = ''; 
                            };
                            resultsDiv.appendChild(item);
                        });
                    } else if (query.length >= 5) {
                        resultsDiv.innerHTML = `<div class="p-2 text-muted">ไม่พบลูกค้า</div>`;
                        document.getElementById('customer_filter_id').value = '';
                    }
                });
        });

        // ล้างค่าเมื่อผู้ใช้ล้างช่อง
        document.getElementById('customer_filter_display').addEventListener('focusout', function() {
            setTimeout(() => {
                document.getElementById('customer_filter_results').innerHTML = '';
            }, 300);

            if (this.value.trim() === '') {
                document.getElementById('customer_filter_id').value = '';
            }
        });

        // --- Filter Toggle Logic ---
        document.getElementById('toggleFilter').addEventListener('click', function() {
            const filterCard = document.getElementById('filterCard');
            if (filterCard.classList.contains('show')) {
                filterCard.classList.remove('show');
                this.innerHTML = '<i class="fas fa-filter me-1"></i> ตัวกรอง (Filter)';
            } else {
                filterCard.classList.add('show');
                this.innerHTML = '<i class="fas fa-times me-1"></i> ปิดตัวกรอง';
            }
        });

        // ถ้ามี filter ค้างอยู่ ให้เปลี่ยนปุ่มเป็นสีที่โดดเด่น
        document.addEventListener('DOMContentLoaded', function() {
            const isFilterActive = <?= $is_filtered ? 'true' : 'false' ?>;
            const toggleBtn = document.getElementById('toggleFilter');
            if (isFilterActive) {
                toggleBtn.classList.remove('btn-outline-secondary');
                toggleBtn.classList.add('btn-warning');
                toggleBtn.innerHTML = '<i class="fas fa-filter me-1"></i> ตัวกรองทำงาน';
            }
        });
    </script>
</body>

</html>