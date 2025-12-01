<?php
// --- role/add_role.php ---
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_role');

// --- (ตัวแปรสำหรับเก็บข้อมูล) ---
$form_data = [];
$errors_to_display = [];
$permissions_list = [];

// --- (A. ส่วนจัดการ POST: เมื่อกดบันทึก) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (ส่วนบันทึกข้อมูล เหมือนเดิมทุกประการ) ...
    $role_name = trim($_POST['role_name']);
    $role_desc = trim($_POST['role_desc']) ?: NULL;
    $selected_permissions = $_POST['permissions'] ?? [];

    $errors = [];
    if (empty($role_name)) $errors[] = "กรุณากรอก 'ชื่อบทบาท (Name)'";
    if (empty($selected_permissions)) $errors[] = "กรุณาเลือกสิทธิ์ (Permissions) อย่างน้อย 1 รายการ";

    if (empty($errors)) {
        $stmt_check = $conn->prepare("SELECT role_id FROM roles WHERE role_name = ?");
        $stmt_check->bind_param("s", $role_name);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) $errors[] = "ชื่อบทบาทนี้มีอยู่แล้ว";
        $stmt_check->close();
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $sql_max = "SELECT IFNULL(MAX(role_id), 0) + 1 AS next_id FROM roles";
            $next_id = $conn->query($sql_max)->fetch_assoc()['next_id'];

            $stmt = $conn->prepare("INSERT INTO roles (role_id, role_name, role_desc, create_at, update_at) VALUES (?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("iss", $next_id, $role_name, $role_desc);
            $stmt->execute();
            $stmt->close();

            $stmt_p = $conn->prepare("INSERT INTO role_permissions (roles_role_id, permissions_permission_id) VALUES (?, ?)");
            foreach ($selected_permissions as $perm_id) {
                $stmt_p->bind_param("ii", $next_id, $perm_id);
                $stmt_p->execute();
            }
            $stmt_p->close();

            $conn->commit();
            $_SESSION['message'] = "เพิ่มบทบาทสำเร็จ";
            $_SESSION['message_type'] = "success";
            header("Location: role.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors_to_display = ["เกิดข้อผิดพลาด: " . $e->getMessage()];
            $form_data = $_POST;
        }
    } else {
        $errors_to_display = $errors;
        $form_data = $_POST;
    }
}

// --- (B. ส่วนดึงข้อมูลสิทธิ์ - แก้ไขให้ดึงทั้งหมดมาเลย) ---
// ไม่ต้องรับค่า GET search/filter แล้ว เพราะเราจะกรองด้วย JS
$sql_perms = "SELECT * FROM permissions ORDER BY permission_name ASC";
$result_perms = $conn->query($sql_perms);
while ($row = $result_perms->fetch_assoc()) {
    $permissions_list[] = $row;
}

