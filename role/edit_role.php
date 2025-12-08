<?php
session_start();
require '../config/config.php'; 
checkPageAccess($conn, 'edit_role');

// รับ ID ที่จะแก้ไข
$role_id_to_edit = (int)($_GET['id'] ?? 0);
if ($role_id_to_edit === 0) {
    die("ไม่พบ ID บทบาทที่ต้องการแก้ไข");
}

// ตัวแปรสำหรับเก็บข้อมูล
$form_data = []; 
$errors_to_display = [];
$permissions_list = []; 
$checked_permissions = [];

//ส่วนจัดการ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ตรวจสอบ ID ว่าตรงกัน
    $post_id = (int)$_POST['role_id'];
    if ($post_id !== $role_id_to_edit) {
        die("ID ที่ส่งมาไม่ตรงกัน!");
    }

    // รับค่าจากฟอร์ม
    $role_name = trim($_POST['role_name']);
    $role_desc = trim($_POST['role_desc']) ?: NULL;
    $selected_permissions = $_POST['permissions'] ?? [];

    // ตรวจสอบข้อมูล
    $errors = [];
    if (empty($role_name)) {
        $errors[] = "กรุณากรอก 'ชื่อบทบาท (Name)'";
    }
    if (empty($selected_permissions)) {
        $errors[] = "กรุณาเลือกสิทธิ์ (Permissions) อย่างน้อย 1 รายการ";
    }

    //ตรวจสอบว่าชื่อบทบาทซ้ำหรือไม่
    if (empty($errors)) {
        $stmt_check = $conn->prepare("SELECT role_id FROM roles WHERE role_name = ? AND role_id != ?");
        $stmt_check->bind_param("si", $role_name, $role_id_to_edit);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();

        if ($check_result->num_rows > 0) {
            $errors[] = "ชื่อบทบาท (Name) '$role_name' นี้มีอยู่แล้วในระบบ";
        }
        $stmt_check->close();
    }

    if (empty($errors)) {
        $conn->begin_transaction(); 

        try {
            $sql_role = "UPDATE roles SET role_name = ?, role_desc = ?, update_at = NOW()
                         WHERE role_id = ?";
            $stmt_role = $conn->prepare($sql_role);
            if (!$stmt_role) throw new Exception("Prepare failed (roles): " . $conn->error);

            $stmt_role->bind_param("ssi", $role_name, $role_desc, $role_id_to_edit);
            if (!$stmt_role->execute()) throw new Exception("Execute failed (roles): " . $stmt_role->error);
            $stmt_role->close();
            $sql_del_perm = "DELETE FROM role_permissions WHERE roles_role_id = ?";
            $stmt_del = $conn->prepare($sql_del_perm);
            if (!$stmt_del) throw new Exception("Prepare failed (delete role_permissions): " . $conn->error);
            $stmt_del->bind_param("i", $role_id_to_edit);
            if (!$stmt_del->execute()) throw new Exception("Execute failed (delete role_permissions): " . $stmt_del->error);
            $stmt_del->close();
            $sql_perm = "INSERT INTO role_permissions (roles_role_id, permissions_permission_id, create_at) 
                         VALUES (?, ?, NOW())";
            $stmt_perm = $conn->prepare($sql_perm);
            if (!$stmt_perm) throw new Exception("Prepare failed (insert role_permissions): " . $conn->error);
            foreach ($selected_permissions as $perm_id) {
                $stmt_perm->bind_param("ii", $role_id_to_edit, $perm_id);
                if (!$stmt_perm->execute()) {
                    throw new Exception("Execute failed (role_permissions loop): " . $stmt_perm->error);
                }
            }
            $stmt_perm->close();

            // สำเร็จทั้งหมด
            $conn->commit();

            $_SESSION['message'] = "แก้ไขบทบาท '$role_name' (ID: $role_id_to_edit) สำเร็จ";
            $_SESSION['message_type'] = "success";
            header("Location: role.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback(); 
            $errors_to_display = ["เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage()];
            $form_data = $_POST; 
            $form_data['role_id'] = $role_id_to_edit;
        }
    } else {
        $errors_to_display = $errors;
        $form_data = $_POST;
        $form_data['role_id'] = $role_id_to_edit;
    }
}
$search_perm = isset($_GET['search_perm']) ? trim($_GET['search_perm']) : '';
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'all';

$filter_labels = [
    'all' => '<i class="fas fa-list me-1"></i> ทั้งหมด',
    'list' => '<i class="fas fa-chalkboard me-1"></i> หน้าหลัก',
    'add' => '<i class="fas fa-plus me-1"></i> เพิ่ม',
    'edit' => '<i class="fas fa-pencil me-1"></i> แก้ไข',
    'del' => '<i class="fas fa-trash-can me-1"></i> ลบ',
    'view' => '<i class="fas fa-eye me-1"></i> ดู'
];
$current_filter_label = $filter_labels[$filter_type] ?? $filter_labels['all'];

$sql_perms = "SELECT permission_id, permission_name, permission_desc FROM permissions";
$where_clauses = [];
$bind_types = "";
$bind_values = [];
if (!empty($search_perm)) {
    $where_clauses[] = "(permission_name LIKE ? OR permission_desc LIKE ?)";
    $search_like = "%" . $search_perm . "%";
    $bind_types .= "ss";
    array_push($bind_values, $search_like, $search_like);
}
if ($filter_type != 'all') {
    if ($filter_type == 'add') $where_clauses[] = "permission_name LIKE 'add_%'";
    elseif ($filter_type == 'edit') $where_clauses[] = "permission_name LIKE 'edit_%'";
    elseif ($filter_type == 'del') $where_clauses[] = "permission_name LIKE 'del_%'";
    elseif ($filter_type == 'view') $where_clauses[] = "permission_name LIKE 'view_%'";
    elseif ($filter_type == 'list') $where_clauses[] = "permission_name NOT LIKE 'add_%' AND permission_name NOT LIKE 'edit_%' AND permission_name NOT LIKE 'del_%' AND permission_name NOT LIKE 'view_%'";
}
if (!empty($where_clauses)) {
    $sql_perms .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql_perms .= " ORDER BY permission_name ASC";

$stmt_perms = $conn->prepare($sql_perms);
if ($stmt_perms) {
    if (!empty($bind_types)) {
        $stmt_perms->bind_param($bind_types, ...$bind_values);
    }
    $stmt_perms->execute();
    $result_perms = $stmt_perms->get_result();
    while ($row = $result_perms->fetch_assoc()) {
        $permissions_list[] = $row;
    }
    $stmt_perms->close();
} else {
    $errors_to_display[] = "Error fetching permissions: " . $conn->error;
}


// ดึงข้อมูล Role หลัก และ สิทธิ์ที่เลือกไว้
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    // ดึงข้อมูล Role
    $stmt_get_role = $conn->prepare("SELECT * FROM roles WHERE role_id = ?");
    $stmt_get_role->bind_param("i", $role_id_to_edit);
    $stmt_get_role->execute();
    $result_role = $stmt_get_role->get_result();

    if ($result_role->num_rows === 0) {
        die("ไม่พบข้อมูลบทบาท ID: $role_id_to_edit");
    }
    $form_data = $result_role->fetch_assoc();
    $stmt_get_role->close();

    // ดึงสิทธิ์ที่ Role 
    $stmt_get_checked = $conn->prepare("SELECT permissions_permission_id FROM role_permissions WHERE roles_role_id = ?");
    $stmt_get_checked->bind_param("i", $role_id_to_edit);
    $stmt_get_checked->execute();
    $result_checked = $stmt_get_checked->get_result();

    $checked_permissions_from_db = [];
    while ($row = $result_checked->fetch_assoc()) {
        $checked_permissions_from_db[] = $row['permissions_permission_id'];
    }
    $stmt_get_checked->close();
    $form_data['permissions'] = $checked_permissions_from_db;
}

$checked_permissions = $form_data['permissions'] ?? [];

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขบทบาท (ID: <?= $role_id_to_edit ?>) - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: #f0fdf4;
            color: #333;
        }

        .form-container {
            max-width: 900px;
            margin: 40px auto;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
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

        .card-body {
            padding: 2rem;
        }

        .form-label {
            font-weight: 500;
            color: #495057;
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 1px solid #ced4da;
            padding: 0.6rem 1rem;
            font-size: 0.9rem;
            background-color: #f8f9fa;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #15803d;
            box-shadow: 0 0 0 0.2rem rgba(21, 128, 61, 0.15);
            background-color: #fff;
        }

        .btn-success {
            background: linear-gradient(135deg, #2dd4bf 0%, #15803d 100%);
            border: none;
            font-weight: 500;
            padding: 0.6rem 1.5rem;
        }

        .btn-secondary {
            font-weight: 500;
            padding: 0.6rem 1.5rem;
        }

        .required {
            color: #dc3545;
            margin-left: 4px;
        }

        .form-text {
            font-size: 0.85rem;
        }

        .alert-danger ul {
            margin-bottom: 0;
            padding-left: 1.5rem;
        }

        /* (CSS สำหรับส่วนสิทธิ์) */
        .permission-header {
            font-weight: 600;
            color: #15803d;
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .filter-form-container {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .btn-outline-secondary {
            border-color: #ced4da;
        }

        .btn-outline-secondary:hover {
            background-color: #e9ecef;
        }

        .dropdown-item.active,
        .dropdown-item:active {
            background-color: #15803d;
        }

        /* (CSS สำหรับปุ่มเลือกทั้งหมด) */
        .select-all-container {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        /* (CSS สำหรับ Checkbox Grid) */
        .permission-grid {
            max-height: 400px;
            overflow-y: auto;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 1.5rem;
            border-radius: 10px;
        }

        .form-check {
            padding: 0.5rem;
            background-color: #fff;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            align-items: center;
        }

        .form-check:hover {
            border-color: #15803d;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .form-check-input {
            margin-top: 0;
            margin-right: 0.5rem;
        }

        .form-check-label {
            width: 100%;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="form-container">

                    <form method="POST" action="edit_role.php?id=<?= $role_id_to_edit ?>" id="editRoleForm">

                        <input type="hidden" name="role_id" value="<?= $role_id_to_edit ?>">

                        <div class="card">
                            <div class="card-header">
                                <h4 class="mb-0"><i class="fas fa-pencil-alt me-2"></i>แก้ไขบทบาท (ID: <?= $role_id_to_edit ?>)</h4>
                            </div>

                            <div class="card-body">

                                <?php if (!empty($errors_to_display)): ?>
                                    <div class="alert alert-danger mb-4">
                                        <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>ข้อมูลไม่ถูกต้อง</h5>
                                        <ul>
                                            <?php foreach ($errors_to_display as $error): ?>
                                                <li><?= htmlspecialchars($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label for="role_name" class="form-label">ชื่อบทบาท (Name)<span class="required">*</span></label>
                                    <input type="text" class="form-control" id="role_name" name="role_name"
                                        value="<?= htmlspecialchars($form_data['role_name'] ?? '') ?>"
                                        required maxlength="50">
                                    <div class="form-text text-muted">
                                        (บังคับ) ชื่อที่สื่อความหมาย เช่น 'Admin', 'Staff', 'Guest' (ต้องไม่ซ้ำกัน)
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="role_desc" class="form-label">คำอธิบาย (Description)</label>
                                    <textarea class="form-control" id="role_desc" name="role_desc" rows="3"
                                        maxlength="100"><?= htmlspecialchars($form_data['role_desc'] ?? '') ?></textarea>
                                </div>

                                <hr class="my-4">

                                <h5 class="permission-header">
                                    <i class="fas fa-shield-alt me-2"></i>เลือกสิทธิ์สำหรับบทบาทนี้<span class="required">*</span>
                                </h5>

                                <div class="filter-form-container">
                                    <form method="GET" action="edit_role.php" id="filterPermissionForm">
                                        <input type="hidden" name="id" value="<?= $role_id_to_edit ?>">

                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-0"><i class="fas fa-search"></i></span>
                                            <input type="text" class="form-control border-0 bg-light" name="search_perm" id="search_perm"
                                                placeholder="ค้นหาสิทธิ์..."
                                                value="<?= htmlspecialchars($search_perm) ?>">

                                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="min-width: 150px;">
                                                <?= $current_filter_label ?>
                                            </button>

                                            <ul class="dropdown-menu dropdown-menu-end" id="filterOptions">
                                                <li><a class="dropdown-item <?= ($filter_type == 'all') ? 'active' : '' ?>" href="#" data-filter="all"><?= $filter_labels['all'] ?></a></li>
                                                <li><a class="dropdown-item <?= ($filter_type == 'list') ? 'active' : '' ?>" href="#" data-filter="list"><?= $filter_labels['list'] ?></a></li>
                                                <li><a class="dropdown-item <?= ($filter_type == 'add') ? 'active' : '' ?>" href="#" data-filter="add"><?= $filter_labels['add'] ?></a></li>
                                                <li><a class="dropdown-item <?= ($filter_type == 'edit') ? 'active' : '' ?>" href="#" data-filter="edit"><?= $filter_labels['edit'] ?></a></li>
                                                <li><a class="dropdown-item <?= ($filter_type == 'del') ? 'active' : '' ?>" href="#" data-filter="del"><?= $filter_labels['del'] ?></a></li>
                                                <li><a class="dropdown-item <?= ($filter_type == 'view') ? 'active' : '' ?>" href="#" data-filter="view"><?= $filter_labels['view'] ?></a></li>
                                            </ul>

                                            <button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i> กรอง</button>
                                            <?php if (!empty($search_perm) || $filter_type != 'all'): ?>
                                                <a href="edit_role.php?id=<?= $role_id_to_edit ?>" class="btn btn-outline-secondary">ล้างกรอง</a>
                                            <?php endif; ?>
                                        </div>
                                        <input type="hidden" name="filter_type" id="filter_type_input" value="<?= htmlspecialchars($filter_type) ?>">
                                    </form>
                                </div>

                                <div class="select-all-container d-flex gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="selectAllBtn">
                                        <i class="fas fa-check-double me-1"></i> เลือกทั้งหมด
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="deselectAllBtn">
                                        <i class="fas fa-times me-1"></i> ยกเลิกทั้งหมด
                                    </button>
                                </div>

                                <div class="permission-grid">
                                    <?php if (count($permissions_list) > 0): ?>
                                        <div class="row row-cols-1 row-cols-md-5 g-3">
                                            <?php foreach ($permissions_list as $perm): ?>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox"
                                                            name="permissions[]"
                                                            value="<?= $perm['permission_id'] ?>"
                                                            id="perm_<?= $perm['permission_id'] ?>"
                                                            <?= (in_array($perm['permission_id'], $checked_permissions)) ? 'checked' : '' ?>>

                                                        <label class="form-check-label" for="perm_<?= $perm['permission_id'] ?>">
                                                            <?= htmlspecialchars($perm['permission_name']) ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-center text-muted mb-0">ไม่พบสิทธิ์ที่ตรงกับตัวกรอง</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="card-footer text-center bg-light p-3">
                                <div class="d-flex gap-2 justify-content-center">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save me-2"></i>บันทึกการแก้ไข
                                    </button>
                                    <a href="role.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>ยกเลิก
                                    </a>
                                </div>
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

            const filterOptions = document.getElementById('filterOptions');
            const filterInput = document.getElementById('filter_type_input');
            const filterForm = document.getElementById('filterPermissionForm');

            if (filterOptions) {
                filterOptions.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = e.target.closest('a.dropdown-item');
                    if (!target) return;

                    filterInput.value = target.dataset.filter;
                    filterForm.submit();
                });
            }

            //  สำหรับปุ่มเลือกทั้งหมด
            const selectAllBtn = document.getElementById('selectAllBtn');
            const deselectAllBtn = document.getElementById('deselectAllBtn');
            const permissionCheckboxes = document.querySelectorAll('.permission-grid input[type="checkbox"]');

            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', function() {
                    permissionCheckboxes.forEach(cb => {
                        cb.checked = true;
                    });
                });
            }

            if (deselectAllBtn) {
                deselectAllBtn.addEventListener('click', function() {
                    permissionCheckboxes.forEach(cb => {
                        cb.checked = false;
                    });
                });
            }

            // สำหรับฟอร์มหลัก
            const mainForm = document.getElementById('editRoleForm');
            mainForm.addEventListener('submit', function(event) {
                const roleName = document.getElementById('role_name').value.trim();
                const checkboxes = mainForm.querySelectorAll('input[name="permissions[]"]:checked');

                if (roleName === '') {
                    event.preventDefault();
                    alert('กรุณากรอก "ชื่อบทบาท (Name)"');
                    document.getElementById('role_name').focus();
                    return;
                }

                if (checkboxes.length === 0) {
                    event.preventDefault();
                    alert('กรุณาเลือกสิทธิ์ (Permissions) อย่างน้อย 1 รายการ');
                    document.querySelector('.permission-grid').focus();
                    return;
                }
            });
        });
    </script>
</body>

</html>