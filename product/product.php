<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'product');

// [1] รับค่าพื้นฐานจาก Session
$shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// [2] ตรวจสอบสิทธิ์ Admin
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
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
    $brand_f = isset($_GET['brand']) ? $_GET['brand'] : '';
    $type_f = isset($_GET['type']) ? $_GET['type'] : '';
    $p_min = isset($_GET['p_min']) && $_GET['p_min'] !== '' ? (float)$_GET['p_min'] : '';
    $p_max = isset($_GET['p_max']) && $_GET['p_max'] !== '' ? (float)$_GET['p_max'] : '';
    $shop_f = isset($_GET['shop_filter']) ? $_GET['shop_filter'] : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20; // 1. แสดงรายการ 20 รายการต่อหน้า
    $offset = ($page - 1) * $limit;

    // 2. เงื่อนไขการกรอง (มองเห็นเฉพาะของร้านตนเอง หรือ Admin เลือกดู)
    $conditions = [];
    if (!$is_super_admin) {
        $conditions[] = "(p.shop_info_shop_id = '$shop_id' OR p.shop_info_shop_id = 0)";
    } elseif (!empty($shop_f)) {
        $conditions[] = "p.shop_info_shop_id = '$shop_f'";
    }

    if (!empty($search)) {
        $conditions[] = "(p.prod_name LIKE '%$search%' OR p.model_name LIKE '%$search%' OR p.prod_id LIKE '%$search%')";
    }
    if (!empty($brand_f)) $conditions[] = "p.prod_brands_brand_id = '$brand_f'";
    if (!empty($type_f)) $conditions[] = "p.prod_types_type_id = '$type_f'";
    if ($p_min !== '') $conditions[] = "p.prod_price >= $p_min";
    if ($p_max !== '') $conditions[] = "p.prod_price <= $p_max";

    $where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // นับจำนวนหน้าทั้งหมด
    $count_res = $conn->query("SELECT COUNT(*) as total FROM products p $where_sql");
    $total_items = $count_res->fetch_assoc()['total'];
    $total_pages = ceil($total_items / $limit);

    // ดึงข้อมูลพร้อมระบุสังกัดร้าน (Admin เห็นชื่อร้าน)
    $sql = "SELECT p.*, pb.brand_name_th as brand_name, pt.type_name_th as type_name, s.shop_name 
            FROM products p 
            LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id 
            LEFT JOIN prod_types pt ON p.prod_types_type_id = pt.type_id 
            LEFT JOIN shop_info s ON p.shop_info_shop_id = s.shop_id 
            $where_sql 
            ORDER BY p.prod_id DESC 
            LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);
