<?php
// --- delete_employee.php ---
session_start();
require '../config/config.php';
checkPageAccess($conn, 'delete_employee');

$redirect_target = 'employee.php';

// ตัวแปรสำหรับกำหนดหน้าตา Modal
$status = 'success';
$title = '';
$message = '';
$header_class = '';
$btn_class = '';
$icon = '';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: $redirect_target");
    exit();
}

$emp_id = (int)$_GET['id'];

try {
    // 1. ดึงข้อมูลพนักงานก่อนลบ (เพื่อเอา ID ไปลบตารางอื่น และเอารูปไปลบไฟล์)
    $sql_get = "SELECT users_user_id, Addresses_address_id, emp_image, firstname_th, lastname_th 
                FROM employees WHERE emp_id = ?";
    $stmt_get = $conn->prepare($sql_get);
    $stmt_get->bind_param("i", $emp_id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("ไม่พบข้อมูลพนักงาน");
    }

    $row = $result->fetch_assoc();
    $user_id = $row['users_user_id'];
    $address_id = $row['Addresses_address_id'];
    $image_path = $row['emp_image'];
    $emp_name = $row['firstname_th'] . ' ' . $row['lastname_th'];
    $stmt_get->close();

    // เริ่ม Transaction (ถ้าลบไม่ผ่านซักอัน ให้ยกเลิกทั้งหมด)
    $conn->begin_transaction();

    // 2. ลบสิทธิ์การใช้งาน (User Roles) ก่อน
    if ($user_id) {
        $stmt_role = $conn->prepare("DELETE FROM user_roles WHERE users_user_id = ?");
        $stmt_role->bind_param("i", $user_id);
        $stmt_role->execute();
        $stmt_role->close();
    }

    // 3. ลบข้อมูลพนักงาน (Employees) **จุดสำคัญ: ต้องลบอันนี้ก่อนลบ User**
    $stmt_emp = $conn->prepare("DELETE FROM employees WHERE emp_id = ?");
    $stmt_emp->bind_param("i", $emp_id);
    if (!$stmt_emp->execute()) {
        // ถ้าลบไม่ได้ (มักจะติด FK จากตารางการขาย bill_headers หรือการซ่อม)
        throw new Exception($conn->error, $conn->errno);
    }
    $stmt_emp->close();

    // 4. ลบข้อมูลผู้ใช้ (Users)
    if ($user_id) {
        $stmt_user = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $stmt_user->close();
    }

    // 5. ลบที่อยู่ (Addresses)
    if ($address_id) {
        $stmt_addr = $conn->prepare("DELETE FROM addresses WHERE address_id = ?");
        $stmt_addr->bind_param("i", $address_id);
        $stmt_addr->execute();
        $stmt_addr->close();
    }

    // ยืนยันการลบทั้งหมด
    $conn->commit();

    // 6. ลบไฟล์รูปภาพ (ทำหลังจาก Database สำเร็จแล้ว)
    if (!empty($image_path)) {
        $full_image_path = "../uploads/employees/" . $image_path;
        if (file_exists($full_image_path)) {
            @unlink($full_image_path);
        }
    }

    // --- ตั้งค่า Modal สำเร็จ ---
    $title = 'ลบข้อมูลสำเร็จ';
    $message = "ลบข้อมูลพนักงาน \"$emp_name\" เรียบร้อยแล้ว";
    $header_class = 'bg-success text-white';
    $btn_class = 'btn-success';
    $icon = '<i class="fas fa-check-circle fa-3x text-success mb-3"></i>';

} catch (Exception $e) {
    $conn->rollback(); // ยกเลิกการลบถ้ามีปัญหา

    // --- ดักจับ Foreign Key Error (เช่น พนักงานเคยขายของแล้ว) ---
    // Error 1451: Cannot delete or update a parent row: a foreign key constraint fails
    if ($e->getCode() == 1451 || strpos($e->getMessage(), 'foreign key constraint fails') !== false) {
        $title = 'ไม่สามารถลบข้อมูลได้';
        $message = "พนักงานรายนี้มีประวัติการทำรายการในระบบ (เช่น การขาย หรือ งานซ่อม)<br>ระบบป้องกันการลบเพื่อรักษาความถูกต้องของบัญชี<br><hr><strong>คำแนะนำ:</strong> กรุณาเปลี่ยนสถานะเป็น <strong>'ลาออก'</strong> หรือ <strong>'พักงาน'</strong> แทนการลบ";
        $header_class = 'bg-warning text-dark';
        $btn_class = 'btn-warning';
        $icon = '<i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>';
    } else {
        // Error อื่นๆ
        $title = 'เกิดข้อผิดพลาด';
        $message = "System Error: " . $e->getMessage();
        $header_class = 'bg-danger text-white';
        $btn_class = 'btn-danger';
        $icon = '<i class="fas fa-times-circle fa-3x text-danger mb-3"></i>';
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แจ้งเตือนระบบ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>

    <style>
        body {
            background-color: rgba(0, 0, 0, 0.5); /* พื้นหลังมืด */
        }
        .modal-content {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        .modal-header {
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }
    </style>
</head>

<body>

    <div class="modal fade" id="statusModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header <?= $header_class ?>">
                    <h5 class="modal-title fw-bold"><i class="fas fa-info-circle me-2"></i><?= $title ?></h5>
                </div>
                <div class="modal-body text-center py-4">
                    <?= $icon ?>
                    <h5 class="fw-bold mb-3"><?= $title ?></h5>
                    <p class="text-muted mb-0"><?= $message ?></p>
                </div>
                <div class="modal-footer justify-content-center border-0 pb-4">
                    <button type="button" class="btn <?= $btn_class ?> px-5 rounded-pill fw-bold shadow-sm" onclick="redirectBack()">
                        ตกลง / รับทราบ
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // แสดง Modal ทันทีเมื่อโหลดหน้าเสร็จ
        document.addEventListener('DOMContentLoaded', function() {
            var myModal = new bootstrap.Modal(document.getElementById('statusModal'));
            myModal.show();
        });

        // ฟังก์ชันกลับหน้ารายการ
        function redirectBack() {
            window.location.href = '<?= $redirect_target ?>';
        }
    </script>
</body>

</html>