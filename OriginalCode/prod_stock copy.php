<?php
session_start();
require '../config/config.php';

// ตั้งค่า Pagination
$limit = 10; // จำนวนสินค้าต่อหน้า
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// รับค่าการค้นหาและกรอง
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$filter_brand = isset($_GET['filter_brand']) ? mysqli_real_escape_string($conn, $_GET['filter_brand']) : '';
$filter_type = isset($_GET['filter_type']) ? mysqli_real_escape_string($conn, $_GET['filter_type']) : '';
$filter_status = isset($_GET['filter_status']) ? mysqli_real_escape_string($conn, $_GET['filter_status']) : '';
$filter_price_min = isset($_GET['filter_price_min']) ? floatval($_GET['filter_price_min']) : 0;
$filter_price_max = isset($_GET['filter_price_max']) ? floatval($_GET['filter_price_max']) : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'ps.id';
$order = isset($_GET['order']) && $_GET['order'] == 'desc' ? 'DESC' : 'ASC';

// สร้าง WHERE clause
$where_conditions = ["1=1"];

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE '%$search%' OR 
                          p.model_name LIKE '%$search%' OR 
                          ps.imei LIKE '%$search%' OR 
                          ps.barcode LIKE '%$search%' OR
                          pb.name_th LIKE '%$search%' OR
                          ps.id LIKE '%$search%')";
}

if (!empty($filter_brand)) {
    $where_conditions[] = "p.prod_brands_id = '$filter_brand'";
}

if (!empty($filter_type)) {
    $where_conditions[] = "p.prod_types_id = '$filter_type'";
}

if (!empty($filter_status)) {
    $where_conditions[] = "ps.proout_types_id = '$filter_status'";
}

if ($filter_price_min > 0) {
    $where_conditions[] = "ps.price >= $filter_price_min";
}

if ($filter_price_max > 0) {
    $where_conditions[] = "ps.price <= $filter_price_max";
}

$where_clause = implode(' AND ', $where_conditions);

// ดึงข้อมูลสำหรับ dropdown filters
$brands_result = mysqli_query($conn, "SELECT id, name_th FROM prod_brands ORDER BY name_th");
$types_result = mysqli_query($conn, "SELECT id, name_th FROM prod_types ORDER BY name_th");
$outtype_result = mysqli_query($conn, "SELECT id, name FROM proout_types ORDER BY name");

// SQL หลักสำหรับดึงข้อมูลสต็อก
$main_sql = "SELECT 
    ps.id as stock_id,
    ps.imei,
    ps.barcode,
    ps.date_in,
    ps.date_out,
    ps.price as stock_price,
    ps.proout_types_id,
    ps.prod_image,
    ps.prod_warranty_id,
    p.id as product_id,
    p.name as product_name,
    p.model_name,
    p.model_no,
    p.price as original_price,
    pb.name_th as brand_name,
    pt.name_th as type_name,
    pw.total_warranty,
    pw.end_date as warranty_end,
    pw.warranty_status,
    s.sp_name as supplier_name,
    bh.id AS receipt_no
FROM prod_stocks ps
LEFT JOIN products p ON ps.products_id = p.id
LEFT JOIN prod_brands pb ON p.prod_brands_id = pb.id
LEFT JOIN prod_types pt ON p.prod_types_id = pt.id
LEFT JOIN prod_warranty pw ON ps.prod_warranty_id = pw.id
LEFT JOIN suppliers s ON ps.supplier_id = s.id
LEFT JOIN bill_details bd ON bd.stock_id = ps.id
LEFT JOIN bill_headers bh ON bh.id = bd.bill_headers_id
WHERE $where_clause
ORDER BY $sort_by $order";


// นับจำนวนทั้งหมด
$count_sql = "SELECT COUNT(*) as total FROM ($main_sql) as count_table";
$count_result = mysqli_query($conn, $count_sql);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

// ดึงข้อมูลพร้อม pagination
$data_sql = $main_sql . " LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $data_sql);

