<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'employee');

// [แก้ไข 1] รับค่า Shop ID จาก Session
$shop_id = $_SESSION['shop_id'];

// --- (ส่วนรับข้อความแจ้งเตือนจากหน้า Add/Edit) ---
$message = $_SESSION['message'] ?? null;
$message_type = $_SESSION['message_type'] ?? null;
unset($_SESSION['message'], $_SESSION['message_type']);

// --- (ส่วนจัดการการค้นหาและฟิลเตอร์) ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'All'; 

$sql = "
    SELECT
        e.emp_id,
        e.emp_code,
        e.firstname_th,
        e.lastname_th,
        e.emp_status,
        e.emp_image,
        p.prefix_th,
        d.dept_name,
        b.branch_name
    FROM employees AS e
    LEFT JOIN prefixs p ON e.prefixs_prefix_id = p.prefix_id
    LEFT JOIN departments d ON e.departments_dept_id = d.dept_id
    LEFT JOIN branches b ON e.branches_branch_id = b.branch_id
";

// [แก้ไข 2] เพิ่มเงื่อนไขกรอง Shop ID ผ่านตาราง Branch
$where_clauses = ["b.shop_info_shop_id = ?"];
$bind_types = "i"; // i = integer
$bind_values = [$shop_id];

// --- เงื่อนไขการค้นหา ---
if (!empty($search_term)) {
    $where_clauses[] = "(e.emp_code LIKE ? OR e.firstname_th LIKE ? OR e.lastname_th LIKE ? OR d.dept_name LIKE ? OR b.branch_name LIKE ?)";
    $search_like = "%" . $search_term . "%";
    $bind_types .= "sssss";
    array_push($bind_values, $search_like, $search_like, $search_like, $search_like, $search_like);
}

// --- เงื่อนไขฟิลเตอร์สถานะ  ---
if ($status_filter != 'All') {
    $where_clauses[] = "e.emp_status = ?";
    $bind_types .= "s";
    $bind_values[] = $status_filter;
}

// --- (รวมเงื่อนไข WHERE) ---
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY e.emp_id DESC"; // เรียงจากใหม่ไปเก่า

// --- ใช้ Prepared Statement เพื่อความปลอดภัย ---
$stmt = $conn->prepare($sql);

