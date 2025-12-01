<?php
// --- view_employee.php ---
session_start();
require '../config/config.php';
checkPageAccess($conn, 'view_employee');

// --- 1. รับ ID พนักงานจาก URL ---
$emp_id = (int)($_GET['id'] ?? 0);

if ($emp_id === 0) {
    die("ไม่พบ ID พนักงานที่ระบุ");
}

// --- 2. ดึงข้อมูลพนักงาน (Query ฉบับสมบูรณ์) ---
// (Query นี้จะ JOIN ตารางที่จำเป็นทั้งหมดเพื่อแสดงรายละเอียด)
$sql = "
    SELECT
        e.*, 
        p.prefix_th, 
        d.dept_name, 
        b.branch_name, 
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

// --- 3. (Helper) จัดรูปแบบที่อยู่ (เหมือนหน้า add_employee) ---
$address_parts = [];
if (!empty($emp['home_no'])) $address_parts['เลขที่'] = htmlspecialchars($emp['home_no']);
if (!empty($emp['moo'])) $address_parts['หมู่'] = htmlspecialchars($emp['moo']);
if (!empty($emp['village'])) $address_parts['หมู่บ้าน/อาคาร'] = htmlspecialchars($emp['village']);
if (!empty($emp['soi'])) $address_parts['ซอย'] = htmlspecialchars($emp['soi']);
if (!empty($emp['road'])) $address_parts['ถนน'] = htmlspecialchars($emp['road']);
if (!empty($emp['subdistrict_name_th'])) $address_parts['ตำบล/แขวง'] = htmlspecialchars($emp['subdistrict_name_th']);
if (!empty($emp['district_name_th'])) $address_parts['อำเภอ/เขต'] = htmlspecialchars($emp['district_name_th']);
if (!empty($emp['province_name_th'])) $address_parts['จังหวัด'] = htmlspecialchars($emp['province_name_th']);
if (!empty($emp['zip_code'])) $address_parts['รหัสไปรษณีย์'] = htmlspecialchars($emp['zip_code']);

