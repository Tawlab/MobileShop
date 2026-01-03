<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'purchase_order');

// [1] รับค่าพื้นฐานจาก Session
$branch_id = $_SESSION['branch_id'];
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
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
    $status_f = isset($_GET['status']) ? $_GET['status'] : '';
    $supplier_f = isset($_GET['supplier']) ? $_GET['supplier'] : '';
    $shop_f = isset($_GET['shop_filter']) ? $_GET['shop_filter'] : '';
    $branch_f = isset($_GET['branch_filter']) ? $_GET['branch_filter'] : '';

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20; // 2. แสดงรายการ 20 รายการต่อหน้า
    $offset = ($page - 1) * $limit;

    // 3. กรองตามสิทธิ์ (สาขาตัวเอง vs ทั้งหมดสำหรับ Admin)
    $conditions = [];
    if (!$is_super_admin) {
        $conditions[] = "po.branches_branch_id = '$branch_id'";
    } else {
        if (!empty($branch_f)) $conditions[] = "po.branches_branch_id = '$branch_f'";
        elseif (!empty($shop_f)) $conditions[] = "b.shop_info_shop_id = '$shop_f'";
    }

    if (!empty($search)) {
        $conditions[] = "(po.purchase_id LIKE '%$search%' OR s.co_name LIKE '%$search%' OR e.firstname_th LIKE '%$search%')";
    }
    if (!empty($status_f)) $conditions[] = "po.po_status = '$status_f'";
    if (!empty($supplier_f)) $conditions[] = "po.suppliers_supplier_id = '$supplier_f'";

    $where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // นับจำนวนหน้า
    $count_sql = "SELECT COUNT(*) as total FROM purchase_orders po LEFT JOIN suppliers s ON po.suppliers_supplier_id = s.supplier_id LEFT JOIN branches b ON po.branches_branch_id = b.branch_id $where_sql";
    $total_items = $conn->query($count_sql)->fetch_assoc()['total'];
    $total_pages = ceil($total_items / $limit);

    // ดึงข้อมูลพร้อมระบุสังกัด (กรณี Admin)
    $sql = "SELECT po.*, s.co_name as supplier_name, b.branch_name, sh.shop_name, e.firstname_th, e.lastname_th,
            (SELECT SUM(od.price * od.amount) FROM order_details od WHERE od.purchase_orders_purchase_id = po.purchase_id) as total_amount,
            (SELECT SUM(od.amount) FROM order_details od WHERE od.purchase_orders_purchase_id = po.purchase_id) as total_qty,
            (SELECT COUNT(DISTINCT sm.prod_stocks_stock_id) FROM order_details od_sm JOIN stock_movements sm ON od_sm.order_id = sm.ref_id AND sm.ref_table = 'order_details' WHERE od_sm.purchase_orders_purchase_id = po.purchase_id) as received_qty
            FROM purchase_orders po
            LEFT JOIN suppliers s ON po.suppliers_supplier_id = s.supplier_id
            LEFT JOIN branches b ON po.branches_branch_id = b.branch_id
            LEFT JOIN shop_info sh ON b.shop_info_shop_id = sh.shop_id
            LEFT JOIN employees e ON po.employees_emp_id = e.emp_id
            $where_sql ORDER BY po.purchase_id DESC LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);
