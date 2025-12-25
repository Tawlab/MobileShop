<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'prename');

// [1] รับค่าพื้นฐานจาก Session
$shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// [2] ตรวจสอบสิทธิ์ Admin และ Permission 'centralinf'
$is_super_admin = false;
$has_central_perm = false;

$check_user_sql = "SELECT r.role_name, p.permission_name 
                   FROM users u
                   JOIN user_roles ur ON u.user_id = ur.users_user_id
                   JOIN roles r ON ur.roles_role_id = r.role_id
                   LEFT JOIN role_permissions rp ON r.role_id = rp.roles_role_id
                   LEFT JOIN permissions p ON rp.permissions_permission_id = p.permission_id
                   WHERE u.user_id = ?";

if ($stmt_user = mysqli_prepare($conn, $check_user_sql)) {
    mysqli_stmt_bind_param($stmt_user, "i", $current_user_id);
    mysqli_stmt_execute($stmt_user);
    $res_user = mysqli_stmt_get_result($stmt_user);
    while ($row = mysqli_fetch_assoc($res_user)) {
        if ($row['role_name'] === 'Admin') { 
            $is_super_admin = true;
        }
        if ($row['permission_name'] === 'centralinf') {
            $has_central_perm = true;
        }
    }
    mysqli_stmt_close($stmt_user);
}

