<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'prod_stock');

// [1] รับค่าพื้นฐานจาก Session
$branch_id = $_SESSION['branch_id'];
$current_user_id = $_SESSION['user_id'];

// =========================================================
// [CHECK PERMISSION] ตรวจสอบสิทธิ์ต่างๆ
// =========================================================

// 1. ตรวจสอบว่าเป็น Admin (Super Admin) หรือไม่
$is_super_admin = false;
$chk_admin_sql = "SELECT r.role_name FROM roles r 
                  JOIN user_roles ur ON r.role_id = ur.roles_role_id 
                  WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt = $conn->prepare($chk_admin_sql)) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $is_super_admin = true;
    $stmt->close();
}

// 2. ตรวจสอบสิทธิ์ "centralinf" (เผื่อใช้ในอนาคต)
$has_central_perm = false;
if ($is_super_admin) {
    $has_central_perm = true;
} else {
    $chk_perm_sql = "SELECT p.permission_name FROM permissions p
                     JOIN role_permissions rp ON p.permission_id = rp.permissions_permission_id
                     JOIN user_roles ur ON rp.roles_role_id = ur.roles_role_id
                     WHERE ur.users_user_id = ? AND p.permission_name = 'centralinf'";
    if ($stmt = $conn->prepare($chk_perm_sql)) {
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) $has_central_perm = true;
        $stmt->close();
    }
}

// =========================================================
// [3] ส่วนประมวลผล AJAX
// =========================================================
if (isset($_GET['ajax'])) {
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
    $brand_f = isset($_GET['brand']) ? $_GET['brand'] : '';
    $type_f = isset($_GET['type']) ? $_GET['type'] : '';
    $status_f = isset($_GET['status']) ? $_GET['status'] : '';
    $p_min = isset($_GET['p_min']) && $_GET['p_min'] !== '' ? (float)$_GET['p_min'] : '';
    $p_max = isset($_GET['p_max']) && $_GET['p_max'] !== '' ? (float)$_GET['p_max'] : '';
    
    // รับค่าตัวกรองร้าน/สาขา (สำหรับ Admin)
    $shop_f = isset($_GET['shop_filter']) ? $_GET['shop_filter'] : '';
    $branch_f = isset($_GET['branch_filter']) ? $_GET['branch_filter'] : '';

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20; 
    $offset = ($page - 1) * $limit;

    // --- สร้างเงื่อนไข WHERE ---
    $conditions = [];
    
    if ($is_super_admin) {
        // [Admin]
        // 1. ถ้าเลือกสาขา -> กรองตามสาขา
        // 2. ถ้าเลือกร้าน (แต่ไม่เลือกสาขา) -> กรองตามร้าน
        // 3. ถ้าไม่เลือกอะไรเลย -> ไม่กรอง (เห็นทั้งหมด)
        if (!empty($branch_f)) {
            $conditions[] = "ps.branches_branch_id = '$branch_f'";
        } elseif (!empty($shop_f)) {
            $conditions[] = "b.shop_info_shop_id = '$shop_f'";
        }
    } else {
        // [User ทั่วไป] 
        // แสดงเฉพาะสินค้าของสาขาตัวเองเท่านั้น (Strict Mode)
        $conditions[] = "ps.branches_branch_id = '$branch_id'";
    }

    if (!empty($search)) $conditions[] = "(p.prod_name LIKE '%$search%' OR p.model_name LIKE '%$search%' OR ps.serial_no LIKE '%$search%' OR ps.stock_id LIKE '%$search%')";
    if (!empty($brand_f)) $conditions[] = "p.prod_brands_brand_id = '$brand_f'";
    if (!empty($type_f)) $conditions[] = "p.prod_types_type_id = '$type_f'";
    if (!empty($status_f)) $conditions[] = "ps.stock_status = '$status_f'";
    if ($p_min !== '') $conditions[] = "ps.price >= $p_min";
    if ($p_max !== '') $conditions[] = "ps.price <= $p_max";

    $where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // นับจำนวนหน้า
    $count_sql = "SELECT COUNT(*) as total 
                  FROM prod_stocks ps 
                  JOIN products p ON ps.products_prod_id = p.prod_id 
                  LEFT JOIN branches b ON ps.branches_branch_id = b.branch_id 
                  LEFT JOIN shop_info s ON b.shop_info_shop_id = s.shop_id 
                  $where_sql";
    $total_items = $conn->query($count_sql)->fetch_assoc()['total'];
    $total_pages = ceil($total_items / $limit);

    // ดึงข้อมูล
    $sql = "SELECT ps.*, p.prod_name, p.model_name, pb.brand_name_th, pt.type_name_th, 
                   b.branch_name, s.shop_name, ps.branches_branch_id
            FROM prod_stocks ps 
            LEFT JOIN products p ON ps.products_prod_id = p.prod_id 
            LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id 
            LEFT JOIN prod_types pt ON p.prod_types_type_id = pt.type_id 
            LEFT JOIN branches b ON ps.branches_branch_id = b.branch_id 
            LEFT JOIN shop_info s ON b.shop_info_shop_id = s.shop_id 
            $where_sql 
            ORDER BY ps.stock_id DESC 
            LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);
