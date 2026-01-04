<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'customer_list');

// [1] รับค่าพื้นฐานจาก Session
$current_shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// [2] ตรวจสอบสิทธิ์ Admin (Super Admin)
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

// [2.1] เพิ่มเติม: หา Branch ID ของพนักงานปัจจุบัน (เพื่อนำไปกรอง)
$current_user_branch_id = 0;
if (!$is_super_admin) {
    $sql_emp_branch = "SELECT branches_branch_id FROM employees WHERE users_user_id = ? LIMIT 1";
    if ($stmt_emp = $conn->prepare($sql_emp_branch)) {
        $stmt_emp->bind_param("i", $current_user_id);
        $stmt_emp->execute();
        $res_emp = $stmt_emp->get_result();
        if ($row_emp = $res_emp->fetch_assoc()) {
            $current_user_branch_id = $row_emp['branches_branch_id'];
        }
        $stmt_emp->close();
    }
}

// ==========================================
// [3] ส่วนประมวลผล AJAX (เรียกผ่าน Fetch API)
// ==========================================
if (isset($_GET['ajax'])) {
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
    $shop_filter = isset($_GET['shop_filter']) ? $_GET['shop_filter'] : '';
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;

    // สร้างเงื่อนไข Query
    $conditions = [];

    // --- จุดที่แก้ไขหลัก ---
    if (!$is_super_admin) {
        // กรณีไม่ใช่ Admin: กรองเฉพาะ "สาขาของฉัน" (branches_branch_id)
        // (ตาราง customers ต้องมีคอลัมน์ branches_branch_id)
        $conditions[] = "c.branches_branch_id = '$current_user_branch_id'";
    } elseif (!empty($shop_filter)) {
        // กรณี Admin: กรองตามร้านที่เลือก (shop_info_shop_id)
        $conditions[] = "c.shop_info_shop_id = '$shop_filter'";
    }
    // ----------------------

    if (!empty($search)) {
        $conditions[] = "(c.firstname_th LIKE '%$search%' OR c.lastname_th LIKE '%$search%' OR c.cs_phone_no LIKE '%$search%' OR c.cs_code LIKE '%$search%')";
    }

    $where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // Query นับจำนวนทั้งหมด
    $count_sql = "SELECT COUNT(*) as total FROM customers c $where_sql";
    $total_items = $conn->query($count_sql)->fetch_assoc()['total'];
    $total_pages = ceil($total_items / $limit);

    // Query ดึงข้อมูลลูกค้า
    $sql = "SELECT c.*, b.branch_name, s.shop_name, p.prefix_th
            FROM customers c
            LEFT JOIN branches b ON c.branches_branch_id = b.branch_id
            LEFT JOIN shop_info s ON c.shop_info_shop_id = s.shop_id
            LEFT JOIN prefixs p ON c.prefixs_prefix_id = p.prefix_id
            $where_sql 
            ORDER BY c.cs_id DESC 
            LIMIT $limit OFFSET $offset";
            
    $result = $conn->query($sql);
    
?>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-center" width="5%">#</th>
                    <th width="10%">รหัส</th>
                    <th width="30%">ชื่อ-นามสกุล</th>
                    <th width="20%">เบอร์โทรศัพท์</th>
                    <?php if ($is_super_admin): ?>
                        <th width="15%" class="text-center">สังกัดร้าน</th>
                    <?php endif; ?>
                    <th width="20%" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0):
                    $idx = $offset + 1;
                    while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center text-muted small fw-bold"><?= $idx++ ?></td>
                            <td class="text-center small"><span class="badge bg-light text-dark border">#<?= $row['cs_id'] ?></span></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['prefix_th'] . $row['firstname_th'] . ' ' . $row['lastname_th']) ?></div>
                                <small class="text-muted"><i class="bi bi-card-text me-1"></i><?= htmlspecialchars($row['cs_national_id'] ?: '-') ?></small>
                            </td>
                            <td><span class="text-primary fw-bold"><?= htmlspecialchars($row['cs_phone_no']) ?></span></td>
                            <?php if ($is_super_admin): ?>
                                <td class="text-center">
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-3">
                                        <i class="bi bi-shop me-1"></i> <?= htmlspecialchars($row['shop_name'] ?? 'ไม่ระบุ') ?>
                                    </span>
                                </td>
                            <?php endif; ?>
                            <td class="text-center">
                                <div class="btn-group gap-2">
                                    <a href="view_customer.php?id=<?= $row['cs_id'] ?>" class="btn btn-outline-info btn-sm border-0" title="ดูข้อมูล"><i class="bi bi-eye-fill fs-5"></i></a>
                                    <a href="edit_customer.php?id=<?= $row['cs_id'] ?>" class="btn btn-outline-warning btn-sm border-0" title="แก้ไข"><i class="bi bi-pencil-square fs-5"></i></a>
                                    <button onclick="confirmDelete(<?= $row['cs_id'] ?>, '<?= addslashes($row['firstname_th']) ?>')" class="btn btn-outline-danger btn-sm border-0" title="ลบ"><i class="bi bi-trash3-fill fs-5"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile;
                else: ?>
                    <tr>
                        <td colspan="<?= $is_super_admin ? 6 : 5 ?>" class="text-center py-5 text-muted italic">-- ไม่พบข้อมูลลูกค้า --</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center pagination-sm">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link ajax-page-link" href="#" data-page="1" title="หน้าแรกสุด"><i class="bi bi-chevron-double-left"></i></a>
                </li>
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link ajax-page-link" href="#" data-page="<?= $page - 1 ?>"><i class="bi bi-chevron-left"></i></a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                        <a class="page-link ajax-page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link ajax-page-link" href="#" data-page="<?= $page + 1 ?>" title="ถัดไป"><i class="bi bi-chevron-right"></i></a>
                </li>
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link ajax-page-link" href="#" data-page="<?= $total_pages ?>" title="หน้าสุดท้าย"><i class="bi bi-chevron-double-right"></i></a>
                </li>
            </ul>
        </nav>
        <div class="d-flex justify-content-center mt-2 gap-2 align-items-center">
            <div class="input-group input-group-sm" style="max-width: 150px;">
                <input type="number" id="jumpPageInput" class="form-control text-center" placeholder="ไปหน้า" min="1" max="<?= $total_pages ?>">
                <button class="btn btn-success" type="button" id="btnJumpPage">ไป</button>
            </div>
            <div class="small text-muted">หน้า <?= $page ?> / <?= $total_pages ?> (รวม <?= number_format($total_items) ?> คน)</div>
        </div>
    <?php endif; ?>
