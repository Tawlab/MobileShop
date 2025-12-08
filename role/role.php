<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'role');

// ส่วนรับข้อความแจ้งเตือน
$message = $_SESSION['message'] ?? null;
$message_type = $_SESSION['message_type'] ?? null;
unset($_SESSION['message'], $_SESSION['message_type']);

//ส่วนจัดการการค้นห
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$roles = [];

// SQL Query หลัก
$sql = "SELECT 
            role_id, 
            role_name, 
            role_desc, 
            create_at, 
            update_at 
        FROM roles
";

if (!empty($search_term)) {
    $sql .= "
        WHERE role_name LIKE ?
        OR role_desc LIKE ?
    ";
}

$sql .= " ORDER BY role_id ASC";
$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($search_term)) {
        $search_like = "%" . $search_term . "%";
        $stmt->bind_param("ss", $search_like, $search_like);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    //  ดึงข้อมูลทั้งหมดมาเก็บใน array 
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
    $stmt->close();
} else {
    // จัดการกรณี Query ผิดพลาด 
    die("Error preparing statement: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการบทบาท (Roles) - Mobile Shop</title>
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

        .btn-primary:hover {
            background-color: #166534;
            border-color: #166534;
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

        .action-icons .fa-shield-alt {
            color: #0d6efd;
        }

        /* (สีฟ้าสำหรับปุ่มกำหนดสิทธิ์) */
        .action-icons .fa-pencil {
            color: #f59e0b;
        }

        /* Amber-500 */
        .action-icons .fa-trash-can {
            color: #ef4444;
        }

        /* Red-500 */

        /* Alert */
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
                            <h4 class="mb-0"><i class="fas fa-users-cog me-2"></i>จัดการบทบาท (Roles)</h4>
                            <a href="add_role.php" class="btn btn-light"><i class="fas fa-plus me-2"></i>เพิ่มบทบาทใหม่</a>
                        </div>
                        <div class="card-body p-4">

                            <form method="GET" action="role.php" class="mb-4">
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control border-0 bg-light" name="search" placeholder="ค้นหาชื่อบทบาท หรือ คำอธิบาย..." value="<?= htmlspecialchars($search_term) ?>" style="padding: 0.75rem 1rem;">
                                    <button class="btn btn-primary" type="submit" style="padding: 0.75rem 1.25rem;">ค้นหา</button>
                                    <?php if (!empty($search_term)): ?>
                                        <a href="role.php" class="btn btn-outline-secondary">ล้างค่า</a>
                                    <?php endif; ?>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="text-nowrap">
                                        <tr>
                                            <th style="width: 5%;">ID</th>
                                            <th style="width: 20%;">ชื่อบทบาท (Name)</th>
                                            <th style="width: 35%;">คำอธิบาย (Description)</th>
                                            <th style="width: 15%;">สร้างเมื่อ</th>
                                            <th style="width: 15%;">อัปเดตเมื่อ</th>
                                            <th style="width: 10%;" class="text-center">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($roles) > 0): ?>
                                            <?php foreach ($roles as $role): ?>
                                                <tr>
                                                    <td><?= $role['role_id'] ?></td>
                                                    <td class="fw-bold"><?= htmlspecialchars($role['role_name']) ?></td>
                                                    <td><?= htmlspecialchars($role['role_desc'] ?? '-') ?></td>
                                                    <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($role['create_at'])) ?></td>
                                                    <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($role['update_at'])) ?></td>
                                                    <td class="text-center action-icons" style="white-space: nowrap;">
                                                        <a href="role_permissions.php?id=<?= $role['role_id'] ?>" title="กำหนดสิทธิ์"><i class="fas fa-shield-alt"></i></a>

                                                        <a href="edit_role.php?id=<?= $role['role_id'] ?>" title="แก้ไข"><i class="fas fa-pencil"></i></a>

                                                        <a href="delete_role.php?id=<?= $role['role_id'] ?>" title="ลบ"
                                                            onclick="return confirm('คำเตือน!\nคุณต้องการลบบทบาท \'<?= htmlspecialchars($role['role_name']) ?>\' ใช่หรือไม่?')">
                                                            <i class="fas fa-trash-can"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-5">
                                                    <?php if (!empty($search_term)): ?>
                                                        <h4><i class="fas fa-search-minus me-2"></i>ไม่พบข้อมูล</h4>
                                                        <p class="mb-0">ไม่พบบทบาทที่ตรงกับ "<?= htmlspecialchars($search_term) ?>"</p>
                                                    <?php else: ?>
                                                        <h4><i class="fas fa-box-open me-2"></i>ยังไม่มีข้อมูลบทบาท</h4>
                                                        <p class="mb-0">คลิกปุ่ม "เพิ่มบทบาทใหม่" เพื่อเริ่มต้น</p>
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
        // สำหรับซ่อน Alert
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
        }, 5000);
    </script>

    <?php
    if (isset($conn)) $conn->close();
    ?>

</body>

</html>