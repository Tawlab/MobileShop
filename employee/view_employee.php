<?php
session_start();
require '../config/config.php';
// checkPageAccess($conn, 'view_employee'); // เปิดใช้งานเมื่อระบบสิทธิ์พร้อม

// --- รับ ID พนักงานจาก URL ---
$emp_id = (int)($_GET['id'] ?? 0);

if ($emp_id === 0) {
    die("ไม่พบ ID พนักงานที่ระบุ");
}

// --- ดึงข้อมูลพนักงาน + ชื่อร้านค้า (Shop) ---
$sql = "
    SELECT
        e.*, 
        p.prefix_th, 
        d.dept_name, 
        b.branch_name, 
        s.shop_name,  /* เพิ่มการดึงชื่อร้านค้า */
        r.religion_name_th,
        a.home_no, a.moo, a.soi, a.road, a.village,
        sd.subdistrict_name_th, sd.zip_code,
        dist.district_name_th,
        prov.province_name_th,
        u.username, u.user_status,
        ro.role_name
    FROM employees e
    LEFT JOIN prefixs p ON e.prefixs_prefix_id = p.prefix_id
    LEFT JOIN departments d ON e.departments_dept_id = d.dept_id
    LEFT JOIN branches b ON e.branches_branch_id = b.branch_id
    LEFT JOIN shop_info s ON b.shop_info_shop_id = s.shop_id /* เชื่อมโยงไปหาร้านค้าผ่านสาขา */
    LEFT JOIN religions r ON e.religions_religion_id = r.religion_id
    LEFT JOIN addresses a ON e.Addresses_address_id = a.address_id
    LEFT JOIN users u ON e.users_user_id = u.user_id
    LEFT JOIN user_roles ur ON u.user_id = ur.users_user_id
    LEFT JOIN roles ro ON ur.roles_role_id = ro.role_id
    LEFT JOIN subdistricts sd ON a.subdistricts_subdistrict_id = sd.subdistrict_id
    LEFT JOIN districts dist ON sd.districts_district_id = dist.district_id
    LEFT JOIN provinces prov ON dist.provinces_province_id = prov.province_id
    WHERE e.emp_id = ?
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL Prepare Error: " . $conn->error);
}

$stmt->bind_param("i", $emp_id);
$stmt->execute();
$result = $stmt->get_result();
$emp = $result->fetch_assoc();
$stmt->close();

if (!$emp) {
    die("ไม่พบข้อมูลพนักงาน ID: $emp_id");
}