?>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-center" width="8%">เลขที่ PO</th>
                    <th width="12%">วันที่สั่ง</th>
                    <th width="18%">Supplier</th>
                    <?php if ($is_super_admin): // คอลัมน์ระบุสาขาสำหรับ Admin 
                    ?>
                        <th width="15%" class="text-center">สาขา/ร้าน</th>
                    <?php endif; ?>
                    <th width="12%">ยอดรวม (บาท)</th>
                    <th width="12%" class="text-center">สถานะการรับ</th>
                    <th width="12%" class="text-center">สถานะ PO</th>
                    <th width="12%" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): while ($row = $result->fetch_assoc()):
                        $total_q = (int)$row['total_qty'];
                        $received_q = (int)$row['received_qty'];
                        $status_badge = match ($row['po_status']) {
                            'Pending' => 'bg-warning text-dark',
                            'Completed' => 'bg-success',
                            'Cancelled' => 'bg-danger',
                            default => 'bg-secondary'
                        };
                ?>
                        <tr>
                            <td class="text-center fw-bold">#<?= $row['purchase_id'] ?></td>
                            <td class="small"><?= date('d/m/Y H:i', strtotime($row['purchase_date'])) ?></td>
                            <td class="small fw-bold text-truncate" style="max-width: 150px;"><?= htmlspecialchars($row['supplier_name'] ?? 'N/A') ?></td>
                            <?php if ($is_super_admin): ?>
                                <td class="text-center small">
                                    <div class="fw-bold text-primary"><?= htmlspecialchars($row['shop_name'] ?? '-') ?></div>
                                    <div class="text-muted fs-xs"><?= htmlspecialchars($row['branch_name'] ?? '-') ?></div>
                                </td>
                            <?php endif; ?>
                            <td class="text-end fw-bold">฿<?= number_format($row['total_amount'] ?? 0, 2) ?></td>
                            <td class="text-center">
                                <div class="progress" style="height: 10px; width: 80px; margin: 0 auto;">
                                    <div class="progress-bar bg-info" style="width: <?= ($total_q > 0) ? ($received_q / $total_q) * 100 : 0 ?>%"></div>
                                </div>
                                <small class="text-muted fs-xs"><?= $received_q ?> / <?= $total_q ?></small>
                            </td>
                            <td class="text-center"><span class="badge <?= $status_badge ?> px-3 rounded-pill"><?= $row['po_status'] ?></span></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <?php if ($row['po_status'] == 'Pending'): ?>
                                        <a href="receive_po.php?po_id=<?= $row['purchase_id'] ?>" class="btn btn-outline-success btn-sm border-0"><i class="bi bi-truck"></i></a>
                                        <a href="edit_purchase_order.php?id=<?= $row['purchase_id'] ?>" class="btn btn-outline-warning btn-sm border-0"><i class="bi bi-pencil-square"></i></a>
                                    <?php endif; ?>
                                    <a href="view_purchase_order.php?id=<?= $row['purchase_id'] ?>" class="btn btn-outline-info btn-sm border-0"><i class="bi bi-eye"></i></a>
                                    <?php if ($row['po_status'] == 'Pending'): ?>
                                        <button onclick="confirmCancel(<?= $row['purchase_id'] ?>)" class="btn btn-outline-danger btn-sm border-0"><i class="bi bi-x-circle"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile;
                else: ?>
                    <tr>
                        <td colspan="<?= $is_super_admin ? 8 : 7 ?>" class="text-center py-5 text-muted">-- ไม่พบข้อมูลใบสั่งซื้อ --</td>
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
        <div class="d-flex justify-content-center mt-2 gap-2 align-items-center">
            <div class="input-group input-group-sm" style="max-width: 150px;">
                <input type="number" id="jumpPageInput" class="form-control text-center" placeholder="ไปหน้า" min="1" max="<?= $total_pages ?>">
                <button class="btn btn-success" type="button" id="btnJumpPage">ไป</button>
            </div>
            <div class="small text-muted">หน้า <?= $page ?> / <?= $total_pages ?> (ทั้งหมด <?= number_format($total_items) ?> รายการ)</div>
        </div>
<?php endif;
    exit();
}

