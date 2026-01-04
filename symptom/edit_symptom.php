<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'edit_symptom');

$current_user_id = $_SESSION['user_id'];
$shop_id = $_SESSION['shop_id']; // Shop ID ของคนล็อกอิน

// -----------------------------------------------------------------------------
// 1. ตรวจสอบสิทธิ์ (Admin / Permission)
// -----------------------------------------------------------------------------
$is_super_admin = false;
$has_central_perm = false;

$check_user_sql = "SELECT r.role_name, p.permission_name 
                   FROM users u
                   JOIN user_roles ur ON u.user_id = ur.users_user_id
                   JOIN roles r ON ur.roles_role_id = r.role_id
                   LEFT JOIN role_permissions rp ON r.role_id = rp.roles_role_id
                   LEFT JOIN permissions p ON rp.permissions_permission_id = p.permission_id
                   WHERE u.user_id = ?";

if ($stmt = $conn->prepare($check_user_sql)) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        if ($row['role_name'] === 'Admin') $is_super_admin = true;
        if ($row['permission_name'] === 'centralinf') $has_central_perm = true;
    }
    $stmt->close();
}

// -----------------------------------------------------------------------------
// 2. รับ ID และโหลดข้อมูลเดิม
// -----------------------------------------------------------------------------
$symptom_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($symptom_id <= 0) {
    $_SESSION['error'] = 'รหัสข้อมูลไม่ถูกต้อง';
    header('Location: symptoms.php');
    exit;
}

// ดึงข้อมูล
$sql_load = "SELECT * FROM symptoms WHERE symptom_id = ?";
$stmt_load = $conn->prepare($sql_load);
$stmt_load->bind_param("i", $symptom_id);
$stmt_load->execute();
$res_load = $stmt_load->get_result();
$symptom_data = $res_load->fetch_assoc();
$stmt_load->close();

if (!$symptom_data) {
    $_SESSION['error'] = 'ไม่พบข้อมูลอาการเสีย';
    header('Location: symptoms.php');
    exit;
}

// -----------------------------------------------------------------------------
// 3. ตรวจสอบสิทธิ์ความเป็นเจ้าของข้อมูล (Access Control)
// -----------------------------------------------------------------------------
$data_owner_shop_id = $symptom_data['shop_info_shop_id'];
$is_central_data = ($data_owner_shop_id == 0);
$is_own_data = ($data_owner_shop_id == $shop_id);

$can_edit = false;
if ($is_super_admin) {
    $can_edit = true; // Admin แก้ได้หมด
} elseif ($is_central_data && $has_central_perm) {
    $can_edit = true; // ส่วนกลาง + มีสิทธิ์ แก้ได้
} elseif ($is_own_data) {
    $can_edit = true; // ของตัวเอง แก้ได้
}

if (!$can_edit) {
    $_SESSION['error'] = 'คุณไม่มีสิทธิ์แก้ไขข้อมูลนี้';
    header('Location: symptoms.php');
    exit;
}

// -----------------------------------------------------------------------------
// 4. บันทึกข้อมูล (POST)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(mysqli_real_escape_string($conn, $_POST['symptom_name']));
    $desc = trim(mysqli_real_escape_string($conn, $_POST['symptom_desc']));
    $status = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    if (empty($name)) {
        $_SESSION['form_error'] = 'กรุณากรอกชื่ออาการเสีย';
    } else {
        // เช็คชื่อซ้ำ (ซ้ำกับ ID อื่น ใน Scope เดียวกัน)
        $chk_sql = "SELECT symptom_id FROM symptoms 
                    WHERE symptom_name = ? 
                    AND shop_info_shop_id = ? 
                    AND symptom_id != ?";
        $chk_stmt = $conn->prepare($chk_sql);
        $chk_stmt->bind_param("sii", $name, $data_owner_shop_id, $symptom_id);
        $chk_stmt->execute();

        if ($chk_stmt->get_result()->num_rows > 0) {
            $_SESSION['form_error'] = "ชื่ออาการ '$name' มีอยู่แล้วในระบบ";
        } else {
            // Update
            $update_sql = "UPDATE symptoms SET symptom_name = ?, symptom_desc = ?, is_active = ?, update_at = NOW() WHERE symptom_id = ?";
            $upd_stmt = $conn->prepare($update_sql);
            $upd_stmt->bind_param("ssii", $name, $desc, $status, $symptom_id);

            if ($upd_stmt->execute()) {
                $_SESSION['success'] = "แก้ไขข้อมูลเรียบร้อยแล้ว";
                header('Location: symptoms.php');
                exit;
            } else {
                $_SESSION['form_error'] = "เกิดข้อผิดพลาด: " . $upd_stmt->error;
            }
            $upd_stmt->close();
        }
        $chk_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>แก้ไขอาการเสีย</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-warning text-dark py-3">
                                <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>แก้ไขอาการเสีย #<?= $symptom_id ?></h5>
                            </div>
                            <div class="card-body p-4">

                                <div class="mb-4 text-center">
                                    <?php if ($is_central_data): ?>
                                        <span class="badge bg-secondary p-2"><i class="bi bi-globe2 me-1"></i> ข้อมูลส่วนกลาง</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark p-2"><i class="bi bi-shop me-1"></i> ข้อมูลสาขา</span>
                                    <?php endif; ?>
                                </div>

                                <form method="POST" id="editForm" novalidate>
                                    <div class="mb-3">
                                        <label for="symptom_name" class="form-label fw-bold">ชื่ออาการเสีย <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="symptom_name" name="symptom_name" required
                                            value="<?= htmlspecialchars($symptom_data['symptom_name']) ?>"
                                            placeholder="เช่น จอแตก, แบตเสื่อม">
                                    </div>

                                    <div class="mb-3">
                                        <label for="symptom_desc" class="form-label fw-bold">รายละเอียดเพิ่มเติม</label>
                                        <textarea class="form-control" id="symptom_desc" name="symptom_desc" rows="3" placeholder="ระบุรายละเอียด (ถ้ามี)"><?= htmlspecialchars($symptom_data['symptom_desc']) ?></textarea>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-bold d-block">สถานะการใช้งาน</label>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="is_active" id="status_active" value="1" <?= $symptom_data['is_active'] == 1 ? 'checked' : '' ?>>
                                            <label class="form-check-label text-success" for="status_active"><i class="bi bi-check-circle-fill"></i> ใช้งาน</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="is_active" id="status_inactive" value="0" <?= $symptom_data['is_active'] == 0 ? 'checked' : '' ?>>
                                            <label class="form-check-label text-secondary" for="status_inactive"><i class="bi bi-x-circle-fill"></i> ระงับ</label>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-warning btn-lg shadow-sm">
                                            <i class="bi bi-save me-2"></i>บันทึกการแก้ไข
                                        </button>
                                        <a href="symptoms.php" class="btn btn-outline-secondary">ยกเลิก</a>
                                    </div>
                                </form>
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
        // Error from PHP
        <?php if (isset($_SESSION['form_error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'บันทึกไม่สำเร็จ',
                text: '<?= $_SESSION['form_error'] ?>'
            });
            <?php unset($_SESSION['form_error']); ?>
        <?php endif; ?>

        // Client Validation
        document.getElementById('editForm').addEventListener('submit', function(e) {
            const name = document.getElementById('symptom_name').value.trim();
            if (name === '') {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'ข้อมูลไม่ครบถ้วน',
                    text: 'กรุณากรอกชื่ออาการเสีย'
                });
            }
        });
    </script>
</body>

</html>