// $conn->close();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดพนักงาน - <?= htmlspecialchars($emp['firstname_th']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>

    <style>
        body {
            background-color: #f0fdf4;
        }

        .view-container {
            max-width: 900px;
            /* ขยายการ์ดให้กว้างขึ้น */
            margin: 40px auto;
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
        }

        .card-header .btn-light:hover {
            background-color: rgba(255, 255, 255, 0.3);
            border-color: white;
        }

        .card-body {
            padding: 2rem;
        }

        .profile-image-lg {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 12px;
            border: 4px solid #fff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-weight: 600;
            color: #15803d;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #a7f3d0;
            font-size: 1.1rem;
        }

        /* (สไตล์การแสดงข้อมูลแบบ Label/Value) */
        .info-label {
            display: block;
            font-size: 0.8rem;
            color: #6c757d;
            font-weight: 500;
            margin-bottom: 0.1rem;
        }

        .info-value {
            display: block;
            margin-bottom: 0.75rem;
            color: #212529;
            font-weight: 500;
            font-size: 1rem;
        }

        .status-badge {
            padding: 0.3em 0.8em;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-active {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-resigned {
            background-color: #f3f4f6;
            color: #4b5563;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="view-container">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><i class="fas fa-user-alt me-2"></i>รายละเอียดพนักงาน</h4>

                            <a href="print_employee.php?id=<?= $emp['emp_id'] ?>" class="btn btn-light" target="_blank">
                                <i class="fas fa-print me-2"></i>พิมพ์ข้อมูล
                            </a>
                        </div>

                        <div class="card-body">
                            <div class="text-center mb-4">
                                <?php if (!empty($emp['emp_image'])): ?>
                                    <img src="../uploads/employees/<?= htmlspecialchars($emp['emp_image']) ?>" alt="รูปพนักงาน" class="profile-image-lg">
                                <?php else: ?>
                                    <i class="fas fa-user-circle fa-8x text-secondary" style="opacity: 0.5;"></i>
                                <?php endif; ?>

                                <h3 class="mt-3 mb-1"><?= htmlspecialchars($emp['prefix_th'] . $emp['firstname_th'] . ' ' . $emp['lastname_th']) ?></h3>
                                <p class="text-muted mb-0">
                                    แผนก: <?= htmlspecialchars($emp['dept_name'] ?? 'N/A') ?> |
                                    สาขา: <?= htmlspecialchars($emp['branch_name'] ?? 'N/A') ?>
                                </p>
                            </div>
                            <hr>

                            <h5 class="section-title"><i class="fas fa-id-card-alt me-2"></i>ข้อมูลส่วนตัว</h5>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <strong class="info-label">รหัสพนักงาน</strong>
                                    <span class="info-value"><?= htmlspecialchars($emp['emp_code']) ?></span>
                                </div>
                                <div class="col-md-8">
                                    <strong class="info-label">เลขบัตรประชาชน</strong>
                                    <span class="info-value"><?= htmlspecialchars($emp['emp_national_id']) ?></span>
                                </div>
                                <div class="col-md-6">
                                    <strong class="info-label">ชื่อ-สกุล (Eng)</strong>
                                    <span class="info-value"><?= htmlspecialchars(($emp['firstname_en'] ?? '') . ' ' . ($emp['lastname_en'] ?? '')) ?: '-' ?></span>
                                </div>
                                <div class="col-md-2">
                                    <strong class="info-label">เพศ</strong>
                                    <span class="info-value"><?= $emp['emp_gender'] == 'Male' ? 'ชาย' : 'หญิง' ?></span>
                                </div>
                                <div class="col-md-4">
                                    <strong class="info-label">วันเกิด</strong>
                                    <span class="info-value"><?= $emp['emp_birthday'] ? date('d/m/Y', strtotime($emp['emp_birthday'])) : '-' ?></span>
                                </div>
                                <div class="col-md-4">
                                    <strong class="info-label">ศาสนา</strong>
                                    <span class="info-value"><?= htmlspecialchars($emp['religion_name_th'] ?? '-') ?></span>
                                </div>
                            </div>

                            <h5 class="section-title"><i class="fas fa-address-book me-2"></i>ข้อมูลติดต่อ</h5>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <strong class="info-label">เบอร์โทรศัพท์</strong>
                                    <span class="info-value"><?= htmlspecialchars($emp['emp_phone_no']) ?></span>
                                </div>
                                <div class="col-md-4">
                                    <strong class="info-label">อีเมล</strong>
                                    <span class="info-value"><?= htmlspecialchars($emp['emp_email'] ?: '-') ?></span>
                                </div>
                                <div class="col-md-4">
                                    <strong class="info-label">Line ID</strong>
                                    <span class="info-value"><?= htmlspecialchars($emp['emp_line_id'] ?: '-') ?></span>
                                </div>
                            </div>

                            <h5 class="section-title"><i class="fas fa-map-marker-alt me-2"></i>ที่อยู่ปัจจุบัน</h5>
                            <div class="row g-2">
                                <?php if (!empty($address_parts)): ?>
                                    <?php foreach ($address_parts as $label => $value): ?>
                                        <div class="col-md-4">
                                            <strong class="info-label"><?= $label ?></strong>
                                            <span class="info-value"><?= $value ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="col-12"><span class="info-value text-muted">ไม่ได้ระบุที่อยู่</span></div>
                                <?php endif; ?>
                            </div>

                            <h5 class="section-title"><i class="fas fa-briefcase me-2"></i>ข้อมูลการทำงานและบัญชีผู้ใช้</h5>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <strong class="info-label">สถานะพนักงาน</strong>
                                    <span class="info-value">
                                        <?php if ($emp['emp_status'] == 'Active'): ?>
                                            <span class="badge rounded-pill status-badge status-active">ทำงานอยู่</span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill status-badge status-resigned">ลาออก</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="col-md-4">
                                    <strong class="info-label">Username</strong>
                                    <span class="info-value"><?= htmlspecialchars($emp['username'] ?? '-') ?></span>
                                </div>
                                <div class="col-md-4">
                                    <strong class="info-label">บทบาท (Role)</strong>
                                    <span class="info-value"><?= htmlspecialchars($emp['role_name'] ?? '-') ?></span>
                                </div>
                                <div class="col-md-4">
                                    <strong class="info-label">สถานะบัญชี</strong>
                                    <span class="info-value">
                                        <?php if ($emp['user_status'] == 'Active'): ?>
                                            <span class="badge rounded-pill status-badge status-active">เปิดใช้งาน</span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill status-badge status-resigned">ปิดใช้งาน</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                        </div>

                        <div class="card-footer text-center bg-light p-3">
                            <a href="employee.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>กลับไปหน้ารายการ</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php
    // ✅ ปิดตรงนี้ (ล่างสุด) หรือปล่อยให้ PHP ปิดเองอัตโนมัติก็ได้
    if (isset($conn)) $conn->close();
    ?>
</body>

</html>