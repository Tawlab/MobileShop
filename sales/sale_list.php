<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'sale_list');

// [1] รับค่าพื้นฐานจาก Session
$branch_id = $_SESSION['branch_id'];
$shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// [2] ตรวจสอบสิทธิ์ผู้ดูแลระบบ (Admin)
$is_super_admin = false;
$check_admin_sql = "SELECT r.role_name FROM roles r 
                    JOIN user_roles ur ON r.role_id = ur.roles_role_id 
                    WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt_admin = $conn->prepare($check_admin_sql)) {
    $stmt_admin->bind_param("i", $current_user_id);
    $stmt_admin->execute();
    if ($stmt_admin->get_result()->num_rows > 0) $is_super_admin = true;
    $stmt_admin->close();
}

// ==========================================
// [3] ส่วนประมวลผล AJAX (ทำงานเมื่อเรียกผ่าน Fetch API)
// ==========================================
if (isset($_GET['ajax'])) {
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
    $status_f = isset($_GET['status']) ? $_GET['status'] : '';
    $payment_f = isset($_GET['payment']) ? $_GET['payment'] : '';
    $date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
    $date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';
    $shop_f = isset($_GET['shop_filter']) ? $_GET['shop_filter'] : '';
    $branch_f = isset($_GET['branch_filter']) ? $_GET['branch_filter'] : '';
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20; // 2. แสดงรายการ 20 รายการต่อหน้า
    $offset = ($page - 1) * $limit;

    // 3. กรองตามสิทธิ์ (เห็นแค่สาขาตัวเอง / แอดมินเห็นทั้งหมดหรือตามกรอง)
    $conditions = [];
    if (!$is_super_admin) {
        $conditions[] = "bh.branches_branch_id = '$branch_id'";
    } else {
        if (!empty($branch_f)) $conditions[] = "bh.branches_branch_id = '$branch_f'";
        elseif (!empty($shop_f)) $conditions[] = "b.shop_info_shop_id = '$shop_f'";
    }

    if (!empty($search)) {
        $conditions[] = "(bh.bill_id LIKE '%$search%' OR c.firstname_th LIKE '%$search%' OR c.lastname_th LIKE '%$search%')";
    }
    if (!empty($status_f)) $conditions[] = "bh.bill_status = '$status_f'";
    if (!empty($payment_f)) $conditions[] = "bh.payment_method = '$payment_f'";
    if (!empty($date_start)) $conditions[] = "DATE(bh.bill_date) >= '$date_start'";
    if (!empty($date_end)) $conditions[] = "DATE(bh.bill_date) <= '$date_end'";

    $where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // นับจำนวนทั้งหมดเพื่อคำนวณหน้า
    $count_sql = "SELECT COUNT(*) as total FROM bill_headers bh 
                  LEFT JOIN customers c ON bh.customers_cs_id = c.cs_id 
                  LEFT JOIN branches b ON bh.branches_branch_id = b.branch_id 
                  $where_sql";
    $total_items = $conn->query($count_sql)->fetch_assoc()['total'];
    $total_pages = ceil($total_items / $limit);

    // ดึงข้อมูลการขาย
    $sql = "SELECT bh.*, c.firstname_th, c.lastname_th, b.branch_name, sh.shop_name, e.firstname_th AS emp_fname
            FROM bill_headers bh
            LEFT JOIN customers c ON bh.customers_cs_id = c.cs_id
            LEFT JOIN branches b ON bh.branches_branch_id = b.branch_id
            LEFT JOIN shop_info sh ON b.shop_info_shop_id = sh.shop_id
            LEFT JOIN employees e ON bh.employees_emp_id = e.emp_id
            $where_sql 
            ORDER BY bh.bill_date DESC 
            LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);
    ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-center" width="8%">เลขที่บิล</th>
                    <th width="15%">วันที่ขาย</th>
                    <th width="20%">ลูกค้า</th>
                    <th width="12%">การชำระเงิน</th>
                    <?php if ($is_super_admin): // 3. เพิ่มคอลัมน์ระบุสาขา/ร้าน ?>
                        <th width="15%" class="text-center">สาขา/ร้าน</th>
                    <?php endif; ?>
                    <th width="12%" class="text-center">สถานะ</th>
                    <th width="12%" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): while ($row = $result->fetch_assoc()): 
                    $status_badge = match($row['bill_status']) {
                        'Pending' => 'bg-warning text-dark',
                        'Completed' => 'bg-success',
                        'Canceled' => 'bg-danger',
                        default => 'bg-secondary'
                    };
                ?>
                <tr>
                    <td class="text-center fw-bold text-primary">#<?= $row['bill_id'] ?></td>
                    <td class="small"><?= date('d/m/Y H:i', strtotime($row['bill_date'])) ?></td>
                    <td>
                        <div class="fw-bold"><?= htmlspecialchars($row['firstname_th'] . ' ' . $row['lastname_th']) ?></div>
                        <div class="small text-muted"><?= $row['emp_fname'] ?> (พนักงาน)</div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border px-2">
                            <i class="fas fa-wallet me-1"></i><?= $row['payment_method'] ?>
                        </span>
                    </td>
                    <?php if ($is_super_admin): ?>
                        <td class="text-center small">
                            <div class="fw-bold text-dark"><?= htmlspecialchars($row['shop_name'] ?? '-') ?></div>
                            <div class="text-muted"><?= htmlspecialchars($row['branch_name'] ?? '-') ?></div>
                        </td>
                    <?php endif; ?>
                    <td class="text-center">
                        <span class="badge <?= $status_badge ?> px-3 rounded-pill"><?= $row['bill_status'] ?></span>
                    </td>
                    <td class="text-center">
                        <div class="d-flex justify-content-center gap-1">
                            <a href="view_sale.php?id=<?= $row['bill_id'] ?>" class="btn btn-outline-primary btn-sm border-0" title="ดูรายละเอียด"><i class="fas fa-eye"></i></a>
                            <?php if ($row['bill_status'] == 'Pending'): ?>
                                <a href="payment_select.php?id=<?= $row['bill_id'] ?>" class="btn btn-outline-warning btn-sm border-0" title="ชำระเงิน"><i class="fas fa-wallet"></i></a>
                            <?php endif; ?>
                            <?php if ($row['bill_status'] != 'Canceled'): ?>
                                <a href="cancel_sale.php?id=<?= $row['bill_id'] ?>" class="btn btn-outline-danger btn-sm border-0" onclick="return confirm('ยกเลิกบิล #<?= $row['bill_id'] ?> ?')" title="ยกเลิก"><i class="fas fa-ban"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="<?= $is_super_admin ? 7 : 6 ?>" class="text-center py-5 text-muted">-- ไม่พบข้อมูลการขาย --</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center pagination-sm">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link ajax-page-link" href="#" data-page="1" title="หน้าแรก"><i class="fas fa-angle-double-left"></i></a>
            </li>
            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                    <a class="page-link ajax-page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link ajax-page-link" href="#" data-page="<?= $total_pages ?>" title="หน้าสุดท้าย"><i class="fas fa-angle-double-right"></i></a>
            </li>
        </ul>
    </nav>
    <div class="d-flex justify-content-center mt-2 gap-2 align-items-center">
        <div class="input-group input-group-sm" style="max-width: 150px;">
            <input type="number" id="jumpPageInput" class="form-control text-center" placeholder="ไปหน้า" min="1" max="<?= $total_pages ?>">
            <button class="btn btn-success" type="button" id="btnJumpPage">ไป</button>
        </div>
        <div class="small text-muted">หน้า <?= $page ?> / <?= $total_pages ?> (ทั้งหมด <?= number_format($total_items) ?> รายการ)</div>
    </div>
    <?php endif; exit(); }

