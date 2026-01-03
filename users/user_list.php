<?php
session_start();
require '../config/config.php';

// ตรวจสอบสิทธิ์การเข้าถึง (ใช้ Permission 'menu_manage_users' หรือ 'user_list')
checkPageAccess($conn, 'menu_manage_users');

// [1] รับค่าพื้นฐาน
$current_shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// ตรวจสอบว่าเป็น Super Admin หรือไม่ (เพื่อเปิดฟังก์ชันกรองร้านค้า)
$is_super_admin = false;
$chk_sql = "SELECT r.role_name FROM roles r 
            JOIN user_roles ur ON r.role_id = ur.roles_role_id 
            WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt = $conn->prepare($chk_sql)) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $is_super_admin = true;
    $stmt->close();
}

// ==========================================
// [2] ส่วนประมวลผล AJAX (ทำงานเมื่อเรียกผ่าน Fetch API)
// ==========================================
if (isset($_GET['ajax'])) {
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
    $shop_f = isset($_GET['shop_filter']) ? $_GET['shop_filter'] : '';
    $role_f = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';
    $status_f = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;

    // สร้างเงื่อนไข Query
    $conditions = [];

    // 2.1 กรองตามสิทธิ์การมองเห็น
    if (!$is_super_admin) {
        // ถ้าไม่ใช่ Admin เห็นแค่คนในร้านตัวเอง (ผ่าน Employee -> Branch -> Shop)
        $conditions[] = "s.shop_id = '$current_shop_id'";
    } elseif (!empty($shop_f)) {
        // ถ้าเป็น Admin และเลือกกรองร้าน
        $conditions[] = "s.shop_id = '$shop_f'";
    }

    // 2.2 กรองตามการค้นหา
    if (!empty($search)) {
        $conditions[] = "(u.username LIKE '%$search%' OR e.firstname_th LIKE '%$search%' OR e.lastname_th LIKE '%$search%' OR e.emp_phone_no LIKE '%$search%')";
    }
    if (!empty($role_f)) $conditions[] = "r.role_id = '$role_f'";
    if (!empty($status_f)) $conditions[] = "u.user_status = '$status_f'";

    $where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // 2.3 นับจำนวนรายการทั้งหมด
    $count_sql = "SELECT COUNT(DISTINCT u.user_id) as total 
                  FROM users u
                  LEFT JOIN employees e ON u.user_id = e.users_user_id
                  LEFT JOIN branches b ON e.branches_branch_id = b.branch_id
                  LEFT JOIN shop_info s ON b.shop_info_shop_id = s.shop_id
                  LEFT JOIN user_roles ur ON u.user_id = ur.users_user_id
                  LEFT JOIN roles r ON ur.roles_role_id = r.role_id
                  $where_sql";
    $total_items = $conn->query($count_sql)->fetch_assoc()['total'];
    $total_pages = ceil($total_items / $limit);

    // 2.4 ดึงข้อมูล Users
    $sql = "SELECT u.*, e.firstname_th, e.lastname_th, e.emp_image, s.shop_name, r.role_name, r.role_id
            FROM users u
            LEFT JOIN employees e ON u.user_id = e.users_user_id
            LEFT JOIN branches b ON e.branches_branch_id = b.branch_id
            LEFT JOIN shop_info s ON b.shop_info_shop_id = s.shop_id
            LEFT JOIN user_roles ur ON u.user_id = ur.users_user_id
            LEFT JOIN roles r ON ur.roles_role_id = r.role_id
            $where_sql
            GROUP BY u.user_id
            ORDER BY u.user_id DESC
            LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);
