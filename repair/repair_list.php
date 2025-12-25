<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'repair_list');

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
// [3] ส่วนประมวลผล AJAX (เรียกผ่าน Fetch API)
// ==========================================
if (isset($_GET['ajax'])) {
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
    $status_f = isset($_GET['status']) ? $_GET['status'] : '';
    $shop_f = isset($_GET['shop_filter']) ? $_GET['shop_filter'] : '';
    $branch_f = isset($_GET['branch_filter']) ? $_GET['branch_filter'] : '';

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20; // 2. แสดงรายการ 20 รายการต่อหน้า
    $offset = ($page - 1) * $limit;

    // 3. กรองตามสิทธิ์ (เห็นแค่สาขาตัวเอง / แอดมินเห็นทั้งหมดหรือตามกรอง)
    $conditions = [];
    if (!$is_super_admin) {
        $conditions[] = "r.branches_branch_id = '$branch_id'";
    } else {
        if (!empty($branch_f)) $conditions[] = "r.branches_branch_id = '$branch_f'";
        elseif (!empty($shop_f)) $conditions[] = "b.shop_info_shop_id = '$shop_f'";
    }

    if (!empty($search)) {
        $conditions[] = "(r.repair_id LIKE '%$search%' OR c.firstname_th LIKE '%$search%' OR r.device_description LIKE '%$search%')";
    }
    if (!empty($status_f)) $conditions[] = "r.repair_status = '$status_f'";

    $where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // นับจำนวนหน้า
    $count_sql = "SELECT COUNT(*) as total FROM repairs r 
                  LEFT JOIN customers c ON r.customers_cs_id = c.cs_id 
                  LEFT JOIN branches b ON r.branches_branch_id = b.branch_id 
                  $where_sql";
    $total_items = $conn->query($count_sql)->fetch_assoc()['total'];
    $total_pages = ceil($total_items / $limit);

    // ดึงข้อมูลแจ้งซ่อม
    $sql = "SELECT r.*, c.firstname_th, c.lastname_th, b.branch_name, sh.shop_name, e.firstname_th as tech_name 
            FROM repairs r
            LEFT JOIN customers c ON r.customers_cs_id = c.cs_id
            LEFT JOIN branches b ON r.branches_branch_id = b.branch_id
            LEFT JOIN shop_info sh ON b.shop_info_shop_id = sh.shop_id
            LEFT JOIN employees e ON r.assigned_employee_id = e.emp_id
            $where_sql ORDER BY r.repair_id DESC LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);
