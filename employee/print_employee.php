<?php
session_start();
require '../config/config.php'; 

// ตรวจสอบสิทธิ์
// checkPageAccess($conn, 'print_employee');

// --- 1. รับ ID พนักงาน ---
$emp_id = (int)($_GET['id'] ?? 0);
if ($emp_id === 0) die("ไม่พบรหัสพนักงาน");

// --- 2. ดึงข้อมูลพนักงาน ---
$sql = "
    SELECT
        e.*, 
        p.prefix_th, 
        d.dept_name, 
        b.branch_name, 
        s.shop_name,
        r.religion_name_th,
        a.home_no, a.moo, a.soi, a.road, a.village,
        sd.subdistrict_name_th, sd.zip_code,
        dist.district_name_th,
        prov.province_name_th,
        u.username, ro.role_name
    FROM employees e
    LEFT JOIN prefixs p ON e.prefixs_prefix_id = p.prefix_id
    LEFT JOIN departments d ON e.departments_dept_id = d.dept_id
    LEFT JOIN branches b ON e.branches_branch_id = b.branch_id
    LEFT JOIN shop_info s ON b.shop_info_shop_id = s.shop_id
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
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$emp) die("ไม่พบข้อมูลพนักงาน");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ประวัติพนักงาน - <?= htmlspecialchars($emp['firstname_th']) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">

    <style>
        /* ตั้งค่าพื้นฐาน */
        body {
            background-color: #e9ecef; /* สีพื้นหลังตอนดูหน้าจอ */
            font-family: 'Sarabun', sans-serif;
            color: #000;
            -webkit-print-color-adjust: exact !important; /* บังคับให้พิมพ์สีพื้นหลัง/กราฟิก */
            print-color-adjust: exact !important;
        }

        /* จำลองกระดาษ A4 */
        .a4-page {
            width: 210mm;
            min-height: 297mm;
            background: #fff;
            margin: 40px auto;
            padding: 20mm 25mm; /* ขอบกระดาษ */
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            position: relative;
        }

        /* ส่วนหัวเอกสาร */
        .doc-header {
            border-bottom: 3px solid #198754; /* เส้นสีเขียว */
            padding-bottom: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .emp-photo {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 2px solid #198754; /* กรอบรูปสีเขียว */
            border-radius: 8px;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.1);
        }

        .header-info h1 {
            font-size: 26px;
            font-weight: bold;
            color: #198754; /* หัวข้อสีเขียว */
            margin: 0;
            line-height: 1.2;
        }
        .header-info h2 {
            font-size: 18px;
            font-weight: normal;
            margin: 5px 0 10px 0;
            color: #555;
            text-transform: uppercase;
        }
        
        .header-badge {
            display: inline-block;
            background-color: #e9f7ef;
            color: #198754;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid #c3e6cb;
        }

        /* หัวข้อส่วน (Section) แบบมีสีสัน */
        .section-title {
            font-size: 16px;
            font-weight: bold;
            background-color: #198754; /* พื้นหลังสีเขียว */
            color: #fff; /* ตัวหนังสือสีขาว */
            padding: 8px 15px;
            margin-top: 25px;
            margin-bottom: 15px;
            border-radius: 4px;
            display: flex;
            align-items: center;
        }
        .section-title i {
            margin-right: 10px;
        }

        /* ข้อมูล (Data) */
        .data-box {
            margin-bottom: 12px;
        }
        .data-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 2px;
        }
        .data-value {
            font-size: 15px;
            font-weight: 600;
            color: #000;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 4px;
            min-height: 24px;
        }
        
        /* การจัดระเบียบที่อยู่ (Address Box) */
        .address-container {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 15px;
        }

        /* ปุ่มลอย */
        .fab-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        .btn-fab {
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: bold;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        /* --- Print Settings --- */
        @media print {
            @page {
                size: A4;
                margin: 0;
            }
            body {
                background: none;
                margin: 0;
            }
            .a4-page {
                width: 100%;
                height: auto;
                margin: 0;
                box-shadow: none;
                border: none;
                padding: 1.5cm; /* ระยะขอบตอนพิมพ์ */
            }
            .no-print {
                display: none !important;
            }
            
            /* บังคับสีพื้นหลังให้ติดตอนพิมพ์ */
            .section-title {
                -webkit-print-color-adjust: exact;
                background-color: #198754 !important;
                color: #fff !important;
            }
            .header-badge {
                background-color: #e9f7ef !important;
                border: 1px solid #c3e6cb !important;
            }
            .address-container {
                background-color: #f8f9fa !important;
                border: 1px solid #dee2e6 !important;
            }
        }
    </style>
</head>
<body>

    <div class="fab-container no-print">
        <button onclick="window.print()" class="btn btn-primary btn-fab">
            <i class="fas fa-print me-2"></i> พิมพ์เอกสาร
        </button>
        <button onclick="window.close()" class="btn btn-secondary btn-fab">
            <i class="fas fa-times me-2"></i> ปิดหน้าต่าง
        </button>
    </div>

    <div class="a4-page">
        
        <div class="doc-header">
            <div class="d-flex align-items-center gap-4">
                <?php if (!empty($emp['emp_image'])): ?>
                    <img src="../uploads/employees/<?= htmlspecialchars($emp['emp_image']) ?>" class="emp-photo">
                <?php else: ?>
                    <div class="emp-photo d-flex align-items-center justify-content-center bg-light text-secondary">
                        <i class="fas fa-user fa-3x"></i>
                    </div>
                <?php endif; ?>

                <div class="header-info">
                    <h1><?= htmlspecialchars($emp['firstname_th'] . ' ' . $emp['lastname_th']) ?></h1>
                    <h2><?= htmlspecialchars($emp['firstname_en'] . ' ' . $emp['lastname_en']) ?></h2>
                    <div class="mt-2">
                        <span class="header-badge">รหัสพนักงาน: <?= htmlspecialchars($emp['emp_code']) ?></span>
                        <span class="header-badge ms-2">ตำแหน่ง: <?= htmlspecialchars($emp['role_name']) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="text-end">
                <div style="font-size: 20px; font-weight: bold; color: #333;">แบบฟอร์มประวัติพนักงาน</div>
                <div style="font-size: 12px; color: #777;">พิมพ์เมื่อ: <?= date('d/m/Y') ?></div>
            </div>
        </div>

        <div class="section-title"><i class="fas fa-user-circle"></i> ข้อมูลส่วนตัว (Personal Information)</div>
        <div class="row">
            <div class="col-6 data-box">
                <div class="data-label">เลขบัตรประจำตัวประชาชน</div>
                <div class="data-value"><?= htmlspecialchars($emp['emp_national_id']) ?></div>
            </div>
            <div class="col-6 data-box">
                <div class="data-label">วัน/เดือน/ปีเกิด</div>
                <div class="data-value"><?= $emp['emp_birthday'] ? date('d/m/Y', strtotime($emp['emp_birthday'])) : '-' ?></div>
            </div>
            <div class="col-6 data-box">
                <div class="data-label">เพศ</div>
                <div class="data-value">
                    <?php 
                        if ($emp['emp_gender'] == 'Male') echo 'ชาย';
                        elseif ($emp['emp_gender'] == 'Female') echo 'หญิง';
                        else echo 'อื่นๆ';
                    ?>
                </div>
            </div>
            <div class="col-6 data-box">
                <div class="data-label">ศาสนา</div>
                <div class="data-value"><?= htmlspecialchars($emp['religion_name_th']) ?></div>
            </div>
        </div>

        <div class="section-title"><i class="fas fa-address-book"></i> การติดต่อ (Contact Information)</div>
        <div class="row">
            <div class="col-4 data-box">
                <div class="data-label">เบอร์โทรศัพท์</div>
                <div class="data-value"><?= htmlspecialchars($emp['emp_phone_no']) ?></div>
            </div>
            <div class="col-4 data-box">
                <div class="data-label">อีเมล</div>
                <div class="data-value"><?= htmlspecialchars($emp['emp_email'] ?: '-') ?></div>
            </div>
            <div class="col-4 data-box">
                <div class="data-label">Line ID</div>
                <div class="data-value"><?= htmlspecialchars($emp['emp_line_id'] ?: '-') ?></div>
            </div>
        </div>

        <div class="section-title"><i class="fas fa-map-marker-alt"></i> ที่อยู่ตามทะเบียนบ้าน (Address)</div>
        <div class="address-container">
            <div class="row g-3">
                <div class="col-3">
                    <div class="data-label">บ้านเลขที่</div>
                    <div class="data-value border-0 p-0"><?= htmlspecialchars($emp['home_no'] ?: '-') ?></div>
                </div>
                <div class="col-3">
                    <div class="data-label">หมู่ที่</div>
                    <div class="data-value border-0 p-0"><?= htmlspecialchars($emp['moo'] ?: '-') ?></div>
                </div>
                <div class="col-6">
                    <div class="data-label">หมู่บ้าน/อาคาร</div>
                    <div class="data-value border-0 p-0"><?= htmlspecialchars($emp['village'] ?: '-') ?></div>
                </div>
                
                <div class="col-4">
                    <div class="data-label">ซอย</div>
                    <div class="data-value border-0 p-0"><?= htmlspecialchars($emp['soi'] ?: '-') ?></div>
                </div>
                <div class="col-8">
                    <div class="data-label">ถนน</div>
                    <div class="data-value border-0 p-0"><?= htmlspecialchars($emp['road'] ?: '-') ?></div>
                </div>

                <div class="col-4">
                    <div class="data-label">ตำบล/แขวง</div>
                    <div class="data-value border-0 p-0"><?= htmlspecialchars($emp['subdistrict_name_th'] ?: '-') ?></div>
                </div>
                <div class="col-4">
                    <div class="data-label">อำเภอ/เขต</div>
                    <div class="data-value border-0 p-0"><?= htmlspecialchars($emp['district_name_th'] ?: '-') ?></div>
                </div>
                <div class="col-4">
                    <div class="data-label">จังหวัด (รหัสไปรษณีย์)</div>
                    <div class="data-value border-0 p-0">
                        <?= htmlspecialchars($emp['province_name_th'] ?: '-') ?> 
                        <?= $emp['zip_code'] ? '('.$emp['zip_code'].')' : '' ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-title"><i class="fas fa-briefcase"></i> ข้อมูลการทำงาน (Work Information)</div>
        <div class="row">
            <div class="col-6 data-box">
                <div class="data-label">สังกัดร้านค้า (Shop)</div>
                <div class="data-value"><?= htmlspecialchars($emp['shop_name']) ?></div>
            </div>
            <div class="col-6 data-box">
                <div class="data-label">สาขา (Branch)</div>
                <div class="data-value"><?= htmlspecialchars($emp['branch_name']) ?></div>
            </div>
            <div class="col-6 data-box">
                <div class="data-label">แผนก (Department)</div>
                <div class="data-value"><?= htmlspecialchars($emp['dept_name']) ?></div>
            </div>
            <div class="col-6 data-box">
                <div class="data-label">ชื่อผู้ใช้งานเข้าระบบ (Username)</div>
                <div class="data-value"><?= htmlspecialchars($emp['username']) ?></div>
            </div>
        </div>

        <!-- <div class="text-center text-muted mt-5 pt-4" style="border-top: 1px solid #ddd; font-size: 12px;">
            เอกสารฉบับนี้ออกโดยระบบอัตโนมัติ ไม่ต้องประทับตราสำคัญ
        </div> -->

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // สั่งพิมพ์อัตโนมัติเมื่อโหลดหน้าเสร็จ
        window.onload = function() {
            // window.print(); // สามารถเปิดบรรทัดนี้หากต้องการให้เด้งพิมพ์ทันที
        }
    </script>
</body>
</html>