?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-center" width="5%">#</th>
                    <th width="10%">รหัสสต็อก</th>
                    <th width="25%">สินค้า / รุ่น</th>
                    <th width="15%">ยี่ห้อ/ประเภท</th>
                    <th width="10%" class="text-end">ราคา</th>
                    <th width="10%" class="text-center">สถานะ</th>
                    <th width="15%" class="text-center">สาขา/ร้าน</th>
                    <th width="10%" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): $idx = $offset + 1;
                    while ($row = $result->fetch_assoc()):
                        $status_class = match ($row['stock_status']) {
                            'In Stock' => 'bg-success',
                            'Sold' => 'bg-danger',
                            'Damage' => 'bg-warning text-dark',
                            'Repair' => 'bg-info',
                            default => 'bg-secondary'
                        };
                        
                        // ตรวจสอบว่าเป็นของส่วนกลางหรือไม่
                        $is_central_stock = ($row['branches_branch_id'] == 0);
                        
                        // [Logic สิทธิ์การแก้ไข]
                        $can_edit = false;
                        if ($is_super_admin) {
                            $can_edit = true; // แอดมินทำได้หมด
                        } elseif ($is_central_stock) {
                            $can_edit = $has_central_perm; 
                        } elseif ($row['branches_branch_id'] == $branch_id) {
                            $can_edit = true; // แก้ไขของตัวเองได้
                        }
                ?>
                        <tr>
                            <td class="text-center fw-bold text-muted small"><?= $idx++ ?></td>
                            <td class="text-center small"><span class="badge bg-light text-dark border">#<?= $row['stock_id'] ?></span></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['prod_name']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($row['model_name']) ?></div>
                                <div class="small text-secondary">S/N: <?= htmlspecialchars($row['serial_no']) ?></div>
                            </td>
                            <td>
                                <div class="small text-primary"><i class="bi bi-tag-fill me-1"></i><?= htmlspecialchars($row['brand_name_th'] ?? '-') ?></div>
                                <div class="small text-muted"><i class="bi bi-grid-fill me-1"></i><?= htmlspecialchars($row['type_name_th'] ?? '-') ?></div>
                            </td>
                            <td class="text-end fw-bold text-success">฿<?= number_format($row['price'], 2) ?></td>
                            <td class="text-center"><span class="badge <?= $status_class ?> bg-opacity-10 text-dark border px-3 rounded-pill"><?= $row['stock_status'] ?></span></td>
                            
                            <td class="text-center small">
                                <?php if ($is_central_stock): ?>
                                    <span class="badge bg-dark"><i class="bi bi-globe2 me-1"></i> ส่วนกลาง</span>
                                <?php else: ?>
                                    <div class="fw-bold"><?= htmlspecialchars($row['shop_name'] ?? '-') ?></div>
                                    <div class="text-muted"><?= htmlspecialchars($row['branch_name'] ?? '-') ?></div>
                                <?php endif; ?>
                            </td>
                            
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <?php if ($can_edit): ?>
                                        <a href="edit_stock.php?id=<?= $row['stock_id'] ?>" class="btn btn-outline-warning btn-sm border-0"><i class="bi bi-pencil-square"></i></a>
                                        <?php if ($row['stock_status'] != 'Sold'): ?>
                                            <button onclick="confirmDelete(<?= $row['stock_id'] ?>, '<?= addslashes($row['prod_name']) ?>')" class="btn btn-outline-danger btn-sm border-0"><i class="bi bi-trash3-fill"></i></button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button class="btn btn-outline-secondary btn-sm border-0" disabled title="ไม่มีสิทธิ์แก้ไข">
                                            <i class="bi bi-lock-fill"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile;
                else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">-- ไม่พบข้อมูลสินค้าในสต็อก --</td>
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
}