// ==========================================
// [3] ส่วนประมวลผล AJAX (จะทำงานเมื่อมีการส่งค่า $_GET['ajax'])
// ==========================================
if (isset($_GET['ajax'])) {
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10; 
    $offset = ($page - 1) * $limit;

    // สร้างเงื่อนไข WHERE แบบ Hybrid
    $conditions = [];
    if (!$is_super_admin) {
        $conditions[] = "(p.shop_info_shop_id = 0 OR p.shop_info_shop_id = '$shop_id')";
    }
    if (!empty($search)) {
        $conditions[] = "(p.prefix_th LIKE '%$search%' OR p.prefix_en LIKE '%$search%' OR p.prefix_th_abbr LIKE '%$search%')";
    }
    $where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // นับจำนวนหน้า
    $count_res = $conn->query("SELECT COUNT(*) as total FROM prefixs p $where_sql");
    $total_items = $count_res->fetch_assoc()['total'];
    $total_pages = ceil($total_items / $limit);

    // ดึงข้อมูลพร้อมชื่อร้าน
    $sql = "SELECT p.*, s.shop_name 
            FROM prefixs p 
            LEFT JOIN shop_info s ON p.shop_info_shop_id = s.shop_id 
            $where_sql 
            ORDER BY p.shop_info_shop_id ASC, p.prefix_id ASC 
            LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);
    ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-center" width="5%">#</th>
                    <th width="10%">รหัส</th>
                    <th width="30%">ชื่อคำนำหน้า (ย่อ)</th>
                    <th width="20%" class="text-center">ผู้เพิ่ม/สังกัด</th>
                    <th width="15%" class="text-center">สถานะ</th>
                    <th width="20%" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): 
                    $idx = $offset + 1;
                    while ($row = $result->fetch_assoc()): 
                        // ตรวจสอบสิทธิ์การแก้ไขสถานะ
                        $can_edit = ($row['shop_info_shop_id'] == $shop_id || $has_central_perm);
                        $clickable = $can_edit ? 'toggle-status' : 'readonly-status';
                ?>
                <tr>
                    <td class="text-center fw-bold text-muted small"><?= $idx++ ?></td>
                    <td class="text-center small"><span class="badge bg-light text-dark border">#<?= $row['prefix_id'] ?></span></td>
                    <td>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['prefix_th']) ?> <small class="text-muted">(<?= htmlspecialchars($row['prefix_th_abbr']) ?>)</small></div>
                        <div class="small text-muted"><?= htmlspecialchars($row['prefix_en'] ?? '-') ?></div>
                    </td>
                    <td class="text-center">
                        <?php if ($row['shop_info_shop_id'] == 0): ?>
                            <span class="badge bg-secondary opacity-75"><i class="bi bi-globe2 me-1"></i> ส่วนกลาง</span>
                        <?php else: ?>
                            <span class="text-primary small fw-bold"><i class="bi bi-shop me-1"></i> <?= htmlspecialchars($row['shop_name'] ?? 'ไม่ทราบร้าน') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <i class="bi bi-check-circle-fill status-icon text-success <?= $row['is_active'] ? '' : 'inactive' ?> <?= $clickable ?>" 
                           data-id="<?= $row['prefix_id'] ?>" data-status="1" style="<?= $can_edit ? '' : 'cursor: default;' ?>"></i>
                        <i class="bi bi-x-circle-fill status-icon text-danger <?= !$row['is_active'] ? '' : 'inactive' ?> <?= $clickable ?>" 
                           data-id="<?= $row['prefix_id'] ?>" data-status="0" style="<?= $can_edit ? '' : 'cursor: default;' ?>"></i>
                    </td>
                    <td class="text-center">
                        <?php if ($is_super_admin || $row['shop_info_shop_id'] == $shop_id): ?>
                            <div class="d-flex justify-content-center gap-2">
                                <a href="edit_prename.php?id=<?= $row['prefix_id'] ?>" class="btn btn-outline-warning btn-sm border-0"><i class="bi bi-pencil-square fs-5"></i></a>
                                <button onclick="confirmDelete(<?= $row['prefix_id'] ?>, '<?= addslashes($row['prefix_th']) ?>')" class="btn btn-outline-danger btn-sm border-0"><i class="bi bi-trash3-fill fs-5"></i></button>
                            </div>
                        <?php else: ?>
                            <span class="text-muted small italic"><i class="bi bi-lock-fill"></i> ปิดล็อก</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php else: ?>
                <tr><td colspan="6" class="text-center py-5 text-muted italic">-- ไม่พบข้อมูล --</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                    <a class="page-link ajax-page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php
    exit(); // จบการทำงานของ AJAX
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการคำนำหน้า - Mobile Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background-color: <?= $background_color ?>; font-family: 'Prompt', sans-serif; }
        .main-card { background: white; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: none; }
        .card-header-custom { background: <?= $theme_color ?>; color: white; border-radius: 15px 15px 0 0; padding: 1.5rem; }
        .status-icon { cursor: pointer; font-size: 1.2rem; transition: transform 0.2s; }
        .status-icon.inactive { opacity: 0.25; filter: grayscale(1); }
        .status-icon.readonly-status { cursor: default !important; opacity: 0.3; }
        .pagination .page-item.active .page-link { background-color: <?= $theme_color ?>; border-color: <?= $theme_color ?>; color: white; }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container py-2" style="max-width: 1000px;">
                    
                    <div class="main-card card">
                        <div class="card-header-custom d-flex justify-content-between align-items-center">
                            <h4 class="mb-0 text-white"><i class="bi bi-person-lines-fill me-2"></i>จัดการคำนำหน้านาม</h4>
                            <a href="add_prename.php" class="btn btn-light btn-sm fw-bold">
                                <i class="bi bi-plus-circle-fill me-1"></i> เพิ่มคำนำหน้า
                            </a>
                        </div>

                        <div class="card-body p-4">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                        <input type="text" id="searchInput" class="form-control" placeholder="ค้นหาชื่อไทย/อังกฤษ/ตัวย่อ...">
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
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">ยืนยันการลบ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <p class="fs-5">ต้องการลบคำนำหน้า <strong id="delName"></strong> ?</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                    <a id="confirmDelBtn" href="#" class="btn btn-danger">ยืนยันการลบ</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function fetchPrefixData(page = 1, search = '') {
            fetch(`prename.php?ajax=1&page=${page}&search=${encodeURIComponent(search)}`)
                .then(res => res.text())
                .then(data => document.getElementById('tableContainer').innerHTML = data);
        }

        document.getElementById('searchInput').addEventListener('input', e => fetchPrefixData(1, e.target.value));

        document.addEventListener('click', e => {
            if (e.target.classList.contains('ajax-page-link')) {
                e.preventDefault();
                fetchPrefixData(e.target.dataset.page, document.getElementById('searchInput').value);
            }
            if (e.target.classList.contains('toggle-status')) {
                fetch('toggle_prename_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${e.target.dataset.id}&status=${e.target.dataset.status}`
                }).then(() => fetchPrefixData(document.querySelector('.page-item.active .ajax-page-link')?.dataset.page || 1, document.getElementById('searchInput').value));
            }
        });

        function confirmDelete(id, name) {
            document.getElementById('delName').innerText = name;
            document.getElementById('confirmDelBtn').href = `delete_prename.php?id=${id}`;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        window.onload = () => fetchPrefixData();
    </script>
</body>
</html>