<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'symptoms');

$shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// --- 1. ตรวจสอบสิทธิ์ ---
$is_super_admin = false;
$has_central_perm = false;

$check_user_sql = "SELECT r.role_name, p.permission_name 
                   FROM users u
                   JOIN user_roles ur ON u.user_id = ur.users_user_id
                   JOIN roles r ON ur.roles_role_id = r.role_id
                   LEFT JOIN role_permissions rp ON r.role_id = rp.roles_role_id
                   LEFT JOIN permissions p ON rp.permissions_permission_id = p.permission_id
                   WHERE u.user_id = ?";

if ($stmt = $conn->prepare($check_user_sql)) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        if ($row['role_name'] === 'Admin') $is_super_admin = true;
        if ($row['permission_name'] === 'centralinf') $has_central_perm = true;
    }
    $stmt->close();
}

// --- 2. AJAX Fetch Data ---
if (isset($_GET['ajax'])) {
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;

    // สร้างเงื่อนไข WHERE
    $conditions = [];
    if (!$is_super_admin) {
        // เห็นของส่วนกลาง (0) OR ของร้านตัวเอง
        $conditions[] = "(s.shop_info_shop_id = 0 OR s.shop_info_shop_id = '$shop_id')";
    }
    if (!empty($search)) {
        $conditions[] = "(s.symptom_name LIKE '%$search%' OR s.symptom_desc LIKE '%$search%')";
    }
    $where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // Count Total
    $count_sql = "SELECT COUNT(*) as total FROM symptoms s $where_sql";
    $total_items = $conn->query($count_sql)->fetch_assoc()['total'];
    $total_pages = ceil($total_items / $limit);

    // Fetch Data
    $sql = "SELECT s.*, sh.shop_name 
            FROM symptoms s 
            LEFT JOIN shop_info sh ON s.shop_info_shop_id = sh.shop_id 
            $where_sql 
            ORDER BY s.shop_info_shop_id ASC, s.symptom_id DESC 
            LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);
?>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-center" width="5%">#</th>
                    <th width="25%">ชื่ออาการเสีย</th>
                    <th width="30%">รายละเอียด</th>
                    <th width="15%" class="text-center">ประเภท</th>
                    <th width="10%" class="text-center">สถานะ</th>
                    <th width="15%" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0):
                    $idx = $offset + 1;
                    while ($row = $result->fetch_assoc()):
                        // เช็คว่าถูกใช้ในงานซ่อมหรือไม่ (เพื่อป้องกันการลบ)
                        $chk_ref = $conn->query("SELECT COUNT(*) c FROM repair_symptoms WHERE symptoms_symptom_id = {$row['symptom_id']}");
                        $is_used = $chk_ref->fetch_assoc()['c'] > 0;

                        // สิทธิ์จัดการ
                        $is_central = ($row['shop_info_shop_id'] == 0);
                        $is_own = ($row['shop_info_shop_id'] == $shop_id);
                        $can_manage = $is_super_admin || $is_own || ($is_central && $has_central_perm);
                ?>
                        <tr>
                            <td class="text-center text-muted small"><?= $idx++ ?></td>
                            <td class="fw-bold text-success"><?= htmlspecialchars($row['symptom_name']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($row['symptom_desc'] ?: '-') ?></td>
                            <td class="text-center">
                                <?php if ($is_central): ?>
                                    <span class="badge bg-secondary opacity-75"><i class="bi bi-globe2"></i> ส่วนกลาง</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark bg-opacity-25"><i class="bi bi-shop"></i> สาขา</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($row['is_active']): ?>
                                    <span class="badge bg-success rounded-pill">ใช้งาน</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary rounded-pill">ระงับ</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($can_manage): ?>
                                    <a href="edit_symptom.php?id=<?= $row['symptom_id'] ?>" class="btn btn-sm btn-outline-warning border-0" title="แก้ไข">
                                        <i class="bi bi-pencil-square fs-5"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-danger border-0"
                                        onclick="deleteSymptom(<?= $row['symptom_id'] ?>, '<?= addslashes($row['symptom_name']) ?>', <?= $is_used ? 1 : 0 ?>)"
                                        title="ลบ">
                                        <i class="bi bi-trash3-fill fs-5"></i>
                                    </button>
                                <?php else: ?>
                                    <i class="bi bi-lock-fill text-muted opacity-50" title="ไม่มีสิทธิ์แก้ไข"></i>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile;
                else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">-- ไม่พบข้อมูล --</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-3 px-2">
            <small class="text-muted">หน้า <?= $page ?> จาก <?= $total_pages ?></small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link ajax-page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
<?php exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>จัดการอาการเสีย</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container py-5">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-success text-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-white"><i class="bi bi-list-check me-2"></i>รายการอาการเสีย</h5>
                        <a href="add_symptom.php" class="btn btn-light btn-sm fw-bold text-success shadow-sm">
                            <i class="bi bi-plus-lg"></i> เพิ่มข้อมูล
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-5 ms-auto">
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                    <input type="text" id="searchInput" class="form-control border-start-0" placeholder="ค้นหาชื่ออาการ...">
                                </div>
                            </div>
                        </div>
                        <div id="tableContainer" style="min-height: 200px;">
                            <div class="text-center py-5">
                                <div class="spinner-border text-success"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // โหลดข้อมูล
        function loadData(page = 1, search = '') {
            fetch(`symptoms.php?ajax=1&page=${page}&search=${search}`)
                .then(res => res.text())
                .then(html => document.getElementById('tableContainer').innerHTML = html);
        }

        document.getElementById('searchInput').addEventListener('input', e => loadData(1, e.target.value));

        document.addEventListener('click', e => {
            if (e.target.closest('.ajax-page-link')) {
                e.preventDefault();
                loadData(e.target.closest('.ajax-page-link').dataset.page, document.getElementById('searchInput').value);
            }
        });

        // ฟังก์ชันลบด้วย SweetAlert
        function deleteSymptom(id, name, isUsed) {
            if (isUsed) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ไม่สามารถลบได้',
                    text: `อาการ "${name}" ถูกใช้งานอยู่ในใบซ่อมแล้ว`,
                    confirmButtonText: 'เข้าใจแล้ว'
                });
                return;
            }

            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: `คุณต้องการลบ "${name}" ใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    // สร้าง Form เสมือนเพื่อส่งค่า POST ไปลบ
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'delete_symptom.php';

                    const inputId = document.createElement('input');
                    inputId.type = 'hidden';
                    inputId.name = 'symptom_id';
                    inputId.value = id;

                    const inputConfirm = document.createElement('input');
                    inputConfirm.type = 'hidden';
                    inputConfirm.name = 'delete_symptom';
                    inputConfirm.value = '1';

                    form.appendChild(inputId);
                    form.appendChild(inputConfirm);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // แสดง Alert จาก Session (กรณีกลับมาจากหน้า Add/Delete)
        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: '<?= $_SESSION['success'] ?>',
                timer: 2000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: '<?= $_SESSION['error'] ?>'
            });
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        loadData();
    </script>
</body>

</html>