<?php
    exit();
}

// ดึงรายชื่อร้านค้าสำหรับตัวกรอง (Admin เท่านั้น)
$shops_res = $is_super_admin ? $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name ASC") : null;
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>จัดการข้อมูลลูกค้า - Mobile Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: #f8fafc;
        }

        .main-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .card-header-custom {
            background: linear-gradient(135deg, #198754 0%, #14532d 100%);
            padding: 1.5rem;
        }

        .card-header-custom h4 {
            color: #ffffff !important;
            font-weight: 600;
            margin-bottom: 0;
        }

        .pagination .page-link {
            border-radius: 8px;
            margin: 0 3px;
            color: #198754;
            font-weight: 600;
            border: none;
        }

        .pagination .page-item.active .page-link {
            background-color: #198754;
            color: white;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container py-2" style="max-width: 1200px;">

                    <div class="main-card card">
                        <div class="card-header-custom d-flex justify-content-between align-items-center">
                            <h4><i class="bi bi-people-fill me-2"></i>จัดการข้อมูลลูกค้า</h4>
                            <a href="add_customer.php" class="btn btn-light btn-sm fw-bold">
                                <i class="bi bi-person-plus-fill me-1"></i> เพิ่มลูกค้าใหม่
                            </a>
                        </div>

                        <div class="card-body p-4">
                            <div class="row g-3 mb-4">
                                <div class="col-md-6 col-lg-4">
                                    <div class="input-group shadow-sm">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                        <input type="text" id="searchInput" class="form-control border-start-0" placeholder="ชื่อ, เบอร์โทร หรือ รหัสลูกค้า...">
                                    </div>
                                </div>

                                <?php if ($is_super_admin): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="input-group shadow-sm">
                                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-shop"></i></span>
                                            <select id="shopFilter" class="form-select border-start-0">
                                                <option value="">-- แสดงลูกค้าทุกร้าน --</option>
                                                <?php while ($s = $shops_res->fetch_assoc()): ?>
                                                    <option value="<?= $s['shop_id'] ?>">ร้าน: <?= htmlspecialchars($s['shop_name']) ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                <?php endif; ?>
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

    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white border-0">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>ยืนยันการลบ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <p class="fs-5 mb-1">ต้องการลบข้อมูลลูกค้า <strong id="delName"></strong> ?</p>
                    <p class="text-danger small mb-0"><i class="bi bi-info-circle me-1"></i>การลบข้อมูลจะไม่สามารถกู้คืนได้</p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <a id="confirmDelBtn" href="#" class="btn btn-danger px-4 shadow-sm">ยืนยันการลบ</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function fetchCustomerData(page = 1) {
            const search = document.getElementById('searchInput').value;
            const shopFilter = document.getElementById('shopFilter')?.value || '';

            fetch(`customer_list.php?ajax=1&page=${page}&search=${encodeURIComponent(search)}&shop_filter=${shopFilter}`)
                .then(res => res.text())
                .then(data => document.getElementById('tableContainer').innerHTML = data);
        }

        document.getElementById('searchInput').addEventListener('input', () => fetchCustomerData(1));
        document.getElementById('shopFilter')?.addEventListener('change', () => fetchCustomerData(1));

        document.addEventListener('click', e => {
            if (e.target.classList.contains('ajax-page-link') || e.target.closest('.ajax-page-link')) {
                e.preventDefault();
                const link = e.target.classList.contains('ajax-page-link') ? e.target : e.target.closest('.ajax-page-link');
                fetchCustomerData(link.dataset.page);
            }
            if (e.target.id === 'btnJumpPage') {
                const p = document.getElementById('jumpPageInput').value;
                if (p > 0) fetchCustomerData(p);
            }
        });

        function confirmDelete(id, name) {
            document.getElementById('delName').innerText = name;
            document.getElementById('confirmDelBtn').href = `delete_customer.php?id=${id}`;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        window.onload = () => fetchCustomerData();
    </script>
</body>

</html>