// [4] โหลดข้อมูล Dropdown สำหรับหน้าเว็บหลัก
// สำหรับตัวกรอง Brand/Type (ถ้าเป็นแอดมินเห็นหมด ถ้า User เห็นเฉพาะของร้านตัวเอง)
$filter_shop_sql = $is_super_admin ? "1=1" : "(shop_info_shop_id = '{$_SESSION['shop_id']}' OR shop_info_shop_id = 0)";

$brands_res = $conn->query("SELECT brand_id, brand_name_th FROM prod_brands WHERE $filter_shop_sql ORDER BY brand_name_th ASC");
$types_res = $conn->query("SELECT type_id, type_name_th FROM prod_types WHERE $filter_shop_sql ORDER BY type_name_th ASC");

// โหลดรายชื่อร้าน/สาขา สำหรับ Admin Filter
if ($is_super_admin) {
    $all_shops = $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name ASC");
    $all_branches = $conn->query("SELECT branch_id, branch_name, shop_info_shop_id FROM branches ORDER BY branch_name ASC");
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สต็อกสินค้า - Mobile Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background-color: #f8fafc; font-family: 'Prompt', sans-serif; }
        .main-card { border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); overflow: hidden; }
        .card-header-custom { background: linear-gradient(135deg, #198754 0%, #14532d 100%); padding: 1.5rem; }
        .card-header-custom h4 { color: #ffffff !important; font-weight: 600; margin-bottom: 0; }
        .pagination .page-link { border-radius: 8px; margin: 0 3px; color: #198754; font-weight: 600; border: none; }
        .pagination .page-item.active .page-link { background-color: #198754; color: white; }
        .form-select-sm-custom { font-size: 0.9rem; padding: 0.4rem 2rem 0.4rem 0.75rem; }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container py-2" style="max-width: 1400px;">

                    <div class="main-card card">
                        <div class="card-header-custom d-flex justify-content-between align-items-center">
                            <h4><i class="bi bi-box-fill me-2"></i>จัดการสต็อกสินค้า (Product Stocks)</h4>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-light btn-sm fw-bold px-3" onclick="toggleFilter()">
                                    <i class="bi bi-filter me-1"></i> <span id="filterBtnText">กรองข้อมูล</span>
                                </button>
                                <a href="add_prodStock.php" class="btn btn-success btn-sm fw-bold px-3 border border-white">
                                    <i class="bi bi-plus-circle me-1"></i> เพิ่มสต็อกใหม่
                                </a>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            <div class="card bg-light border-0 mb-4" id="filterCard" style="display: none; border-radius: 12px;">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-3">
                                        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-sliders me-2"></i>ตั้งค่าการกรองข้อมูล</h6>
                                        <button class="btn btn-link btn-sm text-danger p-0 text-decoration-none" onclick="clearFilters()">
                                            <i class="bi bi-arrow-counterclockwise"></i> ล้างค่าทั้งหมด
                                        </button>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="small text-muted mb-1">ยี่ห้อ</label>
                                            <select id="brandFilter" class="form-select border-0 shadow-sm">
                                                <option value="">-- ทั้งหมด --</option>
                                                <?php while ($b = $brands_res->fetch_assoc()): ?>
                                                    <option value="<?= $b['brand_id'] ?>"><?= htmlspecialchars($b['brand_name_th']) ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small text-muted mb-1">ประเภท</label>
                                            <select id="typeFilter" class="form-select border-0 shadow-sm">
                                                <option value="">-- ทั้งหมด --</option>
                                                <?php while ($t = $types_res->fetch_assoc()): ?>
                                                    <option value="<?= $t['type_id'] ?>"><?= htmlspecialchars($t['type_name_th']) ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small text-muted mb-1">สถานะ</label>
                                            <select id="statusFilter" class="form-select border-0 shadow-sm">
                                                <option value="">-- ทุกสถานะ --</option>
                                                <option value="In Stock">In Stock (พร้อมขาย)</option>
                                                <option value="Sold">Sold (ขายแล้ว)</option>
                                                <option value="Damage">Damage (ชำรุด)</option>
                                                <option value="Repair">Repair (ส่งซ่อม)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small text-muted mb-1">ราคา (ต่ำสุด - สูงสุด)</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" id="pMinInput" class="form-control border-0 shadow-sm" placeholder="0">
                                                <input type="number" id="pMaxInput" class="form-control border-0 shadow-sm" placeholder="ไม่จำกัด">
                                            </div>
                                        </div>

                                        <?php if ($is_super_admin): ?>
                                            <div class="col-12"><hr class="my-2 opacity-10"></div>
                                            <div class="col-md-6">
                                                <label class="small fw-bold text-primary mb-1"><i class="bi bi-shop me-1"></i> ร้านค้า (Shop)</label>
                                                <select id="shopFilter" class="form-select border-primary border-opacity-25 shadow-sm bg-primary bg-opacity-10">
                                                    <option value="">-- แสดงทุกร้านค้า --</option>
                                                    <?php while ($s = $all_shops->fetch_assoc()): ?>
                                                        <option value="<?= $s['shop_id'] ?>"><?= htmlspecialchars($s['shop_name']) ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="small fw-bold text-primary mb-1"><i class="bi bi-geo-alt me-1"></i> สาขา (Branch)</label>
                                                <select id="branchFilter" class="form-select border-primary border-opacity-25 shadow-sm bg-primary bg-opacity-10">
                                                    <option value="">-- แสดงทุกสาขา --</option>
                                                    <?php mysqli_data_seek($all_branches, 0);
                                                    while ($br = $all_branches->fetch_assoc()): ?>
                                                        <option value="<?= $br['branch_id'] ?>" data-shop="<?= $br['shop_info_shop_id'] ?>">
                                                            <?= htmlspecialchars($br['branch_name']) ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-5">
                                    <div class="input-group shadow-sm" style="border-radius: 10px; overflow: hidden;">
                                        <span class="input-group-text bg-white border-0 ps-3"><i class="bi bi-search text-muted"></i></span>
                                        <input type="text" id="searchInput" class="form-control border-0 py-2" placeholder="ค้นหาชื่อสินค้า, รุ่น, รหัสสต็อก หรือ S/N...">
                                    </div>
                                </div>
                            </div>

                            <div id="tableContainer">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-success" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">กำลังดึงข้อมูลสต็อก...</p>
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
                    <h5 class="modal-title text-white"><i class="bi bi-exclamation-triangle me-2"></i>ยืนยันการลบ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <p class="fs-5 mb-1">ต้องการลบสต็อกสินค้า <strong id="delName" class="text-danger"></strong> ?</p>
                    <p class="text-secondary small mb-0">การลบสต็อกจะทำให้ข้อมูลหายไปจากระบบทันที</p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal">ยกเลิก</button>
                    <a id="confirmDelBtn" href="#" class="btn btn-danger px-4 rounded-pill">ยืนยันการลบ</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ฟังก์ชันดึงข้อมูลผ่าน AJAX
        function fetchStockData(page = 1) {
            const search = document.getElementById('searchInput').value;
            const brand = document.getElementById('brandFilter').value;
            const type = document.getElementById('typeFilter').value;
            const status = document.getElementById('statusFilter').value;
            const pMin = document.getElementById('pMinInput').value;
            const pMax = document.getElementById('pMaxInput').value;
            
            // รับค่าจาก Shop/Branch Filter (ถ้ามี)
            const shop = document.getElementById('shopFilter')?.value || '';
            const branch = document.getElementById('branchFilter')?.value || '';

            const params = new URLSearchParams({
                ajax: 1,
                page,
                search,
                brand,
                type,
                status,
                p_min: pMin,
                p_max: pMax,
                shop_filter: shop,
                branch_filter: branch
            });

            // แสดง Loading เล็กๆ (Optional)
            // document.getElementById('tableContainer').style.opacity = '0.5';

            fetch(`prod_stock.php?${params.toString()}`)
                .then(res => res.text())
                .then(data => {
                    document.getElementById('tableContainer').innerHTML = data;
                    // document.getElementById('tableContainer').style.opacity = '1';
                });
        }

        // Toggle Filter Box
        function toggleFilter() {
            const card = document.getElementById('filterCard');
            const btnText = document.getElementById('filterBtnText');
            if (card.style.display === 'none') {
                card.style.display = 'block';
                btnText.innerText = 'ปิดตัวกรอง';
            } else {
                card.style.display = 'none';
                btnText.innerText = 'กรองข้อมูล';
            }
        }

        // Clear All Filters
        function clearFilters() {
            ['brandFilter', 'typeFilter', 'statusFilter', 'pMinInput', 'pMaxInput', 'shopFilter', 'branchFilter'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            // รีเซ็ตตัวเลือกสาขาให้แสดงทั้งหมดก่อน
            const branchSelect = document.getElementById('branchFilter');
            if(branchSelect) {
                Array.from(branchSelect.options).forEach(opt => opt.style.display = 'block');
            }
            fetchStockData(1);
        }

        // Event Listeners สำหรับการเปลี่ยนแปลงค่าต่างๆ
        ['searchInput', 'pMinInput', 'pMaxInput'].forEach(id => {
            const el = document.getElementById(id);
            if(el) el.addEventListener('input', () => fetchStockData(1));
        });
        
        ['brandFilter', 'typeFilter', 'statusFilter', 'shopFilter', 'branchFilter'].forEach(id => {
            const el = document.getElementById(id);
            if(el) el.addEventListener('change', () => fetchStockData(1));
        });

        // Logic กรองสาขา เมื่อเลือกร้านค้า
        document.getElementById('shopFilter')?.addEventListener('change', function() {
            const shopId = this.value;
            const branchSelect = document.getElementById('branchFilter');
            branchSelect.value = ''; // รีเซ็ตค่าสาขาที่เลือก
            
            Array.from(branchSelect.options).forEach(opt => {
                if (opt.value === '') {
                    opt.style.display = 'block'; // แสดงตัวเลือก "ทั้งหมด" เสมอ
                } else {
                    // แสดงเฉพาะสาขาที่ shop_id ตรงกัน หรือแสดงทั้งหมดถ้าไม่ได้เลือกร้าน
                    opt.style.display = (shopId === '' || opt.dataset.shop === shopId) ? 'block' : 'none';
                }
            });
            fetchStockData(1); // รีโหลดข้อมูลทันที
        });

        // Handle Pagination Clicks
        document.addEventListener('click', e => {
            if (e.target.classList.contains('ajax-page-link') || e.target.closest('.ajax-page-link')) {
                e.preventDefault();
                const link = e.target.classList.contains('ajax-page-link') ? e.target : e.target.closest('.ajax-page-link');
                fetchStockData(link.dataset.page);
            }
        });

        function confirmDelete(id, name) {
            document.getElementById('delName').innerText = name;
            document.getElementById('confirmDelBtn').href = `delete_prodstock.php?id=${id}`;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // โหลดข้อมูลครั้งแรก
        window.onload = () => fetchStockData();
    </script>
</body>
</html>