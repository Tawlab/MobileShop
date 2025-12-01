<?php
// --- permission/permission.php ---
session_start();
require '../config/config.php'; // (ตรวจสอบว่า Path 'config.php' ถูกต้อง)
checkPageAccess($conn, 'permission');

// --- (ส่วนรับข้อความแจ้งเตือน) ---
$message = $_SESSION['message'] ?? null;
$message_type = $_SESSION['message_type'] ?? null;
unset($_SESSION['message'], $_SESSION['message_type']);

// --- (1. รับค่าตัวกรองและค้นหา) ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'all'; // (ค่าเริ่มต้นคือ 'all')
$permissions = [];

// --- (2. สร้าง SQL แบบไดนามิก) ---
$sql = "SELECT 
            permission_id, 
            permission_name, 
            permission_desc, 
            create_at, 
            update_at 
        FROM permissions
";

// --- (เตรียมตัวแปรสำหรับ Prepared Statement) ---
$where_clauses = [];
$bind_types = "";
$bind_values = [];

// --- (เงื่อนไข A: ถ้ามีการค้นหาด้วย "คำ") ---
if (!empty($search_term)) {
    $where_clauses[] = "(permission_name LIKE ? OR permission_desc LIKE ?)";
    $search_like = "%" . $search_term . "%";
    $bind_types .= "ss";
    array_push($bind_values, $search_like, $search_like);
}

// --- (เงื่อนไข B: ถ้ามีการ "กรองตามประเภท") ---
if ($filter_type != 'all') {
    if ($filter_type == 'add') {
        $where_clauses[] = "permission_name LIKE 'add_%'";
    } elseif ($filter_type == 'edit') {
        $where_clauses[] = "permission_name LIKE 'edit_%'";
    } elseif ($filter_type == 'del') {
        $where_clauses[] = "permission_name LIKE 'del_%'";
    } elseif ($filter_type == 'view') {
        $where_clauses[] = "permission_name LIKE 'view_%'";
    } elseif ($filter_type == 'list') {
        // (เงื่อนไขสำหรับ "หน้าหลัก" คือ ต้องไม่มี prefix 4 คำนั้น)
        $where_clauses[] = "permission_name NOT LIKE 'add_%' AND 
                            permission_name NOT LIKE 'edit_%' AND 
                            permission_name NOT LIKE 'del_%' AND 
                            permission_name NOT LIKE 'view_%'";
    }
}

// --- (รวมเงื่อนไข WHERE) ---
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY permission_id ASC"; // เรียงตาม ID

// --- (ใช้ Prepared Statement เพื่อความปลอดภัย) ---
$stmt = $conn->prepare($sql);

