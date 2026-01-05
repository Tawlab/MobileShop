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
    $limit = 20; // แสดงรายการ 20 รายการต่อหน้า
    $offset = ($page - 1) * $limit;

    // 2. เงื่อนไขการกรอง
    $conditions = [];
    
    // กรองตามสิทธิ์ (ร้านใครร้านมัน หรือ Admin เลือกดู)
    if (!$is_super_admin) {
        $conditions[] = "(p.shop_info_shop_id = '$shop_id' OR p.shop_info_shop_id = 0)";
    } elseif (!empty($shop_f)) {
        $conditions[] = "p.shop_info_shop_id = '$shop_f'";
    }

    // [แก้ไข] ค้นหาจาก รหัสสินค้า, ชื่อสินค้า, รุ่นสินค้า
    if (!empty($search)) {
        $conditions[] = "(p.prod_code LIKE '%$search%' OR p.prod_name LIKE '%$search%' OR p.model_name LIKE '%$search%')";
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

    // ดึงข้อมูลพร้อมระบุสังกัดร้าน
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
                    <th width="12%">รหัสสินค้า</th>
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
                                <span class="badge bg-light text-dark border fw-bold"><?= htmlspecialchars($row['prod_code']) ?></span>
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
                        <td colspan="<?= $is_super_admin ? 7 : 6 ?>" class="text-center py-5 text-muted">
                            <i class="bi bi-search display-6 d-block mb-3 opacity-50"></i>
                            -- ไม่พบข้อมูลสินค้าตามเงื่อนไข --
                        </td>
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
    <?php endif; ?>
<?php exit();
}

// [4] โหลดข้อมูลตัวเลือกสำหรับ Dropdown (แก้ไขให้แสดงข้อมูลทั้งหมด ไม่กรองร้านค้า เพื่อให้ Dropdown มีค่า)
// เราสมมติว่า Brand และ Type เป็นข้อมูล Master Data ที่ใช้ร่วมกัน
$brands_res = $conn->query("SELECT brand_id, brand_name_th FROM prod_brands ORDER BY brand_name_th ASC");
$types_res = $conn->query("SELECT type_id, type_name_th FROM prod_types ORDER BY type_name_th ASC");
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            background-color: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #dee2e6;
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
                            <a href="add_product.php" class="btn btn-light btn-sm fw-bold shadow-sm">
                                <i class="bi bi-plus-circle-fill me-1"></i> เพิ่มสินค้าใหม่
                            </a>
                        </div>

                        <div class="card-body p-4">
                            
                            <div class="d-flex justify-content-between mb-3">
                                <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="true" aria-controls="filterCollapse">
                                    <i class="bi bi-funnel-fill me-2"></i> แสดง/ซ่อน ตัวกรอง
                                </button>
                                <span class="text-muted small align-self-center"><i class="bi bi-info-circle me-1"></i>ค้นหาจาก รหัส, ชื่อ, หรือรุ่นสินค้า</span>
                            </div>

                            <div class="collapse show" id="filterCollapse">
                                <div class="filter-section shadow-sm">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-muted">ค้นหา (รหัส/ชื่อ/รุ่น)</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                                <input type="text" id="searchInput" class="form-control border-start-0" placeholder="พิมพ์คำค้นหา...">
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
                                            <label class="form-label small fw-bold text-muted">ราคาต่ำสุด</label>
                                            <input type="number" id="pMinInput" class="form-control" placeholder="0">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-bold text-muted">ราคาสูงสุด</label>
                                            <input type="number" id="pMaxInput" class="form-control" placeholder="ไม่จำกัด">
                                        </div>

                                        <?php if ($is_super_admin): ?>
                                            <div class="col-md-4">
                                                <label class="form-label small fw-bold text-muted text-primary">กรองร้านค้า (Admin)</label>
                                                <select id="shopFilter" class="form-select border-primary border-opacity-25">
                                                    <option value="">-- ทุกร้าน --</option>
                                                    <?php while ($s = $shops_res->fetch_assoc()): ?>
                                                        <option value="<?= $s['shop_id'] ?>">ร้าน: <?= htmlspecialchars($s['shop_name']) ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        <?php endif; ?>

                                        <div class="col-12 text-end border-top pt-3 mt-3">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="clearFilters()">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i> ล้างค่าการค้นหา
                                            </button>
                                        </div>
                                    </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ฟังก์ชันโหลดข้อมูล (AJAX)
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

            // แสดง Loading ระหว่างรอ
            // document.getElementById('tableContainer').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div></div>';

            fetch(`product.php?${params.toString()}`)
                .then(res => res.text())
                .then(data => document.getElementById('tableContainer').innerHTML = data)
                .catch(err => console.error('Error fetching data:', err));
        }

        // ฟังก์ชันล้างค่าตัวกรอง
        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('brandFilter').value = '';
            document.getElementById('typeFilter').value = '';
            document.getElementById('pMinInput').value = '';
            document.getElementById('pMaxInput').value = '';
            if(document.getElementById('shopFilter')) document.getElementById('shopFilter').value = '';
            
            fetchProductData(1); // โหลดข้อมูลใหม่
        }

        // จัดการ Event สำหรับตัวกรอง (พิมพ์แล้วค้นหาเลย หรือเปลี่ยนค่าแล้วค้นหาเลย)
        ['searchInput', 'pMinInput', 'pMaxInput'].forEach(id => {
            document.getElementById(id).addEventListener('input', () => {
                // ใช้ Timeout เล็กน้อยเพื่อไม่ให้ยิง request ถี่เกินไปตอนพิมพ์
                clearTimeout(window.searchTimeout);
                window.searchTimeout = setTimeout(() => fetchProductData(1), 500);
            });
        });
        ['brandFilter', 'typeFilter', 'shopFilter'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', () => fetchProductData(1));
        });

        // จัดการคลิก Pagination
        document.addEventListener('click', e => {
            if (e.target.classList.contains('ajax-page-link') || e.target.closest('.ajax-page-link')) {
                e.preventDefault();
                const link = e.target.classList.contains('ajax-page-link') ? e.target : e.target.closest('.ajax-page-link');
                fetchProductData(link.dataset.page);
            }
        });

        // SweetAlert สำหรับยืนยันการลบ
        function confirmDelete(id, name) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: `คุณต้องการลบสินค้า "${name}" ใช่หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `delete_product.php?id=${id}`;
                }
            });
        }

        // โหลดข้อมูลครั้งแรกเมื่อเข้าหน้าเว็บ
        window.onload = () => fetchProductData();
    </script>
</body>

</html>