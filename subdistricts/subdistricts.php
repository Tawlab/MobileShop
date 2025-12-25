<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'subdistricts');

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
// [3] ส่วนประมวลผล AJAX
// ==========================================
if (isset($_GET['ajax'])) {
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20; // 20 รายการต่อหน้า
    $offset = ($page - 1) * $limit;

    // สร้างเงื่อนไข WHERE แบบ Hybrid (ส่วนกลาง + ร้านค้า)
    $conditions = [];
    if (!$is_super_admin) {
        $conditions[] = "(s.shop_info_shop_id = 0 OR s.shop_info_shop_id = '$shop_id')";
    }
    if (!empty($search)) {
        $conditions[] = "(s.subdistrict_name_th LIKE '%$search%' 
                        OR s.subdistrict_name_en LIKE '%$search%' 
                        OR s.zip_code LIKE '%$search%' 
                        OR d.district_name_th LIKE '%$search%')";
    }
    $where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // นับจำนวนหน้าทั้งหมด
    $count_res = $conn->query("SELECT COUNT(*) as total FROM subdistricts s LEFT JOIN districts d ON s.districts_district_id = d.district_id $where_sql");
    $total_items = $count_res->fetch_assoc()['total'];
    $total_pages = ceil($total_items / $limit);

    // ดึงข้อมูลหลัก
    $sql = "SELECT s.*, d.district_name_th, sh.shop_name 
            FROM subdistricts s 
            LEFT JOIN districts d ON s.districts_district_id = d.district_id 
            LEFT JOIN shop_info sh ON s.shop_info_shop_id = sh.shop_id 
            $where_sql 
            ORDER BY s.shop_info_shop_id ASC, d.district_name_th ASC, s.subdistrict_id ASC 
            LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);
    ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-center" width="5%">#</th>
                    <th width="10%">รหัสตำบล</th>
                    <th width="18%">ชื่อตำบล (ไทย)</th>
                    <th width="18%">ชื่อตำบล (อังกฤษ)</th>
                    <th width="12%">รหัสไปรษณีย์</th>
                    <th width="12%">อำเภอ</th>
                    <th width="15%" class="text-center">ผู้เพิ่ม/สังกัด</th>
                    <th width="10%" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): 
                    $idx = $offset + 1;
                    while ($row = $result->fetch_assoc()): 
                ?>
                <tr>
                    <td class="text-center fw-bold text-muted small"><?= $idx++ ?></td>
                    <td class="text-center small"><span class="badge bg-light text-dark border">#<?= $row['subdistrict_id'] ?></span></td>
                    <td class="fw-bold"><?= htmlspecialchars($row['subdistrict_name_th']) ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($row['subdistrict_name_en'] ?? '-') ?></td>
                    <td class="text-center"><span class="badge bg-info text-dark"><?= htmlspecialchars($row['zip_code']) ?></span></td>
                    <td><span class="text-dark small"><i class="bi bi-building me-1"></i><?= htmlspecialchars($row['district_name_th']) ?></span></td>
                    <td class="text-center">
                        <?php if ($row['shop_info_shop_id'] == 0): ?>
                            <span class="badge bg-secondary opacity-75"><i class="bi bi-globe2 me-1"></i> ส่วนกลาง</span>
                        <?php else: ?>
                            <span class="text-primary small fw-bold"><i class="bi bi-shop me-1"></i> <?= htmlspecialchars($row['shop_name'] ?? 'ไม่ทราบร้าน') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($is_super_admin || $row['shop_info_shop_id'] == $shop_id): ?>
                            <div class="d-flex justify-content-center gap-2">
                                <a href="edit_subdistrict.php?id=<?= $row['subdistrict_id'] ?>" class="btn btn-outline-warning btn-sm border-0"><i class="bi bi-pencil-square fs-5"></i></a>
                                <button onclick="confirmDelete(<?= $row['subdistrict_id'] ?>, '<?= addslashes($row['subdistrict_name_th']) ?>')" class="btn btn-outline-danger btn-sm border-0"><i class="bi bi-trash3-fill fs-5"></i></button>
                            </div>
                        <?php else: ?>
                            <i class="bi bi-lock-fill text-muted" title="ข้อมูลส่วนกลางแก้ไขไม่ได้"></i>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php else: ?>
                <tr><td colspan="8" class="text-center py-5 text-muted italic">-- ไม่พบข้อมูลตำบล --</td></tr>
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

            <?php
            $range = 2;
            for ($i = 1; $i <= $total_pages; $i++):
                if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)):
            ?>
                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                    <a class="page-link ajax-page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
                </li>
            <?php
                elseif (($i == $page - $range - 1) || ($i == $page + $range + 1)):
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                endif;
            endfor;
            ?>

            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link ajax-page-link" href="#" data-page="<?= $page + 1 ?>" title="ถัดไป"><i class="bi bi-chevron-right"></i></a>
            </li>
            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link ajax-page-link" href="#" data-page="<?= $total_pages ?>" title="หน้าสุดท้าย"><i class="bi bi-chevron-double-right"></i></a>
            </li>
        </ul>
    </nav>

    <?php if ($total_pages > 10): ?>
    <div class="d-flex justify-content-center mt-2 mb-3">
        <div class="input-group input-group-sm" style="max-width: 180px;">
            <input type="number" id="jumpPageInput" class="form-control text-center" placeholder="ไปที่หน้า..." min="1" max="<?= $total_pages ?>">
            <button class="btn btn-success text-white" type="button" id="btnJumpPage">ไป</button>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="text-center small text-muted">
        หน้า <?= number_format($page) ?> / <?= number_format($total_pages) ?> (ทั้งหมด <?= number_format($total_items) ?> รายการ)
    </div>
    <?php endif; ?>
    <?php
    exit(); 
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการตำบล - Mobile Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background-color: #f8f9fa; font-family: 'Prompt', sans-serif; }
        .main-card { background: white; border-radius: 15px; box-shadow: 0 4px 25px rgba(0,0,0,0.08); border: none; overflow: hidden; }
        .card-header-custom { background-color: #198754; padding: 1.5rem; }
        /* หัวข้อสีขาว */
        .card-header-custom h4 { color: #ffffff !important; font-weight: 600; margin-bottom: 0; }
        .search-box { border-radius: 10px; border: 1px solid #ddd; padding: 10px 15px; }
        .pagination .page-link { border-radius: 8px; margin: 0 3px; color: #198754; font-weight: 600; }
        .pagination .page-item.active .page-link { background-color: #198754; border-color: #198754; color: white; }
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
                            <h4><i class="bi bi-geo-fill me-2"></i>รายการข้อมูลตำบล</h4>
                            <a href="add_subdistricts.php" class="btn btn-light btn-sm fw-bold">
                                <i class="bi bi-plus-circle me-1"></i> เพิ่มตำบล
                            </a>
                        </div>
                        <div class="card-body p-4">
                            <div class="row mb-4">
                                <div class="col-md-6 col-lg-5">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                        <input type="text" id="searchInput" class="form-control border-start-0 search-box" placeholder="ค้นหาตำบล, อำเภอ หรือรหัสไปรษณีย์...">
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
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>ยืนยันการลบข้อมูล</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <p class="fs-5 mb-1">คุณแน่ใจหรือไม่ที่จะลบตำบล <strong id="delName"></strong> ?</p>
                    <p class="text-danger small mb-0">การลบอาจส่งผลต่อข้อมูลที่อยู่พนักงานที่มีอยู่</p>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <a id="confirmDelBtn" href="#" class="btn btn-danger px-4">ยืนยันการลบ</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function fetchSubdistrictData(page = 1, search = '') {
            const container = document.getElementById('tableContainer');
            fetch(`subdistricts.php?ajax=1&page=${page}&search=${encodeURIComponent(search)}`)
                .then(res => res.text())
                .then(data => container.innerHTML = data);
        }

        document.getElementById('searchInput').addEventListener('input', e => fetchSubdistrictData(1, e.target.value));

        // จัดการคลิกไอคอน Pagination และปุ่ม Jump
        document.addEventListener('click', e => {
            if (e.target.classList.contains('ajax-page-link') || e.target.closest('.ajax-page-link')) {
                e.preventDefault();
                const link = e.target.classList.contains('ajax-page-link') ? e.target : e.target.closest('.ajax-page-link');
                fetchSubdistrictData(link.dataset.page, document.getElementById('searchInput').value);
            }
            if (e.target.id === 'btnJumpPage' || e.target.closest('#btnJumpPage')) {
                const jumpInput = document.getElementById('jumpPageInput');
                const targetPage = parseInt(jumpInput.value);
                const maxPage = parseInt(jumpInput.getAttribute('max'));
                if (targetPage >= 1 && targetPage <= maxPage) {
                    fetchSubdistrictData(targetPage, document.getElementById('searchInput').value);
                } else {
                    alert('กรุณากรอกเลขหน้าให้ถูกต้อง');
                }
            }
        });

        function confirmDelete(id, name) {
            document.getElementById('delName').innerText = name;
            document.getElementById('confirmDelBtn').href = `delete_subdistrict.php?id=${id}`;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        window.onload = () => fetchSubdistrictData();
    </script>
</body>
</html>