<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'religion');

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
// [3] ส่วนประมวลผล AJAX สำหรับดึงตารางข้อมูล
// ==========================================
if (isset($_GET['ajax'])) {
    // สร้างเงื่อนไข WHERE แบบ Hybrid (ส่วนกลาง + ร้านค้า)
    $where_sql = "";
    if (!$is_super_admin) {
        $where_sql = "WHERE (r.shop_info_shop_id = 0 OR r.shop_info_shop_id = '$shop_id')";
    }

    // ดึงข้อมูลพร้อมชื่อร้าน
    $sql = "SELECT r.*, s.shop_name 
            FROM religions r 
            LEFT JOIN shop_info s ON r.shop_info_shop_id = s.shop_id 
            $where_sql 
            ORDER BY r.shop_info_shop_id ASC, r.religion_id ASC";
    $result = mysqli_query($conn, $sql);
    ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-center" width="5%">#</th>
                    <th width="10%">รหัส</th>
                    <th width="25%">ชื่อศาสนา (ไทย)</th>
                    <th width="25%">ชื่อศาสนา (อังกฤษ)</th>
                    <th width="15%" class="text-center">ผู้เพิ่ม/สังกัด</th>
                    <th width="10%" class="text-center">สถานะ</th>
                    <th width="10%" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($result) > 0): 
                    $idx = 1;
                    while ($row = mysqli_fetch_assoc($result)): 
                        // ตรวจสอบสิทธิ์การแก้ไข (ของตัวเอง หรือ มีสิทธิ์ centralinf)
                        $can_edit = ($row['shop_info_shop_id'] == $shop_id || $has_central_perm);
                        $clickable_class = $can_edit ? 'toggle-status' : 'readonly-status';
                ?>
                <tr>
                    <td class="text-center"><?= $idx++ ?></td>
                    <td class="text-center"><span class="badge bg-light text-dark border">#<?= $row['religion_id'] ?></span></td>
                    <td class="fw-bold"><?= htmlspecialchars($row['religion_name_th']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($row['religion_name_en'] ?? '-') ?></td>
                    <td class="text-center">
                        <?php if ($row['shop_info_shop_id'] == 0): ?>
                            <span class="badge bg-secondary opacity-75"><i class="bi bi-globe2 me-1"></i> ส่วนกลาง</span>
                        <?php else: ?>
                            <span class="text-primary small fw-bold"><i class="bi bi-shop me-1"></i> <?= htmlspecialchars($row['shop_name'] ?? 'ไม่ทราบร้าน') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <i class="bi bi-check-circle-fill status-icon text-success <?= $row['is_active'] ? '' : 'inactive' ?> <?= $clickable_class ?>" 
                           data-id="<?= $row['religion_id'] ?>" data-status="1" style="<?= $can_edit ? '' : 'cursor: default;' ?>"></i>
                        <i class="bi bi-x-circle-fill status-icon text-danger <?= !$row['is_active'] ? '' : 'inactive' ?> <?= $clickable_class ?>" 
                           data-id="<?= $row['religion_id'] ?>" data-status="0" style="<?= $can_edit ? '' : 'cursor: default;' ?>"></i>
                    </td>
                    <td class="text-center">
                        <?php if ($is_super_admin || $row['shop_info_shop_id'] == $shop_id): ?>
                            <div class="d-flex justify-content-center gap-2">
                                <a href="edit_religion.php?id=<?= $row['religion_id'] ?>" class="btn btn-outline-warning btn-sm border-0"><i class="bi bi-pencil-square fs-5"></i></a>
                                <button onclick="confirmDelete(<?= $row['religion_id'] ?>, '<?= addslashes($row['religion_name_th']) ?>')" class="btn btn-outline-danger btn-sm border-0"><i class="bi bi-trash3-fill fs-5"></i></button>
                            </div>
                        <?php else: ?>
                            <i class="bi bi-lock-fill text-muted" title="ข้อมูลส่วนกลางแก้ไขไม่ได้"></i>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php else: ?>
                <tr><td colspan="7" class="text-center py-5 text-muted italic">-- ไม่พบข้อมูลศาสนา --</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    exit(); 
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการศาสนา - Mobile Shop</title>
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
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container py-2" style="max-width: 1000px;">
                    
                    <div class="main-card card shadow-sm">
                        <div class="card-header-custom d-flex justify-content-between align-items-center">
                            <h4 class="mb-0 text-white"><i class="bi bi-book me-2"></i>รายการข้อมูลศาสนา</h4>
                            <a href="add_religion.php" class="btn btn-light btn-sm fw-bold">
                                <i class="bi bi-plus-circle me-1"></i> เพิ่มศาสนา
                            </a>
                        </div>

                        <div class="card-body p-4">
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
            <div class="modal-content border-0">
                <div class="modal-header bg-danger text-white border-0">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>ยืนยันการลบ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <p class="fs-5">คุณแน่ใจหรือไม่ที่จะลบศาสนา <strong id="delName"></strong> ?</p>
                    <small class="text-muted">ข้อมูลที่ถูกลบไม่สามารถกู้คืนได้</small>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <a id="confirmDelBtn" href="#" class="btn btn-danger px-4">ยืนยันการลบ</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ฟังก์ชันโหลดข้อมูลตาราง
        function fetchReligionData() {
            fetch(`religion.php?ajax=1`)
                .then(res => res.text())
                .then(data => document.getElementById('tableContainer').innerHTML = data);
        }

        document.addEventListener('click', e => {
            // คลิกเปลี่ยนสถานะ
            if (e.target.classList.contains('toggle-status')) {
                fetch('toggle_religion_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${e.target.dataset.id}&status=${e.target.dataset.status}`
                }).then(() => fetchReligionData());
            }
        });

        function confirmDelete(id, name) {
            document.getElementById('delName').innerText = name;
            document.getElementById('confirmDelBtn').href = `delete_religion.php?id=${id}`;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        window.onload = () => fetchReligionData();
    </script>
</body>
</html>