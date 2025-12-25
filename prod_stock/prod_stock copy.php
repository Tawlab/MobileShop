<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'prod_stock');

// [1] รับค่าพื้นฐานจาก Session
$branch_id = $_SESSION['branch_id'];
$shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// [2] ตรวจสอบว่าเป็น Super Admin หรือไม่
$is_super_admin = false;
$check_admin_sql = "SELECT r.role_name FROM roles r 
                    JOIN user_roles ur ON r.role_id = ur.roles_role_id 
                    WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt_admin = $conn->prepare($check_admin_sql)) {
    $stmt_admin->bind_param("i", $current_user_id);
    $stmt_admin->execute();
    if ($stmt_admin->get_result()->num_rows > 0) {
        $is_super_admin = true;
    }
    $stmt_admin->close();
}

// [3] เตรียมเงื่อนไขการกรองพื้นฐาน (Isolation)
$conditions = [];

if (!$is_super_admin) {
    // พนักงานทั่วไป: บังคับเห็นแค่สาขาตัวเองเท่านั้น
    $conditions[] = "ps.branches_branch_id = '$branch_id'";
} else {
    // ผู้ดูแลระบบ: หากมีการเลือกกรองร้านค้า/สาขา ให้กรองตามนั้น (จะทำต่อในขั้นตอน AJAX)
    $shop_filter = isset($_GET['shop_filter']) ? $_GET['shop_filter'] : '';
    $branch_filter = isset($_GET['branch_filter']) ? $_GET['branch_filter'] : '';
    
    if (!empty($branch_filter)) {
        $conditions[] = "ps.branches_branch_id = '$branch_filter'";
    } elseif (!empty($shop_filter)) {
        $conditions[] = "b.shop_info_shop_id = '$shop_filter'";
    }
}
// ==========================================
// [4] ส่วนประมวลผล AJAX (ทำงานเมื่อเรียกผ่าน Fetch API)
// ==========================================
if (isset($_GET['ajax'])) {
    // รับค่าการกรองและค้นหาจาก URL
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
    $brand_f = isset($_GET['brand']) ? $_GET['brand'] : '';
    $type_f = isset($_GET['type']) ? $_GET['type'] : '';
    $status_f = isset($_GET['status']) ? $_GET['status'] : '';
    $p_min = isset($_GET['p_min']) && $_GET['p_min'] !== '' ? (float)$_GET['p_min'] : '';
    $p_max = isset($_GET['p_max']) && $_GET['p_max'] !== '' ? (float)$_GET['p_max'] : '';
    
    // ตั้งค่าการแบ่งหน้า (20 รายการต่อหน้า)
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20; 
    $offset = ($page - 1) * $limit;

    // สร้างเงื่อนไข SQL เพิ่มเติมตามตัวกรอง
    if (!empty($search)) {
        $conditions[] = "(p.prod_name LIKE '%$search%' OR p.model_name LIKE '%$search%' OR ps.serial_no LIKE '%$search%' OR ps.stock_id LIKE '%$search%')";
    }
    if (!empty($brand_f)) $conditions[] = "p.prod_brands_brand_id = '$brand_f'";
    if (!empty($type_f)) $conditions[] = "p.prod_types_type_id = '$type_f'";
    if (!empty($status_f)) $conditions[] = "ps.stock_status = '$status_f'";
    if ($p_min !== '') $conditions[] = "ps.price >= $p_min";
    if ($p_max !== '') $conditions[] = "ps.price <= $p_max";

    $where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // นับจำนวนรายการทั้งหมดที่ตรงเงื่อนไข
    $count_sql = "SELECT COUNT(*) as total FROM prod_stocks ps 
                  JOIN products p ON ps.products_prod_id = p.prod_id 
                  JOIN branches b ON ps.branches_branch_id = b.branch_id 
                  $where_sql";
    $total_items = $conn->query($count_sql)->fetch_assoc()['total'];
    $total_pages = ceil($total_items / $limit);

    // ดึงข้อมูลสต็อกสินค้าพร้อมรายละเอียด
    $sql = "SELECT ps.*, p.prod_name, p.model_name, pb.brand_name_th, pt.type_name_th, b.branch_name, s.shop_name 
            FROM prod_stocks ps 
            LEFT JOIN products p ON ps.products_prod_id = p.prod_id 
            LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id 
            LEFT JOIN prod_types pt ON p.prod_types_type_id = pt.type_id 
            LEFT JOIN branches b ON ps.branches_branch_id = b.branch_id 
            LEFT JOIN shop_info s ON b.shop_info_shop_id = s.shop_id 
            $where_sql 
            ORDER BY ps.stock_id DESC 
            LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);
    
    // (หมายเหตุ: ส่วนการแสดงผล HTML Table และ Pagination UI จะทำในขั้นตอนถัดไป)
    // ตรงนี้เราพักส่วน AJAX ไว้เท่านี้ก่อนครับ
}
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
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'ps.stock_id';
$order = isset($_GET['order']) && $_GET['order'] == 'desc' ? 'DESC' : 'ASC';

