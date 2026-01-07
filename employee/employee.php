<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'employee');

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
    $dept_f = isset($_GET['dept']) ? $_GET['dept'] : '';
    $shop_f = isset($_GET['shop_filter']) ? $_GET['shop_filter'] : '';
    $branch_f = isset($_GET['branch_filter']) ? $_GET['branch_filter'] : '';
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20; // 2. แสดงรายการ 20 รายการต่อหน้า
    $offset = ($page - 1) * $limit;

    // 3. กรองตามสิทธิ์ (เห็นแค่สาขาตัวเอง / แอดมินเห็นทั้งหมดหรือตามกรอง)
    $conditions = [];
    if (!$is_super_admin) {
        $conditions[] = "e.branches_branch_id = '$branch_id'";
    } else {
        if (!empty($branch_f)) $conditions[] = "e.branches_branch_id = '$branch_f'";
        elseif (!empty($shop_f)) $conditions[] = "b.shop_info_shop_id = '$shop_f'";
    }

    if (!empty($search)) {
        $conditions[] = "(e.emp_code LIKE '%$search%' OR e.firstname_th LIKE '%$search%' OR e.lastname_th LIKE '%$search%')";
    }
    if (!empty($status_f)) $conditions[] = "e.emp_status = '$status_f'";
    if (!empty($dept_f)) $conditions[] = "e.departments_dept_id = '$dept_f'";

    $where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // นับจำนวนทั้งหมดเพื่อคำนวณหน้า
    $count_sql = "SELECT COUNT(*) as total FROM employees e 
                  LEFT JOIN branches b ON e.branches_branch_id = b.branch_id 
                  $where_sql";
    $total_items = $conn->query($count_sql)->fetch_assoc()['total'];
    $total_pages = ceil($total_items / $limit);

    // ดึงข้อมูลพนักงาน
    $sql = "SELECT e.*, p.prefix_th, d.dept_name, b.branch_name, sh.shop_name 
            FROM employees e
            LEFT JOIN prefixs p ON e.prefixs_prefix_id = p.prefix_id
            LEFT JOIN departments d ON e.departments_dept_id = d.dept_id
            LEFT JOIN branches b ON e.branches_branch_id = b.branch_id
            LEFT JOIN shop_info sh ON b.shop_info_shop_id = sh.shop_id
            $where_sql 
            ORDER BY e.emp_status ASC, e.emp_id DESC 
            LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);
    ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-center" width="5%">#</th>
                    <th width="8%">รูป</th>
                    <th width="12%">รหัสพนักงาน</th>
                    <th width="22%">ชื่อ-นามสกุล</th>
                    <th width="15%">แผนก</th>
                    <?php if ($is_super_admin): // 3. เพิ่มคอลัมน์ระบุสาขา/ร้าน ?>
                        <th width="15%" class="text-center">สาขา/ร้าน</th>
                    <?php endif; ?>
                    <th width="10%" class="text-center">สถานะ</th>
                    <th width="13%" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): $idx = $offset + 1; while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td class="text-center text-muted fw-bold"><?= $idx++ ?></td>
                    <td class="text-center">
                        <img src="<?= !empty($row['emp_image']) ? '../uploads/employees/'.$row['emp_image'] : '../assets/img/user-avatar.png' ?>" 
                             class="rounded-circle border" style="width: 38px; height: 38px; object-fit: cover;">
                    </td>
                    <td class="fw-bold text-primary"><?= htmlspecialchars($row['emp_code']) ?></td>
                    <td>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['prefix_th'].$row['firstname_th'].' '.$row['lastname_th']) ?></div>
                        <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= $row['emp_phone_no'] ?></div>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['dept_name'] ?? '-') ?></span></td>
                    <?php if ($is_super_admin): ?>
                        <td class="text-center small">
                            <div class="fw-bold text-success"><?= htmlspecialchars($row['shop_name'] ?? '-') ?></div>
                            <div class="text-muted fs-xs"><?= htmlspecialchars($row['branch_name'] ?? '-') ?></div>
                        </td>
                    <?php endif; ?>
                    <td class="text-center">
                        <span class="badge <?= $row['emp_status'] == 'Active' ? 'bg-success' : 'bg-secondary' ?> bg-opacity-10 text-dark border rounded-pill px-3">
                            <?= $row['emp_status'] == 'Active' ? 'ทำงานอยู่' : 'ลาออก' ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <div class="btn-group gap-1">
                            <a href="view_employee.php?id=<?= $row['emp_id'] ?>" class="btn btn-outline-info btn-sm border-0" title="ดูรายละเอียด"><i class="bi bi-eye"></i></a>
                            <a href="edit_employee.php?id=<?= $row['emp_id'] ?>" class="btn btn-outline-warning btn-sm border-0" title="แก้ไข"><i class="bi bi-pencil-square"></i></a>
                            <button onclick="confirmDelete(<?= $row['emp_id'] ?>, '<?= addslashes($row['firstname_th']) ?>')" class="btn btn-outline-danger btn-sm border-0" title="ลบ"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="<?= $is_super_admin ? 8 : 7 ?>" class="text-center py-5 text-muted">-- ไม่พบข้อมูลพนักงาน --</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav class="mt-4"><ul class="pagination justify-content-center pagination-sm">
        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="1"><i class="bi bi-chevron-double-left"></i></a></li>
        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="<?= $page - 1 ?>"><i class="bi bi-chevron-left"></i></a></li>
        <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="<?= $page + 1 ?>"><i class="bi bi-chevron-right"></i></a></li>
        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="<?= $total_pages ?>"><i class="bi bi-chevron-double-right"></i></a></li>
    </ul></nav>
    <div class="d-flex justify-content-center mt-2 gap-2 align-items-center">
        <div class="input-group input-group-sm" style="max-width: 150px;">
            <input type="number" id="jumpPageInput" class="form-control text-center" placeholder="ไปหน้า" min="1" max="<?= $total_pages ?>">
            <button class="btn btn-success" type="button" id="btnJumpPage">ไป</button>
        </div>
        <div class="small text-muted">หน้า <?= $page ?> / <?= $total_pages ?> (ทั้งหมด <?= number_format($total_items) ?> คน)</div>
    </div>
    <?php endif; exit(); }