if ($stmt) {
    // --- ผูกค่าพารามิเตอร์ถ้ามี ---
    if (!empty($bind_types)) {
        $stmt->bind_param($bind_types, ...$bind_values);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    // --- ดึงข้อมูลทั้งหมดมาเก็บใน array ---
    $employees = []; // ประกาศตัวแปร array ไว้ก่อนกัน Error
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
    $stmt->close();
} else {
    // --- จัดการกรณี Query ผิดพลาด ---
    die("Error preparing statement: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการพนักงาน - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <?php require '../config/load_theme.php'; ?>

    <style>
        body {
            background-color: #f0fdf4;
            color: #333;
        }

        /* การ์ดหลัก */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        /* หัวการ์ด */
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

        /* ปุ่มค้นหา */
        .btn-primary {
            background-color: #15803d;
            border-color: #15803d;
        }

        .btn-primary:hover {
            background-color: #166534;
            border-color: #166534;
        }

        /* ตาราง */
        .table thead {
            background-color: #f0fdf4;
            color: #14532d;
            font-weight: 600;
        }

        .table th {
            border-bottom: 2px solid #a7f3d0 !important;
            padding: 1rem 0.75rem;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
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

        /* รูปโปรไฟล์ */
        .profile-pic-sm {
            width: 45px;
            height: 45px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #a7f3d0;
        }

        .profile-icon {
            font-size: 2.5rem;
            color: #d1d5db;
        }

        /* --- CSS สำหรับ Dropdown สถานะ --- */
        .status-select {
            font-size: 0.8rem;
            font-weight: 500;
            border-radius: 50rem;
            padding: 0.3em 0.8em;
            border: 1px solid transparent;
            background-position: right 0.5rem center;
            padding-right: 1.75rem;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            cursor: pointer;
            width: 120px;
        }

        .status-select:focus {
            box-shadow: 0 0 0 0.2rem rgba(21, 128, 61, 0.15);
        }

        /* สีตอน Active */
        .status-select.status-select-active {
            background-color: #d1fae5;
            color: #065f46;
            border-color: #a7f3d0;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23065f46' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        }

        /* สีตอน Resigned */
        .status-select.status-select-resigned {
            background-color: #f3f4f6;
            color: #4b5563;
            border-color: #d1d5db;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%234b5563' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        }

        /* ไอคอน จัดการ */
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

        .action-icons .fa-eye {
            color: #0e7490;
        }

        .action-icons .fa-pencil {
            color: #f59e0b;
        }

        .action-icons .fa-trash-can {
            color: #ef4444;
        }

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
                    <div class="custom-alert alert-<?= $message_type == 'success' ? 'success' : 'danger' ?> shadow-sm mb-4" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-triangle' ?> fa-2x me-3"></i>
                            <div>
                                <strong class="d-block"><?= $message_type == 'success' ? 'สำเร็จ!' : 'เกิดข้อผิดพลาด!' ?></strong>
                                <span><?= htmlspecialchars($message) ?></span>
                            </div>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="container-lg">
                    <div class="card border-0 shadow-sm">

                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h4 class="mb-0" style="color: <?= $theme_color ?>;">
                                <i class="fas fa-users me-2"></i>รายการพนักงาน
                            </h4>
                            <a href="add_employee.php" class="btn btn-success text-white shadow-sm">
                                <i class="fas fa-user-plus me-2"></i>เพิ่มพนักงาน
                            </a>
                        </div>

                        <div class="card-body p-4">

                            <form method="GET" action="employee.php" class="mb-4">
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                                    <input type="text" class="form-control border-start-0 bg-light" name="search"
                                        placeholder="ค้นหารหัส, ชื่อ-สกุล, แผนก..."
                                        value="<?= htmlspecialchars($search_term) ?>">

                                    <span class="input-group-text bg-light border-end-0 ms-2 rounded-start"><i class="fas fa-filter text-muted"></i></span>
                                    <select name="status_filter" class="form-select border-start-0 bg-light" style="max-width: 180px;">
                                        <option value="All" <?= ($status_filter == 'All') ? 'selected' : '' ?>>สถานะทั้งหมด</option>
                                        <option value="Active" <?= ($status_filter == 'Active') ? 'selected' : '' ?>>ทำงานอยู่</option>
                                        <option value="Resigned" <?= ($status_filter == 'Resigned') ? 'selected' : '' ?>>ลาออก</option>
                                    </select>

                                    <button class="btn btn-primary ms-2" type="submit"><i class="fas fa-search"></i> ค้นหา</button>

                                    <?php if (!empty($search_term) || $status_filter != 'All'): ?>
                                        <a href="employee.php" class="btn btn-outline-secondary ms-1">ล้างค่า</a>
                                    <?php endif; ?>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead style="background-color: <?= $header_bg_color ?>; color: <?= $header_text_color ?>;">
                                        <tr>
                                            <th class="text-center" style="width: 5%;">#</th>
                                            <th class="text-center" style="width: 8%;">รูป</th>
                                            <th style="width: 15%;">รหัสพนักงาน</th>
                                            <th style="width: 25%;">ชื่อ - สกุล</th>
                                            <th class="text-center" style="width: 15%;">แผนก</th>
                                            <th class="text-center" style="width: 12%;">สาขา</th>
                                            <th class="text-center" style="width: 10%;">สถานะ</th>
                                            <th class="text-center" style="width: 10%;">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($employees) > 0): ?>
                                            <?php foreach ($employees as $index => $emp): ?>
                                                <tr>
                                                    <td class="text-center text-muted"><?= $index + 1 ?></td>

                                                    <td class="text-center">
                                                        <?php if (!empty($emp['emp_image']) && file_exists("../uploads/employees/" . $emp['emp_image'])): ?>
                                                            <img src="../uploads/employees/<?= htmlspecialchars($emp['emp_image']) ?>"
                                                                alt="Profile"
                                                                class="rounded-circle border"
                                                                style="width: 40px; height: 40px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center text-secondary border"
                                                                style="width: 40px; height: 40px;">
                                                                <i class="fas fa-user"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>

                                                    <td class="fw-bold text-primary"><?= htmlspecialchars($emp['emp_code']) ?></td>

                                                    <td>
                                                        <div class="fw-bold"><?= htmlspecialchars($emp['prefix_th'] . $emp['firstname_th'] . ' ' . $emp['lastname_th']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($emp['emp_email'] ?? '') ?></small>
                                                    </td>

                                                    <td class="text-center"><span class="badge bg-light text-dark border"><?= htmlspecialchars($emp['dept_name'] ?? '-') ?></span></td>
                                                    <td class="text-center"><?= htmlspecialchars($emp['branch_name'] ?? '-') ?></td>

                                                    <td class="text-center">
                                                        <?php if ($emp['emp_status'] == 'Active'): ?>
                                                            <span class="badge bg-success rounded-pill px-3">ทำงานอยู่</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary rounded-pill px-3">ลาออก</span>
                                                        <?php endif; ?>
                                                    </td>

                                                    <td class="text-center">
                                                        <div class="btn-group">
                                                            <a href="view_employee.php?id=<?= $emp['emp_id'] ?>" class="btn btn-sm btn-outline-info" title="ดูรายละเอียด">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="print_employee.php?id=<?= $emp['emp_id'] ?>" class="btn btn-sm btn-outline-primary" title="พิมพ์ใบประวัติ" target="_blank">
                                                                <i class="fas fa-print"></i>
                                                            </a>
                                                            <a href="edit_employee.php?id=<?= $emp['emp_id'] ?>" class="btn btn-sm btn-outline-warning" title="แก้ไข">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="delete_employee.php?id=<?= $emp['emp_id'] ?>" class="btn btn-sm btn-outline-danger"
                                                                onclick="return confirm('คุณต้องการลบข้อมูลพนักงาน <?= htmlspecialchars($emp['firstname_th']) ?> ใช่หรือไม่?');" title="ลบ">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-5 text-muted">
                                                    <i class="fas fa-user-slash fa-3x mb-3 opacity-50"></i><br>
                                                    <?php if (!empty($search_term) || $status_filter != 'All'): ?>
                                                        ไม่พบข้อมูลพนักงานที่ตรงกับการค้นหา
                                                    <?php else: ?>
                                                        ยังไม่มีข้อมูลพนักงานในระบบ
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
    <?php
    if (isset($conn)) $conn->close();
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- สำหรับซ่อน Alert ---
        setTimeout(() => {
            document.querySelectorAll('.custom-alert').forEach(alert => {
                const bsAlert = bootstrap.Alert.getInstance(alert);
                if (bsAlert) {
                    bsAlert.close();
                } else {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 5000);

        // สลับสถานะ
        document.addEventListener('DOMContentLoaded', function() {
            // เลือก dropdown สลับสถานะทุกอัน 
            const statusSelects = document.querySelectorAll('.status-select');

            statusSelects.forEach(select => {
                select.addEventListener('change', function(e) {

                    const sel = this; 
                    const empId = sel.dataset.id;
                    const newStatus = sel.value;
                    const currentStatus = sel.dataset.status; 

                    if (newStatus === currentStatus) {
                        return;
                    }

                    // --- ถามยืนยัน ---
                    if (!confirm(`คุณต้องการเปลี่ยนสถานะพนักงาน ID: ${empId}\nจาก "${currentStatus}" เป็น "${newStatus}" ใช่หรือไม่?`)) {
                        sel.value = currentStatus;
                        return;
                    }

                    // --- ส่งข้อมูลไปอัปเดต (Fetch/AJAX) ---
                    fetch('toggle_employee_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                emp_id: empId,
                                new_status: newStatus
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // ถ้าอัปเดตสำเร็จ
                                sel.dataset.status = newStatus;
                                if (newStatus === 'Active') {
                                    sel.classList.remove('status-select-resigned');
                                    sel.classList.add('status-select-active');
                                } else {
                                    sel.classList.remove('status-select-active');
                                    sel.classList.add('status-select-resigned');
                                }

                                showTempAlert('สำเร็จ!', 'เปลี่ยนสถานะพนักงานเรียบร้อยแล้ว', 'success');

                            } else {
                                // ถ้าล้มเหลว
                                alert('เกิดข้อผิดพลาด: ' + data.message);
                                sel.value = currentStatus; 
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                            sel.value = currentStatus;
                        });
                });
            });

            // สร้าง Alert
            function showTempAlert(title, message, type = 'success') {
                const icon = (type === 'success') ? 'fa-check-circle' : 'fa-exclamation-triangle';
                const alertType = (type === 'success') ? 'alert-success' : 'alert-error';

                const alertDiv = document.createElement('div');
                alertDiv.className = `custom-alert ${alertType}`;
                alertDiv.setAttribute('role', 'alert');
                alertDiv.innerHTML = `
                    <i class="fas ${icon} fa-lg"></i>
                    <div><strong>${title}</strong><br>${message}</div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close" style="filter: invert(1) grayscale(100%) brightness(200%);"></button>
                `;

                document.body.appendChild(alertDiv);

                setTimeout(() => {
                    const bsAlert = bootstrap.Alert.getInstance(alertDiv);
                    if (bsAlert) {
                        bsAlert.close();
                    } else {
                        alertDiv.style.transition = 'opacity 0.5s ease';
                        alertDiv.style.opacity = '0';
                        setTimeout(() => alertDiv.remove(), 500);
                    }
                }, 3000);
            }

        });
    </script>
</body>

</html>