// Helper labels สำหรับ JS
$filter_labels = [
    'all' => '<i class="fas fa-list me-1"></i> ทั้งหมด',
    'list' => '<i class="fas fa-chalkboard me-1"></i> หน้าหลัก',
    'add' => '<i class="fas fa-plus me-1"></i> เพิ่ม',
    'edit' => '<i class="fas fa-pencil me-1"></i> แก้ไข',
    'del' => '<i class="fas fa-trash-can me-1"></i> ลบ',
    'view' => '<i class="fas fa-eye me-1"></i> ดู'
];
$checked_permissions = $form_data['permissions'] ?? [];
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>เพิ่มบทบาทใหม่</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        .form-container {
            max-width: 1000px;
            margin: 40px auto;
        }

        /* ขยาย Container ให้กว้างขึ้น */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }

        .permission-grid {
            max-height: 500px;
            overflow-y: auto;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            border: 1px solid #dee2e6;
        }

        .form-check {
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 0.5rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            height: 100%;
        }

        .form-check:hover {
            border-color: #198754;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .form-check-input {
            margin-top: 0;
            margin-right: 0.5rem;
        }

        .perm-item.d-none {
            display: none !important;
        }

        .form-check-label {
            cursor: pointer;
            font-size: 0.85rem;
        }

        /* ปรับขนาดตัวอักษรเล็กน้อยเพื่อให้พอดี 5 คอลัมน์ */
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="form-container">
                    <form method="POST" id="addRoleForm">
                        <div class="card">
                            <div class="card-header bg-success text-white py-3">
                                <h5 class="mb-0 text-light"><i class="fas fa-plus-circle me-2"></i>เพิ่มบทบาทใหม่</h5>
                            </div>
                            <div class="card-body p-4">
                                <?php if (!empty($errors_to_display)): ?>
                                    <div class="alert alert-danger">
                                        <ul class="mb-0 ps-3">
                                            <?php foreach ($errors_to_display as $err) echo "<li>$err</li>"; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label class="form-label">ชื่อบทบาท <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="role_name" value="<?= htmlspecialchars($form_data['role_name'] ?? '') ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">คำอธิบาย</label>
                                    <textarea class="form-control" name="role_desc" rows="2"><?= htmlspecialchars($form_data['role_desc'] ?? '') ?></textarea>
                                </div>

                                <hr class="my-4">

                                <h5 class="text-success mb-3"><i class="fas fa-shield-alt me-2"></i>กำหนดสิทธิ์</h5>

                                <div class="input-group mb-3">
                                    <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="search_perm" placeholder="ค้นหาสิทธิ์...">

                                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" id="filterBtn">
                                        <?= $filter_labels['all'] ?>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" id="filterOptions">
                                        <?php foreach ($filter_labels as $key => $label): ?>
                                            <li><a class="dropdown-item" href="#" data-value="<?= $key ?>"><?= $label ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <input type="hidden" id="filter_value" value="all">
                                </div>

                                <div class="mb-3">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllBtn">เลือกทั้งหมด (ที่แสดง)</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllBtn">ยกเลิกทั้งหมด</button>
                                </div>

                                <div class="permission-grid">
                                    <div class="row row-cols-2 row-cols-md-5 g-2" id="permContainer">
                                        <?php foreach ($permissions_list as $perm):
                                            // แยกประเภทสิทธิ์จากชื่อ (เช่น add_product -> add)
                                            $parts = explode('_', $perm['permission_name']);
                                            $type = $parts[0];
                                            // ถ้าไม่ใช่ประเภทมาตรฐาน ให้เป็น list
                                            if (!in_array($type, ['add', 'edit', 'del', 'view'])) $type = 'list';

                                            // ข้อความสำหรับค้นหา (รวมชื่อและคำอธิบาย)
                                            $searchText = strtolower($perm['permission_name'] . ' ' . $perm['permission_desc']);
                                        ?>
                                            <div class="col perm-item" data-type="<?= $type ?>" data-text="<?= $searchText ?>">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                                        value="<?= $perm['permission_id'] ?>"
                                                        id="perm_<?= $perm['permission_id'] ?>"
                                                        <?= in_array($perm['permission_id'], $checked_permissions) ? 'checked' : '' ?>>
                                                    <label class="form-check-label w-100" for="perm_<?= $perm['permission_id'] ?>">
                                                        <span class="fw-bold d-block text-truncate"><?= htmlspecialchars($perm['permission_name']) ?></span>
                                                        <small class="text-muted d-block text-truncate" style="font-size: 0.75rem;"><?= htmlspecialchars($perm['permission_desc']) ?></small>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                            </div>
                            <div class="card-footer text-center bg-white py-3">
                                <button type="submit" class="btn btn-success px-4"><i class="fas fa-save me-2"></i>บันทึกข้อมูล</button>
                                <a href="role.php" class="btn btn-secondary px-4 ms-2">ยกเลิก</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search_perm');
            const filterOptions = document.querySelectorAll('#filterOptions .dropdown-item');
            const filterBtn = document.getElementById('filterBtn');
            const filterValInput = document.getElementById('filter_value');
            const permItems = document.querySelectorAll('.perm-item');

            // ฟังก์ชันกรองข้อมูล
            function filterItems() {
                const searchText = searchInput.value.toLowerCase();
                const filterType = filterValInput.value;

                permItems.forEach(item => {
                    const itemText = item.dataset.text;
                    const itemType = item.dataset.type;

                    // เช็คเงื่อนไข: ข้อความตรงไหม AND ประเภทตรงไหม
                    const matchSearch = itemText.includes(searchText);
                    const matchType = (filterType === 'all' || filterType === itemType);

                    if (matchSearch && matchType) {
                        item.classList.remove('d-none');
                    } else {
                        item.classList.add('d-none');
                    }
                });
            }

            // Event: พิมพ์ค้นหา
            searchInput.addEventListener('keyup', filterItems);

            // Event: เลือก Dropdown ประเภท
            filterOptions.forEach(opt => {
                opt.addEventListener('click', function(e) {
                    e.preventDefault();
                    const val = this.dataset.value;
                    const text = this.innerHTML;

                    filterValInput.value = val;
                    filterBtn.innerHTML = text; // เปลี่ยนชื่อปุ่ม
                    filterItems(); // เรียกฟังก์ชันกรอง
                });
            });

            // ปุ่มเลือกทั้งหมด (เฉพาะที่มองเห็นอยู่)
            document.getElementById('selectAllBtn').addEventListener('click', function() {
                document.querySelectorAll('.perm-item:not(.d-none) input[type="checkbox"]').forEach(cb => cb.checked = true);
            });

            // ปุ่มยกเลิกทั้งหมด
            document.getElementById('deselectAllBtn').addEventListener('click', function() {
                document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
            });
        });
    </script>
</body>

</html>