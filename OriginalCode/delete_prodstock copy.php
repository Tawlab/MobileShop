<?php
session_start();
require '../config/config.php';

// รับ ID สต็อกที่ต้องการลบ
$stock_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$stock_id) {
    $_SESSION['error'] = 'ไม่พบรหัสสต็อกที่ต้องการลบ';
    header('Location: prod_stock.php');
    exit;
}

// ดึงข้อมูลสต็อกที่ต้องการลบ
$stock_sql = "SELECT 
                ps.id as stock_id,
                ps.imei,
                ps.barcode,
                ps.stock_status,
                ps.prod_image,
                ps.prod_warranty_id,
                p.name as product_name,
                p.model_name,
                pb.name_th as brand_name,
                pw.warranty_status
            FROM prod_stocks ps
            LEFT JOIN products p ON ps.products_id = p.id
            LEFT JOIN prod_brands pb ON p.prod_brands_id = pb.id
            LEFT JOIN prod_warranty pw ON ps.prod_warranty_id = pw.id
            WHERE ps.id = $stock_id";

$result = mysqli_query($conn, $stock_sql);
$stock_data = mysqli_fetch_assoc($result);

if (!$stock_data) {
    $_SESSION['error'] = 'ไม่พบข้อมูลสต็อกที่ต้องการลบ';
    header('Location: prod_stock.php');
    exit;
}

// ตรวจสอบเงื่อนไขการลบ
$can_delete = true;
$error_message = '';

if ($stock_data['stock_status'] === 'sold') {
    $can_delete = false;
    $error_message = 'ไม่สามารถลบสินค้าที่ขายแล้ว';
}

// จัดการการลบ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    if (!$can_delete) {
        $_SESSION['error'] = $error_message;
        header('Location: prod_stock.php');
        exit;
    }
    
    // เริ่ม Transaction
    mysqli_autocommit($conn, false);
    
    try {
        // ลบรูปภาพ
        if (!empty($stock_data['prod_image'])) {
            $images = json_decode($stock_data['prod_image'], true);
            if (is_array($images)) {
                foreach ($images as $image) {
                    $image_path = '../uploads/products/' . $image;
                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
            }
        }
        
        // ลบการรับประกัน
        if ($stock_data['prod_warranty_id']) {
            $delete_warranty_sql = "DELETE FROM prod_warranty WHERE id = " . $stock_data['prod_warranty_id'];
            mysqli_query($conn, $delete_warranty_sql);
        }
        
        // ลบสต็อก
        $delete_stock_sql = "DELETE FROM prod_stocks WHERE id = $stock_id";
        if (!mysqli_query($conn, $delete_stock_sql)) {
            throw new Exception('ไม่สามารถลบข้อมูลสต็อกได้');
        }
        
        mysqli_commit($conn);
        $_SESSION['success'] = 'ลบสินค้าออกจากสต็อกเรียบร้อยแล้ว';
        header('Location: prod_stock.php');
        exit;
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = 'เกิดข้อผิดพลาดในการลบ: ' . $e->getMessage();
    }
    
    mysqli_autocommit($conn, true);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลบสินค้าจากสต็อก - ระบบจัดการร้านค้ามือถือ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Prompt', sans-serif;
        }

        .main-header {
            background-color: #dc3545;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .delete-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            background-color: #dc3545;
            color: white;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
            font-weight: 600;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
        }

        .info-value {
            color: #212529;
            font-weight: 500;
        }

        .highlight-value {
            color: #dc3545;
            font-weight: 700;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 500;
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

        .warning-box {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .error-box {
            background-color: #f8d7da;
            border: 2px solid #dc3545;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .btn-danger {
            background-color: #dc3545;
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
        }

        .btn-secondary {
            background-color: #6c757d;
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="main-header">
        <div class="container">
            <h1>
                <i class="fas fa-trash-alt me-3"></i>
                ลบสินค้าจากสต็อก
            </h1>
        </div>
    </div>

    <div class="container">
        <!-- แสดงข้อความแจ้งเตือน -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- แสดงข้อผิดพลาดถ้าไม่สามารถลบได้ -->
        <?php if (!$can_delete): ?>
            <div class="error-box">
                <h4 class="text-danger mb-3">
                    <i class="fas fa-ban me-2"></i>ไม่สามารถลบสินค้านี้ได้
                </h4>
                <p class="text-danger fw-bold mb-0"><?= $error_message ?></p>
            </div>
        <?php else: ?>
            <!-- แสดงคำเตือน -->
            <div class="warning-box">
                <h4 class="text-warning mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>คำเตือน
                </h4>
                <p class="mb-0 fw-bold">
                    การลบจะไม่สามารถกู้คืนได้ กรุณาตรวจสอบข้อมูลให้ถูกต้อง
                </p>
            </div>
        <?php endif; ?>

        <!-- ข้อมูลสินค้าที่จะลบ -->
        <div class="delete-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>ข้อมูลสินค้าที่จะลบ
                </h5>
            </div>
            <div class="card-body">
                <div class="info-item">
                    <span class="info-label">รหัสสต็อก:</span>
                    <span class="info-value highlight-value"><?= str_pad($stock_data['stock_id'], 6, '0', STR_PAD_LEFT) ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">สินค้า:</span>
                    <span class="info-value"><?= htmlspecialchars($stock_data['product_name']) ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">ยี่ห้อ/รุ่น:</span>
                    <span class="info-value"><?= htmlspecialchars($stock_data['brand_name']) ?> - <?= htmlspecialchars($stock_data['model_name']) ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">IMEI:</span>
                    <span class="info-value highlight-value"><?= htmlspecialchars($stock_data['imei']) ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">บาร์โค้ด:</span>
                    <span class="info-value"><?= htmlspecialchars($stock_data['barcode']) ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">สถานะ:</span>
                    <span class="info-value">
                        <?php
                        switch ($stock_data['stock_status']) {
                            case 'available':
                                echo '<span class="status-badge status-available">พร้อมขาย</span>';
                                break;
                            case 'sold':
                                echo '<span class="status-badge status-sold">ขายแล้ว</span>';
                                break;
                            case 'damaged':
                                echo '<span class="status-badge status-damaged">ชำรุด</span>';
                                break;
                        }
                        ?>
                    </span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">สถานะการรับประกัน:</span>
                    <span class="info-value">
                        <?php
                        if ($stock_data['warranty_status'] === 'pending') {
                            echo '<span class="badge bg-warning">รอการขาย</span>';
                        } elseif ($stock_data['warranty_status'] === 'active') {
                            echo '<span class="badge bg-success">เริ่มแล้ว</span>';
                        } else {
                            echo '<span class="badge bg-secondary">ไม่ทราบ</span>';
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- ปุ่มดำเนินการ -->
        <div class="text-center">
            <?php if ($can_delete): ?>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="confirm_delete" class="btn btn-danger me-3" 
                            onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบสินค้านี้?\n\nรหัสสต็อก: <?= str_pad($stock_data['stock_id'], 6, '0', STR_PAD_LEFT) ?>\nสินค้า: <?= htmlspecialchars($stock_data['product_name']) ?>\nIMEI: <?= htmlspecialchars($stock_data['imei']) ?>\n\nการลบจะไม่สามารถกู้คืนได้!')">
                        <i class="fas fa-trash-alt me-2"></i>ยืนยันการลบ
                    </button>
                </form>
            <?php endif; ?>
            
            <a href="prod_stock.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>ย้อนกลับ
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>