<?php
session_start();
require '../config/config.php';

// ดึงข้อมูลสต็อก
$stocks = [];
$stock_ids = $_GET['stock_ids'] ?? '';

if (!empty($stock_ids)) {
    // แปลง string เป็น array
    $stock_ids_array = explode(',', $stock_ids);
    $stock_ids_escaped = array_map(function($id) use ($conn) {
        return mysqli_real_escape_string($conn, trim($id));
    }, $stock_ids_array);
    
    // สร้าง WHERE clause
    $where_clause = "ps.id IN (" . implode(',', $stock_ids_escaped) . ")";
    
    // SQL Query ดึงข้อมูลสต็อกพร้อมรายละเอียดสินค้า
    $sql = "SELECT 
                ps.id as stock_id,
                ps.imei,
                ps.barcode,
                ps.price as stock_price,
                ps.date_in,
                p.name as product_name,
                p.model_name,
                p.price as original_price,
                pb.name_th as brand_name,
                pt.name_th as type_name,
                pw.description as warranty_description,
                pw.total_warranty,
                s.sp_name as supplier_name
            FROM prod_stocks ps
            LEFT JOIN products p ON ps.products_id = p.id
            LEFT JOIN prod_brands pb ON p.prod_brands_id = pb.id
            LEFT JOIN prod_types pt ON p.prod_types_id = pt.id
            LEFT JOIN prod_warranty pw ON ps.prod_warranty_id = pw.id
            LEFT JOIN suppliers s ON ps.supplier_id = s.id
            WHERE $where_clause
            ORDER BY ps.id";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $stocks[] = [
                'stock_id' => str_pad($row['stock_id'], 6, '0', STR_PAD_LEFT),
                'barcode' => $row['barcode'],
                'imei' => $row['imei'],
                'product_name' => $row['product_name'],
                'brand' => $row['brand_name'],
                'model' => $row['model_name'],
                'type' => $row['type_name'],
                'price' => floatval($row['stock_price'] ?: $row['original_price']),
                'date_in' => $row['date_in'],
                'warranty_months' => $row['total_warranty'],
                'warranty_description' => $row['warranty_description'],
                'supplier' => $row['supplier_name']
            ];
        }
    }
}