?>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-center" width="5%">#</th>
                    <th width="20%">บัญชีผู้ใช้ (Username)</th>
                    <th width="25%">ชื่อ-นามสกุล (พนักงาน)</th>
                    <th width="15%" class="text-center">บทบาท (Role)</th>
                    <?php if ($is_super_admin): ?>
                        <th width="15%" class="text-center">สังกัดร้าน</th>
                    <?php endif; ?>
                    <th width="10%" class="text-center">สถานะ</th>
                    <th width="10%" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): $idx = $offset + 1;
                    while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center text-muted fw-bold"><?= $idx++ ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle me-2 bg-light text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 35px; height: 35px; border-radius: 50%;">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['username']) ?></div>
                                        <small class="text-muted" style="font-size: 0.75rem;">Last login: -</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($row['firstname_th']): ?>
                                    <?= htmlspecialchars($row['firstname_th'] . ' ' . $row['lastname_th']) ?>
                                <?php else: ?>
                                    <span class="text-muted font-italic">- ไม่ผูกข้อมูลพนักงาน -</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 rounded-pill">
                                    <?= htmlspecialchars($row['role_name'] ?? 'ไม่มีสิทธิ์') ?>
                                </span>
                            </td>
                            <?php if ($is_super_admin): ?>
                                <td class="text-center">
                                    <?php if (empty($row['shop_name']) || $row['shop_name'] == 'ส่วนกลาง (Central)'): ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2"><i class="bi bi-globe2 me-1"></i> ส่วนกลาง</span>
                                    <?php else: ?>
                                        <span class="badge bg-info bg-opacity-10 text-info border px-2"><i class="bi bi-shop me-1"></i> <?= htmlspecialchars($row['shop_name']) ?></span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td class="text-center">
                                <?php if ($row['user_status'] == 'Active'): ?>
                                    <span class="badge bg-success rounded-pill px-3 cursor-pointer" onclick="toggleStatus(<?= $row['user_id'] ?>, 'Active', '<?= $row['username'] ?>')">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger rounded-pill px-3 cursor-pointer" onclick="toggleStatus(<?= $row['user_id'] ?>, 'Inactive', '<?= $row['username'] ?>')">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group gap-1">
                                    <a href="user_view.php?id=<?= $row['user_id'] ?>" class="btn btn-outline-info btn-sm border-0" title="ดูรายละเอียด">
                                        <i class="bi bi-eye-fill"></i>
                                    </a>

                                    <a href="user_edit.php?id=<?= $row['user_id'] ?>" class="btn btn-outline-primary btn-sm border-0" title="แก้ไข">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>

                                    <button onclick="resetPassword(<?= $row['user_id'] ?>, '<?= $row['username'] ?>')" class="btn btn-outline-warning btn-sm border-0" title="แก้ไขรหัสผ่าน">
                                        <i class="bi bi-key-fill"></i>
                                    </button>

                                    <button onclick="deleteUser(<?= $row['user_id'] ?>, '<?= $row['username'] ?>')" class="btn btn-outline-danger btn-sm border-0" title="ลบ">
                                        <i class="bi bi-trash3-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile;
                else: ?>
                    <tr>
                        <td colspan="<?= $is_super_admin ? 7 : 6 ?>" class="text-center py-5 text-muted">-- ไม่พบข้อมูลผู้ใช้งาน --</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center pagination-sm">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="1"><i class="bi bi-chevron-double-left"></i></a></li>
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="<?= $page - 1 ?>"><i class="bi bi-chevron-left"></i></a></li>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="<?= $page + 1 ?>"><i class="bi bi-chevron-right"></i></a></li>
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="<?= $total_pages ?>"><i class="bi bi-chevron-double-right"></i></a></li>
            </ul>
        </nav>
<?php endif;
    exit();
} // จบส่วน AJAX