// จัดการลบข้อมูลรายการเดียว
if (isset($_POST['delete_stock']) && isset($_POST['stock_id'])) {
    $stock_id = mysqli_real_escape_string($conn, $_POST['stock_id']);

    // ตรวจสอบว่าสินค้าถูกขายไปแล้วหรือไม่
    $check_sql = "SELECT proout_types_id, prod_image, prod_warranty_id FROM prod_stocks WHERE id = '$stock_id'";
    $check_result = mysqli_query($conn, $check_sql);
    $stock_info = mysqli_fetch_assoc($check_result);

    if ($stock_info && $stock_info['proout_types_id'] == 2) {
        $_SESSION['error'] = 'ไม่สามารถลบสินค้าที่ขายไปแล้ว';
        header('Location: prod_stock.php');
        exit;
    }

    // ลบข้อมูลสต็อกและประกัน
    mysqli_autocommit($conn, false);

    try {
        // ลบรูปภาพ
        if (!empty($stock_info['prod_image'])) {
            $images = json_decode($stock_info['prod_image'], true);
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
        if ($stock_info['prod_warranty_id']) {
            $delete_warranty_sql = "DELETE FROM prod_warranty WHERE id = " . $stock_info['prod_warranty_id'];
            mysqli_query($conn, $delete_warranty_sql);
        }

        // ลบสต็อก
        $delete_stock_sql = "DELETE FROM prod_stocks WHERE id = '$stock_id'";

        if (mysqli_query($conn, $delete_stock_sql)) {
            mysqli_commit($conn);
            $_SESSION['success'] = 'ลบสินค้าเรียบร้อย';
        } else {
            throw new Exception('ไม่สามารถลบสต็อกได้');
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = 'เกิดข้อผิดพลาดในการลบ: ' . $e->getMessage();
    }

    mysqli_autocommit($conn, true);
    header('Location: prod_stock.php');
    exit;
}

// จัดการลบหลายรายการ
if (isset($_POST['delete_multiple']) && isset($_POST['selected_stocks'])) {
    $selected_stocks = $_POST['selected_stocks'];

    if (empty($selected_stocks)) {
        $_SESSION['error'] = 'กรุณาเลือกสินค้าที่ต้องการลบ';
        header('Location: prod_stock.php');
        exit;
    }

    // สร้าง list ของ stock IDs ที่ปลอดภัย
    $stock_ids = array_map('intval', $selected_stocks);
    $stock_ids_str = implode(',', $stock_ids);

    // ตรวจสอบสินค้าที่ขายแล้ว
    $check_sold_sql = "SELECT id, proout_types_id FROM prod_stocks WHERE id IN ($stock_ids_str) AND proout_types_id = 2";
    $check_sold_result = mysqli_query($conn, $check_sold_sql);

    if (mysqli_num_rows($check_sold_result) > 0) {
        $sold_items = [];
        while ($row = mysqli_fetch_assoc($check_sold_result)) {
            $sold_items[] = str_pad($row['id'], 6, '0', STR_PAD_LEFT);
        }
        $_SESSION['error'] = 'ไม่สามารถลบสินค้าที่ขายแล้ว: ' . implode(', ', $sold_items);
        header('Location: prod_stock.php');
        exit;
    }

    // ดึงข้อมูลรูปภาพและการรับประกันก่อนลบ
    $data_sql = "SELECT id, prod_image, prod_warranty_id FROM prod_stocks WHERE id IN ($stock_ids_str)";
    $data_result = mysqli_query($conn, $data_sql);

    $delete_data = [];
    while ($row = mysqli_fetch_assoc($data_result)) {
        $delete_data[] = $row;
    }

    // เริ่ม Transaction
    mysqli_autocommit($conn, false);

    try {
        $deleted_count = 0;

        foreach ($delete_data as $item) {
            // ลบรูปภาพ
            if (!empty($item['prod_image'])) {
                $images = json_decode($item['prod_image'], true);
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
            if ($item['prod_warranty_id']) {
                $delete_warranty_sql = "DELETE FROM prod_warranty WHERE id = " . $item['prod_warranty_id'];
                mysqli_query($conn, $delete_warranty_sql);
            }

            $deleted_count++;
        }

        // ลบสต็อกทั้งหมด
        $delete_stocks_sql = "DELETE FROM prod_stocks WHERE id IN ($stock_ids_str)";

        if (mysqli_query($conn, $delete_stocks_sql)) {
            mysqli_commit($conn);
            $_SESSION['success'] = "ลบสินค้าเรียบร้อย จำนวน $deleted_count รายการ";
        } else {
            throw new Exception('ไม่สามารถลบข้อมูลสต็อกได้');
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = 'เกิดข้อผิดพลาดในการลบ: ' . $e->getMessage();
    }

    mysqli_autocommit($conn, true);
    header('Location: prod_stock.php');
    exit;
}

// สร้าง query string สำหรับ pagination
function build_query_string($exclude = [])
{
    $params = $_GET;
    foreach ($exclude as $key) {
        unset($params[$key]);
    }
    return !empty($params) ? '&' . http_build_query($params) : '';
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการสินค้าในสต็อก - ระบบจัดการร้านค้ามือถือ</title>
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

        .header-controls {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1rem;
            position: relative;
            z-index: 2;
        }

        .filter-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .table-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .table-responsive {
            border-radius: 15px;
        }

        .table {
            border-collapse: separate;
            border-spacing: 0;
        }

        .table th {
            background-color: #198754;
            color: white;
            font-weight: 600;
            border: 1px solid #fff;
            padding: 0.6rem 0.8rem;
            text-align: center;
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .table td {
            padding: 0.6rem 0.8rem;
            vertical-align: middle;
            border: 1px solid #dee2e6;
            background-color: white;
            font-size: 0.85rem;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: rgba(25, 135, 84, 0.05);
        }

        .table tbody tr:hover td {
            background-color: rgba(25, 135, 84, 0.05);
        }

        .btn-primary {
            background-color: #198754;
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #157347;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(25, 135, 84, 0.3);
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
            border-radius: 8px;
            color: #000;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-warning:hover {
            background-color: #e0a800;
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.4);
        }

        .btn-danger {
            background-color: #dc3545;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-danger:hover {
            background-color: #c82333;
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }

        .btn-info {
            background-color: #17a2b8;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-info:hover {
            background-color: #138496;
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(23, 162, 184, 0.4);
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
        }

        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .badge-price {
            background-color: #28a745;
            color: white;
            font-size: 0.9rem;
        }

        .stock-id {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
        }

        .product-info {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
        }

        .product-details {
            font-size: 0.75rem;
            color: #6c757d;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 11px;
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

        .warranty-info {
            font-size: 11px;
            color: #666;
        }

        .warranty-active {
            color: #198754;
        }

        .warranty-expired {
            color: #dc3545;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }

        .pagination .page-link {
            color: #198754;
            border-color: #198754;
            border-radius: 8px;
            margin: 0 2px;
            font-weight: 500;
        }

        .pagination .page-link:hover {
            background-color: #198754;
            border-color: #198754;
            color: white;
        }

        .pagination .page-item.active .page-link {
            background-color: #198754;
            border-color: #198754;
            color: white;
        }

        .pagination .page-item.disabled .page-link {
            color: #6c757d;
            border-color: #dee2e6;
        }

        .sort-link {
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .sort-link:hover {
            color: #f8f9fa;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }

        .bulk-actions {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .bulk-actions.show {
            display: block;
        }

        .form-check-input:checked {
            background-color: #198754;
            border-color: #198754;
        }

        @media (max-width: 768px) {
            .main-header {
                padding: 1rem 0;
            }

            .header-controls {
                padding: 0.75rem;
            }

            .header-controls .row {
                flex-direction: column;
            }

            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
                margin-bottom: 0.5rem;
            }

            .table {
                font-size: 0.875rem;
            }
        }
    </style>
</head>

<body>
    <!-- Modal ยืนยันลบ -->
    <div class="modal fade" id="confirmDeleteMultipleModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">ยืนยันการลบหลายรายการ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="multipleDeleteText"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger" form="bulkForm">ลบแน่นอน</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal ยืนยันการลบ -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i> ยืนยันการลบ
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>คุณต้องการลบสินค้า <strong id="productName"></strong> ใช่หรือไม่?</p>
                    <div class="alert alert-warning">
                        <strong>รหัสสต็อก:</strong> <span id="deleteStockId"></span><br>
                        <strong>สินค้า:</strong> <span id="deleteProductName"></span>
                    </div>
                    <p class="text-danger">
                        <i class="fas fa-exclamation-circle me-1"></i>
                        <strong>คำเตือน:</strong> การลบจะไม่สามารถกู้คืนได้ และจะลบข้อมูลการรับประกันด้วย
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="stock_id" id="deleteStockIdInput">
                        <button type="submit" name="delete_stock" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i> ลบแน่นอน
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <!-- Header -->
    <div class="main-header">
        <div class="container">
            <div class="row align-items-center">
                <!-- ชื่อหน้าและจำนวนสินค้า -->
                <div class="col-md-6">
                    <h1>
                        <i class="fas fa-warehouse me-3"></i>
                        รายการสินค้าในสต็อก
                        <small class="fs-6 opacity-75 d-block">(<?php echo number_format($total_records); ?> รายการ)</small>
                    </h1>
                </div>

                <!-- ช่องค้นหาและปุ่มต่างๆ ชิดขวา -->
                <div class="col-md-6">
                    <div class="d-flex justify-content-end">
                        <form method="GET" action="" class="d-flex align-items-center gap-2">
                            <input type="hidden" name="filter_brand" value="<?php echo $filter_brand; ?>">
                            <input type="hidden" name="filter_type" value="<?php echo $filter_type; ?>">
                            <input type="hidden" name="filter_status" value="<?php echo $filter_status; ?>">
                            <input type="hidden" name="filter_price_min" value="<?php echo $filter_price_min; ?>">
                            <input type="hidden" name="filter_price_max" value="<?php echo $filter_price_max; ?>">
                            <input type="hidden" name="sort" value="<?php echo $sort_by; ?>">
                            <input type="hidden" name="order" value="<?php echo $order; ?>">

                            <!-- ช่องค้นหาและปุ่มค้นหา -->
                            <div class="input-group" style="width: 350px;">
                                <input type="text" class="form-control" name="search"
                                    value="<?php echo htmlspecialchars($search); ?>"
                                    placeholder="ค้นหารหัสสต็อก, ชื่อสินค้า, IMEI, บาร์โค้ด...">
                                <button type="submit" class="btn btn-light">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>

                            <!-- ปุ่มกรอง -->
                            <button type="button" class="btn btn-outline-light" id="toggleFilter"
                                style="color: white; border-color: rgba(255,255,255,0.3);z-index:1;">
                                <i class="fas fa-filter me-1"></i>กรองข้อมูล
                            </button>

                            <!-- ปุ่มเพิ่ม -->
                            <a href="add_prodStock.php" class="btn btn-warning text-dark" style="z-index:1;">
                                <i class="fas fa-plus me-1"></i>เพิ่มสินค้า
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- แสดงข้อความแจ้งเตือน -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success'];
                unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $_SESSION['error'];
                unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- ฟิลเตอร์ -->
        <div class="card filter-card" id="filterCard" style="display: none;">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    <i class="fas fa-filter me-2"></i>ตัวกรองขั้นสูง
                </h5>
                <form method="GET" action="" class="row g-3">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">

                    <!-- กรองตามยี่ห้อ -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-building me-1"></i>ยี่ห้อ
                        </label>
                        <select class="form-select" name="filter_brand">
                            <option value="">-- ทุกยี่ห้อ --</option>
                            <?php while ($brand = mysqli_fetch_assoc($brands_result)): ?>
                                <option value="<?php echo $brand['id']; ?>"
                                    <?php echo $filter_brand == $brand['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($brand['name_th']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- กรองตามประเภท -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-layer-group me-1"></i>ประเภท
                        </label>
                        <select class="form-select" name="filter_type">
                            <option value="">-- ทุกประเภท --</option>
                            <?php while ($type = mysqli_fetch_assoc($types_result)): ?>
                                <option value="<?php echo $type['id']; ?>"
                                    <?php echo $filter_type == $type['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['name_th']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- กรองตามสถานะ -->
                    <div class="col-md-2">
                        <label class="form-label fw-bold">
                            <i class="fas fa-tag me-1"></i>สถานะ
                        </label>
                        <select class="form-select" name="filter_status">
                            <option value="">-- ทุกสถานะ --</option>
                            <?php while ($outtype = mysqli_fetch_assoc($outtype_result)): ?>
                                <option value="<?php echo $outtype['id']; ?>"
                                    <?php echo $filter_status == $outtype['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($outtype['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- กรองตามราคาต่ำสุด -->
                    <div class="col-md-2">
                        <label class="form-label fw-bold">
                            <i class="fas fa-money-bill-wave me-1"></i>ราคาต่ำสุด
                        </label>
                        <input type="number" class="form-control" name="filter_price_min"
                            value="<?php echo $filter_price_min ?: ''; ?>" placeholder="0" min="0" step="0.01">
                    </div>

                    <!-- กรองตามราคาสูงสุด -->
                    <div class="col-md-2">
                        <label class="form-label fw-bold">
                            <i class="fas fa-money-bill-wave me-1"></i>ราคาสูงสุด
                        </label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="filter_price_max"
                                value="<?php echo $filter_price_max ?: ''; ?>" placeholder="∞" min="0" step="0.01">
                            <button class="btn btn-success" type="submit">
                                <i class="fas fa-filter"></i>
                            </button>
                        </div>
                    </div>

                    <!-- ปุ่มล้างตัวกรอง -->
                    <div class="col-12">
                        <button type="button" class="btn btn-outline-secondary" onclick="clearAllFilters()">
                            <i class="fas fa-times me-1"></i>ล้างตัวกรองทั้งหมด
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions" id="bulkActions">
            <form method="POST" id="bulkForm">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <span id="selectedCount">0</span> รายการที่เลือก
                    </div>
                    <div class="col-md-6 text-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="printSelectedBarcodes()">
                            <i class="fas fa-print me-1"></i>พิมพ์บาร์โค้ดที่เลือก
                        </button>
                        <button type="submit" name="delete_multiple" class="btn btn-outline-danger btn-sm" onclick="return confirmDeleteMultiple()">
                            <i class="fas fa-trash me-1"></i>ลบที่เลือก
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- ตารางแสดงข้อมูล -->
        <div class="card table-card">
            <div class="card-body p-0">
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="4%">
                                        <input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleSelectAll()">
                                    </th>
                                    <th width="10%">
                                        <a href="?sort=ps.id&order=<?php echo ($sort_by == 'ps.id' && $order == 'ASC') ? 'desc' : 'asc'; ?><?php echo build_query_string(['sort', 'order', 'page']); ?>&page=<?php echo $page; ?>" class="sort-link">
                                            รหัสสต็อก <i class="fas fa-sort"></i>
                                        </a>
                                    </th>
                                    <th width="25%">
                                        <a href="?sort=p.name&order=<?php echo ($sort_by == 'p.name' && $order == 'ASC') ? 'desc' : 'asc'; ?><?php echo build_query_string(['sort', 'order', 'page']); ?>&page=<?php echo $page; ?>" class="sort-link">
                                            สินค้า <i class="fas fa-sort"></i>
                                        </a>
                                    </th>
                                    <th width="17%">IMEI / บาร์โค้ด</th>
                                    <th width="10%">
                                        <a href="?sort=ps.price&order=<?php echo ($sort_by == 'ps.price' && $order == 'ASC') ? 'desc' : 'asc'; ?><?php echo build_query_string(['sort', 'order', 'page']); ?>&page=<?php echo $page; ?>" class="sort-link">
                                            ราคา <i class="fas fa-sort"></i>
                                        </a>
                                    </th>
                                    <th width="8%">สถานะ</th>
                                    <th width="10%">วันที่เข้า</th>
                                    <th width="16%">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($result)):
                                    // ตรวจสอบสถานะการรับประกัน
                                    $warranty_status = '';
                                    if ($row['warranty_end']) {
                                        $warranty_end = strtotime($row['warranty_end']);
                                        $today = time();
                                        if ($warranty_end > $today) {
                                            $warranty_status = 'warranty-active';
                                        } else {
                                            $warranty_status = 'warranty-expired';
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <?php if ($row['proout_types_id'] != 2): ?>
                                                <input type="checkbox" class="form-check-input stock-checkbox"
                                                    value="<?= $row['stock_id'] ?>" onchange="updateBulkActions()">
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="stock-id"><?= str_pad($row['stock_id'], 6, '0', STR_PAD_LEFT) ?></div>
                                            <?php if ($row['supplier_name']): ?>
                                                <small class="text-muted">จาก: <?= htmlspecialchars($row['supplier_name']) ?></small>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="product-info"><?= htmlspecialchars($row['product_name']) ?></div>
                                            <div class="product-details">
                                                <span class="badge bg-info" style="font-size: 0.7rem;">
                                                    <?= htmlspecialchars($row['brand_name']) ?>
                                                </span>
                                                <span class="badge bg-warning text-dark" style="font-size: 0.7rem;">
                                                    <?= htmlspecialchars($row['type_name']) ?>
                                                </span>
                                                <br><strong><?= htmlspecialchars($row['model_name']) ?></strong>
                                            </div>
                                            <?php if ($row['total_warranty']): ?>
                                                <div class="warranty-info <?= $warranty_status ?>">
                                                    <i class="fas fa-shield-alt me-1"></i>
                                                    <?php if ($row['warranty_status'] === 'pending'): ?>
                                                        รับประกัน <?= $row['total_warranty'] ?> เดือน (รอการขาย)
                                                    <?php elseif ($row['warranty_status'] === 'active' && $row['warranty_end']): ?>
                                                        รับประกัน <?= $row['total_warranty'] ?> เดือน (ถึง <?= date('d/m/Y', strtotime($row['warranty_end'])) ?>)
                                                    <?php else: ?>
                                                        รับประกัน <?= $row['total_warranty'] ?> เดือน
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div style="font-size: 11px;">
                                                <strong>IMEI:</strong> <?= htmlspecialchars($row['imei']) ?>
                                                <br><strong>บาร์โค้ด:</strong> <?= htmlspecialchars($row['barcode']) ?>
                                            </div>
                                        </td>

                                        <td class="text-center">
                                            <span class="badge badge-price">
                                                ฿<?= number_format(floatval($row['stock_price'] ?: $row['original_price']), 0) ?>
                                            </span>
                                        </td>

                                        <td class="text-center">
                                            <?php
                                            $status_class = '';
                                            $status_text = '';
                                            switch ($row['proout_types_id']) {
                                                case 1:
                                                    $status_class = 'status-available';
                                                    $status_text = 'พร้อมขาย';
                                                    break;
                                                case 2:
                                                    $status_class = 'status-sold';
                                                    $status_text = 'ขายแล้ว';
                                                    break;
                                                case 3:
                                                    $status_class = 'status-damaged';
                                                    $status_text = 'ชำรุด';
                                                    break;
                                                default:
                                                    $status_class = 'status-sold';
                                                    $status_text = 'หาย';
                                            }
                                            ?>

                                            <span class="status-badge <?= $status_class ?>">
                                                <?= $status_text ?>
                                            </span>

                                            <?php if ($row['proout_types_id'] == 2 && !empty($row['receipt_no'])): ?>
                                                <div class="mt-1" style="font-size:11px; color:#555;">
                                                    เลขที่ใบเสร็จ: <?= str_pad($row['receipt_no'], 7, '0', STR_PAD_LEFT) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-center">
                                            <?= date('d/m/Y', strtotime($row['date_in'])) ?>
                                            <?php if ($row['date_out']): ?>
                                                <br><small class="text-muted">ออก: <?= date('d/m/Y', strtotime($row['date_out'])) ?></small>
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-center">
                                            <div class="d-flex justify-content-center" style="gap: 3px;">
                                                <a href="view_stock.php?id=<?= $row['stock_id'] ?>"
                                                    class="btn btn-info btn-sm text-light" title="ดูรายละเอียด">
                                                    <i class="fas fa-eye"></i>
                                                </a>

                                                <a href="edit_stock.php?id=<?= $row['stock_id'] ?>"
                                                    class="btn btn-warning btn-sm text-light" title="แก้ไข">
                                                    <i class="fas fa-edit"></i>
                                                </a>

                                                <a href="print_barcode.php?stock_ids=<?= $row['stock_id'] ?>"
                                                    class="btn btn-outline-secondary btn-sm" title="พิมพ์บาร์โค้ด">
                                                    <i class="fas fa-print"></i>
                                                </a>

                                                <?php if ($row['proout_types_id'] != 2): ?>
                                                    <button type="button" class="btn btn-danger btn-sm"
                                                        onclick="confirmDelete(<?= $row['stock_id'] ?>, '<?= htmlspecialchars($row['product_name']) ?>')"
                                                        title="ลบ">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h4>ไม่พบข้อมูลสินค้าในสต็อก</h4>
                        <?php if (!empty($search) || !empty($filter_brand) || !empty($filter_type)): ?>
                            <p>ลองปรับเงื่อนไขการค้นหาหรือกรองใหม่</p>
                            <a href="prod_stock.php" class="btn btn-outline-secondary">ดูทั้งหมด</a>
                        <?php else: ?>
                            <p>เริ่มต้นด้วยการเพิ่มสินค้าเข้าสต็อก</p>
                            <a href="add_prodStock.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>เพิ่มสินค้าแรก
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-4">
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <!-- Previous Page -->
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo ($page - 1); ?><?php echo build_query_string(['page']); ?>">
                                    <i class="fas fa-chevron-left"></i> ก่อนหน้า
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link"><i class="fas fa-chevron-left"></i> ก่อนหน้า</span>
                            </li>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);

                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1' . build_query_string(['page']) . '">1</a></li>';
                            if ($start > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }

                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo build_query_string(['page']); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php
                        endfor;

                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . build_query_string(['page']) . '">' . $total_pages . '</a></li>';
                        }
                        ?>

                        <!-- Next Page -->
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo ($page + 1); ?><?php echo build_query_string(['page']); ?>">
                                    ถัดไป <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link">ถัดไป <i class="fas fa-chevron-right"></i></span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>

            <!-- Page Info -->
            <div class="text-center mt-3 text-muted">
                <small>
                    แสดง <?php echo (($page - 1) * $limit) + 1; ?> -
                    <?php echo min($page * $limit, $total_records); ?>
                    จาก <?php echo number_format($total_records); ?> รายการ
                </small>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDeleteMultiple() {
            const checkboxes = document.querySelectorAll('.stock-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('กรุณาเลือกสินค้าที่ต้องการลบ');
                return false;
            }

            const text = `🗑 คุณเลือกสินค้าจำนวน ${checkboxes.length} รายการ\nคุณแน่ใจหรือไม่ว่าต้องการลบ?`;
            document.getElementById('multipleDeleteText').textContent = text;

            const modal = new bootstrap.Modal(document.getElementById('confirmDeleteMultipleModal'));
            modal.show();

            return false; // กันไม่ให้ form submit ทันที
        }

        // Toggle Filter Section
        function toggleFilter() {
            const filterCard = document.getElementById('filterCard');
            const toggleBtn = document.getElementById('toggleFilter');

            if (filterCard.style.display === 'none' || filterCard.style.display === '') {
                filterCard.style.display = 'block';
                filterCard.style.opacity = '0';
                filterCard.style.transform = 'translateY(-20px)';

                setTimeout(() => {
                    filterCard.style.transition = 'all 0.3s ease';
                    filterCard.style.opacity = '1';
                    filterCard.style.transform = 'translateY(0)';
                }, 10);

                toggleBtn.innerHTML = '<i class="fas fa-times me-1"></i>ปิดกรอง';
                toggleBtn.classList.remove('btn-light');
                toggleBtn.classList.add('btn-secondary');
            } else {
                filterCard.style.transition = 'all 0.3s ease';
                filterCard.style.opacity = '0';
                filterCard.style.transform = 'translateY(-20px)';

                setTimeout(() => {
                    filterCard.style.display = 'none';
                }, 300);

                toggleBtn.innerHTML = '<i class="fas fa-filter me-1"></i>กรองข้อมูล';
                toggleBtn.classList.remove('btn-secondary');
                toggleBtn.classList.add('btn btn-outline-light');
            }
        }

        // Delete Confirmation
        function confirmDelete(stockId, productName) {
            document.getElementById('deleteStockId').textContent = String(stockId).padStart(6, '0');
            document.getElementById('deleteProductName').textContent = productName;
            document.getElementById('deleteStockIdInput').value = stockId;

            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

        // Clear all filters function
        function clearAllFilters() {
            window.location.href = 'prod_stock.php';
        }

        // Bulk Actions Functions
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.stock-checkbox');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });

            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.stock-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');

            selectedCount.textContent = checkboxes.length;

            if (checkboxes.length > 0) {
                bulkActions.classList.add('show');

                // เพิ่ม hidden inputs สำหรับ selected stocks
                const bulkForm = document.getElementById('bulkForm');

                // ลบ hidden inputs เดิม
                const existingInputs = bulkForm.querySelectorAll('input[name="selected_stocks[]"]');
                existingInputs.forEach(input => input.remove());

                // เพิ่ม hidden inputs ใหม่
                checkboxes.forEach(checkbox => {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'selected_stocks[]';
                    hiddenInput.value = checkbox.value;
                    bulkForm.appendChild(hiddenInput);
                });

            } else {
                bulkActions.classList.remove('show');
            }

            // Update select all checkbox
            const allCheckboxes = document.querySelectorAll('.stock-checkbox');
            const selectAll = document.getElementById('selectAll');

            if (checkboxes.length === allCheckboxes.length && allCheckboxes.length > 0) {
                selectAll.checked = true;
                selectAll.indeterminate = false;
            } else if (checkboxes.length > 0) {
                selectAll.checked = false;
                selectAll.indeterminate = true;
            } else {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
        }

        function printSelectedBarcodes() {
            const checkboxes = document.querySelectorAll('.stock-checkbox:checked');

            if (checkboxes.length === 0) {
                alert('กรุณาเลือกสินค้าที่ต้องการพิมพ์บาร์โค้ด');
                return;
            }

            const stockIds = Array.from(checkboxes).map(cb => cb.value);
            const url = 'print_barcode.php?stock_ids=' + stockIds.join(',');
            window.open(url, '_blank');
        }

        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle filter button
            const toggleBtn = document.getElementById('toggleFilter');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', toggleFilter);
            }

            // Show filter if any filters are active
            const hasActiveFilters = <?= json_encode(!empty($filter_brand) || !empty($filter_type) || !empty($filter_status) || $filter_price_min > 0 || $filter_price_max > 0) ?>;
            if (hasActiveFilters) {
                document.getElementById('filterCard').style.display = 'block';
                const toggleBtn = document.getElementById('toggleFilter');
                toggleBtn.innerHTML = '<i class="fas fa-times me-1"></i>ปิดกรอง';
                toggleBtn.classList.remove('btn-light ');
                toggleBtn.classList.add('btn-secondary');
            }

            // Initialize animations
            const cards = document.querySelectorAll('.filter-card, .table-card, .bulk-actions');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 + (index * 100));
            });

            // Initialize table row animations
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';

                setTimeout(() => {
                    row.style.transition = 'all 0.4s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateX(0)';
                }, 300 + (index * 50));
            });

            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            const tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>

</html>