?>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-center" width="8%">เลขที่ใบซ่อม</th>
                    <th width="20%">ข้อมูลลูกค้า / อุปกรณ์</th>
                    <th width="12%" class="text-center">สถานะ</th>
                    <th width="12%">วันที่รับ</th>
                    <th width="10%" class="text-end">ค่าซ่อมประเมิน</th>
                    <?php if ($is_super_admin): // 3. เพิ่มคอลัมน์ระบุสาขา/ร้าน 
                    ?>
                        <th width="15%" class="text-center">สาขา/ร้าน</th>
                    <?php endif; ?>
                    <th width="13%" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): while ($row = $result->fetch_assoc()):
                        $status_badge = match ($row['repair_status']) {
                            'รับเครื่อง' => 'bg-info',
                            'ประเมิน' => 'bg-primary',
                            'รออะไหล่' => 'bg-warning text-dark',
                            'กำลังซ่อม' => 'bg-info text-dark',
                            'ซ่อมเสร็จ' => 'bg-success',
                            'ส่งมอบ' => 'bg-secondary',
                            'ยกเลิก' => 'bg-danger',
                            default => 'bg-secondary'
                        };
                ?>
                        <tr>
                            <td class="text-center fw-bold">#<?= $row['repair_id'] ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['firstname_th'] . ' ' . $row['lastname_th']) ?></div>
                                <div class="small text-muted text-truncate" style="max-width: 200px;"><?= htmlspecialchars($row['device_description']) ?></div>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= $status_badge ?> px-3 rounded-pill"><?= $row['repair_status'] ?></span>
                            </td>
                            <td class="small"><?= date('d/m/Y H:i', strtotime($row['create_at'])) ?></td>
                            <td class="text-end fw-bold text-success">฿<?= number_format($row['estimated_cost'], 2) ?></td>
                            <?php if ($is_super_admin): ?>
                                <td class="text-center small">
                                    <div class="fw-bold text-primary"><?= htmlspecialchars($row['shop_name'] ?? '-') ?></div>
                                    <div class="text-muted"><?= htmlspecialchars($row['branch_name'] ?? '-') ?></div>
                                </td>
                            <?php endif; ?>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="view_repair.php?id=<?= $row['repair_id'] ?>" class="btn btn-outline-info btn-sm border-0" title="ดูรายละเอียด"><i class="bi bi-eye-fill fs-5"></i></a>
                                    <a href="edit_repair.php?id=<?= $row['repair_id'] ?>" class="btn btn-outline-warning btn-sm border-0" title="อัปเดตสถานะ/แก้ไข"><i class="bi bi-pencil-square fs-5"></i></a>
                                    <?php if ($row['repair_status'] == 'รับเครื่อง'): ?>
                                        <button onclick="confirmCancel(<?= $row['repair_id'] ?>)" class="btn btn-outline-danger btn-sm border-0" title="ยกเลิก"><i class="bi bi-x-circle-fill fs-5"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile;
                else: ?>
                    <tr>
                        <td colspan="<?= $is_super_admin ? 7 : 6 ?>" class="text-center py-5 text-muted">-- ไม่พบข้อมูลรายการซ่อม --</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center pagination-sm">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="1"><i class="bi bi-chevron-double-left"></i></a></li>
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="<?= $page - 1 ?>"><i class="bi bi-chevron-left"></i></a></li>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="<?= $page + 1 ?>"><i class="bi bi-chevron-right"></i></a></li>
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="<?= $total_pages ?>"><i class="bi bi-chevron-double-right"></i></a></li>
            </ul>
        </nav>
        <div class="d-flex justify-content-center mt-2 gap-2 align-items-center">
            <div class="input-group input-group-sm" style="max-width: 150px;">
                <input type="number" id="jumpPageInput" class="form-control text-center" placeholder="ไปหน้า" min="1" max="<?= $total_pages ?>">
                <button class="btn btn-success" type="button" id="btnJumpPage">ไป</button>
            </div>
            <div class="small text-muted">หน้า <?= $page ?> / <?= $total_pages ?> (รวม <?= number_format($total_items) ?> งาน)</div>
        </div>
<?php endif;
    exit();
}

