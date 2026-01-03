<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'view_purchase_order');

// รับ ID จาก URL
$purchase_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($purchase_id === 0) {
    $_SESSION['error'] = 'ไม่พบ ID ของใบสั่งซื้อ';
    header("Location: purchase_order.php");
    exit;
}

// ดึงข้อมูลส่วนหัว (Header)
// (ใช้ Query ที่แก้ไขแล้ว ตัด field ที่ไม่มีจริงออก)
$sql_header = "SELECT 
                    po.*, 
                    s.co_name as supplier_name, 
                    e.firstname_th, 
                    e.lastname_th, 
                    b.branch_name,
                    sh.shop_name,
                    sh.shop_phone,
                    sh.tax_id as shop_tax
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.suppliers_supplier_id = s.supplier_id
                LEFT JOIN employees e ON po.employees_emp_id = e.emp_id
                LEFT JOIN branches b ON po.branches_branch_id = b.branch_id
                LEFT JOIN shop_info sh ON b.shop_info_shop_id = sh.shop_id
                WHERE po.purchase_id = ?";

$stmt_header = $conn->prepare($sql_header);
$stmt_header->bind_param("i", $purchase_id);
$stmt_header->execute();
$header_result = $stmt_header->get_result();

if ($header_result->num_rows === 0) {
    $_SESSION['error'] = "ไม่พบข้อมูลใบสั่งซื้อ ID: $purchase_id";
    header("Location: purchase_order.php");
    exit;
}
$po_data = $header_result->fetch_assoc();
$stmt_header->close();

// ดึงรายการสินค้า (Details)
$sql_details = "SELECT 
                    od.*, 
                    p.prod_name, 
                    p.model_name, 
                    pb.brand_name_th 
                FROM order_details od
                JOIN products p ON od.products_prod_id = p.prod_id
                LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id
                WHERE od.purchase_orders_purchase_id = ?";
