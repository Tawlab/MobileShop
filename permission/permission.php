<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'permission');

// --- [1] รับค่าแจ้งเตือน ---
$message = $_SESSION['message'] ?? null;
$message_type = $_SESSION['message_type'] ?? null;
unset($_SESSION['message'], $_SESSION['message_type']);

// --- [2] กำหนดค่าสำหรับการแบ่งหน้า (Pagination) ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'all';
$items_per_page = 20; // แสดง 20 รายการต่อหน้าตามสั่ง
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $items_per_page;

// --- [3] สร้างเงื่อนไขการกรอง (WHERE Clause) ---
$where_clauses = [];
$bind_types = "";
$bind_values = [];

if (!empty($search_term)) {
    $where_clauses[] = "(permission_name LIKE ? OR permission_desc LIKE ?)";
    $search_like = "%" . $search_term . "%";
    $bind_types .= "ss";
    array_push($bind_values, $search_like, $search_like);
}

if ($filter_type != 'all') {
    if ($filter_type == 'add') $where_clauses[] = "permission_name LIKE 'add_%'";
    elseif ($filter_type == 'edit') $where_clauses[] = "permission_name LIKE 'edit_%'";
    elseif ($filter_type == 'del') $where_clauses[] = "permission_name LIKE 'del_%'";
    elseif ($filter_type == 'view') $where_clauses[] = "permission_name LIKE 'view_%'";
    elseif ($filter_type == 'list') {
        $where_clauses[] = "permission_name NOT LIKE 'add_%' AND permission_name NOT LIKE 'edit_%' AND 
                            permission_name NOT LIKE 'del_%' AND permission_name NOT LIKE 'view_%'";
    }
}

$where_sql = !empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : "";

// --- [4] นับจำนวนรายการทั้งหมดเพื่อคำนวณหน้า ---
$count_sql = "SELECT COUNT(*) as total FROM permissions" . $where_sql;
$stmt_count = $conn->prepare($count_sql);
if (!empty($bind_types)) {
    $stmt_count->bind_param($bind_types, ...$bind_values);
}
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $items_per_page);
$stmt_count->close();

// --- [5] ดึงข้อมูลตามลำดับและจำกัดจำนวน (LIMIT) ---
$sql = "SELECT permission_id, permission_name, permission_desc, create_at, update_at 
        FROM permissions " . $where_sql . " 
        ORDER BY permission_id ASC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$bind_types .= "ii";
array_push($bind_values, $items_per_page, $offset);
$stmt->bind_param($bind_types, ...$bind_values);
$stmt->execute();
$result = $stmt->get_result();
$permissions = [];
while ($row = $result->fetch_assoc()) {
    $permissions[] = $row;
}
$stmt->close();

// สำหรับปุ่ม Dropdown กรอง
$filter_labels = [
    'all' => '<i class="fas fa-list me-1"></i> ทั้งหมด',
    'list' => '<i class="fas fa-chalkboard me-1"></i> หน้าหลัก (List)',
    'add' => '<i class="fas fa-plus me-1"></i> เพิ่ม (Add)',
    'edit' => '<i class="fas fa-pencil me-1"></i> แก้ไข (Edit)',
    'del' => '<i class="fas fa-trash-can me-1"></i> ลบ (Del)',
    'view' => '<i class="fas fa-eye me-1"></i> ดู (View)'
];
$current_filter_label = $filter_labels[$filter_type] ?? $filter_labels['all'];

