<?php
session_start();
require '../config/config.php';

// รับ ID สต็อกที่ต้องการดู
$stock_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$stock_id) {
    $_SESSION['error'] = 'ไม่พบรหัสสต็อกที่ต้องการดู';
    header('Location: prod_stock.php');
    exit;
}

// ดึงข้อมูลสต็อกพร้อมรายละเอียดทั้งหมด
$stock_sql = "SELECT 
                ps.id as stock_id,
                ps.imei,
                ps.barcode,
                ps.date_in,
                ps.date_out,
                ps.price as stock_price,
                ps.prod_image,
                ps.supplier_id,
                ps.proout_types_id,
                p.id as product_id,
                p.name as product_name,
                p.model_name,
                p.model_no,
                p.description as product_description,
                p.price as original_price,
                pb.name_th as brand_name,
                pt.name_th as type_name,
                pw.id as warranty_id,
                pw.start_date,
                pw.end_date,
                pw.total_warranty,
                pw.description as warranty_description,
                pw.warranty_status,
                s.sp_name as supplier_name,
                s.contract_fname,
                s.contract_lname,
                s.phone_no as supplier_phone,
                s.email as supplier_email,
                bh.id AS receipt_id
            FROM prod_stocks ps
            LEFT JOIN products p ON ps.products_id = p.id
            LEFT JOIN prod_brands pb ON p.prod_brands_id = pb.id
            LEFT JOIN prod_types pt ON p.prod_types_id = pt.id
            LEFT JOIN prod_warranty pw ON ps.prod_warranty_id = pw.id
            LEFT JOIN suppliers s ON ps.supplier_id = s.id
            LEFT JOIN bill_details bd ON bd.stock_id = ps.id
            LEFT JOIN bill_headers bh ON bh.id = bd.bill_headers_id
            WHERE ps.id = $stock_id";

$stock_result = mysqli_query($conn, $stock_sql);
$stock_data = mysqli_fetch_assoc($stock_result);

if (!$stock_data) {
    $_SESSION['error'] = 'ไม่พบข้อมูลสต็อกที่ต้องการดู';
    header('Location: prod_stock.php');
    exit;
}

// ตรวจสอบสถานะการรับประกัน
$warranty_status = '';
$warranty_days_left = 0;
$warranty_display = '';