$stmt_details = $conn->prepare($sql_details);
$stmt_details->bind_param("i", $purchase_id);
$stmt_details->execute();
$details_result = $stmt_details->get_result();
$po_details = [];
$total_price = 0;
$total_quantity = 0;
while ($row = $details_result->fetch_assoc()) {
    $po_details[] = $row;
    $total_quantity += $row['amount'];
    $total_price += ($row['amount'] * $row['price']);
}
$stmt_details->close();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ใบสั่งซื้อ (PO) #<?= $purchase_id ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">

    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: <?= $background_color ?>;
            color: <?= $text_color ?>;
        }
        
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .card-header { background-color: #fff; border-bottom: 1px solid #eee; padding: 1.5rem; }
        
        /* ==========================================================================
           PRINT STYLES (ส่วนสำคัญ)
           ========================================================================== */
        @media print {
            @page {
                size: A4;
                margin: 0; /* [สำคัญ] ตั้งขอบเป็น 0 เพื่อซ่อน Header/Footer ของ Browser */
            }

            /* ซ่อน Element ที่ไม่ต้องการ */
            #sidebar-wrapper, .sidebar, #menu-toggle, .btn, .no-print, header, footer, .navbar, .navbar-toggler, #sidebarToggle {
                display: none !important;
            }

            /* จัด Layout หลัก */
            body {
                background-color: #fff !important;
                -webkit-print-color-adjust: exact;
                margin: 1cm; /* เพิ่มขอบให้เนื้อหาแทน เพราะเราปิดขอบ @page ไปแล้ว */
            }
            .main-content, #page-content-wrapper, .container-fluid, .container, #wrapper {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                max-width: none !important;
            }

            .card {
                box-shadow: none !important;
                border: none !important;
                margin-bottom: 0 !important;
            }
            .card-header { display: none !important; }
            .card-body { padding: 0 !important; }

            /* ส่วนหัวเอกสาร */
            .doc-header {
                border-bottom: 2px solid #000;
                margin-bottom: 20px;
                padding-bottom: 10px;
            }
            .doc-title {
                font-size: 24pt;
                font-weight: bold;
                text-align: right;
                color: #000;
                text-transform: uppercase;
            }
            
            /* ตาราง */
            .table-print {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            .table-print th, .table-print td {
                border: 1px solid #000 !important;
                padding: 8px;
                font-size: 11pt;
            }
            .table-print thead th {
                background-color: #f0f0f0 !important;
                color: #000 !important;
                text-align: center;
            }

            /* ลายเซ็น */
            .signature-section {
                margin-top: 50px;
                page-break-inside: avoid;
            }
            .signature-box {
                border-top: 1px solid #000;
                width: 80%;
                margin: 0 auto;
                text-align: center;
                padding-top: 5px;
            }

            * {
                font-family: 'Sarabun', sans-serif !important;
                color: #000 !important;
            }
            
            .print-only { display: block !important; }
            .web-only { display: none !important; }
        }

        .print-only { display: none; }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                
                <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                    <h4 class="mb-0 fw-bold"><i class="fas fa-file-invoice me-2"></i>รายละเอียดใบสั่งซื้อ</h4>
                    <div>
                        <button onclick="window.print()" class="btn btn-primary shadow-sm">
                            <i class="fas fa-print me-2"></i>พิมพ์เอกสาร
                        </button>
                        <a href="purchase_order.php" class="btn btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left me-2"></i>ย้อนกลับ
                        </a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-5">
                        
                        <div class="row doc-header">
                            <div class="col-6">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                    <div style="width: 50px; height: 50px; background-color: #333; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: bold; border-radius: 5px;">LOGO</div>
                                    <div>
                                        <h4 class="m-0 fw-bold"><?= htmlspecialchars($po_data['shop_name']) ?></h4>
                                        <small>สำนักงานใหญ่ / สาขา: <?= htmlspecialchars($po_data['branch_name']) ?></small>
                                    </div>
                                </div>
                                <p class="mb-0 small">
                                    <strong>เลขประจำตัวผู้เสียภาษี:</strong> <?= htmlspecialchars($po_data['shop_tax'] ?? '-') ?><br>
                                    <strong>เบอร์โทรศัพท์:</strong> <?= htmlspecialchars($po_data['shop_phone'] ?? '-') ?>
                                </p>
                            </div>
                            <div class="col-6 text-end">
                                <div class="doc-title">ใบสั่งซื้อ</div>
                                <div class="fs-5 text-uppercase fw-bold text-muted" style="letter-spacing: 2px;">Purchase Order</div>
                                <table class="float-end mt-3" style="font-size: 0.9rem;">
                                    <tr>
                                        <td class="text-end fw-bold pe-3">เลขที่เอกสาร (PO No.):</td>
                                        <td class="text-start"><?= str_pad($po_data['purchase_id'], 6, '0', STR_PAD_LEFT) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-end fw-bold pe-3">วันที่ (Date):</td>
                                        <td class="text-start"><?= date('d/m/Y H:i', strtotime($po_data['purchase_date'])) ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-6">
                                <div class="border p-3 h-100 rounded-3" style="border-color: #ddd !important;">
                                    <h6 class="fw-bold text-uppercase border-bottom pb-2 mb-2">ผู้จำหน่าย (Vendor)</h6>
                                    <p class="mb-1 fw-bold"><?= htmlspecialchars($po_data['supplier_name']) ?></p>
                                    <p class="mb-0 small text-muted">
                                        (ไม่มีข้อมูลที่อยู่/เบอร์โทรในระบบ)
                                    </p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border p-3 h-100 rounded-3" style="border-color: #ddd !important;">
                                    <h6 class="fw-bold text-uppercase border-bottom pb-2 mb-2">จัดส่งที่ (Ship To)</h6>
                                    <p class="mb-1 fw-bold"><?= htmlspecialchars($po_data['shop_name']) ?></p>
                                    <p class="mb-1 small">
                                        สาขา: <?= htmlspecialchars($po_data['branch_name']) ?>
                                    </p>
                                    <p class="mb-0 small">
                                        <strong>ผู้ออกใบสั่งซื้อ:</strong> คุณ <?= htmlspecialchars($po_data['firstname_th'] . ' ' . $po_data['lastname_th']) ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <table class="table table-print">
                            <thead>
                                <tr>
                                    <th style="width: 5%;" class="text-center">#</th>
                                    <th style="width: 45%;">รายการสินค้า (Description)</th>
                                    <th style="width: 15%;" class="text-center">จำนวน (Qty)</th>
                                    <th style="width: 15%;" class="text-end">ราคา/หน่วย</th>
                                    <th style="width: 20%;" class="text-end">จำนวนเงิน (Total)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $i = 1;
                                foreach ($po_details as $item): 
                                ?>
                                <tr>
                                    <td class="text-center"><?= $i++ ?></td>
                                    <td>
                                        <span class="fw-bold"><?= htmlspecialchars($item['prod_name']) ?></span><br>
                                        <small class="text-muted" style="color: #666 !important;">รุ่น: <?= htmlspecialchars($item['model_name']) ?> (<?= htmlspecialchars($item['brand_name_th']) ?>)</small>
                                    </td>
                                    <td class="text-center"><?= number_format($item['amount']) ?></td>
                                    <td class="text-end"><?= number_format($item['price'], 2) ?></td>
                                    <td class="text-end"><?= number_format($item['amount'] * $item['price'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                
                                </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2" class="text-end fw-bold" style="border: none !important;">รวมจำนวน (Total Qty):</td>
                                    <td class="text-center fw-bold" style="background-color: #f9f9f9;"><?= number_format($total_quantity) ?></td>
                                    <td class="text-end fw-bold">รวมเป็นเงิน (Subtotal):</td>
                                    <td class="text-end fw-bold"><?= number_format($total_price, 2) ?></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end fw-bold">ยอดรวมสุทธิ (Grand Total):</td>
                                    <td class="text-end fw-bold" style="font-size: 1.1em; background-color: #eee;"><?= number_format($total_price, 2) ?> บาท</td>
                                </tr>
                            </tfoot>
                        </table>

                        <div class="signature-section mt-5 pt-4">
                            <div class="row">
                                <div class="col-4 text-center">
                                    <br><br><br>
                                    <div class="signature-box">
                                        ผู้จัดทำ (Prepared By)<br>
                                        <?= htmlspecialchars($po_data['firstname_th'] . ' ' . $po_data['lastname_th']) ?><br>
                                        วันที่: <?= date('d/m/Y', strtotime($po_data['create_at'])) ?>
                                    </div>
                                </div>
                                <div class="col-4 text-center">
                                    <br><br><br>
                                    <div class="signature-box">
                                        ผู้อนุมัติ (Approved By)<br>
                                        (....................................................)<br>
                                        วันที่: ......./......./.......
                                    </div>
                                </div>
                                <div class="col-4 text-center">
                                    <br><br><br>
                                    <div class="signature-box">
                                        ผู้รับของ (Received By)<br>
                                        (....................................................)<br>
                                        วันที่: ......./......./.......
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>