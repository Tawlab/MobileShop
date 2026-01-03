<?php
session_start();
require '../config/config.php';

// ตรวจสอบสิทธิ์
checkPageAccess($conn, 'menu_manage_users');

$current_shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// ตรวจสอบว่าเป็น Admin หรือไม่
$is_super_admin = false;
$chk_sql = "SELECT r.role_name FROM roles r JOIN user_roles ur ON r.role_id = ur.roles_role_id WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt = $conn->prepare($chk_sql)) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $is_super_admin = true;
    $stmt->close();
}

// รับ ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: user_list.php");
    exit;
}
$view_id = intval($_GET['id']);

// ดึงข้อมูล User + Employee + Relations
$sql = "SELECT u.*, 
               e.firstname_th, e.lastname_th, e.firstname_en, e.lastname_en, 
               e.emp_code, e.emp_phone_no, e.emp_email, e.emp_image, e.emp_gender, e.emp_birthday,
               p.prefix_th,
               r.role_name,
               b.branch_name,
               s.shop_name, s.shop_id,
               d.dept_name
        FROM users u
        LEFT JOIN employees e ON u.user_id = e.users_user_id
        LEFT JOIN prefixs p ON e.prefixs_prefix_id = p.prefix_id
        LEFT JOIN user_roles ur ON u.user_id = ur.users_user_id
        LEFT JOIN roles r ON ur.roles_role_id = r.role_id
        LEFT JOIN branches b ON e.branches_branch_id = b.branch_id
        LEFT JOIN shop_info s ON b.shop_info_shop_id = s.shop_id
        LEFT JOIN departments d ON e.departments_dept_id = d.dept_id
        WHERE u.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $view_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("ไม่พบข้อมูลผู้ใช้งาน");
}

$data = $result->fetch_assoc();

// Security Check: ห้ามดูข้อมูลร้านอื่น (ยกเว้น Admin)
if (!$is_super_admin) {
    // ถ้า User ไม่มี Shop (เช่นเพิ่งสร้าง) หรือ Shop ID ไม่ตรง
    $user_shop_id = $data['shop_id'] ?? 0;
    if ($user_shop_id != 0 && $user_shop_id != $current_shop_id) {
        die("คุณไม่มีสิทธิ์เข้าถึงข้อมูลของร้านอื่น");
    }
}

