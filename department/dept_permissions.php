<?php
session_start();
require '../config/config.php';
// checkPageAccess($conn, 'department'); // เปิดใช้เมื่อระบบสิทธิ์พร้อม

// [1] รับค่าพื้นฐาน
$current_shop_id = $_SESSION['shop_id'] ?? 0;
$current_branch_id = $_SESSION['branch_id'] ?? 0;
$current_user_id = $_SESSION['user_id'] ?? 0;

// ตรวจสอบสิทธิ์ Super Admin
$is_super_admin = false;
$chk_sql = "SELECT r.role_name FROM roles r JOIN user_roles ur ON r.role_id = ur.roles_role_id WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt = $conn->prepare($chk_sql)) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $is_super_admin = true;
    $stmt->close();
}

// ==========================================
// [2] AJAX HANDLERS
// ==========================================
if (isset($_GET['ajax_action'])) {
    $action = $_GET['ajax_action'];

    // 2.1 โหลดสาขา
    if ($action == 'get_branches') {
        $target_shop_id = isset($_GET['shop_id']) ? intval($_GET['shop_id']) : 0;
        if (!$is_super_admin && $target_shop_id != $current_shop_id) exit; 

        $sql = "SELECT branch_id, branch_name FROM branches WHERE shop_info_shop_id = ? ORDER BY branch_name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $target_shop_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = [];
        while ($row = $res->fetch_assoc()) $data[] = $row;
        echo json_encode($data);
        exit;
    }

    // 2.2 โหลดตารางข้อมูล
    if ($action == 'load_table') {
        $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
        $shop_f = isset($_GET['shop_id']) ? $_GET['shop_id'] : '';
        $branch_f = isset($_GET['branch_id']) ? $_GET['branch_id'] : '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $conditions = [];
        if (!$is_super_admin) {
            // ถ้าไม่ใช่ผู้ดูแลระบบ บังคับให้ดูได้แค่สาขาของตัวเองเท่านั้น
            $conditions[] = "d.branches_branch_id = '$current_branch_id'";
        } else {
            // ถ้าเป็นผู้ดูแลระบบ (Admin) ค่อยให้กรองตามร้านค้า/สาขาที่เลือกได้
            if (!empty($shop_f)) {
                $conditions[] = "d.shop_info_shop_id = '$shop_f'";
            }
            if (!empty($branch_f)) {
                $conditions[] = "d.branches_branch_id = '$branch_f'";
            }
        }
        if (!empty($search)) $conditions[] = "(d.dept_name LIKE '%$search%' OR sh.shop_name LIKE '%$search%' OR b.branch_name LIKE '%$search%')";

        $where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

        // Count Total
        $count_sql = "SELECT COUNT(DISTINCT d.dept_id) as total FROM departments d 
                      LEFT JOIN shop_info sh ON d.shop_info_shop_id = sh.shop_id 
                      LEFT JOIN branches b ON d.branches_branch_id = b.branch_id $where_sql";
        $total_items = $conn->query($count_sql)->fetch_assoc()['total'];
        $total_pages = ceil($total_items / $limit);

        // Fetch Data
        $sql = "SELECT d.*, sh.shop_name, b.branch_name,
                COUNT(dp.permissions_permission_id) as total_perms,
                GROUP_CONCAT(dp.permissions_permission_id SEPARATOR ',') as perm_ids
                FROM departments d
                LEFT JOIN shop_info sh ON d.shop_info_shop_id = sh.shop_id
                LEFT JOIN branches b ON d.branches_branch_id = b.branch_id
                LEFT JOIN dept_permissions dp ON d.dept_id = dp.departments_dept_id
                $where_sql
                GROUP BY d.dept_id
                ORDER BY d.shop_info_shop_id ASC, b.branch_name ASC, d.dept_name ASC
                LIMIT $limit OFFSET $offset";
        $result = $conn->query($sql);
        ?>

        <div class="table-responsive rounded-3 border">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-success text-white">
                    <tr>
                        <th class="text-center" width="5%">#</th>
                        <th width="20%">ชื่อแผนก</th>
                        <th width="20%">สาขา</th>
                        <th class="text-center" width="25%">สถานะสิทธิ์</th>
                        <?php if($is_super_admin): ?><th class="text-center" width="15%">ร้านค้า</th><?php endif; ?>
                        <th class="text-center" width="15%">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): 
                        $idx = $offset + 1;
                        while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td class="text-center fw-bold text-muted"><?= $idx++ ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($row['dept_name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($row['dept_desc'] ?? '') ?></small>
                        </td>
                        <td>
                            <?php if(!empty($row['branch_name'])): ?>
                                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                                    <i class="bi bi-geo-alt me-1"></i> <?= htmlspecialchars($row['branch_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">- ไม่ระบุ -</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($row['total_perms'] > 0): ?>
                                <span class="badge bg-success rounded-pill px-3 shadow-sm">
                                    <i class="bi bi-shield-check me-1"></i> กำหนดเฉพาะแผนกแล้ว (<?= $row['total_perms'] ?>)
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary rounded-pill px-3 opacity-75">
                                    <i class="bi bi-people me-1"></i> ใช้สิทธิ์ตาม Role (Default)
                                </span>
                            <?php endif; ?>
                        </td>
                        <?php if($is_super_admin): ?>
                        <td class="text-center"><span class="small text-muted fw-bold"><?= htmlspecialchars($row['shop_name']) ?></span></td>
                        <?php endif; ?>
                        <td class="text-center">
                            <button class="btn btn-outline-success btn-sm rounded-pill px-3 shadow-sm" 
                                    onclick="openPermModal(<?= $row['dept_id'] ?>, '<?= addslashes($row['dept_name']) ?>', '<?= $row['perm_ids'] ?>')">
                                <i class="bi bi-gear-fill me-1"></i> จัดการสิทธิ์
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="<?= $is_super_admin ? 6 : 5 ?>" class="text-center py-5 text-muted">-- ไม่พบข้อมูลแผนก --</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center pagination-sm">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                        <a class="page-link ajax-page-link cursor-pointer" onclick="loadTable(<?= $i ?>)"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
        <?php
        exit;
    }
}

// ==========================================
// [3] SAVE PERMISSIONS
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'save_permissions') {
    $dept_id = (int)$_POST['dept_id'];
    $selected_perms = isset($_POST['perms']) ? $_POST['perms'] : [];

    mysqli_begin_transaction($conn);
    try {
        $conn->query("DELETE FROM dept_permissions WHERE departments_dept_id = $dept_id");
        if (!empty($selected_perms)) {
            $stmt = $conn->prepare("INSERT INTO dept_permissions (departments_dept_id, permissions_permission_id) VALUES (?, ?)");
            foreach ($selected_perms as $p_id) {
                $stmt->bind_param("ii", $dept_id, $p_id);
                $stmt->execute();
            }
        }
        mysqli_commit($conn);
        echo "success";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "error";
    }
    exit();
}

// *** ตัดสิทธิ์ตาม ID ที่ไม่ต้องการออกด้วยคำสั่ง NOT IN ***
$excluded_ids = '1,2,3,4,14,15,16,17,26,27,28,29,30,31,32,33,34,45,46,47,48,54,55,56,57,
64,65,66,67,68,74,75,76,77,78,89,90,94,95,96,97,110,113,124,125,126,127';

$sql_perms = "SELECT permission_id, permission_name, permission_desc 
              FROM permissions 
              WHERE permission_id NOT IN ($excluded_ids)
              ORDER BY permission_desc DESC, permission_name ASC";
$all_perms = $conn->query($sql_perms);

$perms_array = [];
while ($p = $all_perms->fetch_assoc()) $perms_array[] = $p;

$shops = ($is_super_admin) ? $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name") : null;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>กำหนดสิทธิ์รายแผนก</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background-color: #f0fdf4;  }
        .main-card { border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .card-header-custom { background: linear-gradient(135deg, #16a34a, #14532d); padding: 1.5rem; color: white; }
        
        .perm-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 12px;
            transition: 0.2s;
            height: 100%;
            cursor: pointer;
            display: flex;
            align-items: center;
            background: #fff;
        }
        .perm-card:hover { border-color: #16a34a; background: #f0fdf4; transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .perm-card.active { border-color: #16a34a; background: #dcfce7; color: #14532d; font-weight: bold; }
        .form-check-input:checked { background-color: #16a34a; border-color: #16a34a; }
        .cursor-pointer { cursor: pointer; }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container" style="max-width: 1400px;">
                    
                    <div class="main-card card mb-4">
                        <div class="card-header-custom d-flex justify-content-between align-items-center">
                            <div><h4 class="mb-0 fw-bold text-white"><i class="bi bi-shield-lock-fill me-2"></i>กำหนดสิทธิ์รายแผนก</h4></div>
                        </div>
                        <div class="card-body p-4 bg-light">
                            <div class="row g-3 mb-4">
                                <?php if($is_super_admin): ?>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted">ร้านค้า</label>
                                    <select id="shopFilter" class="form-select select2" onchange="loadBranches(this.value); loadTable(1);">
                                        <option value="">-- ทุกร้านค้า --</option>
                                        <?php while($s = $shops->fetch_assoc()): ?>
                                            <option value="<?= $s['shop_id'] ?>"><?= $s['shop_name'] ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted">สาขา</label>
                                    <select id="branchFilter" class="form-select select2" onchange="loadTable(1);">
                                        <option value="">-- ทุกสาขา --</option>
                                    </select>
                                </div>
                                <?php else: ?>
                                    <input type="hidden" id="shopFilter" value="<?= $current_shop_id ?>">
                                    <input type="hidden" id="branchFilter" value="<?= $current_branch_id ?>">
                                <?php endif; ?>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">ค้นหาแผนก</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                        <input type="text" id="searchInput" class="form-control border-start-0" placeholder="ค้นหาชื่อแผนก..." onkeyup="loadTable(1)">
                                    </div>
                                </div>
                            </div>

                            <div id="tableContainer" class="bg-white rounded-3 shadow-sm p-3">
                                <div class="text-center py-5"><div class="spinner-border text-success"></div></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="permModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-success text-white">
                    <div>
                        <h5 class="modal-title fw-bold text-white"><i class="bi bi-toggles me-2"></i>กำหนดสิทธิ์การใช้งาน</h5>
                        <small class="opacity-75">แผนก: <span id="modalDeptName" class="text-warning fw-bold text-decoration-underline"></span></small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body bg-light p-4">
                    <form id="permForm">
                        <input type="hidden" name="dept_id" id="modalDeptId">
                        <input type="hidden" name="action" value="save_permissions">

                        <div class="row g-3 mb-3 bg-white p-3 rounded shadow-sm border">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-muted small"><i class="bi bi-search me-1"></i> ค้นหาสิทธิ์</label>
                                <input type="text" id="permSearchInput" class="form-control" placeholder="พิมพ์ชื่อสิทธิ์ที่ต้องการ..." onkeyup="filterPermissions()">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-muted small"><i class="bi bi-filter me-1"></i> กรองหมวดหมู่</label>
                                <select id="permGroupFilter" class="form-select" onchange="filterPermissions()">
                                    <option value="all">-- ดูทั้งหมด --</option>
                                    <option value="menu">เมนู (Menu)</option>
                                    <option value="sale">การขาย / POS (Sale & POS)</option>
                                    <option value="product">สินค้า / สต็อก (Product & Stock)</option>
                                    <option value="customer">ลูกค้า (Customer)</option>
                                    <option value="employee">พนักงาน / แผนก (Employee)</option>
                                    <option value="repair">ซ่อม / อาการเสีย (Repair)</option>
                                    <option value="other">อื่นๆ (Others)</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-success mb-0">รายการสิทธิ์ที่มี <span id="permCountText" class="badge bg-success ms-2"><?= count($perms_array) ?></span></h6>
                            <div class="btn-group shadow-sm">
                                <button type="button" class="btn btn-outline-success btn-sm fw-bold" onclick="toggleAll(true)"><i class="bi bi-check-all"></i> เลือกที่แสดงอยู่</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm fw-bold" onclick="toggleAll(false)"><i class="bi bi-x"></i> เอาออกที่แสดงอยู่</button>
                            </div>
                        </div>

                        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-2" id="permsContainer">
                            <?php foreach($perms_array as $p): 
                                // ตั้งค่าการแสดงผล
                                $displayName = !empty($p['permission_desc']) ? $p['permission_desc'] : $p['permission_name'];
                                $subName = !empty($p['permission_desc']) ? $p['permission_name'] : ''; 
                                
                                // จัดหมวดหมู่เบื้องต้นจากชื่อ (สำหรับการกรอง)
                                $p_name = strtolower($p['permission_name']);
                                $group = 'other';
                                if (strpos($p_name, 'menu_') !== false) $group = 'menu';
                                elseif (strpos($p_name, 'sale') !== false || strpos($p_name, 'pay') !== false) $group = 'sale';
                                elseif (strpos($p_name, 'prod') !== false || strpos($p_name, 'stock') !== false || strpos($p_name, 'purchase') !== false || strpos($p_name, 'supplier') !== false) $group = 'product';
                                elseif (strpos($p_name, 'customer') !== false) $group = 'customer';
                                elseif (strpos($p_name, 'employee') !== false || strpos($p_name, 'department') !== false) $group = 'employee';
                                elseif (strpos($p_name, 'repair') !== false || strpos($p_name, 'symptom') !== false || strpos($p_name, 'bill') !== false) $group = 'repair';
                            ?>
                            <div class="col perm-item" data-name="<?= htmlspecialchars(strtolower($displayName . ' ' . $subName)) ?>" data-group="<?= $group ?>">
                                <label class="perm-card" for="p_<?= $p['permission_id'] ?>">
                                    <input class="form-check-input me-2 flex-shrink-0" type="checkbox" name="perms[]" 
                                           value="<?= $p['permission_id'] ?>" id="p_<?= $p['permission_id'] ?>"
                                           onchange="updateCardStyle(this)">
                                    <div class="lh-sm overflow-hidden">
                                        <div class="fw-bold text-dark text-truncate" style="font-size: 0.95rem;" title="<?= htmlspecialchars($displayName) ?>">
                                            <?= htmlspecialchars($displayName) ?>
                                        </div>
                                        <?php if($subName): ?>
                                        <div class="text-muted text-truncate" style="font-size: 0.75rem;" title="<?= htmlspecialchars($subName) ?>">
                                            (<?= htmlspecialchars($subName) ?>)
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div id="noPermsFoundMsg" class="text-center py-4 text-muted d-none">
                            <i class="bi bi-search fs-1"></i>
                            <p class="mt-2">ไม่พบสิทธิ์การใช้งานที่ตรงกับการค้นหา</p>
                        </div>
                    </form>
                </div>
                
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-light rounded-pill px-4 border" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-success rounded-pill px-5 shadow fw-bold" onclick="savePerms()">
                        <i class="bi bi-save me-2"></i>บันทึกข้อมูล
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
            const userShopId = "<?= $current_shop_id ?>";
            loadBranches($('#shopFilter').val() || userShopId);
            loadTable();
        });

        function loadBranches(shopId) {
            if(!shopId) return;
            $.get('dept_permissions.php', { ajax_action: 'get_branches', shop_id: shopId }, function(data) {
                const sel = $('#branchFilter');
                sel.empty().append('<option value="">-- ทุกสาขา --</option>');
                JSON.parse(data).forEach(b => { sel.append(new Option(b.branch_name, b.branch_id)); });
            });
        }

        function loadTable(page = 1) {
            const shop = $('#shopFilter').val();
            const branch = $('#branchFilter').val();
            const search = $('#searchInput').val();
            $('#tableContainer').html('<div class="text-center py-5"><div class="spinner-border text-success"></div></div>');
            $.get('dept_permissions.php', { ajax_action: 'load_table', page: page, shop_id: shop, branch_id: branch, search: search }, function(html) {
                $('#tableContainer').html(html);
            });
        }

        function openPermModal(id, name, currentIds) {
            $('#modalDeptId').val(id);
            $('#modalDeptName').text(name);
            
            // เคลียร์การกรองเดิม
            $('#permSearchInput').val('');
            $('#permGroupFilter').val('all');
            filterPermissions(); // รีเซ็ตให้แสดงทั้งหมด

            // เอาติ๊กออกทั้งหมดก่อน
            $('input[name="perms[]"]').prop('checked', false).closest('.perm-card').removeClass('active');
            
            // ติ๊กสิทธิ์ที่เคยมีอยู่แล้ว
            if(currentIds) {
                currentIds.toString().split(',').forEach(pid => {
                    const cb = $(`#p_${pid}`);
                    if(cb.length) { // เช็คก่อนว่าสิทธิ์โดนตัดออกไปหรือยัง
                        cb.prop('checked', true);
                        updateCardStyle(cb[0]);
                    }
                });
            }
            new bootstrap.Modal(document.getElementById('permModal')).show();
        }

        // ระบบค้นหาและกรองสิทธิ์ภายใน Modal
        function filterPermissions() {
            let searchText = $('#permSearchInput').val().toLowerCase();
            let filterGroup = $('#permGroupFilter').val();
            let visibleCount = 0;

            $('.perm-item').each(function() {
                let name = $(this).data('name');
                let group = $(this).data('group');
                
                let matchSearch = name.includes(searchText);
                let matchGroup = (filterGroup === 'all' || group === filterGroup);

                if (matchSearch && matchGroup) {
                    $(this).show();
                    visibleCount++;
                } else {
                    $(this).hide();
                }
            });

            $('#permCountText').text(visibleCount);
            
            if(visibleCount === 0) {
                $('#noPermsFoundMsg').removeClass('d-none');
            } else {
                $('#noPermsFoundMsg').addClass('d-none');
            }
        }

        // เลือกทั้งหมด/ยกเลิกทั้งหมด (เฉพาะสิทธิ์ที่แสดงอยู่จากการค้นหา/กรอง)
        function toggleAll(check) {
            $('.perm-item:visible input[name="perms[]"]').each(function() { 
                $(this).prop('checked', check); 
                updateCardStyle(this); 
            });
        }

        function updateCardStyle(checkbox) {
            if(checkbox.checked) $(checkbox).closest('.perm-card').addClass('active');
            else $(checkbox).closest('.perm-card').removeClass('active');
        }

        function savePerms() {
            const formData = $('#permForm').serialize();
            
            // ปิด Modal ก่อนและแสดง Loading
            bootstrap.Modal.getInstance(document.getElementById('permModal')).hide();
            Swal.fire({
                title: 'กำลังบันทึกสิทธิ์...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            $.post('dept_permissions.php', formData, function(res) {
                if(res.trim() === 'success') {
                    Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ', text: 'อัปเดตสิทธิ์ของแผนกเรียบร้อย', timer: 1500, showConfirmButton: false });
                    loadTable();
                } else {
                    Swal.fire('ผิดพลาด', 'บันทึกข้อมูลไม่สำเร็จ', 'error');
                }
            });
        }
    </script>
</body>
</html>