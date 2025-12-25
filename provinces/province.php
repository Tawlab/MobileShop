<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'province');

// [1] รับค่าพื้นฐานจาก Session
$shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// [2] ตรวจสอบสิทธิ์ Admin และ Permission 'centralinf' (สิทธิ์จัดการข้อมูลส่วนกลาง)
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
            $is_super_admin = true; // เป็นเจ้าของระบบ
        }
        if ($row['permission_name'] === 'centralinf') {
            $has_central_perm = true; // มีสิทธิ์จัดการข้อมูลส่วนกลาง
        }
    }
    mysqli_stmt_close($stmt_user);
}

// ==========================================
// [3] ส่วนประมวลผล AJAX (ทำงานเมื่อเรียกผ่าน Fetch)
// ==========================================
if (isset($_GET['ajax'])) {
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10; // แสดง 10 รายการต่อหน้า
    $offset = ($page - 1) * $limit;

    // สร้างเงื่อนไข WHERE แบบ Hybrid (ดึงของส่วนกลาง + ของร้านตนเอง)
    $conditions = [];
    if (!$is_super_admin) {
        $conditions[] = "(p.shop_info_shop_id = 0 OR p.shop_info_shop_id = '$shop_id')";
    }
    if (!empty($search)) {
        $conditions[] = "(p.province_name_th LIKE '%$search%' OR p.province_name_en LIKE '%$search%')";
    }
    $where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // นับจำนวนทั้งหมดเพื่อคำนวณเลขหน้า
    $count_res = $conn->query("SELECT COUNT(*) as total FROM provinces p $where_sql");
    $total_items = $count_res->fetch_assoc()['total'];
    $total_pages = ceil($total_items / $limit);

    // ดึงข้อมูลพร้อมชื่อร้านสังกัด (JOIN shop_info)
    $sql = "SELECT p.*, s.shop_name 
            FROM provinces p 
            LEFT JOIN shop_info s ON p.shop_info_shop_id = s.shop_id 
            $where_sql 
            ORDER BY p.shop_info_shop_id ASC, p.province_id ASC 
            LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);
    ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-center" width="5%">ลำดับ</th>
                    <th width="10%">รหัส</th>
                    <th width="30%">ชื่อจังหวัด (ไทย)</th>
                    <th width="25%">ชื่อจังหวัด (อังกฤษ)</th>
                    <th width="20%" class="text-center">ผู้เพิ่ม/สังกัด</th>
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
                    <td class="text-center small"><span class="badge bg-light text-dark border">#<?= $row['province_id'] ?></span></td>
                    <td class="fw-bold"><?= htmlspecialchars($row['province_name_th']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($row['province_name_en'] ?? '-') ?></td>
                    <td class="text-center">
                        <?php if ($row['shop_info_shop_id'] == 0): ?>
                            <span class="badge bg-secondary opacity-75"><i class="bi bi-globe2 me-1"></i> ส่วนกลาง</span>
                        <?php else: ?>
                            <span class="text-primary small fw-bold"><i class="bi bi-shop me-1"></i> <?= htmlspecialchars($row['shop_name'] ?? 'ไม่ทราบร้าน') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php 
                        // เงื่อนไข: แก้ไข/ลบ ได้ถ้าเป็นแอดมิน หรือเป็นข้อมูลของร้านตนเอง
                        if ($is_super_admin || $row['shop_info_shop_id'] == $shop_id): ?>
                            <div class="d-flex justify-content-center gap-2">
                                <a href="edit_province.php?id=<?= $row['province_id'] ?>" class="btn btn-outline-warning btn-sm border-0"><i class="bi bi-pencil-square fs-5"></i></a>
                                <button onclick="confirmDelete(<?= $row['province_id'] ?>, '<?= addslashes($row['province_name_th']) ?>')" class="btn btn-outline-danger btn-sm border-0"><i class="bi bi-trash3-fill fs-5"></i></button>
                            </div>
                        <?php else: ?>
                            <span class="text-muted small italic" title="ข้อมูลส่วนกลางแก้ไขไม่ได้"><i class="bi bi-lock-fill"></i></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php else: ?>
                <tr><td colspan="6" class="text-center py-5 text-muted italic">-- ไม่พบข้อมูลจังหวัด --</td></tr>
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
    exit(); // หยุดการทำงานเพื่อส่งแค่ HTML ตารางกลับไป
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการจังหวัด - Mobile Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    <?php require '../config/load_theme.php'; // โหลดธีมจากระบบ ?>

    <style>
        body { background-color: #f8f9fa; font-family: 'Prompt', sans-serif; }
        .main-card { border: none; border-radius: 15px; box-shadow: 0 4px 25px rgba(0,0,0,0.08); overflow: hidden; }
        .card-header { background-color: #198754; color: white; padding: 1.5rem; border: none; }
        .table thead th { background-color: #f8f9fa; color: #495057; font-weight: 600; text-align: center; border-bottom: 2px solid #dee2e6; }
        .pagination .page-link { border-radius: 8px; margin: 0 3px; color: #198754; font-weight: 600; }
        .pagination .page-item.active .page-link { background-color: #198754; border-color: #198754; color: white; }
        
        @media (max-width: 767.98px) {
            .table th, .table td { font-size: 0.8rem; white-space: nowrap; padding: 0.6rem 0.5rem; }
            .card-header h4 { font-size: 1.1rem; }
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; // Sidebar ส่วนกลาง ?>
        
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container py-2" style="max-width: 1100px;">
                    
                    <div class="main-card card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="mb-0 text-white"><i class="bi bi-geo-alt-fill me-2"></i>จัดการข้อมูลจังหวัด</h4>
                            <a href="add_province.php" class="btn btn-light btn-sm fw-bold">
                                <i class="bi bi-plus-circle me-1"></i> เพิ่มจังหวัด
                            </a>
                        </div>

                        <div class="card-body p-4">
                            <div class="row mb-4">
                                <div class="col-md-6 col-lg-5">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                        <input type="text" id="searchInput" class="form-control border-start-0" placeholder="ค้นหาชื่อจังหวัด (ไทย/อังกฤษ)...">
                                    </div>
                                </div>
                            </div>

                            <div id="tableContainer">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-success" role="status"></div>
                                    <p class="mt-2 text-muted">กำลังเตรียมข้อมูล...</p>
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
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>ยืนยันการลบข้อมูล</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <p class="fs-5 mb-1">คุณแน่ใจหรือไม่ที่จะลบจังหวัด <strong id="delName"></strong> ?</p>
                    <p class="text-danger small mb-0">การลบอาจส่งผลต่อข้อมูลอำเภอและตำบลที่อ้างอิงอยู่</p>
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
        /**
         * ฟังก์ชันโหลดข้อมูลผ่าน AJAX
         */
        function fetchProvinceData(page = 1, search = '') {
            const container = document.getElementById('tableContainer');
            fetch(`province.php?ajax=1&page=${page}&search=${encodeURIComponent(search)}`)
                .then(res => res.text())
                .then(data => {
                    container.innerHTML = data;
                })
                .catch(err => {
                    console.error("Fetch Error:", err);
                    container.innerHTML = '<div class="alert alert-danger">ไม่สามารถโหลดข้อมูลได้</div>';
                });
        }

        // ค้นหาแบบ Real-time: ดักจับการพิมพ์ในช่องค้นหา
        document.getElementById('searchInput').addEventListener('input', e => {
            fetchProvinceData(1, e.target.value);
        });

        // Pagination: ดักจับการคลิกที่ปุ่มเปลี่ยนหน้าแบบ Dynamic
        document.addEventListener('click', e => {
            if (e.target.classList.contains('ajax-page-link')) {
                e.preventDefault();
                const page = e.target.dataset.page;
                const search = document.getElementById('searchInput').value;
                fetchProvinceData(page, search);
            }
        });

        /**
         * ฟังก์ชันแสดง Modal ยืนยันการลบ
         */
        function confirmDelete(id, name) {
            document.getElementById('delName').innerText = name;
            document.getElementById('confirmDelBtn').href = `delete_province.php?id=${id}`;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // โหลดข้อมูลครั้งแรกทันทีเมื่อเปิดหน้า
        window.onload = () => fetchProvinceData();
    </script>
</body>
</html>