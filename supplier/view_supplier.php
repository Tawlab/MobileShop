<?php
session_start();
require '../config/config.php';

checkPageAccess($conn, 'view_supplier');

$supplier_id = $_GET['id'] ?? '';
$data = null;

if (empty($supplier_id)) {
    header("Location: supplier.php");
    exit();
}

// ดึงข้อมูล
$sql = "SELECT 
            s.*, 
            p.prefix_th,
            a.home_no, a.moo, a.soi, a.road, a.village,
            sd.subdistrict_name_th,
            d.district_name_th,
            pv.province_name_th,
            sd.zip_code
        FROM suppliers s
        LEFT JOIN prefixs p ON s.prefixs_prefix_id = p.prefix_id
        LEFT JOIN addresses a ON s.Addresses_address_id = a.address_id
        LEFT JOIN subdistricts sd ON a.subdistricts_subdistrict_id = sd.subdistrict_id
        LEFT JOIN districts d ON sd.districts_district_id = d.district_id
        LEFT JOIN provinces pv ON d.provinces_province_id = pv.province_id
        WHERE s.supplier_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if (!$data) {
    echo "<script>alert('ไม่พบข้อมูลผู้จัดจำหน่าย'); window.location='supplier.php';</script>";
    exit();
}

// จัดการชื่อผู้ติดต่อ
$contact_name = htmlspecialchars($data['prefix_th'] ?? '');
$contact_name .= htmlspecialchars($data['contact_firstname'] ?? '');
$contact_name .= ' ' . htmlspecialchars($data['contact_lastname'] ?? '');
if (trim($contact_name) === '') {
    $contact_name = '<span class="text-muted">-</span>';
}

