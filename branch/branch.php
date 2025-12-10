<?php
session_start();
require '../config/config.php';

// ตรวจสอบสิทธิ์
checkPageAccess($conn, 'branch');
 
// จัดการการค้นหา
$search = $_GET['search'] ?? '';
$where_clause = '';
if (!empty($search)) {
    $search_safe = mysqli_real_escape_string($conn, $search);
    $where_clause = "WHERE b.branch_name LIKE '%$search_safe%' 
                     OR b.branch_code LIKE '%$search_safe%' 
                     OR b.branch_phone LIKE '%$search_safe%'
                     OR s.shop_name LIKE '%$search_safe%'";
}

// ดึงข้อมูลสาขา
$sql = "SELECT b.*, s.shop_name 
        FROM branches b
        LEFT JOIN shop_info s ON b.shop_info_shop_id = s.shop_id
        $where_clause
        ORDER BY b.branch_id DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสาขา</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        .btn-add {
            background-color: <?= $btn_add_color ?>;
            color: white;
        }

        .btn-edit {
            background-color: <?= $btn_edit_color ?>;
            color: black;
        }

        .btn-delete {
            background-color: <?= $btn_delete_color ?>;
            color: white;
        }
    </style>
</head>

<body>

    <div class="d-flex" id="wrapper">

        <?php include '../global/sidebar.php'; ?>

        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="row">
                    <div class="col-md-12">

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3 style="color: <?= $theme_color ?>;"><i class="fas fa-store-alt me-2"></i> จัดการสาขา</h3>
                            <a href="add_branch.php" class="btn btn-add">
                                <i class="fas fa-plus-circle me-1"></i> เพิ่มสาขา
                            </a>
                        </div>

                        <div class="card border-0 shadow-sm mb-4 bg-white">
                            <div class="card-body">
                                <form method="GET" action="branch.php" class="row g-2">
                                    <div class="col-md-10">
                                        <input type="text" name="search" class="form-control"
                                            placeholder="ค้นหา (รหัสสาขา, ชื่อสาขา, เบอร์โทร, ชื่อร้าน)..."
                                            value="<?= htmlspecialchars($search) ?>">
                                    </div>
                                    <div class="col-md-2 d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search me-1"></i> ค้นหา
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead style="background-color: <?= $header_bg_color ?>; color: <?= $header_text_color ?>;">
                                            <tr>
                                                <th class="ps-3">#</th>
                                                <th>รหัสสาขา</th>
                                                <th>ชื่อสาขา</th>
                                                <th>เบอร์โทร</th>
                                                <th>สังกัดร้าน</th>
                                                <th class="text-center" style="width: 150px;">จัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (mysqli_num_rows($result) > 0): ?>
                                                <?php
                                                $i = 1;
                                                while ($row = mysqli_fetch_assoc($result)):
                                                ?>
                                                    <tr>
                                                        <td class="ps-3"><?= $i++ ?></td>
                                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['branch_code']) ?></span></td>
                                                        <td class="fw-bold"><?= htmlspecialchars($row['branch_name']) ?></td>
                                                        <td><?= htmlspecialchars($row['branch_phone']) ?></td>
                                                        <td><?= htmlspecialchars($row['shop_name']) ?></td>
                                                        <td class="text-center">
                                                            <a href="edit_branch.php?id=<?= $row['branch_id'] ?>"
                                                                class="btn btn-edit btn-sm" title="แก้ไข">
                                                                <i class="fas fa-edit"></i>
                                                            </a>

                                                            <a href="delete_branch.php?id=<?= $row['branch_id'] ?>"
                                                                class="btn btn-delete btn-sm"
                                                                onclick="return confirm('คุณต้องการลบสาขานี้ใช่หรือไม่?');" title="ลบ">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center py-5 text-muted">
                                                        <i class="fas fa-store-slash fa-3x mb-3"></i><br>ไม่พบข้อมูลสาขา
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
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
<?php mysqli_close($conn); ?>