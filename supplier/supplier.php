<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'supplier');

// [1] รับค่าพื้นฐานจาก Session
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
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20; // แสดงรายการ 20 รายการต่อหน้า
    $offset = ($page - 1) * $limit;

    // 3. กรองตามสิทธิ์ (เห็นแค่ร้านตัวเอง / แอดมินเห็นทั้งหมด)
    $conditions = [];
    if (!$is_super_admin) {
        $conditions[] = "s.shop_info_shop_id = '$shop_id'";
    }

    if (!empty($search)) {
        $conditions[] = "(s.supplier_id LIKE '%$search%' OR s.co_name LIKE '%$search%' OR s.contact_firstname LIKE '%$search%' OR s.supplier_phone_no LIKE '%$search%')";
    }

    $where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // นับจำนวนทั้งหมดเพื่อคำนวณหน้า
    $count_sql = "SELECT COUNT(*) as total FROM suppliers s $where_sql";
    $total_items = $conn->query($count_sql)->fetch_assoc()['total'];
    $total_pages = ceil($total_items / $limit);

    // ดึงข้อมูลซัพพลายเออร์
    $sql = "SELECT s.*, p.prefix_th, sh.shop_name 
            FROM suppliers s
            LEFT JOIN prefixs p ON s.prefixs_prefix_id = p.prefix_id
            LEFT JOIN shop_info sh ON s.shop_info_shop_id = sh.shop_id
            $where_sql 
            ORDER BY s.supplier_id DESC 
            LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);
    ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-center" width="5%">#</th>
                    <th width="10%">รหัส</th>
                    <th width="25%">ชื่อบริษัท / ร้านค้า</th>
                    <th width="20%">ผู้ติดต่อ / เบอร์โทร</th>
                    <?php if ($is_super_admin): // เพิ่มคอลัมน์ระบุร้านสำหรับ Admin ?>
                        <th width="15%" class="text-center">สังกัดร้าน</th>
                    <?php endif; ?>
                    <th width="12%" class="text-center">อีเมล</th>
                    <th width="13%" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): $idx = $offset + 1; while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td class="text-center text-muted fw-bold"><?= $idx++ ?></td>
                    <td class="text-center small"><span class="badge bg-light text-dark border">#<?= $row['supplier_id'] ?></span></td>
                    <td><div class="fw-bold text-dark"><?= htmlspecialchars($row['co_name']) ?></div><small class="text-muted">Tax ID: <?= htmlspecialchars($row['tax_id'] ?: '-') ?></small></td>
                    <td>
                        <div class="small fw-bold text-primary"><?= htmlspecialchars(($row['prefix_th'] ?? '') . $row['contact_firstname'] . ' ' . $row['contact_lastname']) ?></div>
                        <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($row['supplier_phone_no'] ?? '-') ?></div>
                    </td>
                    <?php if ($is_super_admin): ?>
                        <td class="text-center">
                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-3">
                                <i class="bi bi-shop me-1"></i> <?= htmlspecialchars($row['shop_name'] ?? 'ไม่ระบุ') ?>
                            </span>
                        </td>
                    <?php endif; ?>
                    <td class="text-center small text-muted"><?= htmlspecialchars($row['supplier_email'] ?: '-') ?></td>
                    <td class="text-center">
                        <div class="btn-group gap-1">
                            <a href="view_supplier.php?id=<?= $row['supplier_id'] ?>" class="btn btn-outline-info btn-sm border-0" title="ดูรายละเอียด"><i class="bi bi-eye-fill fs-5"></i></a>
                            <a href="edit_supplier.php?id=<?= $row['supplier_id'] ?>" class="btn btn-outline-warning btn-sm border-0" title="แก้ไข"><i class="bi bi-pencil-square fs-5"></i></a>
                            <button onclick="confirmDelete(<?= $row['supplier_id'] ?>, '<?= addslashes($row['co_name']) ?>')" class="btn btn-outline-danger btn-sm border-0" title="ลบ"><i class="bi bi-trash fs-5"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="<?= $is_super_admin ? 7 : 6 ?>" class="text-center py-5 text-muted">-- ไม่พบข้อมูลซัพพลายเออร์ --</td></tr>
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
        <div class="small text-muted">หน้า <?= $page ?> / <?= $total_pages ?> (รวม <?= number_format($total_items) ?> รายการ)</div>
    </div>
    <?php endif; exit(); }
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการซัพพลายเออร์ - Mobile Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container py-2" style="max-width: 1300px;">
                    
                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom-0">
                            <h4 class="mb-0 text-success fw-bold"><i class="bi bi-truck me-2"></i>จัดการรายชื่อซัพพลายเออร์</h4>
                            <a href="add_supplier.php" class="btn btn-success btn-sm fw-bold px-3">
                                <i class="bi bi-plus-circle me-1"></i> เพิ่มซัพพลายเออร์
                            </a>
                        </div>

                        <div class="card-body p-4">
                            <div class="row mb-4">
                                <div class="col-md-5">
                                    <div class="input-group shadow-sm" style="border-radius: 10px; overflow: hidden;">
                                        <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                                        <input type="text" id="searchInput" class="form-control border-0" placeholder="ค้นหาชื่อบริษัท, ชื่อผู้ติดต่อ, เบอร์โทร...">
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
                    <h5 class="modal-title fw-bold text-white"><i class="bi bi-exclamation-triangle-fill me-2"></i>ยืนยันการลบ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <p class="fs-5 mb-1">ต้องการลบซัพพลายเออร์ <strong id="delName"></strong> ?</p>
                    <p class="text-danger small mb-0"><i class="bi bi-info-circle me-1"></i>โปรดตรวจสอบประวัติการสั่งซื้อ (PO) ก่อนทำการลบ</p>
                </div>
                <div class="modal-footer border-0 justify-content-center bg-light">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <a id="confirmDelBtn" href="#" class="btn btn-danger rounded-pill px-4 shadow-sm">ยืนยันการลบ</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function fetchSupplierData(page = 1) {
            const search = document.getElementById('searchInput').value;
            fetch(`supplier.php?ajax=1&page=${page}&search=${encodeURIComponent(search)}`)
                .then(res => res.text()).then(data => document.getElementById('tableContainer').innerHTML = data);
        }

        document.getElementById('searchInput').addEventListener('input', () => fetchSupplierData(1));

        document.addEventListener('click', e => {
            const link = e.target.closest('.ajax-page-link');
            if (link) { e.preventDefault(); fetchSupplierData(link.dataset.page); }
            if (e.target.id === 'btnJumpPage') {
                const p = document.getElementById('jumpPageInput').value;
                if (p > 0) fetchSupplierData(p);
            }
        });

        function confirmDelete(id, name) {
            document.getElementById('delName').innerText = name;
            document.getElementById('confirmDelBtn').href = `delete_supplier.php?id=${id}`;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        window.onload = () => fetchSupplierData();
    </script>
</body>
</html>