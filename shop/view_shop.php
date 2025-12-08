<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'view_shop');

// ตรวจสอบ ID
$shop_id = $_GET['id'] ?? 0;
if (empty($shop_id)) {
    echo "<script>alert('Shop ID not found'); window.location='shop.php';</script>";
    exit;
}

// ดึงข้อมูล
$sql = "SELECT 
            s.shop_id, s.shop_name, s.tax_id, s.shop_phone, s.shop_email, s.logo,
            a.home_no, a.moo, a.soi, a.road, a.village,
            sub.subdistrict_name_th, sub.zip_code,
            d.district_name_th,
            p.province_name_th
        FROM shop_info s
        LEFT JOIN addresses a ON s.Addresses_address_id = a.address_id
        LEFT JOIN subdistricts sub ON a.subdistricts_subdistrict_id = sub.subdistrict_id
        LEFT JOIN districts d ON sub.districts_district_id = d.district_id
        LEFT JOIN provinces p ON d.provinces_province_id = p.province_id
        WHERE s.shop_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $shop_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if (!$data) {
    echo "<script>alert('ไม่พบข้อมูลร้านค้า'); window.location='shop.php';</script>";
    exit();
}

//  เตรียมข้อมูลรูปภาพ
$current_logo_list = !empty($data['logo']) ? explode(',', $data['logo']) : [];

// ฟังก์ชันสำหรับแสดง '-' ถ้าค่าว่าง
function displayValue($value)
{
    $value = trim($value ?? '');
    return htmlspecialchars($value === '' ? '-' : $value);
}

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดร้านค้า</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            font-size: 15px;
            color: <?= $text_color ?>;
            min-height: 100vh;
        }

        .container {
            max-width: 960px;
            padding: 20px 15px;
        }

        .page-header {
            background: <?= $theme_color ?>;
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .page-header h4 {
            font-weight: 700;
            margin: 0;
            font-size: 28px;
        }

        h5 {
            margin-top: 0;
            padding-bottom: 15px;
            font-weight: 600;
            color: <?= $theme_color ?>;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 25px;
            font-size: 20px;
        }

        .form-section {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }

        .form-label {
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 15px;
            color: #555;
        }

        .view-field {
            display: block;
            width: 100%;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: #212529;
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            min-height: calc(1.5em + 0.75rem + 2px);
        }

        .btn {
            padding: 12px 30px;
            font-weight: 500;
            font-size: 16px;
            border-radius: 10px;
            border: none;
        }

        .btn-edit {
            background: <?= $btn_edit_color ?>;
            color: white;
        }

        .btn-edit:hover {
            color: white;
            filter: brightness(90%);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        .image-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }

        .image-preview-item {
            position: relative;
            width: 150px;
            height: 150px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #ddd;
        }

        .image-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-grid-full {
            grid-column: 1 / -1;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container my-4">
                    <div class="page-header">
                        <h4 class="text-light">
                            <i class="fas fa-eye me-2"></i>
                            รายละเอียดร้านค้า (ID: <?= htmlspecialchars($data['shop_id']) ?>)
                        </h4>
                    </div>

                    <div class="form-section">
                        <h5><i class="fas fa-store me-2"></i>ข้อมูลทั่วไป</h5>
                        <div class="form-grid">
                            <div>
                                <label class="form-label">ชื่อร้านค้า</label>
                                <div class="view-field border-secondary"><?= displayValue($data['shop_name']) ?></div>
                            </div>
                            <div>
                                <label class="form-label">เลขประจำตัวผู้เสียภาษี</label>
                                <div class="view-field border-secondary"><?= displayValue($data['tax_id']) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h5><i class="fas fa-map-marker-alt me-2"></i>ที่อยู่</h5>
                        <div class="form-grid">
                            <div>
                                <label class="form-label">บ้านเลขที่</label>
                                <div class="view-field border-secondary"><?= displayValue($data['home_no']) ?></div>
                            </div>
                            <div>
                                <label class="form-label">หมู่ที่</label>
                                <div class="view-field border-secondary"><?= displayValue($data['moo']) ?></div>
                            </div>
                            <div>
                                <label class="form-label">ซอย</label>
                                <div class="view-field border-secondary"><?= displayValue($data['soi']) ?></div>
                            </div>
                            <div>
                                <label class="form-label">ถนน</label>
                                <div class="view-field border-secondary"><?= displayValue($data['road']) ?></div>
                            </div>
                            <div class="form-grid-full">
                                <label class="form-label">หมู่บ้าน</label>
                                <div class="view-field border-secondary"><?= displayValue($data['village']) ?></div>
                            </div>
                            <div>
                                <label class="form-label">จังหวัด</label>
                                <div class="view-field border-secondary"><?= displayValue($data['province_name_th']) ?></div>
                            </div>
                            <div>
                                <label class="form-label">อำเภอ</label>
                                <div class="view-field border-secondary"><?= displayValue($data['district_name_th']) ?></div>
                            </div>
                            <div>
                                <label class="form-label">ตำบล</label>
                                <div class="view-field border-secondary"><?= displayValue($data['subdistrict_name_th']) ?></div>
                            </div>
                            <div>
                                <label class="form-label">รหัสไปรษณีย์</label>
                                <div class="view-field border-secondary"><?= displayValue($data['zip_code']) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h5><i class="fas fa-phone-alt me-2"></i>ข้อมูลติดต่อ</h5>
                        <div class="form-grid">
                            <div>
                                <label class="form-label">เบอร์โทรศัพท์</label>
                                <div class="view-field border-secondary"><?= displayValue($data['shop_phone']) ?></div>
                            </div>
                            <div>
                                <label class="form-label">อีเมล</label>
                                <div class="view-field border-secondary"><?= displayValue($data['shop_email']) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h5><i class="fas fa-images me-2"></i>รูปภาพร้านค้า</h5>
                        <div id="imagePreview" class="image-preview">
                            <?php if (empty($current_logo_list)): ?>
                                <p class="text-muted">ไม่มีรูปภาพ</p>
                            <?php else: ?>
                                <?php foreach ($current_logo_list as $img_name): ?>
                                    <div class="image-preview-item">
                                        <img src="../uploads/shops/<?= htmlspecialchars($img_name) ?>"
                                            alt="Shop Image"
                                            onerror="this.parentElement.innerHTML = '<div class=\'image-preview-item\'>' + 
                                 '<i class=\'fas fa-exclamation-triangle\'></i><small>Error</small></div>'">
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="text-end">
                        <a href="edit_shop.php?id=<?= htmlspecialchars($shop_id) ?>" class="btn btn-edit">
                            <i class="fas fa-edit me-2"></i>แก้ไขข้อมูล
                        </a>
                        <a href="shop.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>ย้อนกลับ
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>