// [4] โหลดข้อมูลสำหรับตัวกรอง
$suppliers_res = $conn->query("SELECT supplier_id, co_name FROM suppliers WHERE shop_info_shop_id = '$shop_id' ORDER BY co_name ASC");
if ($is_super_admin) {
    $all_shops = $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name ASC");
    $all_branches = $conn->query("SELECT branch_id, branch_name, shop_info_shop_id FROM branches ORDER BY branch_name ASC");
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>รายการใบสั่งซื้อ/รับเข้า - Mobile Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            color: <?= $text_color ?>;
        }

        .container-xl {
            max-width: 1400px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: #fff;
            border-bottom: 2px solid <?= $theme_color ?>;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
        }

        .table th {
            background-color: <?= $header_bg_color ?>;
            color: <?= $header_text_color ?>;
            font-weight: 600;
            vertical-align: middle;
            text-align: center;
        }

        .table td {
            vertical-align: middle;
            font-size: 0.9rem;
        }

        /* **[เพิ่ม]** จัดการคอลัมน์ Action ในตาราง */
        .table td:last-child {
            display: flex;
            gap: 5px;
            justify-content: center;
            align-items: center;
            flex-wrap: nowrap;
            /* ป้องกันปุ่มขึ้นบรรทัดใหม่บน Desktop */
        }

        /* ... โค้ดปุ่มและ Form Control เดิม ... */
        .btn-add {
            background-color: <?= $btn_add_color ?>;
            border-color: <?= $btn_add_color ?>;
            color: white;
        }

        .btn-add:hover {
            color: white;
            filter: brightness(90%);
        }

        .btn-edit {
            background-color: <?= $btn_edit_color ?>;
            color: white;
        }

        .btn-delete {
            background-color: <?= $btn_delete_color ?>;
            color: white;
        }

        .btn-info {
            background-color: #0dcaf0;
            color: white;
        }

        /* (เพิ่มปุ่มรับของ) */
        .btn-receive {
            background-color: #198754;
            color: white;
        }

        .btn-warning {
            background-color: #ffc107;
            color: #000;
        }

        .pagination .page-link {
            color: <?= $theme_color ?>;
        }

        .pagination .page-item.active .page-link {
            background-color: <?= $theme_color ?>;
            border-color: <?= $theme_color ?>;
            color: white;
        }

        .form-control:focus {
            border-color: <?= $theme_color ?>;
            box-shadow: 0 0 0 0.25rem rgba(<?= hexdec(substr($theme_color, 1, 2)) ?>, <?= hexdec(substr($theme_color, 3, 2)) ?>, <?= hexdec(substr($theme_color, 5, 2)) ?>, 0.25);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }

        /* (CSS สำหรับสถานะการรับ) */
        .status-badge {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.3em 0.6em;
            border-radius: 15px;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-partial {
            background-color: #d1edff;
            color: #0c63e4;
        }

        .status-completed {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        /* (เพิ่มสถานะยกเลิก) */
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
            text-decoration: line-through;
        }

        /* -------------------------------------------------------------------- */
        /* --- **[เพิ่ม]** Responsive Override สำหรับ Mobile (จอเล็กกว่า 992px) --- */
        /* -------------------------------------------------------------------- */
        @media (max-width: 991.98px) {
            .container-xl {
                padding-left: 10px;
                padding-right: 10px;
            }

            /* 1. จัดการ Filter/Action Bar (สมมติว่าใช้ d-flex) */
            .card-header .d-flex {
                flex-direction: column;
                gap: 10px;
            }

            .card-header .d-flex>div {
                width: 100% !important;
            }

            /* 2. ทำให้ Form Control และ Button ใช้เต็มความกว้าง */
            .card-header .form-control,
            .card-header .form-select,
            .card-header .btn {
                width: 100% !important;
            }

            /* 3. ปรับ Table Cell Padding/Font */
            .table th,
            .table td {
                padding: 0.5rem 0.5rem;
                font-size: 0.8rem;
                white-space: nowrap;
            }

            /* 4. จัดการคอลัมน์ Action ในตาราง */
            .table td:last-child {
                flex-direction: column;
                /* เรียงปุ่ม Action เป็นแนวตั้งบน Mobile */
                gap: 5px;
            }

            /* 5. ปรับขนาด Badge */
            .status-badge {
                font-size: 10px;
                padding: 3px 6px;
            }
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container" style="max-width: 1400px;">

                    <div class="card shadow-sm border-0 rounded-4">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom-0">
                            <h4 class="mb-0 text-success fw-bold"><i class="bi bi-clipboard-check me-2"></i>รายการใบสั่งซื้อ / รับเข้าสินค้า</h4>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-success btn-sm fw-bold px-3" onclick="toggleFilter()">
                                    <i class="bi bi-filter me-1"></i> <span id="filterBtnText">กรองข้อมูล</span>
                                </button>
                                <a href="add_purchase_order.php" class="btn btn-success btn-sm fw-bold px-3">
                                    <i class="bi bi-plus-circle me-1"></i> สร้างใบสั่งซื้อ
                                </a>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="card bg-light border-0 mb-4" id="filterCard" style="display: none; border-radius: 15px;">
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="small fw-bold text-muted mb-1">สถานะ PO</label>
                                            <select id="statusFilter" class="form-select border-0 shadow-sm">
                                                <option value="">-- ทุกสถานะ --</option>
                                                <option value="Pending">Pending (รอรับ)</option>
                                                <option value="Completed">Completed (ครบแล้ว)</option>
                                                <option value="Cancelled">Cancelled (ยกเลิก)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small fw-bold text-muted mb-1">Supplier</label>
                                            <select id="supplierFilter" class="form-select border-0 shadow-sm">
                                                <option value="">-- ทั้งหมด --</option>
                                                <?php while ($s = $suppliers_res->fetch_assoc()): ?>
                                                    <option value="<?= $s['supplier_id'] ?>"><?= htmlspecialchars($s['co_name']) ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>

                                        <?php if ($is_super_admin): // ผู้ดูแลกรองดูบิลแต่ละร้านได้ 
                                        ?>
                                            <div class="col-md-3">
                                                <label class="small fw-bold text-primary mb-1">ร้านค้า (Shop)</label>
                                                <select id="shopFilter" class="form-select border-primary border-opacity-25 shadow-sm">
                                                    <option value="">-- ทุกร้าน --</option>
                                                    <?php while ($sh = $all_shops->fetch_assoc()): ?>
                                                        <option value="<?= $sh['shop_id'] ?>"><?= htmlspecialchars($sh['shop_name']) ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="small fw-bold text-primary mb-1">สาขา (Branch)</label>
                                                <select id="branchFilter" class="form-select border-primary border-opacity-25 shadow-sm">
                                                    <option value="">-- ทุกสาขา --</option>
                                                    <?php mysqli_data_seek($all_branches, 0);
                                                    while ($br = $all_branches->fetch_assoc()): ?>
                                                        <option value="<?= $br['branch_id'] ?>" data-shop="<?= $br['shop_info_shop_id'] ?>"><?= htmlspecialchars($br['branch_name']) ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        <?php endif; ?>

                                        <div class="col-12 text-end">
                                            <button class="btn btn-link btn-sm text-danger text-decoration-none" onclick="clearFilters()">ล้างค่าทั้งหมด</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-5">
                                    <div class="input-group shadow-sm" style="border-radius: 10px; overflow: hidden;">
                                        <span class="input-group-text bg-white border-0"><i class="bi bi-search"></i></span>
                                        <input type="text" id="searchInput" class="form-control border-0" placeholder="ค้นหาเลขที่ PO, ชื่อซัพพลายเออร์...">
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

    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form id="cancelForm" method="POST" action="cancel_purchase_order.php">
                    <div class="modal-header bg-warning border-0">
                        <h5 class="modal-title fw-bold text-white"><i class="bi bi-exclamation-octagon me-2"></i>ยกเลิกใบสั่งซื้อ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p class="mb-3">คุณแน่ใจหรือไม่ว่าต้องการยกเลิกใบสั่งซื้อนี้? <br><small class="text-muted">กรุณาระบุเหตุผลในการยกเลิก</small></p>
                        <textarea class="form-control border-0 bg-light" name="cancel_comment" rows="3" required placeholder="เหตุผลการยกเลิก..."></textarea>
                        <input type="hidden" name="po_id" id="cancelPoId">
                    </div>
                    <div class="modal-footer border-0 bg-light">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-danger rounded-pill px-4">ยืนยันยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function fetchPOData(page = 1) {
            const params = new URLSearchParams({
                ajax: 1,
                page,
                search: document.getElementById('searchInput').value,
                status: document.getElementById('statusFilter').value,
                supplier: document.getElementById('supplierFilter').value,
                shop_filter: document.getElementById('shopFilter')?.value || '',
                branch_filter: document.getElementById('branchFilter')?.value || ''
            });

            fetch(`purchase_order.php?${params.toString()}`)
                .then(res => res.text()).then(data => document.getElementById('tableContainer').innerHTML = data);
        }

        function toggleFilter() {
            const card = document.getElementById('filterCard');
            const isHidden = card.style.display === 'none';
            card.style.display = isHidden ? 'block' : 'none';
            document.getElementById('filterBtnText').innerText = isHidden ? 'ปิดตัวกรอง' : 'กรองข้อมูล';
        }

        function clearFilters() {
            ['statusFilter', 'supplierFilter', 'shopFilter', 'branchFilter', 'searchInput'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            fetchPOData(1);
        }

        ['searchInput'].forEach(id => document.getElementById(id).addEventListener('input', () => fetchPOData(1)));
        ['statusFilter', 'supplierFilter', 'shopFilter', 'branchFilter'].forEach(id => document.getElementById(id)?.addEventListener('change', () => fetchPOData(1)));

        document.addEventListener('click', e => {
            const link = e.target.closest('.ajax-page-link');
            if (link) {
                e.preventDefault();
                fetchPOData(link.dataset.page);
            }
            if (e.target.id === 'btnJumpPage') {
                const p = document.getElementById('jumpPageInput').value;
                if (p > 0) fetchPOData(p);
            }
        });

        function confirmCancel(id) {
            document.getElementById('cancelPoId').value = id;
            new bootstrap.Modal(document.getElementById('cancelModal')).show();
        }

        // กรองสาขาตามร้านที่เลือก (Admin)
        document.getElementById('shopFilter')?.addEventListener('change', function() {
            const shopId = this.value;
            const branchSelect = document.getElementById('branchFilter');
            branchSelect.value = '';
            Array.from(branchSelect.options).forEach(opt => {
                if (opt.value === '') opt.style.display = 'block';
                else opt.style.display = (shopId === '' || opt.dataset.shop === shopId) ? 'block' : 'none';
            });
        });

        window.onload = () => fetchPOData();
    </script>
</body>

</html>