<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'customer_list');
// [1] รับค่าเส้นทางย้อนกลับ (ถ้ามีส่งมา เช่น มาจากหน้าขาย)
$return_to = isset($_GET['return_to']) ? urldecode($_GET['return_to']) : '';

// [2] กำหนดลิงก์ปุ่ม "ย้อนกลับ"
// ถ้ามี return_to ให้กลับไปที่นั่น, ถ้าไม่มีให้กลับ Dashboard
$btn_back_link = !empty($return_to) ? $return_to : '../dashboard.php';

// [3] กำหนดลิงก์ปุ่ม "เพิ่มลูกค้า"
// ส่งค่า return_to ต่อไปให้หน้า add_customer.php ด้วย
// (ถ้าไม่มี return_to ให้ส่งหน้าตัวเอง customer_list.php ไป เพื่อให้บันทึกเสร็จแล้วกลับมาหน้านี้)
$next_return = !empty($return_to) ? $return_to : 'customer_list.php';
$btn_add_link = "add_customer.php?return_to=" . urlencode($next_return);
// -----------------------------------------------------------------------------
// 1. SETTINGS & PAGINATION
// -----------------------------------------------------------------------------
$limit = 10; // แสดง 10 รายการต่อหน้า
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// -----------------------------------------------------------------------------
// 2. SORTING LOGIC (เรียงลำดับ)
// -----------------------------------------------------------------------------
// คอลัมน์ที่อนุญาตให้เรียงได้
$allowed_sorts = ['c.cs_id', 'c.firstname_th', 'c.cs_phone_no'];
$sort_col = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sorts) ? $_GET['sort'] : 'c.create_at'; // Default เรียงตามวันที่สร้าง
$sort_ord = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC'; // Default ล่าสุดขึ้นก่อน

// Helper Functions สำหรับสร้าง Link และ Query String
function build_query_string($exclude = [])
{
    $params = $_GET;
    foreach ($exclude as $key) unset($params[$key]);
    return http_build_query($params);
}

function get_sort_link($column, $label, $current_sort, $current_order)
{
    $new_order = ($current_sort == $column && $current_order == 'ASC') ? 'DESC' : 'ASC';
    $icon = '';
    if ($current_sort == $column) {
        $icon = $current_order == 'ASC' ? '<i class="fas fa-sort-up ms-1"></i>' : '<i class="fas fa-sort-down ms-1"></i>';
    } else {
        $icon = '<i class="fas fa-sort ms-1 text-muted" style="opacity:0.3;"></i>';
    }

    // สร้าง URL โดยรักษาค่า search เดิมไว้ แต่เปลี่ยน sort/order
    $qs = build_query_string(['sort', 'order']);
    $href = "?$qs&sort=$column&order=$new_order";

    return "<a href='$href' class='text-decoration-none text-dark fw-bold'>$label $icon</a>";
}

// -----------------------------------------------------------------------------
// 3. SEARCH & FILTER
// -----------------------------------------------------------------------------
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$where = [];

if (!empty($search)) {
    $where[] = "(
        c.firstname_th LIKE '%$search%' OR 
        c.lastname_th LIKE '%$search%' OR 
        c.cs_phone_no LIKE '%$search%' OR 
        c.cs_national_id LIKE '%$search%' OR
        c.cs_id LIKE '%$search%'
    )";
}

$where_sql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
$is_filtered = !empty($search); // เช็คว่ามีการกรองอยู่ไหม (เพื่อเปิดกล่องค้างไว้)

// -----------------------------------------------------------------------------
// 4. QUERY DATA
// -----------------------------------------------------------------------------
// นับจำนวนทั้งหมด
$sql_count = "SELECT COUNT(*) as total FROM customers c $where_sql";
$res_count = mysqli_query($conn, $sql_count);
$total_rows = mysqli_fetch_assoc($res_count)['total'];
$total_pages = ceil($total_rows / $limit);