$total_items = count($stocks);
$stock_range = $total_items > 1 
    ? $stocks[0]['stock_id'] . ' - ' . $stocks[$total_items-1]['stock_id']
    : ($total_items > 0 ? $stocks[0]['stock_id'] : '');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>พิมพ์บาร์โค้ด</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        * {
            font-family: 'Prompt', sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
            font-size: 14px;
        }
        
        .print-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin: 20px auto;
            max-width: 1200px;
        }
        
        .barcode-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin: 20px 0;
        }
        
        .barcode-item {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 8px;
            text-align: center;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .barcode-canvas {
            margin: 5px 0;
            border: 1px solid #eee;
            border-radius: 3px;
        }
        
        .stock-id {
            font-weight: bold;
            color: #198754;
            font-size: 12px;
        }
        
        .product-name-info {
            font-size: 10px;
            font-weight: bold;
            color: #333;
            margin-bottom: 3px;
            line-height: 1.2;
        }
        
        .product-brand-model {
            font-size: 9px;
            color: #666;
            margin-bottom: 3px;
            line-height: 1.1;
        }
        
        .imei-info {
            font-size: 9px;
            color: #666;
            margin-top: 3px;
        }
        
        .price-info {
            font-size: 11px;
            color: #dc3545;
            font-weight: 600;
            margin-top: 3px;
        }
        
        .barcode-number {
            font-size: 8px;
            color: #333;
            margin: 2px 0;
        }
        
        .btn-print {
            background: linear-gradient(135deg, #198754 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .btn-print:hover {
            background: linear-gradient(135deg, #157347 0%, #1aa179 100%);
            color: white;
        }
        
        .controls-panel {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .size-selector {
            margin: 10px 0;
        }
        
        .preview-info {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        
        /* Print Styles - ปรับปรุงการพิมพ์ */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                height: 100% !important;
                background: white !important;
                font-size: 10px !important;
                overflow: visible !important;
            }
            
            /* ซ่อนทุกอย่างที่ไม่ต้องการเมื่อพิมพ์ */
            .no-print, 
            .controls-panel, 
            .summary-info, 
            .preview-info, 
            .header-section,
            .alert-no-data {
                display: none !important;
                visibility: hidden !important;
            }
            
            .print-container {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
                max-width: none !important;
                width: 100% !important;
                height: auto !important;
                background: white !important;
                border-radius: 0 !important;
            }
            
            .barcode-grid {
                display: grid !important;
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 3mm !important;
                margin: 0 !important;
                padding: 5mm !important;
                width: calc(100% - 10mm) !important;
                height: auto !important;
                background: white !important;
                box-sizing: border-box !important;
            }
            
            .barcode-item {
                border: 1px solid #000 !important;
                box-shadow: none !important;
                page-break-inside: avoid !important;
                margin: 0 !important;
                padding: 2mm !important;
                width: auto !important;
                height: auto !important;
                background: white !important;
                border-radius: 2px !important;
                box-sizing: border-box !important;
                overflow: visible !important;
            }
            
            /* ขนาดบาร์โค้ดสำหรับการพิมพ์ */
            .barcode-canvas {
                width: 35mm !important;
                height: 15mm !important;
                margin: 1mm auto !important;
                display: block !important;
                border: none !important;
            }
            
            .stock-id {
                font-size: 9px !important;
                margin-bottom: 1mm !important;
                color: #000 !important;
                font-weight: bold !important;
            }
            
            .product-name-info {
                font-size: 8px !important;
                margin-bottom: 1mm !important;
                color: #000 !important;
                line-height: 1.1 !important;
            }
            
            .product-brand-model {
                font-size: 7px !important;
                margin-bottom: 1mm !important;
                color: #000 !important;
                line-height: 1.1 !important;
            }
            
            .imei-info {
                font-size: 7px !important;
                margin-top: 1mm !important;
                color: #000 !important;
            }
            
            .price-info {
                font-size: 8px !important;
                margin-top: 1mm !important;
                color: #000 !important;
                font-weight: bold !important;
            }
            
            .barcode-number {
                font-size: 7px !important;
                margin: 1mm 0 !important;
                color: #000 !important;
            }
            
            /* จัดการการแบ่งหน้า */
            @page {
                size: A4 !important;
                margin: 5mm !important;
            }
            
            /* แบ่งหน้าทุก 24 รายการ (6 แถว × 4 คอลัมน์) */
            .barcode-item:nth-child(24n+1) {
                page-break-before: always !important;
            }
            
            .barcode-item:first-child {
                page-break-before: auto !important;
            }
        }
        
        /* ขนาดบาร์โค้ดสำหรับหน้าจอ */
        .barcode-small .barcode-canvas { width: 120px; height: 40px; }
        .barcode-medium .barcode-canvas { width: 140px; height: 50px; }
        .barcode-large .barcode-canvas { width: 160px; height: 60px; }
        
        .header-section {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #198754;
        }
        
        .summary-info {
            background: linear-gradient(135deg, #198754 0%, #20c997 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .alert-no-data {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .product-list-container {
            max-height: 150px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            background: white;
        }

        .product-list-item {
            padding: 5px 8px;
            margin-bottom: 5px;
            background: #f8f9fa;
            border-radius: 4px;
            border-left: 3px solid #198754;
        }

        .product-list-item:last-child {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="print-container">
        <!-- Header Section -->
        <div class="header-section no-print">
            <h3><i class="fas fa-print me-2"></i>พิมพ์บาร์โค้ดสินค้า</h3>
            <p class="text-muted">รายการบาร์โค้ดสำหรับพิมพ์</p>
        </div>

        <?php if (empty($stock_ids) || empty($stocks)): ?>
        <!-- No Data Alert -->
        <div class="alert-no-data no-print">
            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
            <h5>ไม่พบข้อมูลสำหรับพิมพ์</h5>
            <p>กรุณาเพิ่มสินค้าเข้าสต็อกก่อนหรือตรวจสอบ URL Parameter</p>
            <a href="add_prodStock.php" class="btn btn-success">
                <i class="fas fa-plus me-2"></i>เพิ่มสินค้าเข้าสต็อก
            </a>
        </div>
        <?php else: ?>

        <!-- Controls Panel -->
        <div class="controls-panel no-print">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-cog me-2"></i>การตั้งค่าการพิมพ์</h5>
                            
                            <!-- ข้อมูลสินค้า -->
                            <div class="product-summary mb-3 p-3" style="background: #e8f5e8; border-radius: 8px; border-left: 4px solid #198754;">
                                <div class="row">
                                    <div class="col-12">
                                        <strong><i class="fas fa-box me-2"></i>ข้อมูลสินค้า:</strong>
                                    </div>
                                    <?php if ($total_items == 1): ?>
                                        <div class="col-12 mt-1">
                                            <span class="text-primary fw-bold"><?= htmlspecialchars($stocks[0]['product_name']) ?></span>
                                        </div>
                                        <div class="col-6 mt-1">
                                            <small><strong>ยี่ห้อ:</strong> <?= htmlspecialchars($stocks[0]['brand']) ?></small>
                                        </div>
                                        <div class="col-6 mt-1">
                                            <small><strong>รุ่น:</strong> <?= htmlspecialchars($stocks[0]['model']) ?></small>
                                        </div>
                                    <?php else: ?>
                                        <div class="col-12 mt-1">
                                            <span class="text-primary fw-bold">สินค้าหลายรายการ (<?= $total_items ?> ชิ้น)</span>
                                        </div>
                                        <div class="col-12 mt-2">
                                            <div class="product-list-container">
                                                <?php 
                                                $grouped_products = [];
                                                foreach ($stocks as $stock) {
                                                    $key = $stock['product_name'] . '|' . $stock['brand'] . '|' . $stock['model'];
                                                    if (!isset($grouped_products[$key])) {
                                                        $grouped_products[$key] = [
                                                            'product' => $stock,
                                                            'stock_ids' => []
                                                        ];
                                                    }
                                                    $grouped_products[$key]['stock_ids'][] = $stock['stock_id'];
                                                }
                                                
                                                foreach ($grouped_products as $key => $group): 
                                                    $count = count($group['stock_ids']);
                                                    $stock_list = implode(', ', $group['stock_ids']);
                                                ?>
                                                    <div class="product-list-item">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div class="flex-grow-1">
                                                                <strong style="font-size: 13px; color: #198754;">
                                                                    <?= htmlspecialchars($group['product']['product_name']) ?>
                                                                </strong>
                                                                <br>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-building me-1"></i><?= htmlspecialchars($group['product']['brand']) ?> - 
                                                                    <i class="fas fa-cog me-1"></i><?= htmlspecialchars($group['product']['model']) ?>
                                                                </small>
                                                                <br>
                                                                <small class="text-primary">
                                                                    <i class="fas fa-tag me-1"></i>รหัสสต็อก: <?= $stock_list ?>
                                                                </small>
                                                            </div>
                                                            <span class="badge bg-success"><?= $count ?> ชิ้น</span>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="size-selector">
                                <label class="form-label">ขนาดบาร์โค้ด:</label>
                                <select id="barcodeSize" class="form-select" style="width: 200px;" onchange="changeBarcodeSize()">
                                    <option value="small">เล็ก (120×40px)</option>
                                    <option value="medium" selected>กลาง (140×50px)</option>
                                    <option value="large">ใหญ่ (160×60px)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mt-4">
                                <label class="form-check-label me-3">
                                    <input type="checkbox" class="form-check-input" id="showPrice" checked onchange="togglePrice()">
                                    แสดงราคา
                                </label>
                                <br>
                                <label class="form-check-label">
                                    <input type="checkbox" class="form-check-input" id="showIMEI" checked onchange="toggleIMEI()">
                                    แสดง IMEI
                                </label>
                                <br>
                                <label class="form-check-label">
                                    <input type="checkbox" class="form-check-input" id="showStockId" checked onchange="toggleStockId()">
                                    แสดงรหัสสต็อก
                                </label>
                                <br>
                                <label class="form-check-label">
                                    <input type="checkbox" class="form-check-input" id="showProductName" checked onchange="toggleProductName()">
                                    แสดงชื่อสินค้า
                                </label>
                            </div>
                            
                            <!-- แสดงจำนวนบาร์โค้ดต่อหน้า -->
                            <div class="mt-3 p-2" style="background: #fff3cd; border-radius: 6px;">
                                <small><i class="fas fa-info-circle me-1"></i>
                                <strong>การจัดเรียง:</strong> <span id="layoutInfo">4×6 = 24 ชิ้น/หน้า</span></small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 text-end">
                    <button class="btn btn-print btn-lg" onclick="printBarcodes()">
                        <i class="fas fa-print me-2"></i>พิมพ์บาร์โค้ด
                    </button>
                    <br><br>
                    <a href="add_prodStock.php" class="btn btn-outline-success me-2">
                        <i class="fas fa-plus me-2"></i>เพิ่มสินค้าใหม่
                    </a>
                    <a href="prod_stock.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>ย้อนกลับ
                    </a>
                </div>
            </div>
        </div>

        <!-- Summary Info -->
        <div class="summary-info no-print">
            <div class="row">
                <div class="col-md-3">
                    <i class="fas fa-boxes me-2"></i>
                    <strong>จำนวนรวม:</strong> <?= $total_items ?> ชิ้น
                </div>
                <div class="col-md-3">
                    <i class="fas fa-tag me-2"></i>
                    <strong>รหัสสต็อก:</strong> <?= $stock_range ?>
                </div>
                <div class="col-md-3">
                    <i class="fas fa-calendar me-2"></i>
                    <strong>วันที่พิมพ์:</strong> <?= date('d/m/Y') ?>
                </div>
                <div class="col-md-3">
                    <i class="fas fa-clock me-2"></i>
                    <strong>เวลา:</strong> <?= date('H:i:s') ?>
                </div>
            </div>
        </div>

        <!-- Preview Info -->
        <div class="preview-info no-print">
            <i class="fas fa-info-circle me-2"></i>
            <strong>คำแนะนำ:</strong> ตรวจสอบการตั้งค่าเครื่องพิมพ์ให้เหมาะสมก่อนพิมพ์ 
            แนะนำให้ใช้กระดาษขนาด A4 และตั้งค่า Margins เป็น Minimum
        </div>

        <!-- Barcode Grid - เฉพาะส่วนนี้ที่จะพิมพ์ -->
        <div class="barcode-grid barcode-medium" id="barcodeGrid">
            <?php foreach ($stocks as $index => $stock): ?>
            <div class="barcode-item">
                <div class="stock-id stock-id-info">รหัส: <?= htmlspecialchars($stock['stock_id']) ?></div>
                <div class="product-name-info product-name-display">
                    <?= htmlspecialchars($stock['product_name']) ?>
                </div>
                <div class="product-brand-model product-brand-display">
                    <?= htmlspecialchars($stock['brand']) ?> - <?= htmlspecialchars($stock['model']) ?>
                </div>
                <canvas class="barcode-canvas" id="barcode-<?= $index ?>"></canvas>
                <div class="barcode-number"><?= htmlspecialchars($stock['barcode']) ?></div>
                <div class="imei-info">IMEI: <?= htmlspecialchars($stock['imei']) ?></div>
                <div class="price-info">฿<?= number_format($stock['price'], 2) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>

    <!-- JsBarcode Library -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ข้อมูลสินค้าจาก PHP
        const stockData = <?= json_encode($stocks) ?>;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            generateBarcodes();
            updateLayoutInfo();
        });

        // สร้างบาร์โค้ด
        function generateBarcodes() {
            stockData.forEach((item, index) => {
                const canvas = document.getElementById(`barcode-${index}`);
                if (canvas && item.barcode) {
                    try {
                        JsBarcode(canvas, item.barcode, {
                            format: "CODE128", 
                            width: 2,
                            height: 50,
                            displayValue: false,
                            margin: 5,
                            background: "#ffffff",
                            lineColor: "#000000"
                        });
                    } catch (error) {
                        console.error('Error generating barcode:', error);
                        canvas.getContext('2d').fillText('Error', 10, 30);
                    }
                }
            });
        }

        // เปลี่ยนขนาดบาร์โค้ด
        function changeBarcodeSize() {
            const size = document.getElementById('barcodeSize').value;
            const grid = document.getElementById('barcodeGrid');
            
            // ลบ class เดิม
            grid.classList.remove('barcode-small', 'barcode-medium', 'barcode-large');
            
            // เพิ่ม class ใหม่
            grid.classList.add(`barcode-${size}`);
            
            // อัปเดตข้อมูลการจัดเรียง
            updateLayoutInfo();
            
            // ปรับ grid columns ตามขนาด
            switch(size) {
                case 'small':
                    grid.style.gridTemplateColumns = 'repeat(5, 1fr)';
                    break;
                case 'medium':
                    grid.style.gridTemplateColumns = 'repeat(4, 1fr)';
                    break;
                case 'large':
                    grid.style.gridTemplateColumns = 'repeat(4, 1fr)';
                    break;
            }
            
            // สร้างบาร์โค้ดใหม่ด้วยขนาดที่เปลี่ยน
            setTimeout(() => {
                generateBarcodes();
            }, 100);
        }

        // แสดง/ซ่อนราคา
        function togglePrice() {
            const showPrice = document.getElementById('showPrice').checked;
            const priceElements = document.querySelectorAll('.price-info');
            
            priceElements.forEach(el => {
                el.style.display = showPrice ? 'block' : 'none';
            });
        }

        // แสดง/ซ่อน IMEI
        function toggleIMEI() {
            const showIMEI = document.getElementById('showIMEI').checked;
            const imeiElements = document.querySelectorAll('.imei-info');
            
            imeiElements.forEach(el => {
                el.style.display = showIMEI ? 'block' : 'none';
            });
        }

        // แสดง/ซ่อนรหัสสต็อก
        function toggleStockId() {
            const showStockId = document.getElementById('showStockId').checked;
            const stockIdElements = document.querySelectorAll('.stock-id-info');
            
            stockIdElements.forEach(el => {
                el.style.display = showStockId ? 'block' : 'none';
            });
        }

        // แสดง/ซ่อนชื่อสินค้า
        function toggleProductName() {
            const showProductName = document.getElementById('showProductName').checked;
            const productNameElements = document.querySelectorAll('.product-name-display');
            const productBrandElements = document.querySelectorAll('.product-brand-display');
            
            productNameElements.forEach(el => {
                el.style.display = showProductName ? 'block' : 'none';
            });
            
            productBrandElements.forEach(el => {
                el.style.display = showProductName ? 'block' : 'none';
            });
        }

        // อัปเดตข้อมูลการจัดเรียง
        function updateLayoutInfo() {
            const size = document.getElementById('barcodeSize').value;
            const layoutInfo = document.getElementById('layoutInfo');
            
            let layout = '';
            switch(size) {
                case 'small':
                    layout = '5×6 = 30 ชิ้น/หน้า';
                    break;
                case 'medium':
                    layout = '4×6 = 24 ชิ้น/หน้า';
                    break;
                case 'large':
                    layout = '4×5 = 20 ชิ้น/หน้า';
                    break;
            }
            
            if (layoutInfo) {
                layoutInfo.textContent = layout;
            }
        }

        // ฟังก์ชันพิมพ์ที่ปรับปรุงแล้ว - ลบหน้าเปล่า
        function printBarcodes() {
            if (stockData.length === 0) {
                alert('ไม่มีข้อมูลสำหรับพิมพ์');
                return;
            }
            
            // เพิ่ม CSS พิเศษสำหรับการพิมพ์
            const printCSS = `
                <style>
                    @media print {
                        * {
                            -webkit-print-color-adjust: exact !important;
                            color-adjust: exact !important;
                            print-color-adjust: exact !important;
                        }
                        
                        html, body {
                            margin: 0 !important;
                            padding: 0 !important;
                            width: 100% !important;
                            height: 100% !important;
                            background: white !important;
                            font-size: 10px !important;
                            overflow: visible !important;
                        }
                        
                        @page {
                            size: A4 !important;
                            margin: 5mm !important;
                        }
                        
                        .barcode-grid {
                            display: grid !important;
                            grid-template-columns: repeat(4, 1fr) !important;
                            gap: 3mm !important;
                            margin: 0 !important;
                            padding: 0 !important;
                            width: 100% !important;
                            height: auto !important;
                            background: white !important;
                        }
                        
                        .barcode-item {
                            border: 1px solid #000 !important;
                            page-break-inside: avoid !important;
                            margin: 0 !important;
                            padding: 2mm !important;
                            background: white !important;
                            box-sizing: border-box !important;
                        }
                        
                        .barcode-canvas {
                            width: 35mm !important;
                            height: 15mm !important;
                            margin: 1mm auto !important;
                            display: block !important;
                        }
                        
                        /* แบ่งหน้าทุก 24 รายการ */
                        .barcode-item:nth-child(24n+1):not(:first-child) {
                            page-break-before: always !important;
                        }
                    }
                </style>
            `;
            
            // เพิ่ม CSS ไปใน head
            const styleElement = document.createElement('style');
            styleElement.innerHTML = printCSS.replace('<style>', '').replace('</style>', '');
            document.head.appendChild(styleElement);
            
            // พิมพ์
            setTimeout(() => {
                window.print();
                
                // ลบ CSS หลังพิมพ์
                setTimeout(() => {
                    document.head.removeChild(styleElement);
                }, 1000);
            }, 100);
        }

        // ฟังก์ชันสำหรับ responsive
        function adjustForMobile() {
            if (window.innerWidth < 768) {
                const grid = document.getElementById('barcodeGrid');
                if (grid) {
                    grid.style.gridTemplateColumns = 'repeat(2, 1fr)';
                }
            }
        }

        // Event listeners
        window.addEventListener('resize', adjustForMobile);
        
        // เหตุการณ์ก่อนและหลังพิมพ์
        window.addEventListener('beforeprint', function(e) {
            console.log('กำลังเตรียมพิมพ์...');
            
            // ซ่อนทุกอย่างยกเว้นบาร์โค้ด
            document.querySelectorAll('.no-print, .controls-panel, .summary-info, .preview-info, .header-section').forEach(el => {
                el.style.display = 'none';
            });
            
            // แสดงเฉพาะ barcode grid
            const barcodeGrid = document.getElementById('barcodeGrid');
            if (barcodeGrid) {
                barcodeGrid.style.display = 'grid';
                barcodeGrid.style.position = 'static';
                barcodeGrid.style.margin = '0';
                barcodeGrid.style.padding = '5mm';
            }
        });

        window.addEventListener('afterprint', function(e) {
            console.log('พิมพ์เสร็จสิ้น');
            
            // คืนค่าการแสดงผล
            document.querySelectorAll('.no-print, .controls-panel, .summary-info, .preview-info, .header-section').forEach(el => {
                el.style.display = '';
            });
            
            const barcodeGrid = document.getElementById('barcodeGrid');
            if (barcodeGrid) {
                barcodeGrid.style.margin = '20px 0';
                barcodeGrid.style.padding = '';
                barcodeGrid.style.position = '';
            }
        });

        // Initialize responsive
        adjustForMobile();
        
        // ป้องกันการสูญหายของบาร์โค้ด
        window.addEventListener('load', function() {
            setTimeout(() => {
                generateBarcodes();
            }, 500);
        });
    </script>
</body>
</html>