if ($stmt) {
    // --- (ผูกค่าพารามิเตอร์ (ถ้ามี)) ---
    if (!empty($bind_types)) {
        $stmt->bind_param($bind_types, ...$bind_values);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    // --- ดึงข้อมูลทั้งหมดมาเก็บใน array ---
    while ($row = $result->fetch_assoc()) {
        $permissions[] = $row;
    }
    $stmt->close();
} else {
    // --- จัดการกรณี Query ผิดพลาด ---
    die("Error preparing statement: " . $conn->error);
}

// $conn->close();

// --- (3. ใหม่: Helper สำหรับปุ่ม Dropdown กรอง) ---
// (สร้าง Label ให้ปุ่ม Dropdown)
$filter_labels = [
    'all' => '<i class="fas fa-list me-1"></i> ทั้งหมด',
    'list' => '<i class="fas fa-chalkboard me-1"></i> หน้าหลัก (List)',
    'add' => '<i class="fas fa-plus me-1"></i> เพิ่ม (Add)',
    'edit' => '<i class="fas fa-pencil me-1"></i> แก้ไข (Edit)',
    'del' => '<i class="fas fa-trash-can me-1"></i> ลบ (Del)',
    'view' => '<i class="fas fa-eye me-1"></i> ดู (View)'
];
$current_filter_label = $filter_labels[$filter_type] ?? $filter_labels['all'];

// (สร้าง query string สำหรับลิงก์ใน Dropdown ให้จำคำค้นหาเดิมไว้)
$search_query_param = !empty($search_term) ? '&search=' . urlencode($search_term) : '';

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสิทธิ์ (Permissions) - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: #f0fdf4;
            color: #333;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #2dd4bf 0%, #15803d 100%);
            color: white;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
            padding: 1.25rem 1.5rem;
            border-bottom: none;
        }

        .card-header h4 {
            font-weight: 600;
            margin-bottom: 0;
        }

        .card-header .btn-light {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.7);
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .card-header .btn-light:hover {
            background-color: rgba(255, 255, 255, 0.3);
            border-color: white;
        }

        .btn-primary {
            background-color: #15803d;
            border-color: #15803d;
        }

        .btn-primary:hover,
        .btn-primary.active {
            background-color: #166534;
            border-color: #166534;
        }

        /* (CSS สำหรับปุ่มกรอง) */
        .btn-outline-secondary {
            border-color: #ced4da;
        }

        .btn-outline-secondary:hover {
            background-color: #e9ecef;
        }

        .dropdown-item.active,
        .dropdown-item:active {
            background-color: #15803d;
            /* สีเขียว */
        }

        .table thead {
            background-color: #f0fdf4;
            color: #14532d;
            font-weight: 600;
        }

        .table th {
            border-bottom: 2px solid #a7f3d0 !important;
            padding: 1rem 0.75rem;
            vertical-align: middle;
        }

        .table td {
            padding: 0.85rem 0.75rem;
            border-bottom: 1px solid #e6fcf5;
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background-color: #e6fcf5;
            color: #065f46;
        }

        .action-icons a {
            margin: 0 5px;
            font-size: 1.1rem;
            text-decoration: none;
            opacity: 0.7;
            transition: opacity 0.2s ease;
        }

        .action-icons a:hover {
            opacity: 1;
        }

        .action-icons .fa-pencil {
            color: #f59e0b;
        }

        .action-icons .fa-trash-can {
            color: #ef4444;
        }

        .custom-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 300px;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.3s ease forwards;
            z-index: 1050;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .alert-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }

        .alert-error {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            color: white;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <?php if ($message): ?>
                    <div class="custom-alert alert-<?= $message_type == 'success' ? 'success' : 'danger' ?>" role="alert">
                        <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-triangle' ?> fa-lg"></i>
                        <div><strong><?= $message_type == 'success' ? 'สำเร็จ!' : 'ผิดพลาด!' ?></strong><br><?= htmlspecialchars($message) ?></div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close" style="filter: invert(1) grayscale(100%) brightness(200%);"></button>
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
                                    <span class="input-group-text bg-light border-0"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control border-0 bg-light" name="search" placeholder="ค้นหาชื่อสิทธิ์ หรือ คำอธิบาย..." value="<?= htmlspecialchars($search_term) ?>">

                                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="min-width: 170px;" id="filterDropdownButton">
                                        <?= $current_filter_label ?>
                                    </button>

                                    <ul class="dropdown-menu dropdown-menu-end" id="filterOptions">
                                        <li><a class="dropdown-item <?= ($filter_type == 'all') ? 'active' : '' ?>" href="#" data-filter="all">
                                                <?= $filter_labels['all'] ?>
                                            </a></li>
                                        <li><a class="dropdown-item <?= ($filter_type == 'list') ? 'active' : '' ?>" href="#" data-filter="list">
                                                <?= $filter_labels['list'] ?>
                                            </a></li>
                                        <li><a class="dropdown-item <?= ($filter_type == 'add') ? 'active' : '' ?>" href="#" data-filter="add">
                                                <?= $filter_labels['add'] ?>
                                            </a></li>
                                        <li><a class="dropdown-item <?= ($filter_type == 'edit') ? 'active' : '' ?>" href="#" data-filter="edit">
                                                <?= $filter_labels['edit'] ?>
                                            </a></li>
                                        <li><a class="dropdown-item <?= ($filter_type == 'del') ? 'active' : '' ?>" href="#" data-filter="del">
                                                <?= $filter_labels['del'] ?>
                                            </a></li>
                                        <li><a class="dropdown-item <?= ($filter_type == 'view') ? 'active' : '' ?>" href="#" data-filter="view">
                                                <?= $filter_labels['view'] ?>
                                            </a></li>
                                    </ul>

                                    <button class="btn btn-primary" type="submit">ค้นหา</button>

                                    <?php if (!empty($search_term) || $filter_type != 'all'): ?>
                                        <a href="permission.php" class="btn btn-outline-secondary">ล้างค่า</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="text-nowrap">
                                        <tr>
                                            <th style="width: 5%;">ID</th>
                                            <th style="width: 20%;">ชื่อสิทธิ์ (Name)</th>
                                            <th style="width: 35%;">คำอธิบาย (Description)</th>
                                            <th style="width: 15%;">สร้างเมื่อ</th>
                                            <th style="width: 15%;">อัปเดตเมื่อ</th>
                                            <th style="width: 10%;" class="text-center">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($permissions) > 0): ?>
                                            <?php foreach ($permissions as $perm): ?>
                                                <tr>
                                                    <td><?= $perm['permission_id'] ?></td>
                                                    <td class="fw-bold"><?= htmlspecialchars($perm['permission_name']) ?></td>
                                                    <td><?= htmlspecialchars($perm['permission_desc'] ?? '-') ?></td>
                                                    <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($perm['create_at'])) ?></td>
                                                    <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($perm['update_at'])) ?></td>
                                                    <td class="text-center action-icons">
                                                        <a href="edit_permission.php?id=<?= $perm['permission_id'] ?>" title="แก้ไข"><i class="fas fa-pencil"></i></a>
                                                        <a href="delete_permission.php?id=<?= $perm['permission_id'] ?>" title="ลบ" onclick="return confirm('คุณต้องการลบสิทธิ์ \'<?= htmlspecialchars($perm['permission_name']) ?>\' ใช่หรือไม่? (การลบนี้อาจส่งผลกระทบต่อบทบาทที่ใช้สิทธิ์นี้อยู่)')"><i class="fas fa-trash-can"></i></a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-5">
                                                    <?php if (!empty($search_term) || $filter_type != 'all'): ?>
                                                        <h4><i class="fas fa-search-minus me-2"></i>ไม่พบข้อมูล</h4>
                                                        <p class="mb-0">ไม่พบสิทธิ์ที่ตรงกับการค้นหาหรือตัวกรอง</p>
                                                    <?php else: ?>
                                                        <h4><i class="fas fa-box-open me-2"></i>ยังไม่มีข้อมูลสิทธิ์</h4>
                                                        <p class="mb-0">คลิกปุ่ม "เพิ่มสิทธิ์ใหม่" เพื่อเริ่มต้น</p>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- (Script สำหรับซ่อน Alert) ---
        setTimeout(() => {
            document.querySelectorAll('.custom-alert').forEach(alert => {
                const bsAlert = bootstrap.Alert.getInstance(alert);
                if (bsAlert) {
                    bsAlert.close();
                } else if (alert) {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 5000); // 5 วินาที

        // --- (5. ใหม่: JavaScript สำหรับ Dropdown กรอง) ---
        document.addEventListener('DOMContentLoaded', function() {
            const filterOptions = document.getElementById('filterOptions');
            const filterInput = document.getElementById('filter_type_input');
            const filterButton = document.getElementById('filterDropdownButton');
            const form = filterButton.closest('form');

            // (ดึง Labels จาก PHP มาใช้ใน JS)
            const filterLabelsJS = {
                'all': '<?= $filter_labels['all'] ?>',
                'list': '<?= $filter_labels['list'] ?>',
                'add': '<?= $filter_labels['add'] ?>',
                'edit': '<?= $filter_labels['edit'] ?>',
                'del': '<?= $filter_labels['del'] ?>',
                'view': '<?= $filter_labels['view'] ?>'
            };

            filterOptions.addEventListener('click', function(e) {
                e.preventDefault(); // --- หยุดลิงก์ไม่ให้ทำงาน ---

                // --- หาลิงก์ที่ถูกคลิก (<a>) ---
                const target = e.target.closest('a.dropdown-item');
                if (!target) return;

                const newFilterValue = target.dataset.filter;

                // --- 1. อัปเดตค่าใน input ที่ซ่อนไว้ ---
                filterInput.value = newFilterValue;

                // --- 2. อัปเดตข้อความบนปุ่ม Dropdown ---
                filterButton.innerHTML = filterLabelsJS[newFilterValue];

                // --- 3. (สำคัญ) ส่งฟอร์ม (ค้นหา + กรอง) ---
                form.submit();
            });
        });
    </script>
    <?php
    // ✅ ปิดตรงนี้ (ล่างสุด) หรือปล่อยให้ PHP ปิดเองอัตโนมัติก็ได้
    if (isset($conn)) $conn->close();
    ?>

</body>

</html>