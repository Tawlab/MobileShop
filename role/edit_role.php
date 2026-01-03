<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'edit_role');

// 1. รับ ID ที่จะแก้ไข
$role_id_to_edit = (int)($_GET['id'] ?? 0);
if ($role_id_to_edit === 0) {
    die("ไม่พบ ID บทบาทที่ต้องการแก้ไข");
}

// 2. ดึงสิทธิ์เดิมที่มีอยู่ (Checked Permissions) เพื่อใช้ใน AJAX และ JS
$checked_permissions_from_db = [];
$stmt_get_checked = $conn->prepare("SELECT permissions_permission_id FROM role_permissions WHERE roles_role_id = ?");
$stmt_get_checked->bind_param("i", $role_id_to_edit);
$stmt_get_checked->execute();
$result_checked = $stmt_get_checked->get_result();
while ($row = $result_checked->fetch_assoc()) {
    $checked_permissions_from_db[] = $row['permissions_permission_id'];
}
$stmt_get_checked->close();

// ==========================================
// ส่วนจัดการ AJAX (สำหรับค้นหาและกรอง Real-time)
// ==========================================
if (isset($_GET['ajax'])) {
    $search_perm = isset($_GET['search_perm']) ? trim($_GET['search_perm']) : '';
    $filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'all';
    
    $sql_perms = "SELECT permission_id, permission_name, permission_desc FROM permissions";
    $where_clauses = [];
    $bind_types = "";
    $bind_values = [];

    // 1. กรองตามคำค้นหา
    if (!empty($search_perm)) {
        $where_clauses[] = "(permission_name LIKE ? OR permission_desc LIKE ?)";
        $search_like = "%" . $search_perm . "%";
        $bind_types .= "ss";
        array_push($bind_values, $search_like, $search_like);
    }

    // 2. กรองตามประเภท (ตามโจทย์ 2.1 - 2.6)
    if ($filter_type != 'all') {
        if ($filter_type == 'list') {
            // หน้าหลัก: ไม่ขึ้นต้นด้วย add, edit, del, delete, view
            $where_clauses[] = "permission_name NOT LIKE 'add_%' 
                                AND permission_name NOT LIKE 'edit_%' 
                                AND permission_name NOT LIKE 'del_%' 
                                AND permission_name NOT LIKE 'delete_%' 
                                AND permission_name NOT LIKE 'view_%'";
        } elseif ($filter_type == 'add') {
            $where_clauses[] = "permission_name LIKE 'add_%'";
        } elseif ($filter_type == 'edit') {
            $where_clauses[] = "permission_name LIKE 'edit_%'";
        } elseif ($filter_type == 'del') {
            $where_clauses[] = "(permission_name LIKE 'del_%' OR permission_name LIKE 'delete_%')";
        } elseif ($filter_type == 'view') {
            $where_clauses[] = "permission_name LIKE 'view_%'";
        }
    }

    if (!empty($where_clauses)) {
        $sql_perms .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    // 2.1 เรียงลำดับ A-Z
    $sql_perms .= " ORDER BY permission_name ASC";

    $stmt_perms = $conn->prepare($sql_perms);
    if (!empty($bind_types)) {
        $stmt_perms->bind_param($bind_types, ...$bind_values);
    }
    $stmt_perms->execute();
    $result_perms = $stmt_perms->get_result();
    
    // สร้าง Output HTML ส่งกลับไป
    if ($result_perms->num_rows > 0) {
        echo '<div class="row row-cols-1 row-cols-md-5 g-3">';
        while ($perm = $result_perms->fetch_assoc()) {
            // เช็คว่ามีสิทธิ์ใน DB เดิมหรือไม่ (JS จะมาจัดการเรื่องสิทธิ์ที่เพิ่งติ๊กให้อีกที)
            $is_checked_db = in_array($perm['permission_id'], $checked_permissions_from_db) ? 'checked' : '';
            ?>
            <div class="col">
                <div class="form-check">
                    <input class="form-check-input permission-checkbox" type="checkbox"
                        name="permissions[]"
                        value="<?= $perm['permission_id'] ?>"
                        id="perm_<?= $perm['permission_id'] ?>"
                        <?= $is_checked_db ?>>

                    <label class="form-check-label" for="perm_<?= $perm['permission_id'] ?>">
                        <?= htmlspecialchars($perm['permission_name']) ?>
                    </label>
                </div>
            </div>
            <?php
        }
        echo '</div>';
    } else {
        echo '<p class="text-center text-muted mb-0 py-5"><i class="fas fa-search-minus fa-2x mb-3 d-block"></i>ไม่พบสิทธิ์ที่ตรงกับเงื่อนไข</p>';
    }
    exit; // จบการทำงาน AJAX
}

// ==========================================
// ส่วนจัดการ POST (บันทึกข้อมูล Role + Permissions)
// ==========================================
$form_data = []; 
$errors_to_display = [];

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

    // ตรวจสอบว่าชื่อบทบาทซ้ำหรือไม่
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
            // 1. อัปเดต Role Info
            $sql_role = "UPDATE roles SET role_name = ?, role_desc = ?, update_at = NOW() WHERE role_id = ?";
            $stmt_role = $conn->prepare($sql_role);
            $stmt_role->bind_param("ssi", $role_name, $role_desc, $role_id_to_edit);
            $stmt_role->execute();
            $stmt_role->close();

            // 2. ลบสิทธิ์เดิม
            $sql_del_perm = "DELETE FROM role_permissions WHERE roles_role_id = ?";
            $stmt_del = $conn->prepare($sql_del_perm);
            $stmt_del->bind_param("i", $role_id_to_edit);
            $stmt_del->execute();
            $stmt_del->close();

            // 3. เพิ่มสิทธิ์ใหม่
            $sql_perm = "INSERT INTO role_permissions (roles_role_id, permissions_permission_id, create_at) VALUES (?, ?, NOW())";
            $stmt_perm = $conn->prepare($sql_perm);
            foreach ($selected_permissions as $perm_id) {
                $stmt_perm->bind_param("ii", $role_id_to_edit, $perm_id);
                $stmt_perm->execute();
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

// ดึงข้อมูล Role หลัก (กรณีไม่ใช่ POST หรือ POST Error)
if (empty($form_data)) {
    $stmt_get_role = $conn->prepare("SELECT * FROM roles WHERE role_id = ?");
    $stmt_get_role->bind_param("i", $role_id_to_edit);
    $stmt_get_role->execute();
    $result_role = $stmt_get_role->get_result();

    if ($result_role->num_rows === 0) {
        die("ไม่พบข้อมูลบทบาท ID: $role_id_to_edit");
    }
    $form_data = $result_role->fetch_assoc();
    $stmt_get_role->close();
}
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
        body { background-color: #f0fdf4; color: #333; font-family: 'Sarabun', sans-serif; }
        .form-container { max-width: 1000px; margin: 40px auto; }
        .card { border: none; border-radius: 15px; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08); }
        .card-header {
            background: linear-gradient(135deg, #2dd4bf 0%, #15803d 100%);
            color: white; border-top-left-radius: 15px; border-top-right-radius: 15px; padding: 1.25rem 1.5rem;
        }
        .permission-header {
            font-weight: 600; color: #15803d; margin-top: 1.5rem; margin-bottom: 0.5rem; font-size: 1.1rem;
        }
        .permission-grid {
            max-height: 500px; overflow-y: auto; background-color: #fff;
            border: 1px solid #dee2e6; padding: 1.5rem; border-radius: 10px;
        }
        .form-check {
            padding: 0.5rem; border-radius: 8px; border: 1px solid #e9ecef;
            transition: all 0.2s; display: flex; align-items: center;
        }
        .form-check:hover { border-color: #15803d; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .form-check-input { margin-top: 0; margin-right: 0.5rem; cursor: pointer; }
        .form-check-label { cursor: pointer; font-size: 0.9rem; user-select: none; width: 100%; }
        .permission-grid::-webkit-scrollbar { width: 8px; }
        .permission-grid::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        .permission-grid::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 10px; }
        .permission-grid::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
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
                                <h4 class="mb-0 text-white"><i class="fas fa-pencil-alt me-2"></i>แก้ไขบทบาท (ID: <?= $role_id_to_edit ?>)</h4>
                            </div>

                            <div class="card-body">
                                <?php if (!empty($errors_to_display)): ?>
                                    <div class="alert alert-danger mb-4">
                                        <ul class="mb-0 ps-3">
                                            <?php foreach ($errors_to_display as $error): ?>
                                                <li><?= htmlspecialchars($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label for="role_name" class="form-label">ชื่อบทบาท (Name)<span class="text-danger ms-1">*</span></label>
                                    <input type="text" class="form-control" id="role_name" name="role_name"
                                        value="<?= htmlspecialchars($form_data['role_name'] ?? '') ?>"
                                        required maxlength="50">
                                    <div class="form-text text-muted">
                                        ชื่อที่สื่อความหมาย เช่น 'Admin', 'Staff' (ต้องไม่ซ้ำกัน)
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="role_desc" class="form-label">คำอธิบาย (Description)</label>
                                    <textarea class="form-control" id="role_desc" name="role_desc" rows="3"
                                        maxlength="100"><?= htmlspecialchars($form_data['role_desc'] ?? '') ?></textarea>
                                </div>

                                <hr class="my-4">

                                <h5 class="permission-header">
                                    <i class="fas fa-shield-alt me-2"></i>เลือกสิทธิ์สำหรับบทบาทนี้<span class="text-danger ms-1">*</span>
                                </h5>

                                <div class="bg-light p-3 rounded mb-3 border">
                                    <div class="row g-2 align-items-center">
                                        <div class="col-md-5">
                                            <div class="input-group">
                                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                                <input type="text" class="form-control border-start-0 ps-0" id="search_perm" placeholder="ค้นหาสิทธิ์ (Real-time)...">
                                            </div>
                                        </div>
                                        <div class="col-md-7 d-flex gap-2 justify-content-md-end overflow-auto">
                                            <div class="btn-group" role="group">
                                                <input type="radio" class="btn-check filter-btn" name="filter_type" id="filter_all" value="all" checked>
                                                <label class="btn btn-outline-secondary btn-sm" for="filter_all">ทั้งหมด</label>

                                                <input type="radio" class="btn-check filter-btn" name="filter_type" id="filter_list" value="list">
                                                <label class="btn btn-outline-secondary btn-sm" for="filter_list">หน้าหลัก</label>

                                                <input type="radio" class="btn-check filter-btn" name="filter_type" id="filter_add" value="add">
                                                <label class="btn btn-outline-success btn-sm" for="filter_add">เพิ่ม</label>

                                                <input type="radio" class="btn-check filter-btn" name="filter_type" id="filter_edit" value="edit">
                                                <label class="btn btn-outline-warning btn-sm" for="filter_edit">แก้ไข</label>

                                                <input type="radio" class="btn-check filter-btn" name="filter_type" id="filter_del" value="del">
                                                <label class="btn btn-outline-danger btn-sm" for="filter_del">ลบ</label>

                                                <input type="radio" class="btn-check filter-btn" name="filter_type" id="filter_view" value="view">
                                                <label class="btn btn-outline-info btn-sm" for="filter_view">ดู</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="small text-muted" id="count_display">กำลังโหลด...</div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="selectAllBtn">
                                            <i class="fas fa-check-double me-1"></i> เลือกทั้งหมด (หน้านี้)
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="deselectAllBtn">
                                            <i class="fas fa-times me-1"></i> ยกเลิก (หน้านี้)
                                        </button>
                                    </div>
                                </div>

                                <div class="permission-grid" id="permissionContainer">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer text-center bg-light p-3">
                                <button type="submit" class="btn btn-success px-4">
                                    <i class="fas fa-save me-2"></i>บันทึกการแก้ไข
                                </button>
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
            const roleId = <?= $role_id_to_edit ?>;
            const permissionContainer = document.getElementById('permissionContainer');
            const searchInput = document.getElementById('search_perm');
            const filterRadios = document.querySelectorAll('.filter-btn');
            
            // ใช้ Set เก็บ ID ที่ถูกเลือก เพื่อไม่ให้หายเวลาเปลี่ยน Filter/Search
            // เริ่มต้นด้วยข้อมูลจาก Database (PHP ส่งมาเป็น JSON)
            const selectedPermissions = new Set(<?= json_encode(array_map('strval', $checked_permissions_from_db)) ?>);

            // ฟังก์ชันโหลดข้อมูล AJAX
            function loadPermissions() {
                const search = searchInput.value;
                const filter = document.querySelector('.filter-btn:checked').value;

                permissionContainer.style.opacity = '0.5';

                fetch(`edit_role.php?ajax=1&id=${roleId}&search_perm=${encodeURIComponent(search)}&filter_type=${filter}`)
                    .then(response => response.text())
                    .then(html => {
                        permissionContainer.innerHTML = html;
                        permissionContainer.style.opacity = '1';
                        restoreSelections(); // คืนค่าที่ติ๊กไว้
                        updateCount();
                    })
                    .catch(err => {
                        console.error(err);
                        permissionContainer.innerHTML = '<p class="text-center text-danger">เกิดข้อผิดพลาดในการโหลดข้อมูล</p>';
                    });
            }

            // ฟังก์ชันคืนค่าการติ๊กเลือกจาก Set -> DOM
            function restoreSelections() {
                const checkboxes = permissionContainer.querySelectorAll('.permission-checkbox');
                checkboxes.forEach(cb => {
                    if (selectedPermissions.has(cb.value)) {
                        cb.checked = true;
                    }
                    // เพิ่ม Event Listener ให้ Checkbox ทุกตัวที่โหลดมาใหม่
                    cb.addEventListener('change', function() {
                        if (this.checked) {
                            selectedPermissions.add(this.value);
                        } else {
                            selectedPermissions.delete(this.value);
                        }
                    });
                });
            }
            
            // ฟังก์ชันอัปเดตจำนวนรายการ
            function updateCount() {
                const count = permissionContainer.querySelectorAll('.permission-checkbox').length;
                document.getElementById('count_display').innerText = `แสดงผล ${count} รายการ`;
            }

            // Event Listeners
            searchInput.addEventListener('input', debounce(loadPermissions, 300));
            
            filterRadios.forEach(radio => {
                radio.addEventListener('change', loadPermissions);
            });

            // ปุ่มเลือกทั้งหมด (เฉพาะที่แสดงอยู่)
            document.getElementById('selectAllBtn').addEventListener('click', () => {
                const checkboxes = permissionContainer.querySelectorAll('.permission-checkbox');
                checkboxes.forEach(cb => {
                    cb.checked = true;
                    selectedPermissions.add(cb.value);
                });
            });

            // ปุ่มยกเลิกทั้งหมด (เฉพาะที่แสดงอยู่)
            document.getElementById('deselectAllBtn').addEventListener('click', () => {
                const checkboxes = permissionContainer.querySelectorAll('.permission-checkbox');
                checkboxes.forEach(cb => {
                    cb.checked = false;
                    selectedPermissions.delete(cb.value);
                });
            });

            // Form Submit Logic
            document.getElementById('editRoleForm').addEventListener('submit', function(e) {
                const roleName = document.getElementById('role_name').value.trim();
                
                // ตรวจสอบ Role Name
                if (roleName === '') {
                    e.preventDefault();
                    alert('กรุณากรอก "ชื่อบทบาท (Name)"');
                    document.getElementById('role_name').focus();
                    return;
                }

                // ตรวจสอบว่าเลือกสิทธิ์อย่างน้อย 1 รายการหรือไม่
                if (selectedPermissions.size === 0) {
                    e.preventDefault();
                    alert('กรุณาเลือกสิทธิ์อย่างน้อย 1 รายการ');
                    return;
                }
                
                // สร้าง Hidden Input สำหรับค่าที่อยู่ใน Set เพื่อส่งไปกับ Form POST
                // (เพราะ Checkbox บางตัวอาจถูกซ่อนโดย Filter ทำให้ไม่ถูก Submit ตามปกติ)
                selectedPermissions.forEach(val => {
                    // ถ้าค่านี้ไม่ได้มี input อยู่ในฟอร์มปัจจุบัน (เช่น ถูกซ่อนโดยตัวกรอง) หรือมีอยู่แล้วแต่อยากชัวร์
                    // สร้างใหม่เลยเพื่อความแน่นอน
                    if (!document.querySelector(`input[name="permissions[]"][value="${val}"]`)) {
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'permissions[]';
                        hiddenInput.value = val;
                        this.appendChild(hiddenInput);
                    }
                });
                
                // (หมายเหตุ: Checkbox ที่แสดงอยู่และ Checked จะถูกส่งไปโดยอัตโนมัติตามธรรมชาติของ HTML Form)
            });

            // Debounce function
            function debounce(func, wait) {
                let timeout;
                return function() {
                    const context = this, args = arguments;
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(context, args), wait);
                };
            }

            // โหลดครั้งแรก
            loadPermissions();
        });
    </script>
</body>
</html>