?>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-center" width="5%">#</th>
                    <th width="10%">รหัส</th>
                    <th width="25%">ชื่อสินค้า / รุ่น</th>
                    <th width="15%">แบรนด์/ประเภท</th>
                    <th width="12%" class="text-end">ราคา</th>
                    <?php if ($is_super_admin): ?>
                        <th width="15%" class="text-center">สาขา/ร้าน</th>
                    <?php endif; ?>
                    <th width="13%" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0):
                    $idx = $offset + 1;
                    while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center text-muted fw-bold"><?= $idx++ ?></td>

                            <td class="text-center">
                                <div class="fw-bold text-primary"><?= htmlspecialchars($row['prod_code']) ?></div>
                                <small class="text-muted" style="font-size: 0.7rem;">ID: #<?= $row['prod_id'] ?></small>
                            </td>

                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['prod_name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($row['model_name']) ?></small>
                            </td>

                            <td>
                                <div class="small"><i class="bi bi-tag-fill me-1 text-primary"></i><?= htmlspecialchars($row['brand_name'] ?? '-') ?></div>
                                <div class="small text-muted"><i class="bi bi-grid-fill me-1"></i><?= htmlspecialchars($row['type_name'] ?? '-') ?></div>
                            </td>

                            <td class="text-end fw-bold text-success">฿<?= number_format($row['prod_price'], 2) ?></td>

                            <td class="text-center">
                                <?php if ($row['shop_info_shop_id'] == 0): ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2">
                                        <i class="bi bi-globe2 me-1"></i> ส่วนกลาง
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2">
                                        <i class="bi bi-shop me-1"></i> <?= htmlspecialchars($row['shop_name'] ?? 'ร้านค้า') ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <div class="btn-group gap-1">
                                    <?php if ($is_super_admin || $row['shop_info_shop_id'] == $shop_id): ?>
                                        <a href="edit_product.php?id=<?= $row['prod_id'] ?>" class="btn btn-outline-warning btn-sm border-0" title="แก้ไข">
                                            <i class="bi bi-pencil-square fs-5"></i>
                                        </a>
                                        <button onclick="confirmDelete(<?= $row['prod_id'] ?>, '<?= addslashes($row['prod_name']) ?>')" class="btn btn-outline-danger btn-sm border-0" title="ลบ">
                                            <i class="bi bi-trash3-fill fs-5"></i>
                                        </button>
                                    <?php else: ?>
                                        <i class="bi bi-lock-fill text-muted" title="ข้อมูลส่วนกลาง (อ่านอย่างเดียว)"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile;
                else: ?>
                    <tr>
                        <td colspan="<?= $is_super_admin ? 7 : 6 ?>" class="text-center py-5 text-muted">-- ไม่พบข้อมูลสินค้า --</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center pagination-sm">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link ajax-page-link" href="#" data-page="1" title="หน้าแรกสุด"><i class="bi bi-chevron-double-left"></i></a>
                </li>
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link ajax-page-link" href="#" data-page="<?= $page - 1 ?>" title="ย้อนกลับ"><i class="bi bi-chevron-left"></i></a>
                </li>
                <?php
                $range = 2;
                for ($i = 1; $i <= $total_pages; $i++):
                    if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)): ?>
                        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                            <a class="page-link ajax-page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php elseif (($i == $page - $range - 1) || ($i == $page + $range + 1)): echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    endif; ?>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link ajax-page-link" href="#" data-page="<?= $page + 1 ?>" title="ถัดไป"><i class="bi bi-chevron-right"></i></a>
                </li>
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link ajax-page-link" href="#" data-page="<?= $total_pages ?>" title="หน้าสุดท้าย"><i class="bi bi-chevron-double-right"></i></a>
                </li>
            </ul>
        </nav>
        <div class="d-flex justify-content-center mt-2 gap-2 align-items-center">
            <div class="input-group input-group-sm" style="max-width: 150px;">
                <input type="number" id="jumpPageInput" class="form-control text-center" placeholder="ไปหน้า" min="1" max="<?= $total_pages ?>">
                <button class="btn btn-success" type="button" id="btnJumpPage">ไป</button>
            </div>
            <div class="small text-muted">หน้า <?= $page ?> / <?= $total_pages ?> (รวม <?= number_format($total_items) ?> รายการ)</div>
        </div>
    <?php endif; ?>
<?php exit();
}