// คำนวณอายุงาน หรือ อายุ
$age = ($data['emp_birthday']) ? date_diff(date_create($data['emp_birthday']), date_create('today'))->y : '-';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายละเอียดผู้ใช้งาน - <?= htmlspecialchars($data['username']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background-color: #f4f6f9; color: #333; }
        
        /* Profile Card */
        .profile-card {
            background: white; border-radius: 20px; border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden;
            text-align: center; padding-bottom: 2rem;
        }
        .profile-header-bg {
            height: 120px;
            background: linear-gradient(135deg, <?= $theme_color ?>, #14532d);
        }
        .profile-avatar {
            width: 140px; height: 140px;
            border-radius: 50%; border: 5px solid white;
            object-fit: cover; margin-top: -70px;
            background: #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .status-badge {
            position: absolute; top: 130px; right: calc(50% - 60px);
            width: 20px; height: 20px; border-radius: 50%;
            border: 3px solid white;
        }
        
        /* Details Card */
        .details-card {
            background: white; border-radius: 20px; border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.03);
            height: 100%;
        }
        .nav-tabs .nav-link {
            color: #6c757d; border: none; border-bottom: 3px solid transparent;
            padding: 1rem 1.5rem; font-weight: 600;
        }
        .nav-tabs .nav-link.active {
            color: <?= $theme_color ?>; border-bottom-color: <?= $theme_color ?>;
            background: transparent;
        }
        .info-label { font-size: 0.85rem; color: #8898aa; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem; font-weight: 600; }
        .info-value { font-size: 1rem; color: #333; font-weight: 500; margin-bottom: 1.5rem; }
        
        .action-btn { transition: all 0.3s; }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container py-5">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-1 small">
                                <li class="breadcrumb-item"><a href="user_list.php" class="text-decoration-none text-muted">จัดการผู้ใช้งาน</a></li>
                                <li class="breadcrumb-item active" aria-current="page">รายละเอียด</li>
                            </ol>
                        </nav>
                        <h3 class="fw-bold m-0 text-dark">ข้อมูลส่วนตัว</h3>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="user_list.php" class="btn btn-light rounded-pill px-4 fw-bold action-btn"><i class="bi bi-arrow-left me-1"></i> ย้อนกลับ</a>
                        <a href="user_edit.php?id=<?= $view_id ?>" class="btn btn-warning rounded-pill px-4 fw-bold action-btn text-white"><i class="bi bi-pencil-square me-1"></i> แก้ไขข้อมูล</a>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="profile-card">
                            <div class="profile-header-bg"></div>
                            
                            <?php if (!empty($data['emp_image']) && file_exists("../uploads/employees/" . $data['emp_image'])): ?>
                                <img src="../uploads/employees/<?= $data['emp_image'] ?>" class="profile-avatar" alt="User Image">
                            <?php else: ?>
                                <div class="profile-avatar d-flex align-items-center justify-content-center bg-light text-secondary" style="font-size: 3rem;">
                                    <?= strtoupper(substr($data['username'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>

                            <div class="status-badge <?= $data['user_status'] == 'Active' ? 'bg-success' : 'bg-danger' ?>" 
                                 title="<?= $data['user_status'] ?>"></div>

                            <div class="px-4 mt-3">
                                <h4 class="fw-bold mb-1">
                                    <?= htmlspecialchars($data['firstname_th'] ? $data['prefix_th'] . $data['firstname_th'] . ' ' . $data['lastname_th'] : $data['username']) ?>
                                </h4>
                                <p class="text-muted small mb-2"><?= htmlspecialchars($data['role_name']) ?></p>
                                
                                <div class="d-flex justify-content-center gap-2 mt-3">
                                    <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
                                        <i class="bi bi-building me-1"></i> <?= htmlspecialchars($data['shop_name'] ?? 'ส่วนกลาง') ?>
                                    </span>
                                    <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
                                        <i class="bi bi-geo-alt me-1"></i> <?= htmlspecialchars($data['branch_name'] ?? '-') ?>
                                    </span>
                                </div>

                                <hr class="my-4 opacity-10">

                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="small text-muted fw-bold">สถานะ</div>
                                        <div class="<?= $data['user_status'] == 'Active' ? 'text-success' : 'text-danger' ?> fw-bold">
                                            <?= $data['user_status'] ?>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="small text-muted fw-bold">อายุ (ปี)</div>
                                        <div class="fw-bold text-dark"><?= $age ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="details-card">
                            <div class="card-header bg-white border-bottom-0 pt-3 px-4">
                                <ul class="nav nav-tabs" id="userTab" role="tablist">
                                    <li class="nav-item">
                                        <button class="nav-link active" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button"><i class="bi bi-shield-lock me-2"></i>ข้อมูลบัญชี</button>
                                    </li>
                                    <li class="nav-item">
                                        <button class="nav-link" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button"><i class="bi bi-person-vcard me-2"></i>ข้อมูลส่วนตัว</button>
                                    </li>
                                    <li class="nav-item">
                                        <button class="nav-link" id="work-tab" data-bs-toggle="tab" data-bs-target="#work" type="button"><i class="bi bi-briefcase me-2"></i>การทำงาน</button>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body p-4 p-md-5">
                                <div class="tab-content" id="userTabContent">
                                    
                                    <div class="tab-pane fade show active" id="account">
                                        <h5 class="fw-bold text-primary mb-4">ข้อมูลการเข้าสู่ระบบ</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="info-label">ชื่อผู้ใช้งาน (Username)</div>
                                                <div class="info-value"><?= htmlspecialchars($data['username']) ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-label">บทบาท (Role)</div>
                                                <div class="info-value"><span class="badge bg-primary"><?= htmlspecialchars($data['role_name']) ?></span></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-label">วันที่สร้างบัญชี</div>
                                                <div class="info-value"><?= date('d/m/Y H:i', strtotime($data['create_at'])) ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-label">แก้ไขล่าสุด</div>
                                                <div class="info-value"><?= date('d/m/Y H:i', strtotime($data['update_at'])) ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="personal">
                                        <h5 class="fw-bold text-primary mb-4">ประวัติส่วนตัว</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="info-label">ชื่อ-นามสกุล (ไทย)</div>
                                                <div class="info-value">
                                                    <?= htmlspecialchars($data['prefix_th'] . $data['firstname_th'] . ' ' . $data['lastname_th']) ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-label">ชื่อ-นามสกุล (อังกฤษ)</div>
                                                <div class="info-value">
                                                    <?= htmlspecialchars(($data['firstname_en'] ?? '-') . ' ' . ($data['lastname_en'] ?? '')) ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-label">เบอร์โทรศัพท์</div>
                                                <div class="info-value"><?= htmlspecialchars($data['emp_phone_no'] ?? '-') ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-label">อีเมล</div>
                                                <div class="info-value"><?= htmlspecialchars($data['emp_email'] ?? '-') ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-label">เพศ</div>
                                                <div class="info-value"><?= ($data['emp_gender'] == 'Male' ? 'ชาย' : 'หญิง') ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-label">วันเกิด</div>
                                                <div class="info-value"><?= ($data['emp_birthday']) ? date('d/m/Y', strtotime($data['emp_birthday'])) : '-' ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="work">
                                        <h5 class="fw-bold text-primary mb-4">ข้อมูลการทำงาน</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="info-label">รหัสพนักงาน</div>
                                                <div class="info-value text-primary font-monospace"><?= htmlspecialchars($data['emp_code']) ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-label">แผนก (Department)</div>
                                                <div class="info-value"><?= htmlspecialchars($data['dept_name'] ?? '-') ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-label">สังกัดร้าน (Shop)</div>
                                                <div class="info-value"><?= htmlspecialchars($data['shop_name'] ?? 'ส่วนกลาง') ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-label">สาขา (Branch)</div>
                                                <div class="info-value"><?= htmlspecialchars($data['branch_name'] ?? '-') ?></div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>