// [4] โหลดข้อมูลสำหรับตัวกรอง (Dropdown)
$depts_res = $conn->query("SELECT dept_id, dept_name FROM departments WHERE shop_info_shop_id = '$shop_id' ORDER BY dept_name ASC");
if ($is_super_admin) {
    $all_shops = $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name ASC");
    $all_branches = $conn->query("SELECT branch_id, branch_name, shop_info_shop_id FROM branches ORDER BY branch_name ASC");
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการพนักงาน - Mobile Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: #f0fdf4;
            color: #333;
        }

        /* การ์ดหลัก */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        /* หัวการ์ด */
        .card-header {
            background: linear-gradient(135deg, #2dd4bf 0%, #15803d 100%);
            color: white;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
            padding: 1.25rem 1.5rem;
            border-bottom: none;
        }

        .card-header h4 {
            font-weight: 600;
            margin-bottom: 0;
        }

        .card-header .btn-light {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.7);
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .card-header .btn-light:hover {
            background-color: rgba(255, 255, 255, 0.3);
            border-color: white;
        }

        /* ปุ่มค้นหา */
        .btn-primary {
            background-color: #15803d;
            border-color: #15803d;
        }

        .btn-primary:hover {
            background-color: #166534;
            border-color: #166534;
        }

        /* ตาราง */
        .table thead {
            background-color: #f0fdf4;
            color: #14532d;
            font-weight: 600;
        }

        .table th {
            border-bottom: 2px solid #a7f3d0 !important;
            padding: 1rem 0.75rem;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table td {
            padding: 0.85rem 0.75rem;
            border-bottom: 1px solid #e6fcf5;
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background-color: #e6fcf5;
            color: #065f46;
        }

        /* รูปโปรไฟล์ */
        .profile-pic-sm {
            width: 45px;
            height: 45px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #a7f3d0;
        }

        .profile-icon {
            font-size: 2.5rem;
            color: #d1d5db;
        }

        /* --- CSS สำหรับ Dropdown สถานะ --- */
        .status-select {
            font-size: 0.8rem;
            font-weight: 500;
            border-radius: 50rem;
            padding: 0.3em 0.8em;
            border: 1px solid transparent;
            background-position: right 0.5rem center;
            padding-right: 1.75rem;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            cursor: pointer;
            width: 120px;
        }

        .status-select:focus {
            box-shadow: 0 0 0 0.2rem rgba(21, 128, 61, 0.15);
        }

        /* สีตอน Active */
        .status-select.status-select-active {
            background-color: #d1fae5;
            color: #065f46;
            border-color: #a7f3d0;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23065f46' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        }

        /* สีตอน Resigned */
        .status-select.status-select-resigned {
            background-color: #f3f4f6;
            color: #4b5563;
            border-color: #d1d5db;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%234b5563' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        }

        /* ไอคอน จัดการ */
        .action-icons a {
            margin: 0 5px;
            font-size: 1.1rem;
            text-decoration: none;
            opacity: 0.7;
            transition: opacity 0.2s ease;
        }

        .action-icons a:hover {
            opacity: 1;
        }

        .action-icons .fa-eye {
            color: #0e7490;
        }

        .action-icons .fa-pencil {
            color: #f59e0b;
        }

        .action-icons .fa-trash-can {
            color: #ef4444;
        }

        /* Alert */
        .custom-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 300px;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.3s ease forwards;
            z-index: 1050;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .alert-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }

        .alert-error {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            color: white;
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container" style="max-width: 1300px;">
                    
                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom-0">
                            <h4 class="mb-0 text-success fw-bold"><i class="bi bi-people-fill me-2"></i>ระบบจัดการรายชื่อพนักงาน</h4>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-success btn-sm fw-bold px-3 text-white" onclick="toggleFilter()">
                                    <i class="bi bi-filter me-1"></i> <span id="filterBtnText">กรองพนักงาน</span>
                                </button>
                                <a href="add_employee.php" class="btn btn-success btn-sm fw-bold px-3">
                                    <i class="bi bi-person-plus-fill me-1"></i> เพิ่มพนักงานใหม่
                                </a>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            <div class="card bg-light border-0 mb-4" id="filterCard" style="display: none; border-radius: 15px;">
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="small fw-bold text-muted mb-1">แผนก</label>
                                            <select id="deptFilter" class="form-select border-0 shadow-sm">
                                                <option value="">-- ทุกแผนก --</option>
                                                <?php while($d = $depts_res->fetch_assoc()): ?>
                                                    <option value="<?= $d['dept_id'] ?>"><?= htmlspecialchars($d['dept_name']) ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small fw-bold text-muted mb-1">สถานะ</label>
                                            <select id="statusFilter" class="form-select border-0 shadow-sm">
                                                <option value="">-- ทั้งหมด --</option>
                                                <option value="Active">ทำงานอยู่ (Active)</option>
                                                <option value="Resigned">ลาออก (Resigned)</option>
                                            </select>
                                        </div>

                                        <?php if ($is_super_admin): // ผู้ดูแลกรองดูแต่ละร้านได้ ?>
                                        <div class="col-md-3">
                                            <label class="small fw-bold text-primary mb-1">ร้านค้า (Shop)</label>
                                            <select id="shopFilter" class="form-select border-primary border-opacity-25 shadow-sm">
                                                <option value="">-- ทุกร้าน --</option>
                                                <?php while($sh = $all_shops->fetch_assoc()): ?>
                                                    <option value="<?= $sh['shop_id'] ?>"><?= htmlspecialchars($sh['shop_name']) ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small fw-bold text-primary mb-1">สาขา (Branch)</label>
                                            <select id="branchFilter" class="form-select border-primary border-opacity-25 shadow-sm">
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
                                        <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                                        <input type="text" id="searchInput" class="form-control border-0" placeholder="ค้นหารหัส, ชื่อ, นามสกุล...">
                                    </div>
                                </div>
                            </div>

                            <div id="tableContainer">
                                <div class="text-center py-5"><div class="spinner-border text-success"></div></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white border-0">
                    <h5 class="modal-title fw-bold text-white"><i class="bi bi-exclamation-triangle me-2"></i>ยืนยันการลบ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <p class="fs-5 mb-1">ต้องการลบข้อมูลพนักงาน <strong id="delName"></strong> ?</p>
                    <p class="text-danger small mb-0">การลบข้อมูลพนักงานจะทำให้ประวัติบางส่วนหายไป</p>
                </div>
                <div class="modal-footer border-0 justify-content-center bg-light">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <a id="confirmDelBtn" href="delete_employee.php" class="btn btn-danger rounded-pill px-4 shadow-sm">ยืนยันการลบ</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function fetchEmpData(page = 1) {
            const params = new URLSearchParams({
                ajax: 1, page,
                search: document.getElementById('searchInput').value,
                status: document.getElementById('statusFilter').value,
                dept: document.getElementById('deptFilter').value,
                shop_filter: document.getElementById('shopFilter')?.value || '',
                branch_filter: document.getElementById('branchFilter')?.value || ''
            });

            fetch(`employee.php?${params.toString()}`)
                .then(res => res.text()).then(data => document.getElementById('tableContainer').innerHTML = data);
        }

        function toggleFilter() {
            const card = document.getElementById('filterCard');
            const isHidden = card.style.display === 'none';
            card.style.display = isHidden ? 'block' : 'none';
            document.getElementById('filterBtnText').innerText = isHidden ? 'ปิดตัวกรอง' : 'กรองพนักงาน';
        }

        function clearFilters() {
            ['statusFilter', 'deptFilter', 'shopFilter', 'branchFilter', 'searchInput'].forEach(id => {
                const el = document.getElementById(id); if(el) el.value = '';
            });
            fetchEmpData(1);
        }

        document.getElementById('searchInput').addEventListener('input', () => fetchEmpData(1));
        ['statusFilter', 'deptFilter', 'shopFilter', 'branchFilter'].forEach(id => document.getElementById(id)?.addEventListener('change', () => fetchEmpData(1)));

        document.addEventListener('click', e => {
            const link = e.target.closest('.ajax-page-link');
            if (link) { e.preventDefault(); fetchEmpData(link.dataset.page); }
            if (e.target.id === 'btnJumpPage') {
                const p = document.getElementById('jumpPageInput').value;
                if (p > 0) fetchEmpData(p);
            }
        });

        function confirmDelete(id, name) {
            document.getElementById('delName').innerText = name;
            document.getElementById('confirmDelBtn').href = `delete_employee.php?id=${id}`;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
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

        window.onload = () => fetchEmpData();
    </script>
</body>
</html>