// โหลดข้อมูลสำหรับ Dropdown Filter
$roles_res = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_name");
$shops_res = ($is_super_admin) ? $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name") : null;
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>จัดการบัญชีผู้ใช้งาน - Mobile Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            color: <?= $text_color ?>;
        }

        .cursor-pointer {
            cursor: pointer;
            transition: 0.2s;
        }

        .cursor-pointer:hover {
            transform: scale(1.05);
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container py-2" style="max-width: 1400px;">

                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom-0">
                            <h4 class="mb-0 text-success fw-bold"><i class="bi bi-person-badge-fill me-2"></i>จัดการบัญชีผู้ใช้งาน (Users)</h4>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-success btn-sm fw-bold px-3" onclick="toggleFilter()">
                                    <i class="bi bi-filter me-1"></i> <span id="filterBtnText">กรองข้อมูล</span>
                                </button>

                                <a href="user_add.php" class="btn btn-success btn-sm fw-bold px-3 shadow-sm">
                                    <i class="bi bi-person-plus-fill me-1"></i> เพิ่มผู้ใช้งานใหม่
                                </a>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            <div class="card bg-light border-0 mb-4" id="filterCard" style="display: none; border-radius: 15px;">
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="small fw-bold text-muted mb-1">บทบาท (Role)</label>
                                            <select id="roleFilter" class="form-select border-0 shadow-sm">
                                                <option value="">-- ทั้งหมด --</option>
                                                <?php while ($r = $roles_res->fetch_assoc()): ?>
                                                    <option value="<?= $r['role_id'] ?>"><?= $r['role_name'] ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small fw-bold text-muted mb-1">สถานะ</label>
                                            <select id="statusFilter" class="form-select border-0 shadow-sm">
                                                <option value="">-- ทั้งหมด --</option>
                                                <option value="Active">ใช้งานอยู่ (Active)</option>
                                                <option value="Inactive">ถูกระงับ (Inactive)</option>
                                            </select>
                                        </div>
                                        <?php if ($is_super_admin): ?>
                                            <div class="col-md-3">
                                                <label class="small fw-bold text-primary mb-1">ร้านค้า (Shop)</label>
                                                <select id="shopFilter" class="form-select border-primary border-opacity-25 shadow-sm">
                                                    <option value="">-- ทุกร้าน --</option>
                                                    <?php while ($s = $shops_res->fetch_assoc()): ?>
                                                        <option value="<?= $s['shop_id'] ?>"><?= $s['shop_name'] ?></option>
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
                                        <input type="text" id="searchInput" class="form-control border-0" placeholder="ค้นหา Username, ชื่อพนักงาน, เบอร์โทร...">
                                    </div>
                                </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // ฟังก์ชันโหลดข้อมูล (AJAX)
        function fetchUserData(page = 1) {
            const params = new URLSearchParams({
                ajax: 1,
                page,
                search: document.getElementById('searchInput').value,
                role_filter: document.getElementById('roleFilter').value,
                status_filter: document.getElementById('statusFilter').value,
                shop_filter: document.getElementById('shopFilter')?.value || ''
            });

            fetch(`user_list.php?${params.toString()}`)
                .then(res => res.text())
                .then(data => document.getElementById('tableContainer').innerHTML = data);
        }

        // Toggle Filter
        function toggleFilter() {
            const card = document.getElementById('filterCard');
            card.style.display = (card.style.display === 'none') ? 'block' : 'none';
            document.getElementById('filterBtnText').innerText = (card.style.display === 'none') ? 'กรองข้อมูล' : 'ปิดตัวกรอง';
        }

        // Clear Filters
        function clearFilters() {
            ['roleFilter', 'statusFilter', 'shopFilter', 'searchInput'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            fetchUserData(1);
        }

        // Event Listeners
        document.getElementById('searchInput').addEventListener('input', () => fetchUserData(1));
        ['roleFilter', 'statusFilter', 'shopFilter'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', () => fetchUserData(1));
        });

        document.addEventListener('click', e => {
            const link = e.target.closest('.ajax-page-link');
            if (link) {
                e.preventDefault();
                fetchUserData(link.dataset.page);
            }
        });

        // ----------------------------------------------------
        // SweetAlert Functions
        // ----------------------------------------------------

        // 1. เปลี่ยนสถานะ (Toggle Status)
        function toggleStatus(userId, currentStatus, username) {
            const newStatus = (currentStatus === 'Active') ? 'Inactive' : 'Active';
            const actionText = (newStatus === 'Active') ? 'เปิดใช้งาน' : 'ระงับการใช้งาน';
            const btnColor = (newStatus === 'Active') ? '#198754' : '#dc3545';

            Swal.fire({
                title: `${actionText} ${username}?`,
                text: `คุณต้องการ${actionText}บัญชีผู้ใช้นี้ใช่หรือไม่`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: btnColor,
                cancelButtonColor: '#6c757d',
                confirmButtonText: `ยืนยัน, ${actionText}`,
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    // ส่ง Request ไปยังไฟล์ PHP เพื่ออัปเดต (ต้องสร้างไฟล์ update_user_status.php รองรับ)
                    fetch('update_user_status.php', { // คุณต้องสร้างไฟล์นี้
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `user_id=${userId}&status=${newStatus}`
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('สำเร็จ!', `สถานะถูกเปลี่ยนเป็น ${newStatus} แล้ว`, 'success');
                                fetchUserData(1); // โหลดตารางใหม่
                            } else {
                                Swal.fire('ผิดพลาด!', data.message, 'error');
                            }
                        });
                }
            });
        }

        // 2. รีเซ็ตรหัสผ่าน (Reset Password)
        function resetPassword(userId, username) {
            Swal.fire({
                title: `รีเซ็ตรหัสผ่าน ${username}`,
                input: 'password',
                inputLabel: 'กำหนดรหัสผ่านใหม่',
                inputPlaceholder: 'กรอกรหัสผ่านใหม่...',
                inputAttributes: {
                    minlength: 6,
                    autocapitalize: 'off',
                    autocorrect: 'off'
                },
                showCancelButton: true,
                confirmButtonText: 'บันทึกรหัสผ่าน',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                preConfirm: (newPass) => {
                    if (!newPass) {
                        Swal.showValidationMessage('กรุณากรอกรหัสผ่านใหม่');
                    }
                    return newPass;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('update_user_password.php', { // คุณต้องสร้างไฟล์นี้
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `user_id=${userId}&password=${result.value}`
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('เรียบร้อย!', 'รหัสผ่านถูกเปลี่ยนแล้ว', 'success');
                            } else {
                                Swal.fire('ผิดพลาด!', data.message, 'error');
                            }
                        });
                }
            });
        }
        
        // 3. ฟังก์ชันลบผู้ใช้งาน (ปรับปรุงให้ Redirect ไป delete_user.php)
        function deleteUser(userId, username) {
            Swal.fire({
                title: `ยืนยันการลบ?`,
                text: `คุณต้องการลบผู้ใช้งาน "${username}" ใช่หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ยืนยัน, ลบเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    // ส่งค่าไปลบที่หน้า delete_user.php โดยตรง
                    window.location.href = `delete_user.php?id=${userId}`;
                }
            });
        }

        // เริ่มต้นโหลดข้อมูล
        window.onload = () => fetchUserData();
    </script>
</body>

</html>