// WHERE clause 
$where_conditions = ["1=1"];

// [แก้ไข 2] บังคับกรองสต็อกเฉพาะสาขานี้เท่านั้น
$where_conditions[] = "ps.branches_branch_id = '$branch_id'";

if (!empty($search)) {
    // ค้นหาจาก serial_no แทน imei/barcode
    $where_conditions[] = "(p.prod_name LIKE '%$search%' OR 
                           p.model_name LIKE '%$search%' OR 
                           ps.serial_no LIKE '%$search%' OR 
                           pb.brand_name_th LIKE '%$search%' OR
                           ps.stock_id LIKE '%$search%')";
}

if (!empty($filter_brand)) {
    $where_conditions[] = "p.prod_brands_brand_id = '$filter_brand'";
}

if (!empty($filter_type)) {
    $where_conditions[] = "p.prod_types_type_id = '$filter_type'";
}

if (!empty($filter_status)) {
    $where_conditions[] = "ps.stock_status = '$filter_status'";
}

if ($filter_price_min > 0) {
    $where_conditions[] = "ps.price >= $filter_price_min";
}

if ($filter_price_max > 0) {
    $where_conditions[] = "ps.price <= $filter_price_max";
}

$where_clause = implode(' AND ', $where_conditions);

// [แก้ไข 3] ดึงข้อมูลสำหรับ dropdown filters (เฉพาะของร้านนี้)
$brands_result = mysqli_query($conn, "SELECT brand_id, brand_name_th FROM prod_brands WHERE shop_info_shop_id = '$shop_id' ORDER BY brand_name_th");
$types_result = mysqli_query($conn, "SELECT type_id, type_name_th FROM prod_types WHERE shop_info_shop_id = '$shop_id' ORDER BY type_name_th");

$status_options = ['In Stock', 'Sold', 'Damage', 'Reserved', 'Repair'];

$main_sql = "SELECT 
    ps.stock_id,
    ps.serial_no,
    ps.price as stock_price,
    ps.stock_status,
    ps.image_path,
    p.prod_id,
    p.prod_name,
    p.model_name,
    p.model_no,
    p.prod_price as original_price,
    pb.brand_name_th as brand_name,
    pt.type_name_th as type_name,
    bh.bill_id AS receipt_no,
    (SELECT sm.ref_table FROM stock_movements sm 
     WHERE sm.prod_stocks_stock_id = ps.stock_id 
     AND sm.movement_type = 'IN' 
     LIMIT 1) as entry_type 
