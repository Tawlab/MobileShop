<?php
session_start();
ob_start(); 

require '../config/config.php';
checkPageAccess($conn, 'product');
require '../config/load_theme.php';

// การค้นหาและกรองข้อมูล
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$brand_filter = isset($_GET['brand']) ? mysqli_real_escape_string($conn, $_GET['brand']) : '';
$type_filter = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';
$price_min = isset($_GET['price_min']) && $_GET['price_min'] !== '' ? (float)$_GET['price_min'] : '';
$price_max = isset($_GET['price_max']) && $_GET['price_max'] !== '' ? (float)$_GET['price_max'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'p.prod_id';
$order = isset($_GET['order']) && $_GET['order'] == 'desc' ? 'DESC' : 'ASC';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

//  สร้าง WHERE clause
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(p.prod_id LIKE '%$search%' OR p.prod_name LIKE '%$search%' OR p.model_name LIKE '%$search%' OR p.model_no LIKE '%$search%')";
}
if (!empty($brand_filter)) {
    $where_conditions[] = "p.prod_brands_brand_id = '$brand_filter'";
}
if (!empty($type_filter)) {
    $where_conditions[] = "p.prod_types_type_id = '$type_filter'";
}
if ($price_min !== '') {
    $where_conditions[] = "p.prod_price >= $price_min";
}
if ($price_max !== '') {
    $where_conditions[] = "p.prod_price <= $price_max";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// นับจำนวนสินค้าทั้งหมด
$count_sql = "SELECT COUNT(*) as total FROM products p $where_clause";
$count_result = mysqli_query($conn, $count_sql);
$total_products = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_products / $items_per_page);

//  สำหรับดึงข้อมูลสินค้า
$sql = "SELECT p.prod_id, p.prod_name, p.prod_desc, p.model_name, p.model_no, p.prod_price, 
               pb.brand_name_th as brand_name, pt.type_name_th as type_name 
        FROM products p 
        LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id 
        LEFT JOIN prod_types pt ON p.prod_types_type_id = pt.type_id 
        $where_clause 
        ORDER BY $sort_by $order
        LIMIT $items_per_page OFFSET $offset";

$result = mysqli_query($conn, $sql);

// ดึงข้อมูลสำหรับ dropdown filter
$brands_sql = "SELECT brand_id, brand_name_th FROM prod_brands ORDER BY brand_name_th";
$brands_result = mysqli_query($conn, $brands_sql);

$types_sql = "SELECT type_id, type_name_th FROM prod_types ORDER BY type_name_th";
$types_result = mysqli_query($conn, $types_sql);