// โหลดข้อมูลตัวกรอง (Dropdown)
$filter_shop = $is_super_admin ? "1=1" : "shop_info_shop_id = '$shop_id'";
$brands_res = $conn->query("SELECT brand_id, brand_name_th FROM prod_brands WHERE $filter_shop ORDER BY brand_name_th ASC");
$types_res = $conn->query("SELECT type_id, type_name_th FROM prod_types WHERE $filter_shop ORDER BY type_name_th ASC");
$shops_res = $is_super_admin ? $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name ASC") : null;
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>จัดการสินค้า - Mobile Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: #f8fafc;
            font-family: 'Prompt', sans-serif;
        }

        .main-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .card-header-custom {
            background: linear-gradient(135deg, #198754 0%, #14532d 100%);
            padding: 1.5rem;
        }

        /* หัวข้อสีขาวตามหน้าอำเภอ */
        .card-header-custom h4 {
            color: #ffffff !important;
            font-weight: 600;
            margin-bottom: 0;
        }

        .pagination .page-link {
            border-radius: 8px;
            margin: 0 3px;
            color: #198754;
            font-weight: 600;
            border: none;
        }

        .pagination .page-item.active .page-link {
            background-color: #198754;
            color: white;
        }

        .filter-section {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #edf2f7;
        }
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
                            <h4><i class="bi bi-box-seam-fill me-2"></i>ระบบจัดการรายการสินค้า</h4>
                            <a href="add_product.php" class="btn btn-light btn-sm fw-bold">
                                <i class="bi bi-plus-circle-fill me-1"></i> เพิ่มสินค้าใหม่
                            </a>
                        </div>

                        <div class="card-body p-4">
                            <div class="filter-section shadow-sm">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-muted">ค้นหาทั่วไป</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                            <input type="text" id="searchInput" class="form-control border-start-0" placeholder="ชื่อสินค้า, รุ่น, รหัส...">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold text-muted">ยี่ห้อ</label>
                                        <select id="brandFilter" class="form-select">
                                            <option value="">-- ทั้งหมด --</option>
                                            <?php while ($b = $brands_res->fetch_assoc()): ?>
                                                <option value="<?= $b['brand_id'] ?>"><?= htmlspecialchars($b['brand_name_th']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold text-muted">ประเภท</label>
                                        <select id="typeFilter" class="form-select">
                                            <option value="">-- ทั้งหมด --</option>
                                            <?php while ($t = $types_res->fetch_assoc()): ?>
                                                <option value="<?= $t['type_id'] ?>"><?= htmlspecialchars($t['type_name_th']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold text-muted">ราคาต่ำสุด (฿)</label>
                                        <input type="number" id="pMinInput" class="form-control" placeholder="0">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold text-muted">ราคาสูงสุด (฿)</label>
                                        <input type="number" id="pMaxInput" class="form-control" placeholder="ไม่จำกัด">
                                    </div>

                                    <?php if ($is_super_admin): ?>
                                        <div class="col-md-4 mt-3">
                                            <label class="form-label small fw-bold text-muted text-primary"><i class="bi bi-shop me-1"></i>กรองตามร้านค้า (Admin Only)</label>
                                            <select id="shopFilter" class="form-select border-primary border-opacity-25">
                                                <option value="">-- สินค้าทุกร้านในระบบ --</option>
                                                <?php while ($s = $shops_res->fetch_assoc()): ?>
                                                    <option value="<?= $s['shop_id'] ?>">ร้าน: <?= htmlspecialchars($s['shop_name']) ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div id="tableContainer">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-success"></div>
                                    <p class="mt-2 text-muted">กำลังโหลดข้อมูลสินค้า...</p>
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
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>ยืนยันการลบ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <p class="fs-5 mb-1">ต้องการลบสินค้า <strong id="delName"></strong> ?</p>
                    <p class="text-danger small mb-0">ข้อมูลนี้จะถูกลบถาวรและส่งผลต่อรายงานสต็อก</p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <a id="confirmDelBtn" href="#" class="btn btn-danger px-4 shadow-sm">ยืนยันการลบ</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function fetchProductData(page = 1) {
            const search = document.getElementById('searchInput').value;
            const brand = document.getElementById('brandFilter').value;
            const type = document.getElementById('typeFilter').value;
            const pMin = document.getElementById('pMinInput').value;
            const pMax = document.getElementById('pMaxInput').value;
            const shop = document.getElementById('shopFilter')?.value || '';

            const params = new URLSearchParams({
                ajax: 1,
                page,
                search,
                brand,
                type,
                p_min: pMin,
                p_max: pMax,
                shop_filter: shop
            });

            fetch(`product.php?${params.toString()}`)
                .then(res => res.text())
                .then(data => document.getElementById('tableContainer').innerHTML = data);
        }

        // จัดการ Event สำหรับตัวกรองทั้งหมด
        ['searchInput', 'pMinInput', 'pMaxInput'].forEach(id => {
            document.getElementById(id).addEventListener('input', () => fetchProductData(1));
        });
        ['brandFilter', 'typeFilter', 'shopFilter'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', () => fetchProductData(1));
        });

        // จัดการคลิก Pagination และปุ่ม Jump
        document.addEventListener('click', e => {
            if (e.target.classList.contains('ajax-page-link') || e.target.closest('.ajax-page-link')) {
                e.preventDefault();
                const link = e.target.classList.contains('ajax-page-link') ? e.target : e.target.closest('.ajax-page-link');
                fetchProductData(link.dataset.page);
            }
            if (e.target.id === 'btnJumpPage') {
                const p = document.getElementById('jumpPageInput').value;
                if (p > 0) fetchProductData(p);
            }
        });

        function confirmDelete(id, name) {
            document.getElementById('delName').innerText = name;
            document.getElementById('confirmDelBtn').href = `delete_product.php?id=${id}`;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        window.onload = () => fetchProductData();
    </script>
</body>

</html>