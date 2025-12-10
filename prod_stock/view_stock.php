<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'view_stock');

$stock_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$stock_id) {
    $_SESSION['error'] = 'ไม่พบรหัสสต็อกที่ต้องการดู';
    header('Location: prod_stock.php');
    exit;
}

$stock_sql = "SELECT 
                ps.stock_id,
                ps.serial_no,
                ps.price as stock_price,
                ps.stock_status,
                ps.image_path,
                ps.create_at as date_in,
                p.prod_id,
                p.prod_name,
                p.model_name,
                p.model_no,
                p.prod_price as original_price,
                pb.brand_name_th as brand_name,
                pt.type_name_th as type_name,
                sm.ref_table as entry_type,
                sm.ref_id as entry_ref_id,
                bh.bill_id AS receipt_id,
                bh.bill_date as sale_date,
                bd.warranty_duration_months,
                bd.warranty_note

            FROM prod_stocks ps
            LEFT JOIN products p ON ps.products_prod_id = p.prod_id
            LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id
            LEFT JOIN prod_types pt ON p.prod_types_type_id = pt.type_id
            
            LEFT JOIN stock_movements sm ON sm.prod_stocks_stock_id = ps.stock_id AND sm.movement_type = 'IN'
            
            LEFT JOIN bill_details bd ON bd.prod_stocks_stock_id = ps.stock_id
            LEFT JOIN bill_headers bh ON bh.bill_id = bd.bill_headers_bill_id

            WHERE ps.stock_id = $stock_id
            LIMIT 1"; 

$stock_result = mysqli_query($conn, $stock_sql);
$stock_data = mysqli_fetch_assoc($stock_result);

if (!$stock_data) {
    $_SESSION['error'] = 'ไม่พบข้อมูลสต็อกที่ต้องการดู';
    header('Location: prod_stock.php');
    exit;
}

// ดึงข้อมูล Supplier 
$supplier_name = null;
if ($stock_data['entry_type'] == 'order_details' && !empty($stock_data['entry_ref_id'])) {
    $order_detail_id = $stock_data['entry_ref_id'];
    $supplier_sql = "SELECT s.co_name 
                     FROM suppliers s
                     LEFT JOIN purchase_orders po ON s.supplier_id = po.suppliers_supplier_id
                     LEFT JOIN order_details od ON po.purchase_id = od.purchase_orders_purchase_id
                     WHERE od.order_id = $order_detail_id";
    $supplier_result = mysqli_query($conn, $supplier_sql);
    if ($supplier_data = mysqli_fetch_assoc($supplier_result)) {
        $supplier_name = $supplier_data['co_name'];
    }
}

// ตรวจสอบสถานะการรับประกัน (ถ้าขายแล้ว)
$warranty_status = 'pending'; 
$warranty_display = 'รอการขาย (ยังไม่เริ่ม)';
$warranty_days_left = 0;