// --- จัดรูปแบบที่อยู่  ---
$address_parts = [];
if (!empty($emp['home_no'])) $address_parts[] = "เลขที่ " . htmlspecialchars($emp['home_no']);
if (!empty($emp['moo'])) $address_parts[] = "หมู่ " . htmlspecialchars($emp['moo']);
if (!empty($emp['village'])) $address_parts[] = "หมู่บ้าน/อาคาร " . htmlspecialchars($emp['village']);
if (!empty($emp['soi'])) $address_parts[] = "ซอย " . htmlspecialchars($emp['soi']);
if (!empty($emp['road'])) $address_parts[] = "ถนน " . htmlspecialchars($emp['road']);
if (!empty($emp['subdistrict_name_th'])) $address_parts[] = "ต." . htmlspecialchars($emp['subdistrict_name_th']);
if (!empty($emp['district_name_th'])) $address_parts[] = "อ." . htmlspecialchars($emp['district_name_th']);
if (!empty($emp['province_name_th'])) $address_parts[] = "จ." . htmlspecialchars($emp['province_name_th']);
if (!empty($emp['zip_code'])) $address_parts[] = htmlspecialchars($emp['zip_code']);
$full_address = implode(" ", $address_parts);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลพนักงาน - <?= htmlspecialchars($emp['firstname_th']) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <?php require '../config/load_theme.php'; ?>

    <style>
        body {
            background-color: #f3f4f6;
            font-family: 'Sarabun', sans-serif;
        }

        .view-container {
            max-width: 1000px;
            margin: 40px auto;
        }

        .main-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            background: white;
        }

        /* ส่วนหัวการ์ด Gradient */
        .card-header-bg {
            background: linear-gradient(135deg, #0f5132 0%, #198754 100%);
            height: 120px;
            position: relative;
        }

        .profile-section {
            padding: 0 2rem 2rem;
            margin-top: -60px; /* ดึงรูปขึ้นไปทับ Header */
            text-align: center;
        }

        .profile-img-container {
            position: relative;
            display: inline-block;
        }

        .profile-image-lg {
            width: 140px;
            height: 140px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
            background: white;
        }

        .status-pill {
            position: absolute;
            bottom: 10px;
            right: 5px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 3px solid white;
        }
        .status-online { background-color: #10b981; }
        .status-offline { background-color: #ef4444; }

        .info-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            height: 100%;
            border: 1px solid #e9ecef;
            transition: transform 0.2s;
        }
        .info-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .section-header {
            color: #15803d;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            font-size: 1.1rem;
        }
        .section-header i {
            background: #d1fae5;
            color: #15803d;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            margin-right: 10px;
        }

        .data-label {
            color: #6c757d;
            font-size: 0.85rem;
            margin-bottom: 2px;
        }
        .data-value {
            color: #212529;
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 1rem;
            word-break: break-word;
        }

        .badge-custom {
            padding: 0.5em 1em;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.85rem;
        }
        .badge-shop { background-color: #e0e7ff; color: #4338ca; }
        .badge-branch { background-color: #fae8ff; color: #86198f; }
        .badge-dept { background-color: #ecfccb; color: #3f6212; }

    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="view-container">
                    
                    <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                        <a href="employee.php" class="btn btn-outline-secondary rounded-pill px-4">
                            <i class="fas fa-arrow-left me-2"></i>ย้อนกลับ
                        </a>
                        <a href="print_employee.php?id=<?= $emp['emp_id'] ?>" class="btn btn-primary rounded-pill px-4 shadow-sm" target="_blank">
                            <i class="fas fa-print me-2"></i>พิมพ์ประวัติ
                        </a>
                    </div>

                    <div class="main-card">
                        <div class="card-header-bg"></div>

                        <div class="profile-section">
                            <div class="profile-img-container">
                                <?php if (!empty($emp['emp_image'])): ?>
                                    <img src="../uploads/employees/<?= htmlspecialchars($emp['emp_image']) ?>" alt="Profile" class="profile-image-lg">
                                <?php else: ?>
                                    <img src="../assets/img/default-avatar.png" alt="Profile" class="profile-image-lg">
                                <?php endif; ?>
                                
                                <div class="status-pill <?= ($emp['emp_status'] == 'Active') ? 'status-online' : 'status-offline' ?>" 
                                     title="<?= htmlspecialchars($emp['emp_status']) ?>"></div>
                            </div>

                            <h2 class="mt-3 fw-bold text-dark mb-1">
                                <?= htmlspecialchars($emp['prefix_th'] . $emp['firstname_th'] . ' ' . $emp['lastname_th']) ?>
                            </h2>
                            <p class="text-muted mb-3"><?= htmlspecialchars($emp['firstname_en'] . ' ' . $emp['lastname_en']) ?></p>

                            <div class="d-flex justify-content-center flex-wrap gap-2">
                                <span class="badge-custom badge-shop">
                                    <i class="fas fa-store me-1"></i> ร้าน: <?= htmlspecialchars($emp['shop_name'] ?? 'ส่วนกลาง/ไม่ระบุ') ?>
                                </span>
                                <span class="badge-custom badge-branch">
                                    <i class="fas fa-code-branch me-1"></i> สาขา: <?= htmlspecialchars($emp['branch_name'] ?? '-') ?>
                                </span>
                                <span class="badge-custom badge-dept">
                                    <i class="fas fa-sitemap me-1"></i> แผนก: <?= htmlspecialchars($emp['dept_name'] ?? '-') ?>
                                </span>
                            </div>
                        </div>

                        <div class="card-body px-4 pb-5">
                            <div class="row g-4">
                                
                                <div class="col-lg-6">
                                    <div class="info-card">
                                        <div class="section-header">
                                            <i class="fas fa-user-check"></i> ข้อมูลส่วนตัว
                                        </div>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <div class="data-label">รหัสพนักงาน</div>
                                                <div class="data-value text-primary"><?= htmlspecialchars($emp['emp_code']) ?></div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="data-label">เลขบัตรประชาชน</div>
                                                <div class="data-value"><?= htmlspecialchars($emp['emp_national_id']) ?></div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="data-label">เพศ</div>
                                                <div class="data-value"><?= $emp['emp_gender'] == 'Male' ? 'ชาย' : ($emp['emp_gender'] == 'Female' ? 'หญิง' : 'LGBTQ+') ?></div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="data-label">วันเกิด</div>
                                                <div class="data-value">
                                                    <?= $emp['emp_birthday'] ? date('d/m/Y', strtotime($emp['emp_birthday'])) : '-' ?>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="data-label">ศาสนา</div>
                                                <div class="data-value"><?= htmlspecialchars($emp['religion_name_th'] ?? '-') ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="info-card">
                                        <div class="section-header">
                                            <i class="fas fa-address-book"></i> การติดต่อ & ที่อยู่
                                        </div>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <div class="data-label">เบอร์โทรศัพท์</div>
                                                <div class="data-value text-success"><i class="fas fa-phone-alt me-1 small"></i> <?= htmlspecialchars($emp['emp_phone_no']) ?></div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="data-label">Line ID</div>
                                                <div class="data-value text-success"><i class="fab fa-line me-1 small"></i> <?= htmlspecialchars($emp['emp_line_id'] ?: '-') ?></div>
                                            </div>
                                            <div class="col-12">
                                                <div class="data-label">อีเมล</div>
                                                <div class="data-value"><?= htmlspecialchars($emp['emp_email'] ?: '-') ?></div>
                                            </div>
                                            <div class="col-12">
                                                <div class="data-label">ที่อยู่ตามทะเบียนบ้าน</div>
                                                <div class="data-value text-muted fw-normal" style="line-height: 1.6;">
                                                    <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                                    <?= !empty($full_address) ? $full_address : 'ไม่ได้ระบุข้อมูล' ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="info-card border-0 bg-light">
                                        <div class="section-header mb-3">
                                            <i class="fas fa-user-shield"></i> บัญชีผู้ใช้งานระบบ (System Account)
                                        </div>
                                        <div class="d-flex flex-wrap gap-5 align-items-center">
                                            <div>
                                                <div class="data-label">Username</div>
                                                <div class="data-value mb-0 text-primary fw-bold fs-5"><?= htmlspecialchars($emp['username'] ?? '-') ?></div>
                                            </div>
                                            <div>
                                                <div class="data-label">ระดับสิทธิ์ (Role)</div>
                                                <div class="data-value mb-0">
                                                    <span class="badge bg-secondary rounded-pill px-3">
                                                        <?= htmlspecialchars($emp['role_name'] ?? 'User') ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="data-label">สถานะพนักงาน</div>
                                                <div class="data-value mb-0">
                                                    <?php if ($emp['emp_status'] == 'Active'): ?>
                                                        <span class="badge bg-success rounded-pill px-3"><i class="fas fa-check-circle me-1"></i> ทำงานปกติ</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger rounded-pill px-3"><i class="fas fa-times-circle me-1"></i> <?= htmlspecialchars($emp['emp_status']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div> </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>