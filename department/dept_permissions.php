<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'department'); // ตรวจสอบสิทธิ์เข้าถึง

// [1] รับค่าพื้นฐานจาก Session
$shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// ตรวจสอบสิทธิ์ Admin
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
// [2] ส่วนประมวลผล AJAX สำหรับบันทึกสิทธิ์
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'save_permissions') {
    $dept_id = (int)$_POST['dept_id'];
    $selected_perms = isset($_POST['perms']) ? $_POST['perms'] : [];

    mysqli_begin_transaction($conn);
    try {
        // ลบสิทธิ์เดิมออก
        $conn->query("DELETE FROM dept_permissions WHERE departments_dept_id = $dept_id");
        
        // เพิ่มสิทธิ์ใหม่
        if (!empty($selected_perms)) {
            $stmt = $conn->prepare("INSERT INTO dept_permissions (departments_dept_id, permissions_permission_id) VALUES (?, ?)");
            foreach ($selected_perms as $p_id) {
                $stmt->bind_param("ii", $dept_id, $p_id);
                $stmt->execute();
            }
        }
        mysqli_commit($conn);
        echo "success";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "error";
    }
    exit();
}

// ==========================================
// [3] ส่วนประมวลผล AJAX สำหรับดึงตารางข้อมูล
// ==========================================
if (isset($_GET['ajax'])) {
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20; // 1. แสดงรายการแค่ 20 รายการต่อหน้า
    $offset = ($page - 1) * $limit;

    // 3. กรองแสดงเฉพาะแผนกร้านตนเองและส่วนกลาง
    $conditions = [];
    if (!$is_super_admin) {
        $conditions[] = "(d.shop_info_shop_id = 0 OR d.shop_info_shop_id = '$shop_id')";
    }
    if (!empty($search)) {
        $conditions[] = "(d.dept_name LIKE '%$search%' OR sh.shop_name LIKE '%$search%')";
    }
    $where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // นับจำนวนเพื่อทำ Pagination
    $count_res = $conn->query("SELECT COUNT(DISTINCT d.dept_id) as total FROM departments d LEFT JOIN shop_info sh ON d.shop_info_shop_id = sh.shop_id $where_sql");
    $total_items = $count_res->fetch_assoc()['total'];
    $total_pages = ceil($total_items / $limit);

    // ดึงข้อมูลพร้อมสิทธิ์ที่ผูกไว้
    $sql = "SELECT d.*, sh.shop_name, 
            COUNT(dp.permissions_permission_id) as total_perms,
            GROUP_CONCAT(dp.permissions_permission_id SEPARATOR ',') as perm_ids
            FROM departments d
            LEFT JOIN shop_info sh ON d.shop_info_shop_id = sh.shop_id
            LEFT JOIN dept_permissions dp ON d.dept_id = dp.departments_dept_id
            $where_sql
            GROUP BY d.dept_id
            ORDER BY d.shop_info_shop_id ASC, d.dept_name ASC
            LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);
    ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-center" width="5%">#</th>
                    <th width="25%">ชื่อแผนก</th>
                    <th width="35%">สถานะการกำหนดสิทธิ์</th>
                    <th width="15%" class="text-center">สังกัด</th>
                    <th width="20%" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): 
                    $idx = $offset + 1;
                    while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td class="text-center fw-bold text-muted small"><?= $idx++ ?></td>
                    <td><div class="fw-bold text-dark"><?= htmlspecialchars($row['dept_name']) ?></div></td>
                    <td>
                        <?php if ($row['total_perms'] > 0): ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3">
                                <i class="bi bi-shield-check me-1"></i> กำหนดแล้ว <?= $row['total_perms'] ?> รายการ
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3">
                                <i class="bi bi-shield-slash me-1"></i> ยังไม่ได้กำหนด
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($row['shop_info_shop_id'] == 0): ?>
                            <span class="badge bg-dark opacity-75">ส่วนกลาง</span>
                        <?php else: ?>
                            <span class="text-primary small fw-bold"><?= htmlspecialchars($row['shop_name'] ?? 'ร้านค้า') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm" 
                                onclick="openPermModal(<?= $row['dept_id'] ?>, '<?= addslashes($row['dept_name']) ?>', '<?= $row['perm_ids'] ?>')">
                            <i class="bi bi-key-fill me-1"></i> ตั้งค่าสิทธิ์เข้าถึง
                        </button>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="5" class="text-center py-5 text-muted">-- ไม่พบข้อมูลแผนก --</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center pagination-sm">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link ajax-page-link" href="#" data-page="1" title="หน้าแรก"><i class="bi bi-chevron-double-left"></i></a>
            </li>
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link ajax-page-link" href="#" data-page="<?= $page - 1 ?>" title="ย้อนกลับ"><i class="bi bi-chevron-left"></i></a>
            </li>
            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
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
        <div class="small text-muted">หน้า <?= $page ?> / <?= $total_pages ?></div>
    </div>
    <?php endif; ?>
    <?php exit();
}

