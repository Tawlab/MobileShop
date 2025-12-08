<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'sale_list');
// -----------------------------------------------------------------------------
//  SETTINGS & PAGINATION
// -----------------------------------------------------------------------------
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Sorting
$sort_col = isset($_GET['sort']) ? $_GET['sort'] : 'bh.bill_date';
$sort_ord = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// -----------------------------------------------------------------------------
// FILTERS
// -----------------------------------------------------------------------------
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$f_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$f_payment = isset($_GET['payment']) ? mysqli_real_escape_string($conn, $_GET['payment']) : '';
$f_date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$f_date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';

// เช็คว่ามีการกรองอยู่หรือไม่ 
$is_filtering = ($search || $f_status || $f_payment || $f_date_start || $f_date_end);

$where = [];

if ($search) {
    $where[] = "(bh.bill_id LIKE '%$search%' OR c.firstname_th LIKE '%$search%' OR c.lastname_th LIKE '%$search%' OR c.cs_phone_no LIKE '%$search%')";
}
if ($f_status) {
    $where[] = "bh.bill_status = '$f_status'";
}
if ($f_payment) {
    $where[] = "bh.payment_method = '$f_payment'";
}
if ($f_date_start) {
    $where[] = "DATE(bh.bill_date) >= '$f_date_start'";
}
if ($f_date_end) {
    $where[] = "DATE(bh.bill_date) <= '$f_date_end'";
}

$where_sql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// -----------------------------------------------------------------------------
// QUERY DATA
// -----------------------------------------------------------------------------
// Count Total
$sql_count = "SELECT COUNT(*) as total 
              FROM bill_headers bh 
              LEFT JOIN customers c ON bh.customers_cs_id = c.cs_id 
              $where_sql";
$res_count = mysqli_query($conn, $sql_count);
$total_rows = mysqli_fetch_assoc($res_count)['total'];
$total_pages = ceil($total_rows / $limit);

// Fetch Data
$sql = "SELECT bh.*, 
               c.firstname_th, c.lastname_th, c.cs_phone_no,
               e.firstname_th AS emp_fname
        FROM bill_headers bh
        LEFT JOIN customers c ON bh.customers_cs_id = c.cs_id
        LEFT JOIN employees e ON bh.employees_emp_id = e.emp_id
        $where_sql
        ORDER BY $sort_col $sort_ord
        LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);

