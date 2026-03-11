<?php
session_start();
require '../config/config.php';
require '../vendor/autoload.php';

// ตรวจสอบสิทธิ์ (ใช้สิทธิ์ report_repairs)
checkPageAccess($conn, 'report_repairs');

$current_user_id = $_SESSION['user_id'];
$current_branch_id = $_SESSION['branch_id'];

// -----------------------------------------------------------------------------
//  CHECK ADMIN & PREPARE DATA
// -----------------------------------------------------------------------------
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

// ดึงรายชื่อร้านค้าและสาขา
$shops_list = [];
$branches_list = [];
if ($is_admin) {
    $shop_res = $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name");
    while ($row = $shop_res->fetch_assoc()) $shops_list[] = $row;
    
    $br_res = $conn->query("SELECT branch_id, branch_name, shop_info_shop_id FROM branches ORDER BY branch_name");
    while ($row = $br_res->fetch_assoc()) $branches_list[] = $row;
}

// ดึงพนักงาน
$emp_sql = "SELECT emp_id, firstname_th, lastname_th FROM employees WHERE emp_status = 'Active'";
if (!$is_admin) $emp_sql .= " AND branches_branch_id = '$current_branch_id'";
$emp_res = $conn->query($emp_sql);

// ดึงยี่ห้อและประเภทสินค้า (อะไหล่/ค่าบริการ)
$brand_res = $conn->query("SELECT brand_id, brand_name_th FROM prod_brands ORDER BY brand_name_th");
$type_res = $conn->query("SELECT type_id, type_name_th FROM prod_types ORDER BY type_name_th");


// -----------------------------------------------------------------------------
//  AJAX ประมวลผลรายงาน
// -----------------------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'get_report') {
    ob_clean();
    header('Content-Type: application/json');

    $report_type = $_POST['report_type'];
    $start_date = $_POST['start_date'] . " 00:00:00";
    $end_date = $_POST['end_date'] . " 23:59:59";
    $min_amt = !empty($_POST['min_amt']) ? (float)$_POST['min_amt'] : 0;
    $max_amt = !empty($_POST['max_amt']) ? (float)$_POST['max_amt'] : 999999999;
    $filter_id = !empty($_POST['filter_id']) ? (int)$_POST['filter_id'] : 0;

    // --- จัดการสาขา ---
    $target_branch_id = 0;
    if ($is_admin) {
        if (!empty($_POST['branch_id'])) $target_branch_id = (int)$_POST['branch_id'];
    } else {
        $target_branch_id = $current_branch_id;
    }

    $data = [];
    $summary = ['total_sales' => 0, 'total_items' => 0, 'count_bill' => 0];

    // --- SQL Queries เปลี่ยน bill_type เป็น 'Repair' ---
    if ($report_type === 'employee') {
        // รายงานตามพนักงาน (คนที่ออกบิล/รับเงิน)
        $sql = "SELECT e.firstname_th, e.lastname_th, e.emp_code,
                       COUNT(DISTINCT bh.bill_id) as bill_count,
                       SUM(bd.price * bd.amount) as total_sales
                FROM bill_headers bh
                JOIN employees e ON bh.employees_emp_id = e.emp_id
                JOIN bill_details bd ON bh.bill_id = bd.bill_headers_bill_id
                WHERE bh.bill_type = 'Repair' 
                  AND bh.bill_status = 'Completed'
                  AND bh.bill_date BETWEEN '$start_date' AND '$end_date'";
        
        if ($target_branch_id > 0) $sql .= " AND bh.branches_branch_id = '$target_branch_id'";
        if ($filter_id > 0) $sql .= " AND e.emp_id = '$filter_id'";

        $sql .= " GROUP BY e.emp_id 
                  HAVING total_sales BETWEEN $min_amt AND $max_amt
                  ORDER BY total_sales DESC";

    } elseif ($report_type === 'brand') {
        // รายงานตามยี่ห้อ (อะไหล่ที่ใช้ซ่อม)
        $sql = "SELECT pb.brand_name_th as name,
                       SUM(bd.amount) as item_count,
                       SUM(bd.price * bd.amount) as total_sales
                FROM bill_details bd
                JOIN bill_headers bh ON bd.bill_headers_bill_id = bh.bill_id
                JOIN products p ON bd.products_prod_id = p.prod_id
                JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id
                WHERE bh.bill_type = 'Repair' 
                  AND bh.bill_status = 'Completed'
                  AND bh.bill_date BETWEEN '$start_date' AND '$end_date'";

        if ($target_branch_id > 0) $sql .= " AND bh.branches_branch_id = '$target_branch_id'";
        if ($filter_id > 0) $sql .= " AND pb.brand_id = '$filter_id'";

        $sql .= " GROUP BY pb.brand_id 
                  HAVING total_sales BETWEEN $min_amt AND $max_amt
                  ORDER BY total_sales DESC";

    } elseif ($report_type === 'type') {
        // รายงานตามประเภท (ประเภทอะไหล่/ค่าบริการ)
        $sql = "SELECT pt.type_name_th as name,
                       SUM(bd.amount) as item_count,
                       SUM(bd.price * bd.amount) as total_sales
                FROM bill_details bd
                JOIN bill_headers bh ON bd.bill_headers_bill_id = bh.bill_id
                JOIN products p ON bd.products_prod_id = p.prod_id
                JOIN prod_types pt ON p.prod_types_type_id = pt.type_id
                WHERE bh.bill_type = 'Repair' 
                  AND bh.bill_status = 'Completed'
                  AND bh.bill_date BETWEEN '$start_date' AND '$end_date'";

        if ($target_branch_id > 0) $sql .= " AND bh.branches_branch_id = '$target_branch_id'";
        if ($filter_id > 0) $sql .= " AND pt.type_id = '$filter_id'";

        $sql .= " GROUP BY pt.type_id 
                  HAVING total_sales BETWEEN $min_amt AND $max_amt
                  ORDER BY total_sales DESC";
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

    echo json_encode(['success' => true, 'data' => $data, 'summary' => $summary]);
    exit;
}