if ($stock_data['warranty_status'] === 'pending' || $stock_data['proout_types_id'] != 2) {
    $warranty_status = 'pending';
    $warranty_display = 'รอการขาย';
} elseif ($stock_data['warranty_status'] === 'active' && $stock_data['end_date']) {
    $warranty_end = strtotime($stock_data['end_date']);
    $today = time();
    $warranty_days_left = ceil(($warranty_end - $today) / (60 * 60 * 24));

    if ($warranty_end > $today) {
        $warranty_status = 'active';
        $warranty_display = 'ยังอยู่ในประกัน';
    } else {
        $warranty_status = 'expired';
        $warranty_display = 'หมดประกันแล้ว';
    }
} else {
    $warranty_status = 'unknown';
    $warranty_display = 'ไม่ทราบสถานะ';
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดสินค้าในสต็อก - ระบบจัดการร้านค้ามือถือ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Prompt', sans-serif;
            min-height: 100vh;
        }

        .main-header {
            background-color: #198754;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .main-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="50" height="50" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        }

        .main-header h1 {
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            font-weight: 700;
            margin-bottom: 0;
        }

        .info-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
            height: 100%;
        }

        .card-header {
            background: linear-gradient(135deg, #198754 0%, #20c997 100%);
            color: white;
            padding: 1rem 1.5rem;
            border: none;
            font-weight: 600;
        }

        .card-body {
            padding: 1.5rem;
        }

        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            display: inline-block;
        }

        .status-available {
            background-color: #d1edff;
            color: #0c63e4;
        }

        .status-sold {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-damaged {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-lost {
            background-color: #f5c6cb;
            color: #721c24;
        }

        .warranty-active {
            background-color: #d4edda;
            color: #155724;
        }

        .warranty-expired {
            background-color: #f8d7da;
            color: #721c24;
        }

        .warranty-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .warranty-warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .info-item {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }

        .info-value {
            color: #212529;
            font-size: 1rem;
        }

        .highlight-value {
            font-weight: 600;
            color: #198754;
            font-size: 1.1rem;
        }

        .btn-success {
            background-color: #198754;
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            background-color: #157347;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(25, 135, 84, 0.3);
        }

        .btn-warning {
            background-color: #ffc107;
            border: none;
            border-radius: 10px;
            color: #000;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-warning:hover {
            background-color: #e0a800;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 193, 7, 0.3);
        }

        .btn-secondary {
            background-color: #6c757d;
            border: none;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background-color: #5c636a;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
        }

        .product-images {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .product-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .product-image:hover {
            transform: scale(1.05);
            border-color: #198754;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .no-image {
            height: 150px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            border: 2px dashed #dee2e6;
        }

        .barcode-display {
            background: #f8f9fa;
            border: 2px solid #198754;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin: 15px 0;
        }

        .barcode-number {
            font-family: 'Courier New', monospace;
            font-size: 1.5rem;
            font-weight: bold;
            color: #198754;
            margin-top: 10px;
        }

        .action-buttons {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        /* Main Carousel Container */
        .main-carousel-container {
            position: relative;
            padding: 0 80px 60px 80px;
        }

        /* Main Carousel Controls */
        .main-carousel-control {
            width: 50px;
            height: 50px;
            background-color: rgba(25, 135, 84, 0.9);
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            box-shadow: 0 4px 15px rgba(25, 135, 84, 0.3);
        }

        .main-carousel-control:hover {
            background-color: rgba(25, 135, 84, 1);
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 6px 20px rgba(25, 135, 84, 0.4);
        }

        .main-carousel-control-prev {
            left: -25px;
        }

        .main-carousel-control-next {
            right: -25px;
        }

        /* Main Carousel Indicators */
        .main-carousel-indicators {
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 15px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .main-carousel-indicators button {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            border: none;
            background-color: rgba(25, 135, 84, 0.3);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .main-carousel-indicators button.active {
            background-color: #198754;
            transform: scale(1.3);
        }

        .main-carousel-indicators button:hover {
            background-color: rgba(25, 135, 84, 0.7);
        }

        /* ให้การ์ดทั้งหมดมีความสูงเท่ากัน */
        .info-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
            height: 100%;
            min-height: 450px;
            display: flex;
            flex-direction: column;
        }

        .info-card .card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }

        @media (max-width: 991px) {
            .main-carousel-container {
                padding: 0 15px 40px 15px;
            }

            .main-carousel-control {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .main-header {
                padding: 1rem 0;
            }

            .product-images {
                grid-template-columns: repeat(2, 1fr);
            }

            .action-buttons .btn {
                margin-bottom: 0.5rem;
                width: 100%;
            }

            .main-carousel-container {
                padding: 0 10px 30px 10px;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="main-header">
        <div class="container">
            <h1>
                <i class="fas fa-eye me-3"></i>
                รายละเอียดสินค้าในสต็อก
                <small class="fs-6 opacity-75 d-block">รหัสสต็อก: <?= str_pad($stock_data['stock_id'], 6, '0', STR_PAD_LEFT) ?></small>
            </h1>
        </div>
    </div>

    <div class="container">
        <!-- รูปภาพสินค้า - ด้านบน -->
        <?php
        $images = json_decode($stock_data['prod_image'], true);
        if (!empty($images)):
        ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="info-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-images me-2"></i>รูปภาพสินค้า</h5>
                        </div>
                        <div class="card-body">
                            <div class="product-images">
                                <?php foreach ($images as $image): ?>
                                    <img src="../uploads/products/<?= htmlspecialchars($image) ?>"
                                        alt="Product Image" class="product-image"
                                        onclick="showImageModal('<?= htmlspecialchars($image) ?>')">
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="info-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-images me-2"></i>รูปภาพสินค้า</h5>
                        </div>
                        <div class="card-body">
                            <div class="no-image">
                                <div class="text-center">
                                    <i class="fas fa-image fa-3x mb-2"></i>
                                    <p class="mb-0">ไม่มีรูปภาพ</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ข้อมูลหลักในแถวเดียว - Carousel แสดง 3 การ์ดพร้อมกัน -->
        <div class="row">
            <div class="col-12">
                <div class="main-carousel-container">
                    <div id="mainCarousel" class="carousel slide" data-bs-ride="false">
                        <div class="carousel-inner">
                            <!-- Slide 1: ข้อมูลพื้นฐาน, ข้อมูลสินค้า, ประกัน -->
                            <div class="carousel-item active">
                                <div class="row">
                                    <!-- ข้อมูลพื้นฐาน -->
                                    <div class="col-lg-4 col-md-6 mb-4">
                                        <div class="info-card">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>ข้อมูลพื้นฐาน</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="info-item">
                                                    <div class="info-label">รหัสสต็อก</div>
                                                    <div class="info-value highlight-value"><?= str_pad($stock_data['stock_id'], 6, '0', STR_PAD_LEFT) ?></div>
                                                </div>

                                                <div class="info-item">
                                                    <div class="info-label">IMEI</div>
                                                    <div class="info-value highlight-value"><?= htmlspecialchars($stock_data['imei']) ?></div>
                                                </div>

                                                <div class="info-item">
                                                    <div class="info-label">สถานะ</div>
                                                    <div class="info-value">
                                                        <?php
                                                        $status_class = '';
                                                        $status_text = '';
                                                        // แก้ไขจาก proout_type_id เป็น proout_types_id
                                                        switch ($stock_data['proout_types_id']) {
                                                            case '1':
                                                                $status_class = 'status-available';
                                                                $status_text = 'พร้อมขาย';
                                                                break;
                                                            case '2':
                                                                $status_class = 'status-sold';
                                                                $status_text = 'ขายแล้ว';
                                                                break;
                                                            case '3':
                                                                $status_class = 'status-damaged';
                                                                $status_text = 'ชำรุด';
                                                                break;
                                                            case '4':
                                                                $status_class = 'status-lost';
                                                                $status_text = 'หาย';
                                                                break;
                                                            default:
                                                                $status_class = 'status-available';
                                                                $status_text = $stock_data['proout_types_id'] ?: 'พร้อมขาย';
                                                        }
                                                        ?>
                                                        <span class="status-badge <?= $status_class ?>">
                                                            <?= $status_text ?>
                                                        </span>
                                                        <?php if ($stock_data['proout_types_id'] == 2 && !empty($stock_data['receipt_id'])): ?>
                                                            <div class="mt-1" style="font-size: 13px;">
                                                                <a href="../sales/view_sale.php?id=<?= $stock_data['receipt_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="text-decoration-none text-primary">
                                                                    <i class="fas fa-receipt me-1"></i>ใบเสร็จเลขที่ <?= str_pad($stock_data['receipt_id'], 7, '0', STR_PAD_LEFT) ?>
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="info-item">
                                                    <div class="info-label">ราคาขาย</div>
                                                    <div class="info-value highlight-value">฿<?= number_format($stock_data['stock_price'], 2) ?></div>
                                                    <?php if ($stock_data['original_price'] != $stock_data['stock_price']): ?>
                                                        <small class="text-muted">ราคาเดิม: ฿<?= number_format($stock_data['original_price'], 2) ?></small>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="info-item">
                                                    <div class="info-label">วันที่เข้าสต็อก</div>
                                                    <div class="info-value"><?= date('d/m/Y', strtotime($stock_data['date_in'])) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ข้อมูลสินค้า -->
                                    <div class="col-lg-4 col-md-6 mb-4">
                                        <div class="info-card">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="fas fa-box me-2"></i>ข้อมูลสินค้า</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="info-item">
                                                    <div class="info-label">ชื่อสินค้า</div>
                                                    <div class="info-value highlight-value"><?= htmlspecialchars($stock_data['product_name']) ?></div>
                                                </div>

                                                <div class="info-item">
                                                    <div class="info-label">ยี่ห้อ</div>
                                                    <div class="info-value"><?= htmlspecialchars($stock_data['brand_name']) ?></div>
                                                </div>

                                                <div class="info-item">
                                                    <div class="info-label">ประเภท</div>
                                                    <div class="info-value"><?= htmlspecialchars($stock_data['type_name']) ?></div>
                                                </div>

                                                <div class="info-item">
                                                    <div class="info-label">รุ่น</div>
                                                    <div class="info-value"><?= htmlspecialchars($stock_data['model_name']) ?></div>
                                                </div>

                                                <div class="info-item">
                                                    <div class="info-label">รหัสรุ่น</div>
                                                    <div class="info-value"><?= htmlspecialchars($stock_data['model_no']) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ข้อมูลการรับประกัน -->
                                    <div class="col-lg-4 col-md-6 mb-4">
                                        <div class="info-card">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>การรับประกัน</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="info-item">
                                                    <div class="info-label">สถานะการรับประกัน</div>
                                                    <div class="info-value">
                                                        <?php if ($warranty_status == 'active'): ?>
                                                            <span class="status-badge warranty-active">
                                                                <i class="fas fa-check-circle me-1"></i><?= $warranty_display ?>
                                                            </span>
                                                        <?php elseif ($warranty_status == 'expired'): ?>
                                                            <span class="status-badge warranty-expired">
                                                                <i class="fas fa-times-circle me-1"></i><?= $warranty_display ?>
                                                            </span>
                                                        <?php elseif ($warranty_status == 'pending'): ?>
                                                            <span class="status-badge warranty-pending">
                                                                <i class="fas fa-clock me-1"></i><?= $warranty_display ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="status-badge bg-secondary text-white">
                                                                <i class="fas fa-question-circle me-1"></i><?= $warranty_display ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="info-item">
                                                    <div class="info-label">ระยะเวลาการรับประกัน</div>
                                                    <div class="info-value"><?= $stock_data['total_warranty'] ?> เดือน</div>
                                                </div>

                                                <?php if ($warranty_status == 'active'): ?>
                                                    <div class="info-item">
                                                        <div class="info-label">วันที่เริ่มประกัน</div>
                                                        <div class="info-value"><?= date('d/m/Y', strtotime($stock_data['start_date'])) ?></div>
                                                    </div>

                                                    <div class="info-item">
                                                        <div class="info-label">วันที่หมดประกัน</div>
                                                        <div class="info-value"><?= date('d/m/Y', strtotime($stock_data['end_date'])) ?></div>
                                                    </div>

                                                    <div class="info-item">
                                                        <div class="info-label">วันที่เหลือ</div>
                                                        <div class="info-value">
                                                            <?php if ($warranty_days_left <= 30): ?>
                                                                <span class="status-badge warranty-warning">
                                                                    <i class="fas fa-exclamation-triangle me-1"></i><?= $warranty_days_left ?> วัน
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="status-badge warranty-active">
                                                                    <i class="fas fa-calendar me-1"></i><?= $warranty_days_left ?> วัน
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php elseif ($warranty_status == 'pending'): ?>
                                                    <div class="info-item">
                                                        <div class="info-label">หมายเหตุ</div>
                                                        <div class="info-value">
                                                            <small class="text-muted">
                                                                การรับประกันจะเริ่มต้นเมื่อสินค้าขายแล้วเท่านั้น<br>
                                                                ปัจจุบันสถานะ: <?= $stock_data['proout_types_id'] == '1' ? 'พร้อมขาย' : 'ชำรุด' ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                <?php elseif ($warranty_status == 'expired'): ?>
                                                    <div class="info-item">
                                                        <div class="info-label">วันที่เริ่มประกัน</div>
                                                        <div class="info-value"><?= date('d/m/Y', strtotime($stock_data['start_date'])) ?></div>
                                                    </div>

                                                    <div class="info-item">
                                                        <div class="info-label">วันที่หมดประกัน</div>
                                                        <div class="info-value"><?= date('d/m/Y', strtotime($stock_data['end_date'])) ?></div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Slide 2: บาร์โค้ด และการ์ดเพิ่มเติม -->
                            <div class="carousel-item">
                                <div class="row">
                                    <!-- บาร์โค้ด -->
                                    <div class="col-lg-4 col-md-6 mb-4">
                                        <div class="info-card">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="fas fa-barcode me-2"></i>บาร์โค้ด</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="barcode-display">
                                                    <canvas id="barcodeCanvas" style="max-width: 100%;"></canvas>
                                                    <div class="barcode-number"><?= htmlspecialchars($stock_data['barcode']) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Supplier -->
                                    <div class="col-lg-4 col-md-6 mb-4">
                                        <div class="info-card">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="fas fa-truck me-2"></i>ผู้จำหน่าย</h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if ($stock_data['supplier_name']): ?>
                                                    <div class="info-item">
                                                        <div class="info-label">ชื่อบริษัท</div>
                                                        <div class="info-value highlight-value"><?= htmlspecialchars($stock_data['supplier_name']) ?></div>
                                                    </div>

                                                    <?php if ($stock_data['contract_fname']): ?>
                                                        <div class="info-item">
                                                            <div class="info-label">ผู้ติดต่อ</div>
                                                            <div class="info-value"><?= htmlspecialchars($stock_data['contract_fname'] . ' ' . $stock_data['contract_lname']) ?></div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($stock_data['supplier_phone']): ?>
                                                        <div class="info-item">
                                                            <div class="info-label">เบอร์โทรศัพท์</div>
                                                            <div class="info-value">
                                                                <a href="tel:<?= htmlspecialchars($stock_data['supplier_phone']) ?>" class="text-decoration-none">
                                                                    <i class="fas fa-phone me-1"></i><?= htmlspecialchars($stock_data['supplier_phone']) ?>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($stock_data['supplier_email']): ?>
                                                        <div class="info-item">
                                                            <div class="info-label">อีเมล</div>
                                                            <div class="info-value">
                                                                <a href="mailto:<?= htmlspecialchars($stock_data['supplier_email']) ?>" class="text-decoration-none">
                                                                    <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($stock_data['supplier_email']) ?>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                <?php else: ?>
                                                    <div class="text-center text-muted">
                                                        <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
                                                        <p>ไม่มีข้อมูลผู้จำหน่าย</p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ข้อมูลการนำออก -->
                                    <div class="col-lg-4 col-md-6 mb-4">
                                        <div class="info-card">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="fas fa-sign-out-alt me-2"></i>ข้อมูลการนำออก</h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if ($stock_data['date_out']): ?>
                                                    <div class="info-item">
                                                        <div class="info-label">วันที่นำออก</div>
                                                        <div class="info-value highlight-value"><?= date('d/m/Y', strtotime($stock_data['date_out'])) ?></div>
                                                    </div>

                                                    <div class="info-item">
                                                        <div class="info-label">เวลาที่นำออก</div>
                                                        <div class="info-value"><?= date('H:i น.', strtotime($stock_data['date_out'])) ?></div>
                                                    </div>

                                                    <div class="info-item">
                                                        <div class="info-label">อายุในสต็อก</div>
                                                        <div class="info-value">
                                                            <?php
                                                            $date_in = strtotime($stock_data['date_in']);
                                                            $date_out = strtotime($stock_data['date_out']);
                                                            $days = ceil(($date_out - $date_in) / (60 * 60 * 24));
                                                            echo $days . ' วัน';
                                                            ?>
                                                        </div>
                                                    </div>

                                                    <div class="info-item">
                                                        <div class="info-label">สาเหตุการนำออก</div>
                                                        <div class="info-value">
                                                            <?php
                                                            // แก้ไขจาก proout_type_id เป็น proout_types_id
                                                            switch ($stock_data['proout_types_id']) {
                                                                case '2':
                                                                    echo '<span class="status-badge status-sold"><i class="fas fa-shopping-cart me-1"></i>ขายแล้ว</span>';
                                                                    break;
                                                                case '3':
                                                                    echo '<span class="status-badge status-damaged"><i class="fas fa-exclamation-triangle me-1"></i>ชำรุด</span>';
                                                                    break;
                                                                case '4':
                                                                    echo '<span class="status-badge status-lost"><i class="fas fa-times-circle me-1"></i>หาย</span>';
                                                                    break;
                                                                default:
                                                                    echo '<span class="status-badge bg-secondary text-white"><i class="fas fa-question me-1"></i>ไม่ระบุ</span>';
                                                            }
                                                            ?>
                                                            <?php if ($stock_data['proout_types_id'] == 2 && !empty($stock_data['receipt_id'])): ?>
                                                                <?php if ($stock_data['proout_types_id'] == 2 && !empty($stock_data['receipt_id'])): ?>
                                                                    <div class="mt-1" style="font-size: 13px;">
                                                                        <a href="../sales/view_sale.php?id=<?= $stock_data['receipt_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="text-decoration-none text-primary">
                                                                            <i class="fas fa-receipt me-1"></i>ใบเสร็จเลขที่ <?= str_pad($stock_data['receipt_id'], 7, '0', STR_PAD_LEFT) ?>
                                                                        </a>
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <?php if ($stock_data['proout_types_id'] === '2'): ?>
                                                        <div class="info-item">
                                                            <div class="info-label">สถานะการรับประกัน</div>
                                                            <div class="info-value">
                                                                <?php if ($warranty_status === 'active'): ?>
                                                                    <span class="status-badge warranty-active">
                                                                        <i class="fas fa-play-circle me-1"></i>เริ่มการรับประกันแล้ว
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="status-badge warranty-expired">
                                                                        <i class="fas fa-times-circle me-1"></i>หมดประกันแล้ว
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                <?php else: ?>
                                                    <div class="info-item">
                                                        <div class="info-label">สถานะ</div>
                                                        <div class="info-value">
                                                            <span class="status-badge status-available">
                                                                <i class="fas fa-box me-1"></i>ยังอยู่ในสต็อก
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="info-item">
                                                        <div class="info-label">อายุในสต็อกปัจจุบัน</div>
                                                        <div class="info-value">
                                                            <?php
                                                            $date_in = strtotime($stock_data['date_in']);
                                                            $today = time();
                                                            $days = ceil(($today - $date_in) / (60 * 60 * 24));
                                                            echo $days . ' วัน';
                                                            ?>
                                                        </div>
                                                    </div>

                                                    <div class="info-item">
                                                        <div class="info-label">สถานะการรับประกัน</div>
                                                        <div class="info-value">
                                                            <span class="status-badge warranty-pending">
                                                                <i class="fas fa-clock me-1"></i>รอการขาย
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="text-center text-muted mt-3">
                                                        <i class="fas fa-clock fa-2x mb-2"></i>
                                                        <p class="mb-0">การรับประกันจะเริ่มเมื่อขายสินค้า</p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ปุ่มควบคุม Main Carousel -->
                        <button class="main-carousel-control main-carousel-control-prev" type="button" data-bs-target="#mainCarousel" data-bs-slide="prev">
                            <i class="fas fa-chevron-left text-white"></i>
                        </button>
                        <button class="main-carousel-control main-carousel-control-next" type="button" data-bs-target="#mainCarousel" data-bs-slide="next">
                            <i class="fas fa-chevron-right text-white"></i>
                        </button>

                        <!-- Indicators สำหรับ Main Carousel -->
                        <div class="main-carousel-indicators">
                            <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="0" class="active" aria-label="หน้า 1"></button>
                            <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="1" aria-label="หน้า 2"></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons text-center mt-4">
            <a href="edit_stock.php?id=<?= $stock_data['stock_id'] ?>" class="btn btn-warning me-2">
                <i class="fas fa-edit me-2"></i>แก้ไขข้อมูล
            </a>
            <a href="print_barcode.php?stock_ids=<?= $stock_data['stock_id'] ?>" class="btn btn-success me-2">
                <i class="fas fa-print me-2"></i>พิมพ์บาร์โค้ด
            </a>
            <a href="prod_stock.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>ย้อนกลับ
            </a>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">รูปภาพสินค้า</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="Product Image" style="max-width: 100%; height: auto;">
                </div>
            </div>
        </div>
    </div>

    <!-- JsBarcode Library -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <!-- QR Code Library -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // สร้างบาร์โค้ดและ QR Code
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('barcodeCanvas');
            const barcodeValue = '<?= htmlspecialchars($stock_data['barcode']) ?>';

            // สร้างบาร์โค้ด
            if (canvas && barcodeValue) {
                try {
                    JsBarcode(canvas, barcodeValue, {
                        format: "CODE128",
                        width: 2,
                        height: 80,
                        displayValue: true,
                        fontSize: 14,
                        margin: 10,
                        background: "#ffffff",
                        lineColor: "#000000"
                    });
                } catch (error) {
                    console.error('Error generating barcode:', error);
                    canvas.getContext('2d').fillText('Error generating barcode', 10, 50);
                }
            }

            // อัปเดต indicators ของ main carousel
            updateMainCarouselIndicators();

            // Animation เมื่อโหลดหน้า
            const cards = document.querySelectorAll('.info-card, .action-buttons');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 + (index * 100));
            });
        });

        // ฟังก์ชันอัปเดต indicators สำหรับ main carousel
        function updateMainCarouselIndicators() {
            const mainCarousel = document.getElementById('mainCarousel');
            if (mainCarousel) {
                mainCarousel.addEventListener('slide.bs.carousel', function(e) {
                    const indicators = document.querySelectorAll('.main-carousel-indicators button');
                    indicators.forEach((indicator, index) => {
                        indicator.classList.toggle('active', index === e.to);
                    });
                });
            }
        }

        // แสดง modal รูปภาพ
        function showImageModal(imageName) {
            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            const modalImage = document.getElementById('modalImage');
            modalImage.src = '../uploads/products/' + imageName;
            modal.show();
        }

        // Copy text functionality
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('คัดลอกแล้ว: ' + text);
            });
        }

        // เพิ่มการคลิกเพื่อคัดลอก IMEI และ บาร์โค้ด
        document.addEventListener('DOMContentLoaded', function() {
            // เพิ่ม cursor pointer และ tooltip
            const copyableElements = document.querySelectorAll('.highlight-value');
            copyableElements.forEach(element => {
                element.style.cursor = 'pointer';
                element.title = 'คลิกเพื่อคัดลอก';
                element.addEventListener('click', function() {
                    copyToClipboard(this.textContent.trim());
                });
            });

            // เพิ่มการคลิกคัดลอกบาร์โค้ด
            const barcodeNumber = document.querySelector('.barcode-number');
            if (barcodeNumber) {
                barcodeNumber.style.cursor = 'pointer';
                barcodeNumber.title = 'คลิกเพื่อคัดลอกบาร์โค้ด';
                barcodeNumber.addEventListener('click', function() {
                    copyToClipboard(this.textContent.trim());
                });
            }
        });
    </script>
</body>

</html>