// ดึงข้อมูล
$sql = "SELECT c.*, p.prefix_th 
        FROM customers c
        LEFT JOIN prefixs p ON c.prefixs_prefix_id = p.prefix_id
        $where_sql
        ORDER BY $sort_col $sort_ord
        LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ข้อมูลลูกค้า (Customers)</title>
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
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .table th {
            background-color: <?= $header_bg_color ?>;
            color: <?= $header_text_color ?>;
            white-space: nowrap;
        }

        /* Avatar วงกลมสำหรับตัวอักษรย่อ */
        .avatar-circle {
            width: 35px;
            height: 35px;
            background-color: #e9ecef;
            color: #495057;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container py-5">

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i> <?= $_SESSION['success'];
                                                                        unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle me-2"></i> <?= $_SESSION['error'];
                                                                            unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 style="color: <?= $theme_color ?>;"><i class="fas fa-users me-2"></i>ข้อมูลลูกค้า (ทั้งหมด <?= number_format($total_rows) ?> คน)</h3>

                        <div class="d-flex gap-2">
                            <!-- <a href="<?= htmlspecialchars($btn_back_link) ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> ย้อนกลับ
            </a> -->

                            <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="<?= $is_filtered ? 'true' : 'false' ?>">
                                <i class="fas fa-filter"></i> ตัวกรอง
                            </button>

                            <a href="<?= htmlspecialchars($btn_add_link) ?>" class="btn btn-success">
                                <i class="fas fa-user-plus me-2"></i> เพิ่มลูกค้า
                            </a>
                        </div>
                    </div>

                    <div class="collapse <?= $is_filtered ? 'show' : '' ?> mb-4" id="filterCollapse">
                        <div class="card card-body bg-light">
                            <form method="GET" class="row g-2 align-items-end">
                                <div class="col-md-10">
                                    <label class="form-label fw-bold"><i class="fas fa-search me-1"></i> ค้นหาข้อมูล</label>
                                    <input type="text" name="search" class="form-control" placeholder="ระบุชื่อ, นามสกุล, เบอร์โทร หรือ รหัสลูกค้า..." value="<?= htmlspecialchars($search) ?>">
                                </div>
                                <div class="col-md-2">
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> ค้นหา</button>
                                        <?php if ($search): ?>
                                            <a href="customer_list.php" class="btn btn-outline-secondary"><i class="fas fa-undo"></i> ล้างค่า</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center" width="80">ลำดับ</th>
                                            <th class="text-center" width="120">
                                                <?= get_sort_link('c.cs_id', 'รหัสลูกค้า', $sort_col, $sort_ord) ?>
                                            </th>
                                            <th class="ps-4">
                                                <?= get_sort_link('c.firstname_th', 'ชื่อ-นามสกุล', $sort_col, $sort_ord) ?>
                                            </th>
                                            <th>
                                                <?= get_sort_link('c.cs_phone_no', 'เบอร์โทรศัพท์', $sort_col, $sort_ord) ?>
                                            </th>
                                            <th class="text-center" width="150">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (mysqli_num_rows($result) > 0): ?>
                                            <?php
                                            $i = 0;
                                            while ($row = mysqli_fetch_assoc($result)):
                                                $i++;
                                                $seq = $offset + $i; // คำนวณลำดับต่อเนื่อง
                                            ?>
                                                <tr>
                                                    <td class="text-center text-muted"><?= $seq ?></td>
                                                    <td class="text-center fw-bold text-secondary"><?= $row['cs_id'] ?></td>
                                                    <td class="ps-4">
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-circle">
                                                                <?= mb_substr($row['firstname_th'], 0, 1) ?>
                                                            </div>
                                                            <div>
                                                                <span class="fw-bold"><?= $row['prefix_th'] . $row['firstname_th'] . ' ' . $row['lastname_th'] ?></span>
                                                                <?php if ($row['cs_line_id']): ?>
                                                                    <br><small class="text-success"><i class="fab fa-line"></i> <?= $row['cs_line_id'] ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="fw-bold text-primary"><?= $row['cs_phone_no'] ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="btn-group">
                                                            <a href="view_customer.php?id=<?= $row['cs_id'] ?>" class="btn btn-sm btn-outline-info" title="ดูรายละเอียด"><i class="fas fa-eye"></i></a>
                                                            <a href="edit_customer.php?id=<?= $row['cs_id'] ?>" class="btn btn-sm btn-outline-warning" title="แก้ไข"><i class="fas fa-edit"></i></a>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?= $row['cs_id'] ?>, '<?= $row['firstname_th'] ?>')" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-5 text-muted">
                                                    <i class="fas fa-user-slash fa-3x mb-3"></i><br>ไม่พบข้อมูลลูกค้า
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <?php if ($total_pages > 1): ?>
                            <div class="card-footer bg-white border-0 py-3">
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center mb-0">

                                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $page - 1 ?>&<?= getQueryStr(['page']) ?>">
                                                <i class="fas fa-chevron-left me-1"></i> ก่อนหน้า
                                            </a>
                                        </li>

                                        <?php
                                        $range = 2; // แสดงหน้าใกล้เคียง +/- 2
                                        for ($i = 1; $i <= $total_pages; $i++):
                                            if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)):
                                        ?>
                                                <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                                    <a class="page-link" href="?page=<?= $i ?>&<?= getQueryStr(['page']) ?>"><?= $i ?></a>
                                                </li>
                                            <?php elseif (($i == $page - $range - 1) || ($i == $page + $range + 1)): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif;
                                        endfor; ?>

                                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $page + 1 ?>&<?= getQueryStr(['page']) ?>">
                                                ถัดไป <i class="fas fa-chevron-right ms-1"></i>
                                            </a>
                                        </li>

                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function confirmDelete(id, name) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: `คุณต้องการลบข้อมูลลูกค้า "${name}" ใช่หรือไม่? การลบนี้ไม่สามารถกู้คืนได้`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `delete_customer.php?id=${id}`;
                }
            })
        }
    </script>

</body>

</html>