// AJAX Load Branches & Employees for Admin
if (isset($_POST['action']) && $_POST['action'] === 'get_branch_employees') {
    ob_clean();
    header('Content-Type: application/json');
    $br_id = (int)$_POST['branch_id'];
    $sql = "SELECT emp_id, firstname_th, lastname_th FROM employees WHERE emp_status = 'Active'";
    if ($br_id > 0) $sql .= " AND branches_branch_id = '$br_id'";
    $res = $conn->query($sql);
    $emps = [];
    while($r = $res->fetch_assoc()) $emps[] = $r;
    echo json_encode($emps);
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานยอดซ่อม (Repair Report)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <?php require '../config/load_theme.php'; ?>
    
    <style>
        body { background-color: <?= $background_color ?>; font-family: '<?= $font_style ?>', sans-serif; }
        .filter-card { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: #fff; margin-bottom: 20px; }
        .filter-header { background: #fd7e14; /* สีส้มสำหรับงานซ่อม */ color: white; padding: 15px 20px; border-radius: 15px 15px 0 0; font-weight: 600; }
        .report-card { border: none; border-radius: 12px; color: white; transition: transform 0.2s; }
        .report-card:hover { transform: translateY(-5px); }
        .bg-gradient-primary { background: linear-gradient(45deg, #fd7e14, #d63384); } /* ปรับสีให้ต่างจากยอดขาย */
        .bg-gradient-success { background: linear-gradient(45deg, #20c997, #0ca678); }
        .bg-gradient-info { background: linear-gradient(45deg, #0dcaf0, #0aa2c0); }
        .table-responsive { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .table thead th { background-color: #fff3cd; border-bottom: 2px solid #ffe69c; color: #664d03; }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                
                <h4 class="mb-4 fw-bold text-secondary"><i class="fas fa-tools me-2"></i>รายงานยอดซ่อม (Repair Report)</h4>

                <div class="filter-card">
                    <div class="filter-header">
                        <i class="fas fa-filter me-2"></i> กำหนดเงื่อนไขรายงาน
                    </div>
                    <div class="card-body p-4">
                        <form id="reportForm">
                            
                            <?php if ($is_admin): ?>
                            <div class="row g-3 mb-3 p-3 bg-light rounded border">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-warning">เลือกร้านค้า (Admin)</label>
                                    <select class="form-select select2" id="admin_shop_id">
                                        <option value="">-- เลือกร้านค้า --</option>
                                        <?php foreach ($shops_list as $shop): ?>
                                            <option value="<?= $shop['shop_id'] ?>"><?= $shop['shop_name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-warning">เลือกสาขา (Admin)</label>
                                    <select class="form-select select2" name="branch_id" id="admin_branch_id">
                                        <option value="0">-- ทุกสาขา --</option>
                                        <?php foreach ($branches_list as $br): ?>
                                            <option value="<?= $br['branch_id'] ?>" data-shop="<?= $br['shop_info_shop_id'] ?>"><?= $br['branch_name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">จากวันที่</label>
                                    <input type="date" class="form-control" name="start_date" id="start_date" value="<?= date('Y-m-01') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">ถึงวันที่</label>
                                    <input type="date" class="form-control" name="end_date" id="end_date" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">รูปแบบรายงาน</label>
                                    <select class="form-select" name="report_type" id="report_type" onchange="toggleFilters()">
                                        <option value="employee">👷 ยอดซ่อมตามพนักงาน</option>
                                        <option value="brand">🏷️ ยอดซ่อมตามยี่ห้อ (อะไหล่)</option>
                                        <option value="type">🛠️ ยอดซ่อมตามประเภท (อะไหล่)</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">ยอดซ่อมขั้นต่ำ-สูงสุด</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="min_amt" placeholder="0">
                                        <input type="number" class="form-control" name="max_amt" placeholder="Max">
                                    </div>
                                </div>

                                <div class="col-md-6 filter-group" id="filter_employee">
                                    <label class="form-label">เลือกพนักงาน (ทั้งหมด)</label>
                                    <select class="form-select select2" name="emp_id" id="emp_id">
                                        <option value="0">-- พนักงานทั้งหมด --</option>
                                        <?php while($r = $emp_res->fetch_assoc()): ?>
                                            <option value="<?= $r['emp_id'] ?>"><?= $r['firstname_th'].' '.$r['lastname_th'] ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 filter-group d-none" id="filter_brand">
                                    <label class="form-label">เลือกยี่ห้อ (ทั้งหมด)</label>
                                    <select class="form-select select2" name="brand_id" id="brand_id">
                                        <option value="0">-- ยี่ห้อทั้งหมด --</option>
                                        <?php while($r = $brand_res->fetch_assoc()): ?>
                                            <option value="<?= $r['brand_id'] ?>"><?= $r['brand_name_th'] ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 filter-group d-none" id="filter_type">
                                    <label class="form-label">เลือกประเภท (ทั้งหมด)</label>
                                    <select class="form-select select2" name="type_id" id="type_id">
                                        <option value="0">-- ประเภททั้งหมด --</option>
                                        <?php while($r = $type_res->fetch_assoc()): ?>
                                            <option value="<?= $r['type_id'] ?>"><?= $r['type_name_th'] ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="col-md-12 text-end mt-4">
                                    <button type="button" class="btn btn-secondary me-2" onclick="resetForm()"><i class="fas fa-undo"></i> รีเซ็ต</button>
                                    <button type="submit" class="btn btn-warning text-white px-4"><i class="fas fa-search"></i> แสดงรายงาน</button>
                                    <button type="button" class="btn btn-success ms-2" onclick="printReport()"><i class="fas fa-print"></i> พิมพ์รายงาน</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row g-4 mb-4" id="summary_section" style="display:none;">
                    <div class="col-md-4">
                        <div class="card report-card bg-gradient-primary h-100 p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="small text-white-50 text-uppercase fw-bold">ยอดซ่อมรวม (Total Repair)</div>
                                    <div class="h3 mb-0 fw-bold mt-2" id="sum_total_sales">0.00 ฿</div>
                                </div>
                                <div class="fs-1 text-white-50"><i class="fas fa-wrench"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card report-card bg-gradient-success h-100 p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="small text-white-50 text-uppercase fw-bold" id="label_count_1">จำนวนบิลซ่อม</div>
                                    <div class="h3 mb-0 fw-bold mt-2" id="sum_count_1">0</div>
                                </div>
                                <div class="fs-1 text-white-50"><i class="fas fa-file-invoice-dollar"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card report-card bg-gradient-info h-100 p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="small text-white-50 text-uppercase fw-bold" id="label_count_2">รายการอะไหล่</div>
                                    <div class="h3 mb-0 fw-bold mt-2" id="sum_count_2">-</div>
                                </div>
                                <div class="fs-1 text-white-50"><i class="fas fa-cogs"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive" id="result_section" style="display:none;">
                    <h5 class="fw-bold mb-3 text-secondary" id="table_title">รายละเอียดข้อมูล</h5>
                    <table class="table table-hover table-striped align-middle w-100" id="reportTable">
                        <thead><tr id="table_head_row"></tr></thead>
                        <tbody id="table_body"></tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        const allBranches = <?php echo json_encode($branches_list); ?>;
        const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;

        $(document).ready(function() {
            $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });

            if (isAdmin) {
                $('#admin_shop_id').on('change', function() {
                    const shopId = $(this).val();
                    const $branchSelect = $('#admin_branch_id');
                    $branchSelect.empty().append('<option value="0">-- ทุกสาขา --</option>');
                    if (shopId) {
                        const filtered = allBranches.filter(b => b.shop_info_shop_id == shopId);
                        filtered.forEach(b => $branchSelect.append(new Option(b.branch_name, b.branch_id)));
                    }
                    $branchSelect.trigger('change');
                });
                $('#admin_branch_id').on('change', function() {
                    const brId = $(this).val();
                    $.post('report_repairs.php', { action: 'get_branch_employees', branch_id: brId }, function(data) {
                        const $empSelect = $('#emp_id');
                        $empSelect.empty().append('<option value="0">-- พนักงานทั้งหมด --</option>');
                        data.forEach(e => $empSelect.append(new Option(e.firstname_th + ' ' + e.lastname_th, e.emp_id)));
                        $empSelect.trigger('change');
                    }, 'json');
                });
            }
        });

        function toggleFilters() {
            const type = $('#report_type').val();
            $('.filter-group').addClass('d-none');
            if (type === 'employee') $('#filter_employee').removeClass('d-none');
            if (type === 'brand') $('#filter_brand').removeClass('d-none');
            if (type === 'type') $('#filter_type').removeClass('d-none');
        }

        function resetForm() {
            document.getElementById('reportForm').reset();
            $('#report_type').val('employee').trigger('change');
            $('.select2').val('0').trigger('change');
            if(isAdmin) $('#admin_shop_id').val('').trigger('change');
            $('#result_section, #summary_section').slideUp();
        }

        $('#reportForm').on('submit', function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            formData.append('action', 'get_report');
            
            const type = $('#report_type').val();
            if(type === 'employee') formData.append('filter_id', $('#emp_id').val());
            if(type === 'brand') formData.append('filter_id', $('#brand_id').val());
            if(type === 'type') formData.append('filter_id', $('#type_id').val());

            Swal.fire({ title: 'กำลังประมวลผล...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

            $.ajax({
                url: 'report_repairs.php', type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json',
                success: function(res) {
                    Swal.close();
                    if(res.success) renderReport(res.data, res.summary, type);
                    else Swal.fire('Error', 'เกิดข้อผิดพลาด', 'error');
                },
                error: function() { Swal.fire('Error', 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้', 'error'); }
            });
        });

        function renderReport(data, summary, type) {
            $('#sum_total_sales').text(numberWithCommas(summary.total_sales.toFixed(2)) + ' ฿');
            if (type === 'employee') {
                $('#label_count_1').text('จำนวนบิลซ่อม'); $('#sum_count_1').text(summary.count_bill);
                $('#label_count_2').text('-'); $('#sum_count_2').text('-');
            } else {
                $('#label_count_1').text('จำนวนอะไหล่ที่ใช้'); $('#sum_count_1').text(summary.total_items);
                $('#label_count_2').text('-'); $('#sum_count_2').text('-');
            }
            $('#summary_section').slideDown();

            let thead = '';
            if (type === 'employee') thead = `<th>รหัสพนักงาน</th><th>ชื่อ-นามสกุล</th><th class="text-center">จำนวนบิล</th><th class="text-end">ยอดซ่อมรวม</th>`;
            else if (type === 'brand') thead = `<th>ยี่ห้ออะไหล่</th><th class="text-center">จำนวนชิ้น</th><th class="text-end">ยอดซ่อมรวม</th>`;
            else if (type === 'type') thead = `<th>ประเภทอะไหล่</th><th class="text-center">จำนวนชิ้น</th><th class="text-end">ยอดซ่อมรวม</th>`;
            $('#table_head_row').html(thead);

            let tbody = '';
            if (data.length > 0) {
                data.forEach(row => {
                    tbody += `<tr>`;
                    if (type === 'employee') tbody += `<td>${row.emp_code || '-'}</td><td>${row.firstname_th} ${row.lastname_th}</td><td class="text-center">${row.bill_count}</td><td class="text-end fw-bold text-danger">${numberWithCommas(parseFloat(row.total_sales).toFixed(2))}</td>`;
                    else tbody += `<td>${row.name}</td><td class="text-center">${row.item_count}</td><td class="text-end fw-bold text-danger">${numberWithCommas(parseFloat(row.total_sales).toFixed(2))}</td>`;
                    tbody += `</tr>`;
                });
            } else { tbody = `<tr><td colspan="4" class="text-center text-muted py-4">ไม่พบข้อมูล</td></tr>`; }
            $('#table_body').html(tbody);
            $('#result_section').slideDown();
        }

        function numberWithCommas(x) { return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","); }

        function printReport() {
            const formData = new FormData(document.getElementById('reportForm'));
            const params = new URLSearchParams(formData).toString();
            const type = $('#report_type').val();
            let fid = 0;
            if(type === 'employee') fid = $('#emp_id').val();
            if(type === 'brand') fid = $('#brand_id').val();
            if(type === 'type') fid = $('#type_id').val();
            
            // หน้าพิมพ์จะสร้างเป็นไฟล์ print_report_repairs.php ในขั้นตอนถัดไป
            window.open('print_report_repairs.php?' + params + '&filter_id=' + fid, '_blank');
        }
    </script>
</body>
</html>