FROM prod_stocks ps
LEFT JOIN products p ON ps.products_prod_id = p.prod_id
LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id
LEFT JOIN prod_types pt ON p.prod_types_type_id = pt.type_id
LEFT JOIN bill_details bd ON bd.prod_stocks_stock_id = ps.stock_id
LEFT JOIN bill_headers bh ON bh.bill_id = bd.bill_headers_bill_id
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

    // ตรวจสอบว่าสินค้าถูกขายไปแล้วหรือไม่ (และต้องเป็นของสาขานี้ด้วยเพื่อความปลอดภัย)
    $check_sql = "SELECT stock_status, image_path FROM prod_stocks WHERE stock_id = '$stock_id' AND branches_branch_id = '$branch_id'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) == 0) {
         $_SESSION['error'] = 'ไม่พบสินค้าหรือคุณไม่มีสิทธิ์ลบสินค้านี้';
         header('Location: prod_stock.php');
         exit;
    }

    $stock_info = mysqli_fetch_assoc($check_result);

    if ($stock_info && $stock_info['stock_status'] == 'Sold') {
        $_SESSION['error'] = 'ไม่สามารถลบสินค้าที่ขายไปแล้ว (สถานะ Sold)';
        header('Location: prod_stock.php');
        exit;
    }

    // ลบข้อมูลสต็อก
    mysqli_autocommit($conn, false);

    try {
        // ลบรูปภาพ 
        if (!empty($stock_info['image_path'])) {
            $image_path = '../uploads/products/' . $stock_info['image_path'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }

        // ต้องลบจาก stock_movements ก่อน
        $delete_movements_sql = "DELETE FROM stock_movements WHERE prod_stocks_stock_id = '$stock_id'";
        if (!mysqli_query($conn, $delete_movements_sql)) {
        }

        // ลบสต็อก
        $delete_stock_sql = "DELETE FROM prod_stocks WHERE stock_id = '$stock_id'";

        if (mysqli_query($conn, $delete_stock_sql)) {
            mysqli_commit($conn);
            $_SESSION['success'] = 'ลบสินค้า (ID: ' . $stock_id . ') เรียบร้อย';
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

// จัดการลบหลายรายการ ***
if (isset($_POST['delete_multiple']) && isset($_POST['selected_stocks'])) {
    $selected_stocks = $_POST['selected_stocks'];

    if (empty($selected_stocks)) {
        $_SESSION['error'] = 'กรุณาเลือกสินค้าที่ต้องการลบ';
        header('Location: prod_stock.php');
        exit;
    }

    // สร้าง list ของ stock IDs
    $stock_ids = array_map('intval', $selected_stocks);
    $stock_ids_str = implode(',', $stock_ids);

    // ตรวจสอบสินค้าที่ขายแล้ว (และเช็คสาขา)
    $check_sold_sql = "SELECT stock_id, stock_status FROM prod_stocks WHERE stock_id IN ($stock_ids_str) AND stock_status = 'Sold' AND branches_branch_id = '$branch_id'";
    $check_sold_result = mysqli_query($conn, $check_sold_sql);

    if (mysqli_num_rows($check_sold_result) > 0) {
        $sold_items = [];
        while ($row = mysqli_fetch_assoc($check_sold_result)) {
            $sold_items[] = $row['stock_id'];
        }
        $_SESSION['error'] = 'ไม่สามารถลบสินค้าที่ขายแล้ว: ' . implode(', ', $sold_items);
        header('Location: prod_stock.php');
        exit;
    }

    // ดึงข้อมูลรูปภาพก่อนลบ (เช็คสาขา)
    $data_sql = "SELECT stock_id, image_path FROM prod_stocks WHERE stock_id IN ($stock_ids_str) AND branches_branch_id = '$branch_id'";
    $data_result = mysqli_query($conn, $data_sql);

    $delete_data = [];
    $valid_stock_ids = []; // เก็บเฉพาะ ID ที่เป็นของสาขานี้จริงๆ เพื่อใช้ลบ
    while ($row = mysqli_fetch_assoc($data_result)) {
        $delete_data[] = $row;
        $valid_stock_ids[] = $row['stock_id'];
    }
    
    if (empty($valid_stock_ids)) {
         $_SESSION['error'] = 'ไม่พบสินค้าที่เลือกในสาขานี้';
         header('Location: prod_stock.php');
         exit;
    }
    
    $valid_stock_ids_str = implode(',', $valid_stock_ids);

    mysqli_autocommit($conn, false);
    try {
        $deleted_count = 0;

        foreach ($delete_data as $item) {
            // ลบรูปภาพ
            if (!empty($item['image_path'])) {
                $image_path = '../uploads/products/' . $item['image_path'];
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
            $deleted_count++;
        }

        // ต้องลบจาก stock_movements ก่อน
        $delete_movements_sql = "DELETE FROM stock_movements WHERE prod_stocks_stock_id IN ($valid_stock_ids_str)";
        if (!mysqli_query($conn, $delete_movements_sql)) {
        }

        // ลบสต็อกทั้งหมด
        $delete_stocks_sql = "DELETE FROM prod_stocks WHERE stock_id IN ($valid_stock_ids_str)";

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
    <title>รายการสินค้าในสต็อก - ระบบจัดการร้านค้ามือถือ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            min-height: 100vh;
        }
        /* ... โค้ด .main-header และ .filter-card เดิม ... */
        .main-header {
            background-color: <?= $theme_color ?>;
            /* (ใช้สีธีม) */
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

        .filter-card {
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .table-card {
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .table th {
            background-color: <?= $header_bg_color ?>;
            /* (ใช้สีธีม) */
            color: <?= $header_text_color ?>;
            /* (ใช้สีธีม) */
            font-weight: 600;
            border: 1px solid <?= $header_bg_color ?>;
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
        
        /* **[เพิ่ม]** จัดการปุ่ม Action ในตาราง */
        .table td:last-child {
            display: flex;
            gap: 5px; 
            justify-content: center;
            align-items: center;
        }
        /* ... โค้ดปุ่มและ Form Control เดิม ... */
        .btn-add {
            background-color: <?= $btn_add_color ?>;
            /* (ใช้สีธีม) */
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 600;
        }

        .btn-add:hover {
            color: white;
            filter: brightness(90%);
        }

        .btn-edit {
            background-color: <?= $btn_edit_color ?>;
        }

        .btn-delete {
            background-color: <?= $btn_delete_color ?>;
        }

        .btn-info {
            background-color: #0dcaf0;
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: <?= $theme_color ?>;
            /* (ใช้สีธีม) */
            box-shadow: 0 0 0 0.2rem rgba(<?= hexdec(substr($theme_color, 1, 2)) ?>, <?= hexdec(substr($theme_color, 3, 2)) ?>, <?= hexdec(substr($theme_color, 5, 2)) ?>, 0.25);
        }

        /* ... โค้ดสถานะและอื่นๆ เดิม ... */
        .alert {
            border-radius: 10px;
            border: none;
        }

        .badge-price {
            background-color: <?= $theme_color ?>;
            /* (ใช้สีธีม) */
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

        /* (13) *** CSS สถานะ (แก้ไข) *** */
        .status-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 500;
        }

        .status-in-stock {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        /* เขียว */
        .status-sold {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* แดง */
        .status-damage {
            background-color: #fff3cd;
            color: #856404;
        }

        /* เหลือง */
        .status-reserved {
            background-color: #d1edff;
            color: #0c63e4;
        }

        /* ฟ้า */
        .status-repair {
            background-color: #e2d9f3;
            color: #49287f;
        }

        /* ม่วง */

        /* (*** FIXED 2.1: CSS สำหรับเหตุผล) *** */
        .entry-type-badge {
            background-color: #e9ecef;
            color: #495057;
        }

        .entry-type-badge.po {
            background-color: #d1edff;
            color: #0c63e4;
        }

        .entry-type-badge.manual {
            background-color: #e2d9f3;
            color: #49287f;
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
            color: <?= $theme_color ?>;
            border-color: <?= $theme_color ?>;
        }

        .pagination .page-link:hover {
            background-color: <?= $theme_color ?>;
            color: white;
        }

        .pagination .page-item.active .page-link {
            background-color: <?= $theme_color ?>;
            border-color: <?= $theme_color ?>;
            color: white;
        }

        .sort-link {
            color: white;
            text-decoration: none;
        }

        .sort-link:hover {
            color: #f8f9fa;
        }

        .bulk-actions {
            background: rgba(255, 255, 255, 0.95);
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
            background-color: <?= $theme_color ?>;
            border-color: <?= $theme_color ?>;
        }

        /* -------------------------------------------------------------------- */
        /* --- **[เพิ่ม]** Responsive Override สำหรับ Mobile (จอเล็กกว่า 768px) --- */
        /* -------------------------------------------------------------------- */
        @media (max-width: 767.98px) {
            .main-header {
                padding: 1rem 0;
            }

            /* 1. ปรับ Filter Card Layout */
            .filter-card .d-flex {
                /* ทำให้องค์ประกอบภายใน Filter Card เรียงเป็นแนวตั้ง */
                flex-direction: column; 
                gap: 10px; 
            }

            /* 2. ทำให้ Form Control และ Button ใช้เต็มความกว้าง */
            .filter-card .form-control,
            .filter-card .form-select,
            .filter-card .btn {
                width: 100% !important; 
            }

            /* 3. ปรับ Table Cell Padding/Font */
            .table th, .table td {
                padding: 0.6rem 0.5rem; 
                font-size: 0.8rem; 
                white-space: nowrap; /* สำคัญเมื่อใช้ table-responsive */
            }
            
            /* 4. ปรับ Bulk Action Bar */
            .bulk-actions .d-flex {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="modal fade" id="deleteModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> ยืนยันการลบ</h5>
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
                                    <strong>คำเตือน:</strong> การลบจะไม่สามารถกู้คืนได้ (จะลบการเคลื่อนไหวของสต็อกนี้ด้วย)
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
                                <button type="submit" class="btn btn-danger" form="bulkForm" name="delete_multiple">ลบแน่นอน</button>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="main-header">
                    <div class="container">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h1 class="text-light">
                                    <i class="fas fa-warehouse me-3"></i>
                                    รายการสินค้าในสต็อก
                                    <small class="fs-6 opacity-75 d-block">(<?php echo number_format($total_records); ?> รายการ)</small>
                                </h1>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-end">
                                    <form method="GET" action="" class="d-flex align-items-center gap-2">
                                        <input type="hidden" name="filter_brand" value="<?php echo htmlspecialchars($filter_brand); ?>">
                                        <input type="hidden" name="filter_type" value="<?php echo htmlspecialchars($filter_type); ?>">
                                        <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($filter_status); ?>">
                                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_by); ?>">
                                        <input type="hidden" name="order" value="<?php echo htmlspecialchars($order); ?>">

                                        <div class="input-group" style="width: 350px;">
                                            <input type="text" class="form-control" name="search"
                                                value="<?php echo htmlspecialchars($search); ?>"
                                                placeholder="ค้นหารหัสสต็อก, ชื่อสินค้า, Serial No...">
                                            <button type="submit" class="btn btn-light">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                        <button type="button" class="btn btn-outline-light" id="toggleFilter"
                                            style="color: white; border-color: rgba(255,255,255,0.3);z-index:1;">
                                            <i class="fas fa-filter me-1"></i>กรองข้อมูล
                                        </button>
                                        <a href="add_prodStock.php" class="btn btn-add" style="z-index:1;">
                                            <i class="fas fa-plus me-1"></i>เพิ่มสินค้า
                                        </a>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="container">
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

                    <div class="card filter-card" id="filterCard" style="display: none;">
                        <div class="card-body">
                            <h5 class="card-title mb-3"><i class="fas fa-filter me-2"></i>ตัวกรองขั้นสูง</h5>
                            <form method="GET" action="" class="row g-3">
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">

                                <div class="col-md-3">
                                    <label class="form-label fw-bold"><i class="fas fa-building me-1"></i>ยี่ห้อ</label>
                                    <select class="form-select" name="filter_brand">
                                        <option value="">-- ทุกยี่ห้อ --</option>
                                        <?php mysqli_data_seek($brands_result, 0); // (ย้อน $brands_result) 
                                        ?>
                                        <?php while ($brand = mysqli_fetch_assoc($brands_result)): ?>
                                            <option value="<?php echo $brand['brand_id']; ?>"
                                                <?php echo $filter_brand == $brand['brand_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($brand['brand_name_th']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold"><i class="fas fa-layer-group me-1"></i>ประเภท</label>
                                    <select class="form-select" name="filter_type">
                                        <option value="">-- ทุกประเภท --</option>
                                        <?php mysqli_data_seek($types_result, 0); // (ย้อน $types_result) 
                                        ?>
                                        <?php while ($type = mysqli_fetch_assoc($types_result)): ?>
                                            <option value="<?php echo $type['type_id']; ?>"
                                                <?php echo $filter_type == $type['type_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type['type_name_th']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-bold"><i class="fas fa-tag me-1"></i>สถานะ</label>
                                    <select class="form-select" name="filter_status">
                                        <option value="">-- ทุกสถานะ --</option>
                                        <?php foreach ($status_options as $status): ?>
                                            <option value="<?php echo $status; ?>"
                                                <?php echo $filter_status == $status ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($status); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-bold">ราคาต่ำสุด</label>
                                    <input type="number" class="form-control" name="filter_price_min"
                                        value="<?php echo $filter_price_min ?: ''; ?>" placeholder="0" min="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-bold">ราคาสูงสุด</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="filter_price_max"
                                            value="<?php echo $filter_price_max ?: ''; ?>" placeholder="∞" min="0">
                                        <button class="btn btn-success" type="submit">
                                            <i class="fas fa-filter"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <a href="prod_stock.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>ล้างตัวกรองทั้งหมด
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="bulk-actions" id="bulkActions">
                        <form method="POST" id="bulkForm">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <span id="selectedCount">0</span> รายการที่เลือก
                                </div>
                                <div class="col-md-6 text-end">
                                    <button type="submit" name="delete_multiple" class="btn btn-outline-danger btn-sm" onclick="return confirmDeleteMultiple()">
                                        <i class="fas fa-trash me-1"></i>ลบที่เลือก
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="card table-card">
                        <div class="card-body p-0">
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th width="4%"><input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleSelectAll()"></th>
                                                <th width="10%">รหัสสต็อก</th>
                                                <th width="28%">สินค้า</th>
                                                <th width="15%">เหตุผล/ที่มา</th>
                                                <th width="18%">Serial Number</th>
                                                <th width="8%">ราคา</th>
                                                <th width="10%">สถานะ</th>
                                                <th width="12%">จัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                                <tr>
                                                    <td>
                                                        <?php if ($row['stock_status'] != 'Sold'): ?>
                                                            <input type="checkbox" class="form-check-input stock-checkbox"
                                                                value="<?= $row['stock_id'] ?>" onchange="updateBulkActions()">
                                                        <?php endif; ?>
                                                    </td>

                                                    <td>
                                                        <div class="stock-id"><?= htmlspecialchars($row['stock_id']) ?></div>
                                                    </td>

                                                    <td>
                                                        <div class="product-info"><?= htmlspecialchars($row['prod_name']) ?></div>
                                                        <div class="product-details">
                                                            <span class="badge bg-info" style="font-size: 0.7rem;"><?= htmlspecialchars($row['brand_name']) ?></span>
                                                            <span class="badge bg-warning text-dark" style="font-size: 0.7rem;"><?= htmlspecialchars($row['type_name']) ?></span>
                                                            <br><strong><?= htmlspecialchars($row['model_name']) ?></strong>
                                                        </div>
                                                    </td>

                                                    <td class="text-center">
                                                        <?php
                                                        $entry_type = $row['entry_type'];
                                                        $entry_text = 'ไม่ระบุ';
                                                        $entry_class = 'entry-type-badge';

                                                        if ($entry_type == 'order_details') {
                                                            $entry_text = 'รับจาก PO';
                                                            $entry_class .= ' po';
                                                        } elseif ($entry_type == 'MANUAL_ENTRY') {
                                                            $entry_text = 'ปรับสต็อก (กรอกเอง)';
                                                            $entry_class .= ' manual';
                                                        } elseif ($entry_type == 'FREEBIE') {
                                                            $entry_text = 'ของแถม';
                                                            $entry_class .= ' manual';
                                                        } elseif ($entry_type == 'RETURN') {
                                                            $entry_text = 'รับคืน';
                                                            $entry_class .= ' manual';
                                                        } elseif (!empty($entry_type)) {
                                                            $entry_text = $entry_type;
                                                            $entry_class .= ' manual';
                                                        }
                                                        ?>
                                                        <span class="status-badge <?= $entry_class ?>">
                                                            <?= htmlspecialchars($entry_text) ?>
                                                        </span>
                                                    </td>

                                                    <td>
                                                        <div style="font-size: 11px;">
                                                            <strong>Serial:</strong> <?= htmlspecialchars($row['serial_no']) ?>
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
                                                        $status_text = htmlspecialchars($row['stock_status']);
                                                        switch ($row['stock_status']) {
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
                                                        <?php if ($row['stock_status'] == 'Sold' && !empty($row['receipt_no'])): ?>
                                                            <div class="mt-1" style="font-size:11px; color:#555;">
                                                                บิล: <?= htmlspecialchars($row['receipt_no']) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>

                                                    <td class="text-center">
                                                        <div class="d-flex justify-content-center" style="gap: 3px;">
                                                            <a href="view_stock.php?id=<?= $row['stock_id'] ?>"
                                                                class="btn btn-info btn-sm text-light" title="ดูรายละเอียด">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="edit_stock.php?id=<?= $row['stock_id'] ?>"
                                                                class="btn btn-edit btn-sm text-light" title="แก้ไข">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <?php if ($row['stock_status'] != 'Sold'): ?>
                                                                <button type="button" class="btn btn-delete btn-sm"
                                                                    onclick="confirmDelete(<?= $row['stock_id'] ?>, '<?= htmlspecialchars($row['prod_name']) ?>')"
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
                                        <a href="add_prodStock.php" class="btn btn-add">
                                            <i class="fas fa-plus me-2"></i>เพิ่มสินค้า
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>                 
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        // Modal ลบหลายรายการ
        function confirmDeleteMultiple() {
            const checkboxes = document.querySelectorAll('.stock-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('กรุณาเลือกสินค้าที่ต้องการลบ');
                return false;
            }
            const text = `🗑 คุณเลือกสินค้าจำนวน ${checkboxes.length} รายการ\nคุณแน่ใจหรือไม่ว่าต้องการลบ? (สินค้าที่ "Sold" จะไม่ถูกลบ)`;
            document.getElementById('multipleDeleteText').textContent = text;
            const modal = new bootstrap.Modal(document.getElementById('confirmDeleteMultipleModal'));
            modal.show();
            return false;
        }

        // Modal ลบรายการเดียว
        function confirmDelete(stockId, productName) {
            document.getElementById('deleteStockId').textContent = stockId;
            document.getElementById('deleteProductName').textContent = productName;
            document.getElementById('deleteStockIdInput').value = stockId;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

        // Toggle Filter
        document.getElementById('toggleFilter').addEventListener('click', function() {
            const filterCard = document.getElementById('filterCard');
            if (filterCard.style.display === 'none' || filterCard.style.display === '') {
                filterCard.style.display = 'block';
                this.innerHTML = '<i class="fas fa-times me-1"></i>ปิดกรอง';
                this.classList.remove('btn-outline-light');
                this.classList.add('btn-secondary');
            } else {
                filterCard.style.display = 'none';
                this.innerHTML = '<i class="fas fa-filter me-1"></i>กรองข้อมูล';
                this.classList.remove('btn-secondary');
                this.classList.add('btn-outline-light');
            }
        });

        // แสดง Filter ถ้ามีการกรอง
        document.addEventListener('DOMContentLoaded', function() {
            const hasActiveFilters = <?= json_encode(!empty($filter_brand) || !empty($filter_type) || !empty($filter_status) || $filter_price_min > 0 || $filter_price_max > 0) ?>;
            if (hasActiveFilters) {
                document.getElementById('filterCard').style.display = 'block';
                const toggleBtn = document.getElementById('toggleFilter');
                toggleBtn.innerHTML = '<i class="fas fa-times me-1"></i>ปิดกรอง';
                toggleBtn.classList.remove('btn-outline-light');
                toggleBtn.classList.add('btn-secondary');
            }
        });

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
                const bulkForm = document.getElementById('bulkForm');
                const existingInputs = bulkForm.querySelectorAll('input[name="selected_stocks[]"]');
                existingInputs.forEach(input => input.remove());
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
    </script>
</body>

</html>