// ฟังก์ชันแสดงผล (ถ้าว่างให้โชว์ -)
function showVal($val) {
    return (isset($val) && trim($val) !== '') ? htmlspecialchars($val) : '<span class="text-muted">-</span>';
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดผู้จัดจำหน่าย - <?= htmlspecialchars($data['co_name']) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: <?= $background_color ?>;
            color: #495057;
        }
        
        .main-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .card-header-profile {
            background: linear-gradient(135deg, <?= $theme_color ?> 0%, #1e293b 100%);
            color: white;
            padding: 2.5rem 2rem;
            position: relative;
        }

        .supplier-icon {
            font-size: 3rem;
            opacity: 0.8;
        }

        .info-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: #888;
            margin-bottom: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 1.05rem;
            font-weight: 500;
            color: #2c3e50;
            padding-bottom: 8px;
            border-bottom: 1px solid #f0f0f0;
            min-height: 35px;
        }

        .info-group {
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: <?= $theme_color ?>;
            margin-bottom: 1.2rem;
            padding-left: 10px;
            border-left: 4px solid <?= $theme_color ?>;
        }

        .btn-action {
            border-radius: 50px;
            padding: 8px 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container" style="max-width: 1000px;">
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <a href="supplier.php" class="btn btn-outline-secondary rounded-pill px-4">
                            <i class="bi bi-arrow-left me-2"></i>ย้อนกลับ
                        </a>
                    </div>

                    <div class="card main-card mb-5">
                        
                        <div class="card-header-profile d-flex align-items-center gap-4">
                            <div class="bg-white bg-opacity-10 p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="bi bi-building supplier-icon text-white"></i>
                            </div>
                            <div>
                                <h2 class="mb-1 fw-bold text-white"><?= htmlspecialchars($data['co_name']) ?></h2>
                                <div class="d-flex gap-3 text-white-50 ">
                                    <span><i class="bi bi-hash me-1"></i>ID: <?= str_pad($data['supplier_id'], 6, '0', STR_PAD_LEFT) ?></span>
                                    <span><i class="bi bi-calendar3 me-1"></i>วันที่เพิ่ม: <?= date('d/m/Y', strtotime($data['create_at'])) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-4 p-lg-5">
                            
                            <div class="row g-5 mb-4">
                                <div class="col-lg-6">
                                    <div class="section-title">
                                        <i class="bi bi-info-circle me-2"></i>ข้อมูลทั่วไป
                                    </div>
                                    <div class="info-group">
                                        <div class="info-label">ชื่อบริษัท / ร้านค้า</div>
                                        <div class="info-value"><?= showVal($data['co_name']) ?></div>
                                    </div>
                                    <div class="info-group">
                                        <div class="info-label">เลขประจำตัวผู้เสียภาษี</div>
                                        <div class="info-value"><?= showVal($data['tax_id']) ?></div>
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="section-title">
                                        <i class="bi bi-person-rolodex me-2"></i>ข้อมูลผู้ติดต่อ
                                    </div>
                                    <div class="info-group">
                                        <div class="info-label">ชื่อผู้ติดต่อ</div>
                                        <div class="info-value"><?= $contact_name ?></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="info-group">
                                                <div class="info-label">เบอร์โทรศัพท์</div>
                                                <div class="info-value">
                                                    <?php if($data['supplier_phone_no']): ?>
                                                        <a href="tel:<?= $data['supplier_phone_no'] ?>" class="text-decoration-none text-dark">
                                                            <i class="bi bi-telephone-fill me-2 text-success"></i><?= $data['supplier_phone_no'] ?>
                                                        </a>
                                                    <?php else: echo showVal(null); endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="info-group">
                                                <div class="info-label">อีเมล</div>
                                                <div class="info-value">
                                                    <?php if($data['supplier_email']): ?>
                                                        <a href="mailto:<?= $data['supplier_email'] ?>" class="text-decoration-none text-dark">
                                                            <i class="bi bi-envelope-fill me-2 text-danger"></i><?= $data['supplier_email'] ?>
                                                        </a>
                                                    <?php else: echo showVal(null); endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4 text-muted opacity-25">

                            <div class="row">
                                <div class="col-12">
                                    <div class="section-title">
                                        <i class="bi bi-geo-alt-fill me-2"></i>ที่อยู่
                                    </div>
                                </div>
                                
                                <div class="col-md-4 col-sm-6">
                                    <div class="info-group">
                                        <div class="info-label">บ้านเลขที่</div>
                                        <div class="info-value"><?= showVal($data['home_no']) ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6">
                                    <div class="info-group">
                                        <div class="info-label">หมู่ที่</div>
                                        <div class="info-value"><?= showVal($data['moo']) ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6">
                                    <div class="info-group">
                                        <div class="info-label">หมู่บ้าน / อาคาร</div>
                                        <div class="info-value"><?= showVal($data['village']) ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6">
                                    <div class="info-group">
                                        <div class="info-label">ซอย</div>
                                        <div class="info-value"><?= showVal($data['soi']) ?></div>
                                    </div>
                                </div>
                                <div class="col-md-8 col-sm-6">
                                    <div class="info-group">
                                        <div class="info-label">ถนน</div>
                                        <div class="info-value"><?= showVal($data['road']) ?></div>
                                    </div>
                                </div>

                                <div class="col-md-4 col-sm-6">
                                    <div class="info-group">
                                        <div class="info-label">จังหวัด</div>
                                        <div class="info-value"><?= showVal($data['province_name_th']) ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6">
                                    <div class="info-group">
                                        <div class="info-label">อำเภอ / เขต</div>
                                        <div class="info-value"><?= showVal($data['district_name_th']) ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6">
                                    <div class="info-group">
                                        <div class="info-label">ตำบล / แขวง</div>
                                        <div class="info-value"><?= showVal($data['subdistrict_name_th']) ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6">
                                    <div class="info-group">
                                        <div class="info-label">รหัสไปรษณีย์</div>
                                        <div class="info-value fw-bold text-primary"><?= showVal($data['zip_code']) ?></div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="card-footer bg-light p-4 text-end border-top-0">
                            <a href="edit_supplier.php?id=<?= $supplier_id ?>" class="btn btn-warning btn-action shadow-sm text-dark">
                                <i class="bi bi-pencil-square me-2"></i>แก้ไขข้อมูล
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>