// Query String for Pagination
function getQueryStr($exclude = [])
{
    $params = $_GET;
    foreach ($exclude as $k) unset($params[$k]);
    return http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>รายการขายสินค้า</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            color: <?= $text_color ?>;
        }

        .card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .status-badge {
            font-size: 0.85rem;
            padding: 5px 10px;
            border-radius: 20px;
            min-width: 80px;
            display: inline-block;
            text-align: center;
        }

        .bg-Pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .bg-Completed {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .bg-Canceled {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container py-5">

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 style="color: <?= $theme_color ?>;"><i class="fas fa-shopping-cart me-2"></i>รายการขายสินค้า</h3>
                        <div>
                            <button class="btn btn-outline-primary me-2" type="button" data-bs-toggle="collapse" data-bs-target="#filterSection" aria-expanded="<?= $is_filtering ? 'true' : 'false' ?>">
                                <i class="fas fa-filter"></i> ตัวกรอง
                            </button>
                            <a href="add_sale.php?return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-success">
                                <i class="fas fa-plus me-2"></i>เปิดบิลขายใหม่
                            </a>
                        </div>
                    </div>

                    <div class="collapse <?= $is_filtering ? 'show' : '' ?>" id="filterSection">
                        <div class="card mb-4">
                            <div class="card-body bg-light">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold text-muted">ค้นหาทั่วไป</label>
                                        <input type="text" name="search" class="form-control" placeholder="เลขบิล, ชื่อลูกค้า..." value="<?= htmlspecialchars($search) ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold text-muted">สถานะ</label>
                                        <select name="status" class="form-select">
                                            <option value="">-- ทั้งหมด --</option>
                                            <option value="Pending" <?= $f_status == 'Pending' ? 'selected' : '' ?>>รอดำเนินการ</option>
                                            <option value="Completed" <?= $f_status == 'Completed' ? 'selected' : '' ?>>สำเร็จ</option>
                                            <option value="Canceled" <?= $f_status == 'Canceled' ? 'selected' : '' ?>>ยกเลิก</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold text-muted">การชำระเงิน</label>
                                        <select name="payment" class="form-select">
                                            <option value="">-- ทั้งหมด --</option>
                                            <option value="Cash" <?= $f_payment == 'Cash' ? 'selected' : '' ?>>เงินสด</option>
                                            <option value="QR" <?= $f_payment == 'QR' ? 'selected' : '' ?>>QR Code</option>
                                            <option value="Credit" <?= $f_payment == 'Credit' ? 'selected' : '' ?>>บัตรเครดิต</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold text-muted">ช่วงวันที่</label>
                                        <div class="input-group">
                                            <input type="date" name="date_start" class="form-control" value="<?= $f_date_start ?>">
                                            <input type="date" name="date_end" class="form-control" value="<?= $f_date_end ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <div class="d-grid gap-2 w-100 d-md-flex">
                                            <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-search"></i> ค้นหา</button>
                                            <a href="sale_list.php" class="btn btn-outline-secondary"><i class="fas fa-sync"></i></a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead style="background-color: <?= $header_bg_color ?>; color: <?= $header_text_color ?>;">
                                        <tr>
                                            <th class="py-3 ps-4">เลขที่บิล</th>
                                            <th>วันที่ขาย</th>
                                            <th>ลูกค้า</th>
                                            <th>พนักงานขาย</th>
                                            <th>การชำระเงิน</th>
                                            <th class="text-center">สถานะ</th>
                                            <th class="text-center">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (mysqli_num_rows($result) > 0): ?>
                                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold text-primary">#<?= $row['bill_id'] ?></td>
                                                    <td><?= date('d/m/Y H:i', strtotime($row['bill_date'])) ?></td>
                                                    <td>
                                                        <?= $row['firstname_th'] . ' ' . $row['lastname_th'] ?>
                                                        <div class="small text-muted"><?= $row['cs_phone_no'] ?></div>
                                                    </td>
                                                    <td><?= $row['emp_fname'] ?></td>
                                                    <td>
                                                        <?php if ($row['payment_method'] == 'QR'): ?>
                                                            <span class="badge bg-info text-dark"><i class="fas fa-qrcode"></i> QR</span>
                                                        <?php elseif ($row['payment_method'] == 'Cash'): ?>
                                                            <span class="badge bg-success"><i class="fas fa-money-bill"></i> เงินสด</span>
                                                        <?php elseif ($row['payment_method'] == 'Credit'): ?>
                                                            <span class="badge bg-primary"><i class="fas fa-credit-card"></i> บัตร</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="status-badge bg-<?= $row['bill_status'] ?>">
                                                            <?= $row['bill_status'] ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="view_sale.php?id=<?= $row['bill_id'] ?>" class="btn btn-sm btn-outline-primary" title="ดูรายละเอียด">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        
                                                        <?php if ($row['bill_status'] == 'Pending'): ?>
                                                            <a href="payment_select.php?id=<?= $row['bill_id'] ?>" class="btn btn-sm btn-warning" title="ชำระเงิน/แก้ไข">
                                                                <i class="fas fa-wallet"></i>
                                                            </a>
                                                        <?php endif; ?>

                                                        <?php if ($row['bill_status'] != 'Canceled'): ?>
                                                            <a href="cancel_sale.php?id=<?= $row['bill_id'] ?>" 
                                                               class="btn btn-sm btn-outline-danger" 
                                                               title="ยกเลิกบิล"
                                                               onclick="return confirm('ยืนยันที่จะยกเลิกบิล #<?= $row['bill_id'] ?> นี้หรือไม่? \n(สินค้าจะถูกคืนเข้าสต็อก)')">
                                                                <i class="fas fa-ban"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-secondary" disabled title="ยกเลิกแล้ว">
                                                                <i class="fas fa-ban"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-5 text-muted">
                                                    <i class="fas fa-inbox fa-3x mb-3"></i><br>ไม่พบข้อมูลการขาย
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>&<?= getQueryStr(['page']) ?>">ก่อนหน้า</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&<?= getQueryStr(['page']) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>&<?= getQueryStr(['page']) ?>">ถัดไป</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>