// ฟังก์ชันสร้าง Query String สำหรับรักษาค่าค้นหาเมื่อเปลี่ยนหน้า
function build_query($exclude = []) {
    $params = $_GET;
    foreach ($exclude as $key) unset($params[$key]);
    return !empty($params) ? '&' . http_build_query($params) : '';
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสิทธิ์ (Permissions) - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background-color: #f0fdf4; font-family: 'Sarabun', sans-serif; }
        .card { border-radius: 15px; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08); border: none; }
        .card-header { background: linear-gradient(135deg, #2dd4bf 0%, #15803d 100%); color: white; border-radius: 15px 15px 0 0 !important; }
        .pagination .page-link { border-radius: 8px; margin: 0 3px; color: #15803d; font-weight: 600; }
        .pagination .page-item.active .page-link { background-color: #15803d; border-color: #15803d; color: white; }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <?php if ($message): ?>
                    <div class="custom-alert alert-<?= $message_type == 'success' ? 'success' : 'danger' ?> alert dismissible fade show" role="alert" id="autoCloseAlert">
                        <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-triangle' ?> fa-lg"></i>
                        <div><strong><?= $message_type == 'success' ? 'สำเร็จ!' : 'ผิดพลาด!' ?></strong><br><?= htmlspecialchars($message) ?></div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="container-lg mt-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><i class="fas fa-shield-alt me-2"></i>จัดการสิทธิ์ (Permissions)</h4>
                            <a href="add_permission.php" class="btn btn-light"><i class="fas fa-plus me-2"></i>เพิ่มสิทธิ์ใหม่</a>
                        </div>
                        <div class="card-body p-4">

                            <form method="GET" action="permission.php" class="mb-4">
                                <input type="hidden" name="filter_type" id="filter_type_input" value="<?= htmlspecialchars($filter_type) ?>">
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted"></i></span>
                                    <input type="text" class="form-control border-0 bg-light" name="search" placeholder="ค้นหาชื่อสิทธิ์ หรือ คำอธิบาย..." value="<?= htmlspecialchars($search_term) ?>">
                                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" id="filterDropdownButton">
                                        <?= $current_filter_label ?>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" id="filterOptions">
                                        <?php foreach ($filter_labels as $key => $label): ?>
                                            <li><a class="dropdown-item <?= ($filter_type == $key) ? 'active' : '' ?>" href="#" data-filter="<?= $key ?>"><?= $label ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <button class="btn btn-primary" type="submit">ค้นหา</button>
                                    <?php if (!empty($search_term) || $filter_type != 'all'): ?>
                                        <a href="permission.php" class="btn btn-outline-secondary">ล้างค่า</a>
                                    <?php endif; ?>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>ชื่อสิทธิ์ (Name)</th>
                                            <th>คำอธิบาย (Description)</th>
                                            <th class="text-center">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($permissions) > 0): ?>
                                            <?php foreach ($permissions as $perm): ?>
                                                <tr>
                                                    <td><?= $perm['permission_id'] ?></td>
                                                    <td class="fw-bold text-success"><?= htmlspecialchars($perm['permission_name']) ?></td>
                                                    <td class="small"><?= htmlspecialchars($perm['permission_desc'] ?? '-') ?></td>
                                                    <td class="text-center">
                                                        <div class="d-flex justify-content-center gap-2">
                                                            <a href="edit_permission.php?id=<?= $perm['permission_id'] ?>" class="text-warning"><i class="fas fa-pencil"></i></a>
                                                            <a href="delete_permission.php?id=<?= $perm['permission_id'] ?>" class="text-danger" onclick="return confirm('ยืนยันการลบสิทธิ์นี้?')"><i class="fas fa-trash-can"></i></a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="4" class="text-center py-5 text-muted">ไม่พบข้อมูลสิทธิ์</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($total_pages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center pagination-sm">
                                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=1<?= build_query(['page']) ?>" title="หน้าแรกสุด"><i class="bi bi-chevron-double-left"></i></a>
                                    </li>
                                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page - 1 ?><?= build_query(['page']) ?>" title="ย้อนกลับ"><i class="bi bi-chevron-left"></i></a>
                                    </li>

                                    <?php
                                    $range = 2;
                                    for ($i = 1; $i <= $total_pages; $i++):
                                        if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)):
                                    ?>
                                        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?><?= build_query(['page']) ?>"><?= $i ?></a>
                                        </li>
                                    <?php
                                        elseif (($i == $page - $range - 1) || ($i == $page + $range + 1)):
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        endif;
                                    endfor;
                                    ?>

                                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page + 1 ?><?= build_query(['page']) ?>" title="ถัดไป"><i class="bi bi-chevron-right"></i></a>
                                    </li>
                                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $total_pages ?><?= build_query(['page']) ?>" title="หน้าสุดท้าย"><i class="bi bi-chevron-double-right"></i></a>
                                    </li>
                                </ul>
                            </nav>

                            <div class="d-flex justify-content-center mt-3">
                                <div class="input-group input-group-sm" style="max-width: 200px;">
                                    <span class="input-group-text bg-white text-muted">ไปที่หน้า</span>
                                    <input type="number" id="jumpPageInput" class="form-control text-center" min="1" max="<?= $total_pages ?>" value="<?= $page ?>">
                                    <button class="btn btn-success" type="button" id="btnJumpPage"><i class="bi bi-arrow-right-short fs-5"></i></button>
                                </div>
                            </div>
                            <div class="text-center small text-muted mt-2">หน้า <?= $page ?> / <?= $total_pages ?> (รวม <?= number_format($total_records) ?> รายการ)</div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ระบบค้นหาเลขหน้า
        document.getElementById('btnJumpPage')?.addEventListener('click', function() {
            const page = document.getElementById('jumpPageInput').value;
            const max = <?= $total_pages ?>;
            if (page >= 1 && page <= max) {
                const url = new URL(window.location.href);
                url.searchParams.set('page', page);
                window.location.href = url.href;
            } else {
                alert('กรุณากรอกเลขหน้าระหว่าง 1 ถึง ' + max);
            }
        });

        // รองรับการกด Enter ในช่องเลขหน้า
        document.getElementById('jumpPageInput')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') document.getElementById('btnJumpPage').click();
        });

        // จัดการ Dropdown กรอง
        document.getElementById('filterOptions').addEventListener('click', function(e) {
            const target = e.target.closest('a.dropdown-item');
            if (target) {
                e.preventDefault();
                document.getElementById('filter_type_input').value = target.dataset.filter;
                this.closest('form').submit();
            }
        });

        // ซ่อนแจ้งเตือนอัตโนมัติ
        setTimeout(() => {
            const alert = document.getElementById('autoCloseAlert');
            if (alert) bootstrap.Alert.getOrCreateInstance(alert).close();
        }, 5000);
    </script>
</body>
</html>