// [4] โหลดข้อมูลสำหรับตัวกรอง (สำหรับ Admin)
if ($is_super_admin) {
    $all_shops = $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name ASC");
    $all_branches = $conn->query("SELECT branch_id, branch_name, shop_info_shop_id FROM branches ORDER BY branch_name ASC");
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายการขายสินค้า - Mobile Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .status-badge {
            font-size: 0.85rem;
            padding: 5px 10px;
            border-radius: 20px;
            min-width: 80px;
            display: inline-block;
            text-align: center;
        }

        .bg-Pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .bg-Completed {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .bg-Canceled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* -------------------------------------------------------------------- */
        /* --- **[เพิ่ม]** Responsive Override สำหรับ Mobile (จอเล็กกว่า 992px) --- */
        /* -------------------------------------------------------------------- */
        @media (max-width: 991.98px) {
            /* 1. จัดการ Filter/Action Bar (สมมติว่าใช้ d-flex ใน card-body/header) */
            .card-body .d-flex,
            .card-header .d-flex {
                flex-direction: column; /* เรียงเป็นแนวตั้ง */
                gap: 10px;
            }

            .card-body .d-flex > *,
            .card-header .d-flex > * {
                 width: 100% !important; /* ทำให้ส่วนประกอบกินเต็มความกว้าง */
            }

            /* 2. ปรับ Table Cell/Font */
            .table th, .table td {
                padding: 0.6rem 0.5rem; /* ลด Padding ด้านข้าง */
                font-size: 0.8rem; /* ลดขนาด Font เล็กน้อย */
                white-space: nowrap; /* ป้องกันไม่ให้ข้อความยาวๆ ขึ้นบรรทัดใหม่ในตาราง Responsive */
            }

            /* 3. จัดการคอลัมน์ Action ในตาราง */
            .table td:last-child {
                display: flex;
                flex-direction: column; /* เรียงปุ่ม Action เป็นแนวตั้งบน Mobile */
                gap: 5px;
            }
            
            /* 4. ปรับขนาด Badge */
            .status-badge {
                font-size: 0.75rem;
                padding: 4px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container py-2" style="max-width: 1400px;">
                    
                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                            <h4 class="mb-0 fw-bold text-primary"><i class="fas fa-shopping-basket me-2"></i>รายการข้อมูลการขาย</h4>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm fw-bold px-3" onclick="toggleFilter()">
                                    <i class="fas fa-filter me-1"></i> <span id="filterBtnText">ตัวกรอง</span>
                                </button>
                                <a href="add_sale.php" class="btn btn-success btn-sm fw-bold px-3">
                                    <i class="fas fa-plus me-1"></i> เปิดบิลขายใหม่
                                </a>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            <div class="card bg-light border-0 mb-4" id="filterCard" style="display: none; border-radius: 15px;">
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-md-2">
                                            <label class="small fw-bold text-muted mb-1">สถานะบิล</label>
                                            <select id="statusFilter" class="form-select border-0 shadow-sm">
                                                <option value="">-- ทั้งหมด --</option>
                                                <option value="Pending">Pending (รอดำเนินการ)</option>
                                                <option value="Completed">Completed (สำเร็จ)</option>
                                                <option value="Canceled">Canceled (ยกเลิก)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small fw-bold text-muted mb-1">การชำระเงิน</label>
                                            <select id="paymentFilter" class="form-select border-0 shadow-sm">
                                                <option value="">-- ทั้งหมด --</option>
                                                <option value="Cash">Cash (เงินสด)</option>
                                                <option value="QR">QR Code</option>
                                                <option value="Credit">Credit Card</option>
                                                <option value="Banking">Mobile Banking</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="small fw-bold text-muted mb-1">ช่วงวันที่</label>
                                            <div class="input-group input-group-sm">
                                                <input type="date" id="dateStartFilter" class="form-control border-0 shadow-sm">
                                                <span class="input-group-text bg-transparent border-0">-</span>
                                                <input type="date" id="dateEndFilter" class="form-control border-0 shadow-sm">
                                            </div>
                                        </div>
                                        
                                        <?php if ($is_super_admin): // แอดมินกรองดูแต่ละร้านได้ ?>
                                        <div class="col-md-2">
                                            <label class="small fw-bold text-primary mb-1">ร้านค้า (Shop)</label>
                                            <select id="shopFilter" class="form-select border-primary border-opacity-10 shadow-sm">
                                                <option value="">-- ทุกร้าน --</option>
                                                <?php while($sh = $all_shops->fetch_assoc()): ?>
                                                    <option value="<?= $sh['shop_id'] ?>"><?= htmlspecialchars($sh['shop_name']) ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small fw-bold text-primary mb-1">สาขา (Branch)</label>
                                            <select id="branchFilter" class="form-select border-primary border-opacity-10 shadow-sm">
                                                <option value="">-- ทุกสาขา --</option>
                                                <?php mysqli_data_seek($all_branches, 0); while($br = $all_branches->fetch_assoc()): ?>
                                                    <option value="<?= $br['branch_id'] ?>" data-shop="<?= $br['shop_info_shop_id'] ?>"><?= htmlspecialchars($br['branch_name']) ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <?php endif; ?>

                                        <div class="col-12 text-end">
                                            <button class="btn btn-link btn-sm text-danger text-decoration-none" onclick="clearFilters()">ล้างค่ากรอง</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-5">
                                    <div class="input-group shadow-sm" style="border-radius: 10px; overflow: hidden;">
                                        <span class="input-group-text bg-white border-0"><i class="fas fa-search text-muted"></i></span>
                                        <input type="text" id="searchInput" class="form-control border-0" placeholder="ค้นหาเลขที่บิล, ชื่อลูกค้า, เบอร์โทร...">
                                    </div>
                                </div>
                            </div>

                            <div id="tableContainer">
                                <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function fetchSaleData(page = 1) {
            const params = new URLSearchParams({
                ajax: 1, page,
                search: document.getElementById('searchInput').value,
                status: document.getElementById('statusFilter').value,
                payment: document.getElementById('paymentFilter').value,
                date_start: document.getElementById('dateStartFilter').value,
                date_end: document.getElementById('dateEndFilter').value,
                shop_filter: document.getElementById('shopFilter')?.value || '',
                branch_filter: document.getElementById('branchFilter')?.value || ''
            });

            fetch(`sale_list.php?${params.toString()}`)
                .then(res => res.text()).then(data => document.getElementById('tableContainer').innerHTML = data);
        }

        function toggleFilter() {
            const card = document.getElementById('filterCard');
            const isHidden = card.style.display === 'none';
            card.style.display = isHidden ? 'block' : 'none';
            document.getElementById('filterBtnText').innerText = isHidden ? 'ปิดตัวกรอง' : 'ตัวกรอง';
        }

        function clearFilters() {
            ['statusFilter', 'paymentFilter', 'dateStartFilter', 'dateEndFilter', 'shopFilter', 'branchFilter', 'searchInput'].forEach(id => {
                const el = document.getElementById(id); if(el) el.value = '';
            });
            fetchSaleData(1);
        }

        document.getElementById('searchInput').addEventListener('input', () => fetchSaleData(1));
        ['statusFilter', 'paymentFilter', 'dateStartFilter', 'dateEndFilter', 'shopFilter', 'branchFilter'].forEach(id => document.getElementById(id)?.addEventListener('change', () => fetchSaleData(1)));

        document.addEventListener('click', e => {
            const link = e.target.closest('.ajax-page-link');
            if (link) { e.preventDefault(); fetchSaleData(link.dataset.page); }
            if (e.target.id === 'btnJumpPage') {
                const p = document.getElementById('jumpPageInput').value;
                if (p > 0) fetchSaleData(p);
            }
        });

        // กรองสาขาตามร้าน (Admin Only)
        document.getElementById('shopFilter')?.addEventListener('change', function() {
            const shopId = this.value;
            const branchSelect = document.getElementById('branchFilter');
            branchSelect.value = '';
            Array.from(branchSelect.options).forEach(opt => {
                if (opt.value === '') opt.style.display = 'block';
                else opt.style.display = (shopId === '' || opt.dataset.shop === shopId) ? 'block' : 'none';
            });
        });

        window.onload = () => fetchSaleData();
    </script>
</body>
</html>