if ($stock_data['stock_status'] === 'Sold' && !empty($stock_data['sale_date'])) {
    $duration = (int)$stock_data['warranty_duration_months'];

    if ($duration > 0) {
        $start_date = strtotime($stock_data['sale_date']);
        $end_date_str = date('Y-m-d H:i:s', strtotime("+$duration months", $start_date));
        $end_date = strtotime($end_date_str);
        $today = time();

        $warranty_days_left = ceil(($end_date - $today) / (60 * 60 * 24));

        if ($end_date > $today) {
            $warranty_status = 'active';
            $warranty_display = 'ยังอยู่ในประกัน';
        } else {
            $warranty_status = 'expired';
            $warranty_display = 'หมดประกันแล้ว';
        }
    } else {
        $warranty_status = 'none';
        $warranty_display = 'ไม่มีการรับประกัน';
    }
}

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดสต็อก #<?= $stock_data['stock_id'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <?php require '../config/load_theme.php'; ?>

    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            color: <?= $text_color ?>;
        }

        .main-header {
            background-color: <?= $theme_color ?>;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .main-header h1 {
            font-weight: 700;
            margin-bottom: 0;
        }

        .info-card {
            background: #fff;
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            overflow: hidden;
            height: 100%;
        }

        .card-header {
            background-color: <?= $header_bg_color ?>;
            color: <?= $header_text_color ?>;
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

        .status-in-stock {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .status-sold {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-damage {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-reserved {
            background-color: #d1edff;
            color: #0c63e4;
        }

        .status-repair {
            background-color: #e2d9f3;
            color: #49287f;
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
            font-weight: 700;
            color: <?= $theme_color ?>;
            font-size: 1.1rem;
            word-break: break-all;
            cursor: pointer;
        }

        .btn-success {
            background-color: <?= $btn_add_color ?>;
            border-color: <?= $btn_add_color ?>;
            color: white;
        }

        .btn-warning {
            background-color: <?= $btn_edit_color ?>;
            color: white;
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .product-image {
            width: 100%;
            max-width: 400px;
            height: auto;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .product-image:hover {
            transform: scale(1.02);
        }

        .no-image {
            height: 250px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            border: 2px dashed #dee2e6;
        }

        .barcode-canvas {
            max-width: 100%;
            height: auto;
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
                        <h1 class="text-light">
                            <i class="fas fa-eye me-3"></i>
                            รายละเอียดสต็อก
                            <small class="fs-6 opacity-75 d-block">รหัสสต็อก: <?= htmlspecialchars($stock_data['stock_id']) ?></small>
                        </h1>
                    </div>
                </div>

                <div class="container">

                    <div class="row">
                        <div class="col-lg-5 mb-4">
                            <div class="info-card">
                                <div class="card-header">
                                    <h5 class="mb-0 text-light"><i class="fas fa-camera me-2"></i>รูปภาพ</h5>
                                </div>
                                <div class="card-body text-center">
                                    <?php if (!empty($stock_data['image_path'])): ?>
                                        <img src="../uploads/products/<?= htmlspecialchars($stock_data['image_path']) ?>"
                                            alt="Product Image" class="product-image"
                                            onclick="showImageModal('<?= htmlspecialchars($stock_data['image_path']) ?>')">
                                    <?php else: ?>
                                        <div class="no-image">
                                            <div class="text-center">
                                                <i class="fas fa-image fa-3x mb-2"></i>
                                                <p class="mb-0">ไม่มีรูปภาพ</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7 mb-4">
                            <div class="info-card">
                                <div class="card-header">
                                    <h5 class="mb-0 text-light"><i class="fas fa-info-circle me-2"></i>ข้อมูลพื้นฐาน</h5>
                                </div>
                                <div class="card-body">
                                    <div class="info-item">
                                        <div class="info-label">Serial Number (S/N)</div>
                                        <div class="info-value highlight-value" title="คลิกเพื่อคัดลอก"
                                            onclick="copyToClipboard('<?= htmlspecialchars($stock_data['serial_no']) ?>')">
                                            <?= htmlspecialchars($stock_data['serial_no']) ?>
                                        </div>
                                    </div>

                                    <div class="info-item">
                                        <div class="info-label">สถานะ</div>
                                        <div class="info-value">
                                            <?php
                                            $status_class = '';
                                            $status_text = htmlspecialchars($stock_data['stock_status']);
                                            switch ($stock_data['stock_status']) {
                                                case 'In Stock':
                                                    $status_class = 'status-in-stock';
                                                    break;
                                                case 'Sold':
                                                    $status_class = 'status-sold';
                                                    break;
                                                case 'Damage':
                                                    $status_class = 'status-damage';
                                                    break;
                                                case 'Reserved':
                                                    $status_class = 'status-reserved';
                                                    break;
                                                case 'Repair':
                                                    $status_class = 'status-repair';
                                                    break;
                                                default:
                                                    $status_class = 'bg-secondary text-white';
                                            }
                                            ?>
                                            <span class="status-badge <?= $status_class ?>">
                                                <?= $status_text ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="info-item">
                                        <div class="info-label">ราคาขาย</div>
                                        <div class="info-value highlight-value">฿<?= number_format($stock_data['stock_price'], 2) ?></div>
                                    </div>

                                    <div class="info-item">
                                        <div class="info-label">วันที่เข้าสต็อก</div>
                                        <div class="info-value"><?= date('d/m/Y H:i', strtotime($stock_data['date_in'])) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="info-card">
                                <div class="card-header">
                                    <h5 class="mb-0 text-light"><i class="fas fa-box me-2"></i>ข้อมูลสินค้า</h5>
                                </div>
                                <div class="card-body">
                                    <div class="info-item">
                                        <div class="info-label">ชื่อสินค้า</div>
                                        <div class="info-value highlight-value"><?= htmlspecialchars($stock_data['prod_name']) ?></div>
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
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="info-card">
                                <div class="card-header">
                                    <h5 class="mb-0 text-light"><i class="fas fa-history me-2"></i>ประวัติ</h5>
                                </div>
                                <div class="card-body">
                                    <div class="info-item">
                                        <div class="info-label">ที่มา (การรับเข้า)</div>
                                        <div class="info-value">
                                            <?php
                                            if ($stock_data['entry_type'] == 'order_details') {
                                                echo "รับจาก PO (Supplier: " . htmlspecialchars($supplier_name ?: 'N/A') . ")";
                                            } else if (!empty($stock_data['entry_type'])) {
                                                echo "กรณีพิเศษ (" . htmlspecialchars($stock_data['entry_type']) . ")";
                                            } else {
                                                echo "ไม่ระบุ";
                                            }
                                            ?>
                                        </div>
                                    </div>

                                    <?php if ($stock_data['stock_status'] === 'Sold'): ?>
                                        <div class="info-item">
                                            <div class="info-label">วันที่ขาย</div>
                                            <div class="info-value"><?= date('d/m/Y H:i', strtotime($stock_data['sale_date'])) ?></div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">ใบเสร็จ</div>
                                            <div class="info-value">
                                                <a href="../sales/view_sale.php?id=<?= $stock_data['receipt_id'] ?>" class="text-decoration-none">
                                                    <i class="fas fa-receipt me-1"></i> ดูบิล #<?= $stock_data['receipt_id'] ?>
                                                </a>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="info-item">
                                            <div class="info-label">การขาย</div>
                                            <div class="info-value">ยังไม่ถูกขาย</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="info-card">
                                <div class="card-header">
                                    <h5 class="mb-0 text-light"><i class="fas fa-shield-alt me-2"></i>การรับประกัน (ตอนขาย)</h5>
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
                                        <div class="info-label">ระยะเวลา (ที่ตกลงตอนขาย)</div>
                                        <div class="info-value"><?= (int)$stock_data['warranty_duration_months'] ?> เดือน</div>
                                    </div>

                                    <?php if ($warranty_status == 'active' || $warranty_status == 'expired'): ?>
                                        <div class="info-item">
                                            <div class="info-label">วันที่เริ่มประกัน</div>
                                            <div class="info-value"><?= date('d/m/Y', strtotime($stock_data['sale_date'])) ?></div>
                                        </div>

                                        <div class="info-item">
                                            <div class="info-label">วันที่หมดประกัน</div>
                                            <div class="info-value"><?= date('d/m/Y', strtotime("+$duration months", strtotime($stock_data['sale_date']))) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($warranty_status == 'active'): ?>
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
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12 mb-4">
                            <div class="info-card">
                                <div class="card-header">
                                    <h5 class="mb-0 text-light"><i class="fas fa-barcode me-2"></i>บาร์โค้ด / Serial</h5>
                                </div>
                                <div class="card-body text-center">
                                    <canvas id="barcodeCanvas"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mb-4">
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
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // สร้างบาร์โค้ด (ใช้ Serial No)
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('barcodeCanvas');
            const barcodeValue = '<?= htmlspecialchars($stock_data['serial_no']) ?>';

            if (canvas && barcodeValue) {
                try {
                    JsBarcode(canvas, barcodeValue, {
                        format: "CODE128",
                        width: 2.5,
                        height: 100,
                        displayValue: true,
                        fontSize: 18,
                        margin: 10
                    });
                } catch (error) {
                    console.error('Error generating barcode:', error);
                }
            }
        });

        // แสดง modal รูปภาพ
        function showImageModal(imageName) {
            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            const modalImage = document.getElementById('modalImage');
            modalImage.src = '../uploads/products/' + imageName;
            modal.show();
        }

        // Copy text
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('คัดลอกแล้ว: ' + text);
            });
        }
    </script>
</body>

</html>