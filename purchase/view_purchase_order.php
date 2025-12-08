<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'view_purchase_order');

//รับ ID จาก URL
$purchase_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($purchase_id === 0) {
    $_SESSION['error'] = 'ไม่พบ ID ของใบสั่งซื้อ';
    header("Location: purchase_order.php");
    exit;
}

// ดึงข้อมูลส่วนหัว (Header)
$sql_header = "SELECT 
                    po.*, 
                    s.co_name as supplier_name, 
                    e.firstname_th, 
                    e.lastname_th, 
                    b.branch_name
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.suppliers_supplier_id = s.supplier_id
                LEFT JOIN employees e ON po.employees_emp_id = e.emp_id
                LEFT JOIN branches b ON po.branches_branch_id = b.branch_id
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
$po_header = $header_result->fetch_assoc();
$stmt_header->close();


//  ดึงข้อมูลรายการสินค้า
$sql_details = "SELECT 
                    od.*, 
                    p.prod_name, 
                    p.model_name, 
                    pb.brand_name_th 
                FROM order_details od
                LEFT JOIN products p ON od.products_prod_id = p.prod_id
                LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id
                WHERE od.purchase_orders_purchase_id = ?
                ORDER BY od.order_id ASC";

$stmt_details = $conn->prepare($sql_details);
$stmt_details->bind_param("i", $purchase_id);
$stmt_details->execute();
$details_result = $stmt_details->get_result();

// เก็บผลลัพธ์ Details ไว้ใน Array
$po_details = [];
while ($row = $details_result->fetch_assoc()) {
    $po_details[] = $row;
}
$stmt_details->close();

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดใบรับเข้า #<?= htmlspecialchars($po_header['purchase_id']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <?php require '../config/load_theme.php';
    ?>
    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>';
            color: <?= $text_color ?>;
        }

        .container {
            max-width: 1100px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            background-color: #fff;
            border-bottom: 2px solid <?= $theme_color ?>;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: <?= $theme_color ?>;
        }

        .table th {
            background-color: <?= $header_bg_color ?>;
            color: <?= $header_text_color ?>;
            font-weight: 600;
            vertical-align: middle;
            text-align: center;
        }

        .table td {
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .header-info {
            font-size: 1rem;
            line-height: 1.8;
        }

        .header-info strong {
            display: inline-block;
            min-width: 120px;
            color: #333;
        }

        .btn-print {
            background-color: #0dcaf0;
            /* (Info color) */
            border-color: #0dcaf0;
            color: white;
        }

        .btn-back {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        }

        /* (CSS สำหรับซ่อนปุ่มตอนพิมพ์) */
        @media print {
            .no-print {
                display: none !important;
            }

            .card {
                box-shadow: none;
                border: 1px solid #dee2e6;
            }

            body {
                background-color: #fff;
            }
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container py-5">

                    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                        <h4 class="mb-0">
                            <i class="fas fa-file-invoice me-2" style="color: <?= $theme_color ?>;"></i>
                            รายละเอียดใบรับเข้า #<?= htmlspecialchars($po_header['purchase_id']) ?>
                        </h4>
                        <div>
                            <button type="button" class="btn btn-print" onclick="window.print();">
                                <i class="fas fa-print me-2"></i>พิมพ์
                            </button>
                            <a href="purchase_order.php" class="btn btn-back">
                                <i class="fas fa-arrow-left me-2"></i>กลับหน้ารายการ
                            </a>
                        </div>
                    </div>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show no-print">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $_SESSION['success'];
                            unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-file-invoice me-2"></i>
                            ข้อมูลใบรับเข้า
                        </div>
                        <div class="card-body">
                            <div class="row g-3 header-info">
                                <div class="col-md-6">
                                    <strong>เลขที่ PO:</strong>
                                    <?= htmlspecialchars($po_header['purchase_id']) ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>วันที่รับเข้า:</strong>
                                    <?= date('d/m/Y H:i', strtotime($po_header['purchase_date'])) ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Supplier:</strong>
                                    <?= htmlspecialchars($po_header['supplier_name'] ?? 'N/A') ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>สาขาที่รับเข้า:</strong>
                                    <?= htmlspecialchars($po_header['branch_name'] ?? 'N/A') ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>พนักงาน:</strong>
                                    <?= htmlspecialchars($po_header['firstname_th'] . ' ' . $po_header['lastname_th']) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-boxes me-2"></i>
                            รายการสินค้า
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th width="5%">#</th>
                                            <th width="45%">สินค้า</th>
                                            <th width="15%">จำนวน</th>
                                            <th width="15%">ราคา/หน่วย (บาท)</th>
                                            <th width="20%">ราคารวม (บาท)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $i = 1;
                                        $total_quantity = 0;
                                        $total_price = 0;

                                        foreach ($po_details as $item):
                                            $line_total = $item['amount'] * $item['price'];
                                            $total_quantity += $item['amount'];
                                            $total_price += $line_total;
                                        ?>
                                            <tr>
                                                <td class="text-center"><?= $i++ ?></td>
                                                <td>
                                                    <?= htmlspecialchars($item['brand_name_th'] ?? '') ?> -
                                                    <?= htmlspecialchars($item['prod_name'] ?? '') ?>
                                                    (<?= htmlspecialchars($item['model_name'] ?? '') ?>)
                                                </td>
                                                <td class="text-center"><?= number_format($item['amount']) ?></td>
                                                <td class="text-end"><?= number_format($item['price'], 2) ?></td>
                                                <td class="text-end"><?= number_format($line_total, 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>

                                        <?php if (empty($po_details)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">ไม่พบรายการสินค้าในใบรับนี้</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>

                                    <?php if (!empty($po_details)): ?>
                                        <tfoot>
                                            <tr style="background-color: #f8f9fa;">
                                                <td colspan="2" class="text-end fw-bold">
                                                    รวม
                                                </td>
                                                <td class="text-center fw-bold">
                                                    <?= number_format($total_quantity) ?>
                                                </td>
                                                <td></td>
                                                <td class="text-end fw-bold fs-5" style="color: <?= $theme_color ?>;">
                                                    <?= number_format($total_price, 2) ?>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    <?php endif; ?>
                                </table>
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
<?php
$conn->close();
?>