// [4] โหลดข้อมูลสำหรับตัวกรอง
if ($is_super_admin) {
    $all_shops = $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name ASC");
    $all_branches = $conn->query("SELECT branch_id, branch_name, shop_info_shop_id FROM branches ORDER BY branch_name ASC");
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>รายการแจ้งซ่อม - Mobile Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
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

            .filter-card .row>[class*='col-'] {
                margin-bottom: 10px;
            }

            .table th,
            .table td {
                padding: 0.6rem 0.5rem;
                /* ลด Padding ด้านข้าง */
                font-size: 0.8rem;
                /* ลดขนาด Font เล็กน้อย */
                white-space: nowrap;
                /* ป้องกันไม่ให้ข้อความยาวๆ ขึ้นบรรทัดใหม่ในตาราง Responsive */
            }

            .table td:last-child {
                flex-direction: column;
                /* เรียงปุ่ม Action เป็นแนวตั้งบน Mobile */
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
                <div class="container py-2" style="max-width: 1300px;">

                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h4 class="mb-0 text-success fw-bold"><i class="bi bi-tools me-2"></i>รายการข้อมูลงานซ่อม</h4>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-success btn-sm fw-bold px-3" onclick="toggleFilter()">
                                    <i class="bi bi-filter me-1"></i> <span id="filterBtnText">ตัวกรอง</span>
                                </button>
                                <a href="add_repair.php" class="btn btn-success btn-sm fw-bold px-3">
                                    <i class="bi bi-plus-circle me-1"></i> รับงานซ่อมใหม่
                                </a>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            <div class="card bg-light border-0 mb-4" id="filterCard" style="display: none; border-radius: 15px;">
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="small fw-bold text-muted mb-1">สถานะงานซ่อม</label>
                                            <select id="statusFilter" class="form-select border-0 shadow-sm">
                                                <option value="">-- ทุกสถานะ --</option>
                                                <option value="รับเครื่อง">รับเครื่อง</option>
                                                <option value="ประเมิน">ประเมิน</option>
                                                <option value="รออะไหล่">รออะไหล่</option>
                                                <option value="กำลังซ่อม">กำลังซ่อม</option>
                                                <option value="ซ่อมเสร็จ">ซ่อมเสร็จ</option>
                                                <option value="ส่งมอบ">ส่งมอบ</option>
                                                <option value="ยกเลิก">ยกเลิก</option>
                                            </select>
                                        </div>

                                        <?php if ($is_super_admin): // แอดมินกรองดูแต่ละร้านได้ 
                                        ?>
                                            <div class="col-md-3">
                                                <label class="small fw-bold text-primary mb-1">ร้านค้า (Shop)</label>
                                                <select id="shopFilter" class="form-select border-primary border-opacity-25 shadow-sm">
                                                    <option value="">-- ทุกร้าน --</option>
                                                    <?php while ($sh = $all_shops->fetch_assoc()): ?>
                                                        <option value="<?= $sh['shop_id'] ?>"><?= htmlspecialchars($sh['shop_name']) ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="small fw-bold text-primary mb-1">สาขา (Branch)</label>
                                                <select id="branchFilter" class="form-select border-primary border-opacity-25 shadow-sm">
                                                    <option value="">-- ทุกสาขา --</option>
                                                    <?php mysqli_data_seek($all_branches, 0);
                                                    while ($br = $all_branches->fetch_assoc()): ?>
                                                        <option value="<?= $br['branch_id'] ?>" data-shop="<?= $br['shop_info_shop_id'] ?>"><?= htmlspecialchars($br['branch_name']) ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        <?php endif; ?>

                                        <div class="col-md-3 d-flex align-items-end">
                                            <button class="btn btn-link btn-sm text-danger text-decoration-none" onclick="clearFilters()">ล้างค่ากรอง</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-5">
                                    <div class="input-group shadow-sm" style="border-radius: 10px; overflow: hidden;">
                                        <span class="input-group-text bg-white border-0"><i class="bi bi-search"></i></span>
                                        <input type="text" id="searchInput" class="form-control border-0" placeholder="ค้นหาเลขที่ใบซ่อม, ชื่อลูกค้า, อาการ...">
                                    </div>
                                </div>
                            </div>

                            <div id="tableContainer">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-success"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form id="cancelForm" method="POST" action="cancel_repair_logic.php">
                    <div class="modal-header bg-danger text-white border-0">
                        <h5 class="modal-title fw-bold"><i class="bi bi-x-circle me-2"></i>ยกเลิกรายการซ่อม</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p class="mb-3">ต้องการยกเลิกใบซ่อม <strong id="cancelRepairIdText"></strong> หรือไม่?</p>
                        <textarea class="form-control border-0 bg-light" name="cancel_reason" rows="3" required placeholder="เหตุผลในการยกเลิก..."></textarea>
                        <input type="hidden" name="repair_id" id="cancelRepairIdInput">
                    </div>
                    <div class="modal-footer border-0 bg-light">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-danger rounded-pill px-4 shadow">ยืนยันยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function fetchRepairData(page = 1) {
            const params = new URLSearchParams({
                ajax: 1,
                page,
                search: document.getElementById('searchInput').value,
                status: document.getElementById('statusFilter').value,
                shop_filter: document.getElementById('shopFilter')?.value || '',
                branch_filter: document.getElementById('branchFilter')?.value || ''
            });

            fetch(`repair_list.php?${params.toString()}`)
                .then(res => res.text()).then(data => document.getElementById('tableContainer').innerHTML = data);
        }

        function toggleFilter() {
            const card = document.getElementById('filterCard');
            const isHidden = card.style.display === 'none';
            card.style.display = isHidden ? 'block' : 'none';
            document.getElementById('filterBtnText').innerText = isHidden ? 'ปิดตัวกรอง' : 'ตัวกรอง';
        }

        function clearFilters() {
            ['statusFilter', 'shopFilter', 'branchFilter', 'searchInput'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            fetchRepairData(1);
        }

        document.getElementById('searchInput').addEventListener('input', () => fetchRepairData(1));
        ['statusFilter', 'shopFilter', 'branchFilter'].forEach(id => document.getElementById(id)?.addEventListener('change', () => fetchRepairData(1)));

        document.addEventListener('click', e => {
            const link = e.target.closest('.ajax-page-link');
            if (link) {
                e.preventDefault();
                fetchRepairData(link.dataset.page);
            }
            if (e.target.id === 'btnJumpPage') {
                const p = document.getElementById('jumpPageInput').value;
                if (p > 0) fetchRepairData(p);
            }
        });

        function confirmCancel(id) {
            document.getElementById('cancelRepairIdText').innerText = '#' + id;
            document.getElementById('cancelRepairIdInput').value = id;
            new bootstrap.Modal(document.getElementById('cancelModal')).show();
        }

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

        window.onload = () => fetchRepairData();
    </script>
</body>

</html>