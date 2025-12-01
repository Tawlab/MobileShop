<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'view_product');
// (1) โหลดธีม
require '../config/load_theme.php';

// (2) ตรวจสอบว่ามี ID ส่งมาหรือไม่
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ไม่พบรหัสสินค้าที่ต้องการดู";
    header('Location: product.php');
    exit();
}

$product_id = $_GET['id'];

// (3) ดึงข้อมูลสินค้า (แก้คอลัมน์และ JOIN)
$product_sql = "SELECT p.prod_id, p.prod_name, p.model_name, p.model_no, p.prod_desc, p.prod_price, 
                       pb.brand_name_th as brand_name, pt.type_name_th as type_name 
                FROM products p 
                LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id 
                LEFT JOIN prod_types pt ON p.prod_types_type_id = pt.type_id 
                WHERE p.prod_id = ?";

if ($stmt = mysqli_prepare($conn, $product_sql)) {
    // (4) ใช้ Prepared Statement
    mysqli_stmt_bind_param($stmt, "s", $product_id); // "s" เผื่อ ID เป็น string
    mysqli_stmt_execute($stmt);
    $product_result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($product_result) == 0) {
        $_SESSION['error'] = "ไม่พบข้อมูลสินค้าที่ต้องการดู";
        header('Location: product.php');
        exit();
    }

    $product = mysqli_fetch_assoc($product_result);
    mysqli_stmt_close($stmt);
} else {
    $_SESSION['error'] = "เกิดข้อผิดพลาดในการดึงข้อมูล";
    header('Location: product.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดสินค้า - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            color: <?= $text_color ?>;
            min-height: 100vh;
        }

        .main-header {
            background-color: <?= $theme_color ?>;
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
        }

        .main-header h1 {
            font-weight: 700;
            margin-bottom: 0;
        }

        .product-card {
            background: white;
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .product-header {
            background: <?= $theme_color ?>;
            color: white;
            padding: 1.5rem;
        }

        .product-header h4 {
            margin: 0;
            font-weight: 600;
            font-size: 1.25rem;
        }

        .product-body {
            padding: 2rem;
        }

        .info-group {
            margin-bottom: 1.5rem;
        }

        .info-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }

        .info-label i {
            margin-right: 0.5rem;
            color: <?= $theme_color ?>;
        }

        .info-value {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 0.95rem;
            color: #495057;
        }

        .info-value.large {
            font-size: 1.1rem;
            font-weight: 600;
            color: <?= $theme_color ?>;
        }

        .info-value.description {
            min-height: 80px;
            line-height: 1.6;
        }

        .badge-custom {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .badge-brand {
            background-color: #0dcaf0;
            color: white;
        }

        /* Info */
        .badge-type {
            background-color: <?= $btn_edit_color ?>;
            color: #000;
        }

        /* Warning */
        .badge-price {
            background-color: <?= $status_on_color ?>;
            color: white;
            font-size: 1.2rem;
            padding: 0.75rem 1.5rem;
        }

        .action-buttons {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 0.75rem;
            justify-content: center;
        }

        .btn {
            border-radius: 8px;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            filter: brightness(90%);
            transform: translateY(-2px);
        }

        .btn-warning {
            background-color: <?= $btn_edit_color ?>;
            border: none;
            color: #000 !important;
        }

        .btn-secondary {
            background: #6c757d;
            border: none;
            color: white;
        }

        .product-id {
            background: <?= $theme_color ?>20;
            /* 20% opacity */
            color: <?= $theme_color ?>;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.6s ease forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="main-header">
                    <div class="container">
                        <div class="row">
                            <div class="col-12">
                                <h1 class="text-light">
                                    <i class="bi bi-eye-fill me-3"></i>
                                    รายละเอียดสินค้า
                                </h1>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="container">
                    <div class="card product-card fade-in">
                        <div class="product-header">
                            <h4 class="text-light">
                                <i class="bi bi-phone-fill me-2"></i>
                                <?php echo htmlspecialchars($product['prod_name']); ?>
                            </h4>
                        </div>

                        <div class="product-body ">
                            <div class="product-id ">
                                <i class="bi bi-upc-scan me-2"></i>
                                รหัสสินค้า: #<?php echo $product['prod_id']; ?>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-group">
                                        <div class="info-label">
                                            <i class="bi bi-tag-fill"></i>
                                            ชื่อสินค้า
                                        </div>
                                        <div class="info-value large border-secondary">
                                            <?php echo htmlspecialchars($product['prod_name']); ?>
                                        </div>
                                    </div>

                                    <div class="info-group">
                                        <div class="info-label">
                                            <i class="bi bi-building"></i>
                                            ยี่ห้อ
                                        </div>
                                        <div class="info-value border-secondary">
                                            <span class="badge badge-custom badge-brand ">
                                                <?php echo htmlspecialchars($product['brand_name'] ?? 'ไม่ระบุ'); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="info-group">
                                        <div class="info-label">
                                            <i class="bi bi-diagram-3-fill"></i>
                                            ประเภทสินค้า
                                        </div>
                                        <div class="info-value border-secondary">
                                            <span class="badge badge-custom badge-type">
                                                <?php echo htmlspecialchars($product['type_name'] ?? 'ไม่ระบุ'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="info-group">
                                        <div class="info-label">
                                            <i class="bi bi-upc"></i>
                                            ชื่อรุ่น
                                        </div>
                                        <div class="info-value border-secondary">
                                            <?php echo htmlspecialchars($product['model_name']); ?>
                                        </div>
                                    </div>

                                    <div class="info-group">
                                        <div class="info-label">
                                            <i class="bi bi-qr-code"></i>
                                            รหัสรุ่น
                                        </div>
                                        <div class="info-value border-secondary">
                                            <code><?php echo htmlspecialchars($product['model_no']); ?></code>
                                        </div>
                                    </div>

                                    <div class="info-group">
                                        <div class="info-label border-secondary">
                                            <i class="bi bi-cash-coin"></i>
                                            ราคา
                                        </div>
                                        <div class="info-value border-secondary">
                                            <span class="badge badge-custom badge-price">
                                                ฿<?php echo number_format($product['prod_price'], 2); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="info-group">
                                <div class="info-label">
                                    <i class="bi bi-file-text-fill"></i>
                                    คำอธิบาย
                                </div>
                                <div class="info-value description border-secondary">
                                    <?php
                                    // (18) แก้คอลัมน์ prod_desc
                                    if (!empty($product['prod_desc'])) {
                                        echo nl2br(htmlspecialchars($product['prod_desc']));
                                    } else {
                                        echo '<span class="text-muted">ไม่มีคำอธิบาย</span>';
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <a href="product.php" class="btn btn-secondary">
                                    <i class="bi bi-list-task me-2"></i>
                                    กลับไปยังรายการ
                                </a>
                                <a href="edit_product.php?id=<?php echo $product['prod_id']; ?>" class="btn btn-warning text-dark">
                                    <i class="bi bi-pencil-fill me-2"></i>
                                    แก้ไขสินค้า
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // เพิ่ม animation
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach((el, index) => {
                el.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>

</html>