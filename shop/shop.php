<?php
session_start();
require_once '../config/config.php';
checkPageAccess($conn, 'shop');

// รับค่าการค้นหา
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

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
        WHERE 1=1";

if ($search) {
    // (4) *** แก้ไขฟิลด์ที่ใช้ค้นหา ***
    $sql .= " AND (s.shop_name LIKE '%$search%' 
             OR s.tax_id LIKE '%$search%'
             OR s.shop_phone LIKE '%$search%'
             OR s.shop_email LIKE '%$search%')";
}

$sql .= " ORDER BY s.shop_id DESC";
$result = mysqli_query($conn, $sql);

// ฟังก์ชันตัดข้อความ
function truncateText($text, $length = 50)
{
    if (mb_strlen($text) > $length) {
        return mb_substr($text, 0, $length) . '...';
    }
    return $text;
}

//  ฟังก์ชันแสดงที่อยู่ 
function formatAddress($shop)
{
    $address_parts = [];
    if (!empty($shop['home_no'])) $address_parts[] = "เลขที่ " . $shop['home_no'];
    if (!empty($shop['moo'])) $address_parts[] = "หมู่ " . $shop['moo'];
    if (!empty($shop['soi'])) $address_parts[] = "ซอย" . $shop['soi'];
    if (!empty($shop['road'])) $address_parts[] = "ถ." . $shop['road'];
    if (!empty($shop['village'])) $address_parts[] = $shop['village'];
    if (!empty($shop['subdistrict_name_th'])) $address_parts[] = "ต." . $shop['subdistrict_name_th'];
    if (!empty($shop['district_name_th'])) $address_parts[] = "อ." . $shop['district_name_th'];
    if (!empty($shop['province_name_th'])) $address_parts[] = "จ." . $shop['province_name_th'];
    if (!empty($shop['zip_code'])) $address_parts[] = $shop['zip_code'];

    return !empty($address_parts) ? implode(' ', $address_parts) : '-';
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการร้านค้า</title>
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
            max-width: 1200px;
            padding: 20px 15px;
        }

        .page-header {
            background: <?= $theme_color ?>;
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .page-header h4 {
            font-weight: 700;
            margin: 0;
            font-size: 28px;
        }

        .search-section {
            background: #fff;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .search-input {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 20px;
            font-size: 15px;
        }

        .btn-search {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
        }

        .btn-add {
            background: <?= $btn_add_color ?>;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 500;
            text-decoration: none;
        }

        .btn-add:hover {
            color: white;
            filter: brightness(90%);
        }

        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .shop-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .shop-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .shop-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #a0aec0;
            font-size: 60px;
        }

        .shop-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .shop-body {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .shop-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .shop-info {
            font-size: 14px;
            color: #4a5568;
            margin-bottom: 8px;
            display: flex;
            align-items: start;
            gap: 10px;
        }

        .shop-info i {
            /* (ใช้ $theme_color) */
            color: <?= $theme_color ?>;
            width: 16px;
            margin-top: 2px;
        }

        .shop-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
            padding-top: 15px;
        }

        .btn-action {
            flex: 1;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            text-align: center;
            color: white;
            border: none;
        }

        .btn-view {
            background: #667eea;
            color: white;
        }

        .btn-edit {
            background-color: <?= $btn_edit_color ?>;
        }

        .btn-delete {
            background-color: <?= $btn_delete_color ?>;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
        }

        .empty-state i {
            font-size: 80px;
            color: #cbd5e0;
            margin-bottom: 20px;
        }

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
            animation: slideIn 0.3s ease;
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
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container my-4">
                    <div class="page-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <h4 class="text-light"><i class="fas fa-store me-2 text-light"></i>รายการร้านค้า</h4>
                            </div>
                            <div class="col-md-2">
                                <a href="add_shop.php" class="btn btn-add w-100">
                                    <i class="fas fa-plus me-2"></i>เพิ่มร้านค้า
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="search-section">
                        <form method="GET" action="shop.php">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control search-input"
                                    placeholder="ค้นหาชื่อร้าน, เลขผู้เสียภาษี, เบอร์โทร..."
                                    value="<?= htmlspecialchars($search) ?>">
                                <button class="btn btn-search" type="submit">
                                    <i class="fas fa-search me-1"></i> ค้นหา
                                </button>
                                <?php if ($search): ?>
                                    <a href="shop.php" class="btn btn-outline-danger">
                                        <i class="fas fa-times"></i> ล้าง
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <div class="shop-grid">
                            <?php while ($shop = mysqli_fetch_assoc($result)): ?>
                                <div class="shop-card">
                                    <div class="shop-image">
                                        <?php if (!empty($shop['logo'])): ?>
                                            <?php
                                            // (ใช้รูปแรกสุด)
                                            $images = explode(',', $shop['logo']);
                                            $first_image = trim($images[0]);
                                            // (สมมติว่า Path คือ ../uploads/shops/ ตามโค้ดเดิม)
                                            $img_path = "../uploads/shops/" . $first_image;
                                            ?>
                                            <img src="<?= htmlspecialchars($img_path) ?>"
                                                alt="<?= htmlspecialchars($shop['shop_name']) ?>"
                                                onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-store\'></i>';">
                                        <?php else: ?>
                                            <i class="fas fa-store"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="shop-body">
                                        <h5 class="shop-title"><?= htmlspecialchars($shop['shop_name']) ?></h5>

                                        <div class="shop-info">
                                            <i class="fas fa-file-invoice"></i>
                                            <span>เลขผู้เสียภาษี: <?= htmlspecialchars($shop['tax_id'] ?? '-') ?></span>
                                        </div>

                                        <?php if (!empty($shop['shop_phone'])): ?>
                                            <div class="shop-info">
                                                <i class="fas fa-phone"></i>
                                                <span><?= htmlspecialchars($shop['shop_phone']) ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($shop['shop_email'])): ?>
                                            <div class="shop-info">
                                                <i class="fas fa-envelope"></i>
                                                <span><?= htmlspecialchars($shop['shop_email']) ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <div class="shop-info">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span><?= truncateText(formatAddress($shop), 60) ?></span>
                                        </div>

                                        <div class="shop-actions">
                                            <a href="view_shop.php?id=<?= $shop['shop_id'] ?>" class="btn-action btn-view">
                                                <i class="fas fa-eye me-1"></i>ดูข้อมูล
                                            </a>
                                            <a href="edit_shop.php?id=<?= $shop['shop_id'] ?>" class="btn-action btn-edit">
                                                <i class="fas fa-edit me-1"></i>แก้ไข
                                            </a>
                                            <a href="javascript:void(0);" class="btn-action btn-delete"
                                                onclick="confirmDelete(<?= $shop['shop_id'] ?>, '<?= htmlspecialchars($shop['shop_name']) ?>')">
                                                <i class="fas fa-trash me-1"></i>ลบ
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-store-slash"></i>
                            <h5>ไม่พบข้อมูลร้านค้า</h5>
                            <p class="text-muted">
                                <?php if ($search): ?>
                                    ไม่พบร้านค้าที่ตรงกับคำค้นหา "<?php echo htmlspecialchars($search); ?>"
                                <?php else: ?>
                                    ยังไม่มีข้อมูลร้านค้าในระบบ
                                <?php endif; ?>
                            </p>
                            <a href="add_shop.php" class="btn btn-add">
                                <i class="fas fa-plus me-2"></i>เพิ่มร้านค้าแรก
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="modal fade" id="deleteModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header border-0">
                                <h5 class="modal-title">
                                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                    ยืนยันการลบ
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-0">คุณต้องการลบร้านค้า "<span id="shopName"></span>" ใช่หรือไม่?</p>
                                <p class="text-muted small mt-2">การดำเนินการนี้ไม่สามารถย้อนกลับได้</p>
                            </div>
                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                <a id="deleteLink" href="#" class="btn btn-danger">
                                    <i class="fas fa-trash me-2"></i>ลบ
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function confirmDelete(id, name) {
            document.getElementById('shopName').textContent = name;
            document.getElementById('deleteLink').href = 'delete_shop.php?id=' + id;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.custom-alert');
            alerts.forEach(alert => {
                alert.style.animation = 'slideIn 0.3s ease reverse';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>