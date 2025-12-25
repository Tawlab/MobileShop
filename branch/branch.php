<?php
session_start();
require '../config/config.php';
require '../config/load_theme.php';

// ตรวจสอบสิทธิ์การเข้าถึงหน้าจัดการสาขา
checkPageAccess($conn, 'branch');

$current_shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// [1] ตรวจสอบว่าเป็น Admin หรือไม่
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
// [2] ส่วนประมวลผล AJAX
// ==========================================
if (isset($_GET['ajax'])) {
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
    $shop_f = isset($_GET['shop_filter']) ? $_GET['shop_filter'] : '';
    
    // สร้างเงื่อนไข Query
    $conditions = [];
    
    // 2.1 กรองตามสิทธิ์
    if (!$is_super_admin) {
        // ร้านค้าทั่วไป: เห็นแค่ของตัวเอง
        $conditions[] = "b.shop_info_shop_id = '$current_shop_id'";
    } elseif (!empty($shop_f)) {
        // Admin: กรองตามร้านที่เลือก
        $conditions[] = "b.shop_info_shop_id = '$shop_f'";
    }

    // 2.2 กรองจากการค้นหา
    if (!empty($search)) {
        $conditions[] = "(b.branch_name LIKE '%$search%' OR b.branch_code LIKE '%$search%' OR b.branch_phone LIKE '%$search%')";
    }

    $where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // 2.3 ดึงข้อมูลสาขา
    $sql = "SELECT b.*, s.shop_name,
                   p.province_name_th, d.district_name_th, sd.subdistrict_name_th, sd.zip_code
            FROM branches b
            LEFT JOIN shop_info s ON b.shop_info_shop_id = s.shop_id
            LEFT JOIN addresses a ON b.Addresses_address_id = a.address_id
            LEFT JOIN subdistricts sd ON a.subdistricts_subdistrict_id = sd.subdistrict_id
            LEFT JOIN districts d ON sd.districts_district_id = d.district_id
            LEFT JOIN provinces p ON d.provinces_province_id = p.province_id
            $where_sql
            ORDER BY b.branch_id DESC";
            
    $result = $conn->query($sql);
    ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-center" width="5%">#</th>
                    <th width="15%">รหัสสาขา</th>
                    <th width="25%">ชื่อสาขา</th>
                    <?php if ($is_super_admin): ?>
                        <th width="15%" class="text-center">สังกัดร้าน</th>
                    <?php endif; ?>
                    <th width="20%">ที่อยู่/เบอร์โทร</th>
                    <th width="10%" class="text-center">สถานะ</th>
                    <th width="10%" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): $idx = 1; while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td class="text-center text-muted fw-bold"><?= $idx++ ?></td>
                    <td>
                        <span class="badge bg-light text-dark border font-monospace">
                            <?= htmlspecialchars($row['branch_code'] ?? '-') ?>
                        </span>
                    </td>
                    <td>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['branch_name']) ?></div>
                        <small class="text-muted"><i class="bi bi-calendar-check me-1"></i>สร้างเมื่อ: <?= date('d/m/Y', strtotime($row['create_at'])) ?></small>
                    </td>
                    <?php if ($is_super_admin): ?>
                    <td class="text-center">
                        <?php if($row['shop_info_shop_id'] == 0): ?>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2"><i class="bi bi-globe2 me-1"></i> ส่วนกลาง</span>
                        <?php else: ?>
                            <span class="badge bg-info bg-opacity-10 text-info border px-2"><i class="bi bi-shop me-1"></i> <?= htmlspecialchars($row['shop_name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td>
                        <div class="small"><i class="bi bi-telephone-fill me-1 text-success"></i> <?= htmlspecialchars($row['branch_phone']) ?></div>
                        <div class="small text-muted text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($row['province_name_th']) ?>">
                            <i class="bi bi-geo-alt-fill me-1 text-danger"></i> <?= htmlspecialchars($row['province_name_th'] ?? '-') ?>
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-success rounded-pill px-2">เปิดใช้งาน</span>
                    </td>
                    <td class="text-center">
                        <div class="btn-group gap-1">
                            <a href="edit_branch.php?id=<?= $row['branch_id'] ?>" class="btn btn-outline-primary btn-sm border-0" title="แก้ไข">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <button onclick="deleteBranch(<?= $row['branch_id'] ?>, '<?= addslashes($row['branch_name']) ?>')" class="btn btn-outline-danger btn-sm border-0" title="ลบ">
                                <i class="bi bi-trash3-fill"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="<?= $is_super_admin ? 7 : 6 ?>" class="text-center py-5 text-muted">
                        <i class="bi bi-shop-window fs-1 text-secondary opacity-25"></i>
                        <p class="mt-2 mb-0">ไม่พบข้อมูลสาขา</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php exit(); } // จบส่วน AJAX ?>

<?php
// เตรียมข้อมูลสำหรับ Dropdown Filter (เฉพาะ Admin)
$shops = ($is_super_admin) ? $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name") : null;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการข้อมูลสาขา - Mobile Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body { background-color: <?= $background_color ?>; font-family: '<?= $font_style ?>', sans-serif; color: <?= $text_color ?>; }
        .main-card { border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .table-hover tbody tr:hover { background-color: rgba(var(--bs-primary-rgb), 0.02); }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container py-2">
                    
                    <div class="main-card card bg-white">
                        <div class="card-header bg-white py-3 border-bottom-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h4 class="mb-0 text-primary fw-bold"><i class="bi bi-shop me-2"></i>รายการสาขา (Branches)</h4>
                            <a href="add_branch.php" class="btn btn-success btn-sm fw-bold px-3 shadow-sm rounded-pill">
                                <i class="bi bi-plus-lg me-1"></i> เพิ่มสาขาใหม่
                            </a>
                        </div>

                        <div class="card-body p-4">
                            <div class="row g-3 mb-4 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label small text-muted fw-bold">ค้นหา</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                                        <input type="text" id="searchInput" class="form-control border-start-0 bg-light" placeholder="ชื่อสาขา, รหัส, เบอร์โทร...">
                                    </div>
                                </div>
                                
                                <?php if ($is_super_admin): ?>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold">กรองตามร้านค้า</label>
                                    <select id="shopFilter" class="form-select">
                                        <option value="">-- แสดงทุกร้าน --</option>
                                        <?php while($s = $shops->fetch_assoc()): ?>
                                            <option value="<?= $s['shop_id'] ?>"><?= $s['shop_name'] ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div id="tableContainer">
                                <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
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
        function fetchBranchData() {
            const params = new URLSearchParams({
                ajax: 1,
                search: document.getElementById('searchInput').value,
                shop_filter: document.getElementById('shopFilter')?.value || ''
            });

            fetch(`branch.php?${params.toString()}`)
                .then(res => res.text())
                .then(data => document.getElementById('tableContainer').innerHTML = data);
        }

        // Event Listeners
        document.getElementById('searchInput').addEventListener('input', fetchBranchData);
        if(document.getElementById('shopFilter')) {
            document.getElementById('shopFilter').addEventListener('change', fetchBranchData);
        }

        // ลบข้อมูล
        function deleteBranch(id, name) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: `คุณต้องการลบสาขา "${name}" ใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ลบข้อมูล',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `delete_branch.php?id=${id}`;
                }
            });
        }

        // เริ่มทำงาน
        window.onload = fetchBranchData;
    </script>
</body>
</html>