// [4] ดึงสิทธิ์ทั้งหมดสำหรับ Modal
$all_perms = $conn->query("SELECT permission_id, permission_name, permission_desc FROM permissions ORDER BY permission_name ASC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>กำหนดสิทธิ์รายแผนก - Mobile Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background-color: #f8fafc; font-family: 'Prompt', sans-serif; }
        .main-card { border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden; }
        .card-header-custom { background: linear-gradient(135deg, #198754 0%, #14532d 100%); padding: 1.5rem; }
        .card-header-custom h4 { color: #ffffff !important; font-weight: 600; margin: 0; }
        .perm-item { transition: 0.2s; padding: 10px; border-radius: 10px; border: 1px solid #f1f5f9; margin-bottom: 5px; cursor: pointer; display: flex; align-items: start; }
        .perm-item:hover { background-color: #f0fdf4; border-color: #198754; }
        .pagination .page-link { border-radius: 8px; margin: 0 3px; color: #198754; font-weight: 600; border: none; }
        .pagination .page-item.active .page-link { background-color: #198754; color: white; }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container" style="max-width: 1000px;">
                    <div class="main-card card">
                        <div class="card-header-custom d-flex justify-content-between align-items-center">
                            <h4><i class="bi bi-shield-lock me-2"></i>จัดการสิทธิ์ตามแผนก</h4>
                        </div>
                        <div class="card-body p-4">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="input-group shadow-sm">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                        <input type="text" id="searchInput" class="form-control border-start-0" placeholder="ค้นหาแผนกในร้านของคุณ...">
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

    <div class="modal fade" id="permModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-shield-check me-2"></i>ตั้งค่าสิทธิ์: <span id="modalDeptName" class="fw-bold"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" style="max-height: 65vh; overflow-y: auto;">
                    <form id="permForm">
                        <input type="hidden" name="dept_id" id="modalDeptId">
                        <input type="hidden" name="action" value="save_permissions">
                        <div class="row g-2">
                            <?php while($p = $all_perms->fetch_assoc()): ?>
                            <div class="col-md-6">
                                <label class="perm-item w-100" for="p_<?= $p['permission_id'] ?>">
                                    <input class="form-check-input me-3 mt-1" type="checkbox" name="perms[]" 
                                           value="<?= $p['permission_id'] ?>" id="p_<?= $p['permission_id'] ?>">
                                    <div>
                                        <div class="fw-bold text-dark small mb-0"><?= htmlspecialchars($p['permission_name']) ?></div>
                                        <div class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($p['permission_desc'] ?: '-') ?></div>
                                    </div>
                                </label>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-success rounded-pill px-4 shadow" onclick="savePerms()">บันทึกสิทธิ์</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadTable(page = 1, search = '') {
            fetch(`dept_permissions.php?ajax=1&page=${page}&search=${encodeURIComponent(search)}`)
                .then(res => res.text()).then(html => document.getElementById('tableContainer').innerHTML = html);
        }

        // ค้นหาแบบ Real-time
        document.getElementById('searchInput').addEventListener('input', e => loadTable(1, e.target.value));

        document.addEventListener('click', e => {
            if (e.target.classList.contains('ajax-page-link') || e.target.closest('.ajax-page-link')) {
                e.preventDefault();
                const link = e.target.classList.contains('ajax-page-link') ? e.target : e.target.closest('.ajax-page-link');
                loadTable(link.dataset.page, document.getElementById('searchInput').value);
            }
            if (e.target.id === 'btnJumpPage') {
                const p = document.getElementById('jumpPageInput').value;
                if (p > 0) loadTable(p, document.getElementById('searchInput').value);
            }
        });

        function openPermModal(id, name, currentIds) {
            document.getElementById('modalDeptId').value = id;
            document.getElementById('modalDeptName').innerText = name;
            const ids = currentIds.split(',');
            document.querySelectorAll('#permForm input[type="checkbox"]').forEach(cb => {
                cb.checked = ids.includes(cb.value);
            });
            new bootstrap.Modal(document.getElementById('permModal')).show();
        }

        function savePerms() {
            const formData = new FormData(document.getElementById('permForm'));
            fetch('dept_permissions.php', { method: 'POST', body: formData })
                .then(res => res.text()).then(text => {
                    if (text.trim() === 'success') { loadTable(); bootstrap.Modal.getInstance(document.getElementById('permModal')).hide(); }
                    else { alert('เกิดข้อผิดพลาด'); }
                });
        }

        window.onload = () => loadTable();
    </script>
</body>
</html>