function build_query_string($exclude = [])
{
    $params = $_GET;
    foreach ($exclude as $key) {
        unset($params[$key]);
    }
    return !empty($params) ? '&' . http_build_query($params) : '';
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการสินค้า - Mobile Shop</title>
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

        .filter-card,
        .table-card {
            background: white;
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .table th {
            background-color: <?= $header_bg_color ?>;
            color: <?= $header_text_color ?>;
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
            font-size: 0.85rem;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .btn-success {
            background-color: <?= $btn_add_color ?>;
            border: none;
            color: white !important;
        }

        .btn-warning {
            background-color: <?= $btn_edit_color ?>;
            border: none;
            color: #000 !important;
        }

        .btn-danger {
            background-color: <?= $btn_delete_color ?>;
            border: none;
            color: white !important;
        }

        .btn-info {
            background-color: #0dcaf0;
            border: none;
            color: white !important;
        }

        .btn:hover {
            filter: brightness(90%);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: <?= $theme_color ?>;
            box-shadow: 0 0 0 0.2rem <?= $theme_color ?>40;
        }

        .pagination .page-link {
            color: <?= $theme_color ?>;
            border-color: <?= $theme_color ?>;
        }

        .pagination .page-link:hover {
            background-color: <?= $theme_color ?>;
            border-color: <?= $theme_color ?>;
            color: white;
        }

        .pagination .page-item.active .page-link {
            background-color: <?= $theme_color ?>;
            border-color: <?= $theme_color ?>;
            color: white;
        }

        .sort-link {
            color: <?= $header_text_color ?>;
            text-decoration: none;
        }

        .sort-link:hover {
            color: #f8f9fa;
        }

        .badge-price {
            background-color: <?= $status_on_color ?>;
            color: white;
        }

        .product-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
        }

        .product-model {
            font-size: 0.75rem;
            color: #6c757d;
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
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="main-header">
                    <div class="container">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <h1 class="text-light">
                                    <i class="bi bi-boxes me-3"></i> 
                                    จัดการสินค้า
                                    <small class="fs-6 opacity-75 d-block">(<?php echo number_format($total_products); ?> รายการ)</small>
                                </h1>
                            </div>
                            <div class="col-md-8">
                                <div class="header-controls">
                                    <form method="GET" action="" class="row g-2 align-items-center">
                                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_by); ?>">
                                        <input type="hidden" name="order" value="<?php echo htmlspecialchars($order); ?>">
                                        <div class="col-md-6">
                                            <div class="input-group">
                                                <input type="text" class="form-control" name="search"
                                                    value="<?php echo htmlspecialchars($search); ?>"
                                                    placeholder="ค้นหารหัสสินค้า, ชื่อสินค้า, รุ่น, หรือรหัสสินค้า...">
                                                <button type="submit" class="btn btn-light">
                                                    <i class="bi bi-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex gap-2 justify-content-end">
                                                <button type="button" class="btn btn-light btn-sm" id="toggleFilter">
                                                    <i class="bi bi-filter me-1"></i>กรองข้อมูล
                                                </button>
                                                <a href="add_product.php" class="btn btn-warning text-dark btn-sm">
                                                    <i class="bi bi-plus-circle me-1"></i>เพิ่มสินค้า
                                                </a>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="container">
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?php echo $_SESSION['success'];
                            unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php echo $_SESSION['error'];
                            unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- ฟิลเตอร์ -->
                    <div class="card filter-card" id="filterCard" style="display: none;">
                        <div class="card-body">
                            <h5 class="card-title mb-3">
                                <i class="bi bi-filter me-2"></i>ตัวกรองขั้นสูง
                            </h5>
                            <form method="GET" action="" class="row g-3">
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-building me-1"></i>ยี่ห้อ
                                    </label>
                                    <select class="form-select" name="brand">
                                        <option value="">-- ทุกยี่ห้อ --</option>
                                        <?php while ($brand = mysqli_fetch_assoc($brands_result)): ?>
                                            <option value="<?php echo $brand['brand_id']; ?>"
                                                <?php echo $brand_filter == $brand['brand_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($brand['brand_name_th']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-diagram-3 me-1"></i>ประเภท
                                    </label>
                                    <select class="form-select" name="type">
                                        <option value="">-- ทุกประเภท --</option>
                                        <?php while ($type = mysqli_fetch_assoc($types_result)): ?>
                                            <option value="<?php echo $type['type_id']; ?>"
                                                <?php echo $type_filter == $type['type_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type['type_name_th']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-cash-coin me-1"></i>ราคาต่ำสุด
                                    </label>
                                    <input type="number" class="form-control" name="price_min"
                                        value="<?php echo $price_min; ?>"
                                        placeholder="0" min="0" step="0.01">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-cash-coin me-1"></i>ราคาสูงสุด
                                    </label>
                                    <input type="number" class="form-control" name="price_max"
                                        value="<?php echo $price_max; ?>"
                                        placeholder="ไม่จำกัด" min="0" step="0.01">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="w-100">
                                        <button type="submit" class="btn btn-success w-100 mb-1">
                                            <i class="bi bi-search me-1"></i>กรอง
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary w-100 btn-sm" onclick="clearAllFilters()">
                                            <i class="bi bi-x-lg me-1"></i>ล้างทั้งหมด
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <?php
                            $active_filters = [];
                            if (!empty($search)) $active_filters[] = "ค้นหา: " . htmlspecialchars($search);
                            if (!empty($brand_filter)) {
                                mysqli_data_seek($brands_result, 0);
                                while ($brand = mysqli_fetch_assoc($brands_result)) {
                                    if ($brand['brand_id'] == $brand_filter) {
                                        $active_filters[] = "ยี่ห้อ: " . htmlspecialchars($brand['brand_name_th']);
                                        break;
                                    }
                                }
                            }
                            if (!empty($type_filter)) {
                                mysqli_data_seek($types_result, 0);
                                while ($type = mysqli_fetch_assoc($types_result)) {
                                    if ($type['type_id'] == $type_filter) {
                                        $active_filters[] = "ประเภท: " . htmlspecialchars($type['type_name_th']);
                                        break;
                                    }
                                }
                            }
                            if ($price_min !== '' || $price_max !== '') {
                                $price_text = "ราคา: ";
                                if ($price_min !== '' && $price_max !== '') {
                                    $price_text .= number_format($price_min) . " - " . number_format($price_max);
                                } elseif ($price_min !== '') {
                                    $price_text .= "ตั้งแต่ " . number_format($price_min);
                                } else {
                                    $price_text .= "ไม่เกิน " . number_format($price_max);
                                }
                                $active_filters[] = $price_text;
                            }
                            ?>
                            <?php if (!empty($active_filters)): ?>
                                <div class="mt-3 pt-3 border-top">
                                    <small class="text-muted fw-bold">ตัวกรองที่ใช้งาน:</small>
                                    <div class="mt-2">
                                        <?php foreach ($active_filters as $filter): ?>
                                            <span class="badge bg-info me-2 mb-1"><?php echo $filter; ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card table-card">
                        <div class="card-body p-0">
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th width="6%">
                                                    <a href="?sort=p.prod_id&order=<?php echo ($sort_by == 'p.prod_id' && $order == 'ASC') ? 'desc' : 'asc'; ?><?php echo build_query_string(['sort', 'order']); ?>" class="sort-link">
                                                        ID <i class="bi bi-arrow-down-up"></i>
                                                    </a>
                                                </th>
                                                <th width="22%">
                                                    <a href="?sort=p.prod_name&order=<?php echo ($sort_by == 'p.prod_name' && $order == 'ASC') ? 'desc' : 'asc'; ?><?php echo build_query_string(['sort', 'order']); ?>" class="sort-link">
                                                        ชื่อสินค้า <i class="bi bi-arrow-down-up"></i>
                                                    </a>
                                                </th>
                                                <th width="12%">ยี่ห้อ</th>
                                                <th width="12%">ประเภท</th>
                                                <th width="20%">รุ่น</th>
                                                <th width="12%">
                                                    <a href="?sort=p.prod_price&order=<?php echo ($sort_by == 'p.prod_price' && $order == 'ASC') ? 'desc' : 'asc'; ?><?php echo build_query_string(['sort', 'order']); ?>" class="sort-link">
                                                        ราคา <i class="bi bi-arrow-down-up"></i>
                                                    </a>
                                                </th>
                                                <th width="16%">จัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                                <tr>
                                                    <td class="text-center">
                                                        <span class="badge bg-secondary">#<?php echo $row['prod_id']; ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="product-name"><?php echo htmlspecialchars($row['prod_name']); ?></div>
                                                        <?php if (!empty($row['prod_desc'])): ?>
                                                            <div class="product-model"><?php echo htmlspecialchars(mb_substr($row['prod_desc'], 0, 30)); ?>...</div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-info" style="font-size: 0.75rem;">
                                                            <?php echo htmlspecialchars($row['brand_name'] ?? 'ไม่ระบุ'); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-warning text-dark" style="font-size: 0.75rem;">
                                                            <?php echo htmlspecialchars($row['type_name'] ?? 'ไม่ระบุ'); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="fw-bold" style="font-size: 0.85rem;"><?php echo htmlspecialchars($row['model_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($row['model_no']); ?></small>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge badge-price" style="font-size: 0.8rem;">
                                                            ฿<?php echo number_format($row['prod_price'], 0); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="d-flex justify-content-center" style="gap: 3px;">
                                                            <a href="view_product.php?id=<?php echo $row['prod_id']; ?>"
                                                                class="btn btn-info btn-sm text-light" title="ดูรายละเอียด">
                                                                <i class="bi bi-eye-fill"></i>
                                                            </a>
                                                            <a href="edit_product.php?id=<?php echo $row['prod_id']; ?>"
                                                                class="btn btn-warning btn-sm text-dark" title="แก้ไข">
                                                                <i class="bi bi-pencil-fill"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-danger btn-sm"
                                                                onclick="confirmDelete('<?php echo $row['prod_id']; ?>', '<?php echo addslashes($row['prod_name']); ?>')"
                                                                title="ลบ">
                                                                <i class="bi bi-trash3-fill"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="bi bi-inbox-fill"></i>
                                    <h4>ไม่พบข้อมูลสินค้า</h4>
                                    <p>ไม่มีสินค้าที่ตรงกับเงื่อนไขการค้นหา</p>
                                    <a href="add_product.php" class="btn btn-success">
                                        <i class="bi bi-plus-circle me-2"></i>เพิ่มสินค้า
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-center mt-4">
                            <nav aria-label="Page navigation">
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page - 1); ?><?php echo build_query_string(['page']); ?>">
                                                <i class="bi bi-chevron-left"></i> ก่อนหน้า
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link"><i class="bi bi-chevron-left"></i> ก่อนหน้า</span>
                                        </li>
                                    <?php endif; ?>

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

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page + 1); ?><?php echo build_query_string(['page']); ?>">
                                                ถัดไป <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">ถัดไป <i class="bi bi-chevron-right"></i></span>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="modal fade" id="deleteModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title danger">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>ยืนยันการลบ
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>คุณต้องการลบสินค้า <strong id="productName"></strong> ใช่หรือไม่?</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x-lg me-1"></i>ยกเลิก
                                </button>
                                <a href="#" id="confirmDeleteBtn" class="btn btn-delete">
                                    <i class="bi bi-trash3-fill me-1"></i>ลบสินค้า
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
        // ฟังก์ชันยืนยันการลบ
        function confirmDelete(id, name) {
            document.getElementById('productName').textContent = name;
            document.getElementById('confirmDeleteBtn').href = 'delete_product.php?id=' + id;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Toggle filter card
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('toggleFilter');
            const filterCard = document.getElementById('filterCard');

            // ตรวจสอบว่ามีการกรองอยู่หรือไม่
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('brand') || urlParams.has('type') || urlParams.has('price_min') || urlParams.has('price_max')) {
                if (urlParams.get('brand') || urlParams.get('type') || urlParams.get('price_min') || urlParams.get('price_max')) {
                    filterCard.style.display = 'block';
                    toggleBtn.innerHTML = '<i class="bi bi-x-lg me-1"></i>ปิดกรอง';
                    toggleBtn.classList.remove('btn-light');
                    toggleBtn.classList.add('btn-secondary');
                }
            }

            if (toggleBtn && filterCard) {
                toggleBtn.addEventListener('click', function() {
                    const isHidden = filterCard.style.display === 'none' || filterCard.style.display === '';
                    if (isHidden) {
                        filterCard.style.display = 'block';
                        this.innerHTML = '<i class="bi bi-x-lg me-1"></i>ปิดกรอง';
                        this.classList.remove('btn-light');
                        this.classList.add('btn-secondary');
                    } else {
                        filterCard.style.display = 'none';
                        this.innerHTML = '<i class="bi bi-filter me-1"></i>กรองข้อมูล';
                        this.classList.remove('btn-secondary');
                        this.classList.add('btn-light');
                    }
                });
            }
        });

        // Clear all filters function
        function clearAllFilters() {
            window.location.href = 'product.php';
        }

        // Auto-submit หลังจากเลือก filter
        document.querySelectorAll('select[name="brand"], select[name="type"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>

</html>