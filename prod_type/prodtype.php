<?php
session_start();
ob_start();

require '../config/config.php';
checkPageAccess($conn, 'prodtype');
require '../config/load_theme.php';

// [1] รับค่า Shop ID และ User ID
$shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// [2] ตรวจสอบสิทธิ์ "centralinf"
$has_centralinf_permission = false;
$check_perm_sql = "SELECT p.permission_id 
                   FROM permissions p
                   JOIN role_permissions rp ON p.permission_id = rp.permissions_permission_id
                   JOIN user_roles ur ON rp.roles_role_id = ur.roles_role_id
                   WHERE ur.users_user_id = ? 
                   AND p.permission_name = 'centralinf' 
                   LIMIT 1";

if ($stmt_perm = mysqli_prepare($conn, $check_perm_sql)) {
  mysqli_stmt_bind_param($stmt_perm, "i", $current_user_id);
  mysqli_stmt_execute($stmt_perm);
  mysqli_stmt_store_result($stmt_perm);
  if (mysqli_stmt_num_rows($stmt_perm) > 0) {
    $has_centralinf_permission = true;
  }
  mysqli_stmt_close($stmt_perm);
}

// [3] การจัดการการลบประเภท 
if (isset($_GET['delete_id'])) {
  $delete_id = $_GET['delete_id'];

  // เตรียม SQL ตามสิทธิ์
  if ($has_centralinf_permission) {
    // มีสิทธิ์ centralinf -> ลบได้ทุกอย่าง
    $delete_sql = "DELETE FROM prod_types WHERE type_id = ?";
  } else {
    // ไม่มีสิทธิ์ -> ลบได้เฉพาะของร้านตัวเอง
    $delete_sql = "DELETE FROM prod_types WHERE type_id = ? AND shop_info_shop_id = ?";
  }

  if ($stmt = mysqli_prepare($conn, $delete_sql)) {
    if ($has_centralinf_permission) {
      mysqli_stmt_bind_param($stmt, "s", $delete_id);
    } else {
      mysqli_stmt_bind_param($stmt, "si", $delete_id, $shop_id);
    }

    if (mysqli_stmt_execute($stmt)) {
      if (mysqli_stmt_affected_rows($stmt) > 0) {
        $_SESSION['success'] = "ลบประเภทสินค้าสำเร็จ";
      } else {
        $_SESSION['error'] = "คุณไม่สามารถลบประเภทสินค้านี้ได้ (อาจเป็นประเภทส่วนกลางหรือไม่มีสิทธิ์)";
      }
    } else {
      // ตรวจจับ Foreign Key Error
      if (mysqli_errno($conn) == 1451) {
        $_SESSION['error'] = "ลบไม่สำเร็จ: ประเภทนี้ถูกใช้งานโดยสินค้าในระบบแล้ว";
      } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการลบ: " . mysqli_error($conn);
      }
    }
    mysqli_stmt_close($stmt);
  }

  // ล้าง output buffer และ redirect
  ob_end_clean();
  header('Location: prodtype.php');
  exit();
}

// การค้นหา 
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'type_id'; // default sort
$order = isset($_GET['order']) && $_GET['order'] == 'desc' ? 'DESC' : 'ASC';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// สร้าง WHERE clause แบบ Hybrid
$where_conditions = [];
$where_conditions[] = "(shop_info_shop_id = 0 OR shop_info_shop_id = '$shop_id')";

if (!empty($search)) {
  $where_conditions[] = "(type_name_th LIKE '%$search%' OR type_name_en LIKE '%$search%')";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// นับจำนวน
$count_sql = "SELECT COUNT(*) as total FROM prod_types $where_clause";
$count_result = mysqli_query($conn, $count_sql);
$total_types = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_types / $items_per_page);

// ดึงข้อมูล (เพิ่ม shop_info_shop_id มาเช็คที่หน้า HTML)
$sql = "SELECT type_id, type_name_th, type_name_en, shop_info_shop_id 
        FROM prod_types 
        $where_clause 
        ORDER BY $sort_by $order 
        LIMIT $items_per_page OFFSET $offset";
$result = mysqli_query($conn, $sql);

// ฟังก์ชันช่วยสร้าง query string
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
  <title>จัดการประเภทสินค้า - Mobile Shop</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background-color: <?= $background_color ?>;
      color: <?= $text_color ?>;
      font-family: '<?= $font_style ?>', sans-serif;
      min-height: 100vh;
    }

    .main-header {
      background-color: <?= $theme_color ?>;
      /* Theme */
      color: white;
      padding: 1.5rem 0;
      margin-bottom: 1.5rem;
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

    .table-card {
      background: white;
      border: none;
      border-radius: 15px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }

    .table th {
      background-color: <?= $header_bg_color ?>;
      color: <?= $header_text_color ?>;
      font-weight: 600;
      border: 1px solid <?= $header_bg_color ?>;
      padding: 0.4rem 0.6rem;
      text-align: center;
      vertical-align: middle;
      font-size: 0.8rem;
    }

    .table td {
      padding: 0.4rem 0.6rem;
      vertical-align: middle;
      border: 1px solid #dee2e6;
      background-color: white;
      font-size: 0.75rem;
    }

    .table tbody tr {
      transition: all 0.3s ease;
    }

    .table tbody tr:hover {
      background-color: #f8f9fa;
    }

    .btn {
      border-radius: 8px;
      padding: 0.6rem 1.5rem;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .btn-success {
      background: <?= $btn_add_color ?>;
      border: none;
      color: white !important;
      box-shadow: 0 4px 15px <?= $btn_add_color ?>40;
    }

    .btn-success:hover {
      filter: brightness(90%);
    }

    .btn-warning {
      background-color: <?= $btn_edit_color ?>;
      border: none;
      color: #000 !important;
    }

    .btn-warning:hover {
      filter: brightness(90%);
    }

    .btn-danger {
      background-color: <?= $btn_delete_color ?>;
      border: none;
    }

    .btn-danger:hover {
      filter: brightness(90%);
    }

    .form-control:focus {
      border-color: <?= $theme_color ?>;
      box-shadow: 0 0 0 0.15rem <?= $theme_color ?>40;
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
      color: white;
      text-decoration: none;
    }

    .sort-link:hover {
      color: #f8f9fa;
    }

    .empty-state {
      text-align: center;
      padding: 3rem;
      color: #6c757d;
    }
  </style>
</head>

<body>
  <div class="d-flex" id="wrapper">
    <?php include '../global/sidebar.php'; ?>
    <div class="main-content w-100">
      <div class="container-fluid py-4">

        <div class="main-header py-3 text-white">
          <div class="container">
            <div class="row align-items-center gy-2">
              <div class="col-md-4">
                <h1 class="h4 mb-0 text-light">
                  <i class="bi bi-diagram-3 me-2"></i> จัดการประเภทสินค้า
                  <small class="fs-6 d-block opacity-75">
                    (<?php echo number_format($total_types); ?> รายการ)
                  </small>
                </h1>
              </div>
              <div class="col-md-8">
                <div class="d-flex justify-content-md-end flex-wrap gap-2">
                  <form method="GET" class="d-flex">
                    <?php if (isset($_GET['sort'])): ?>
                      <input type="hidden" name="sort" value="<?php echo htmlspecialchars($_GET['sort']); ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['order'])): ?>
                      <input type="hidden" name="order" value="<?php echo htmlspecialchars($_GET['order']); ?>">
                    <?php endif; ?>

                    <div class="input-group" style="max-width: 300px;">
                      <input type="text" name="search" class="form-control"
                        placeholder="ค้นหาประเภท (ไทย/อังกฤษ)..."
                        value="<?php echo htmlspecialchars($search); ?>"
                        autocomplete="off">
                      <button class="btn btn-light" type="submit">
                        <i class="bi bi-search"></i>
                      </button>
                      <?php if (!empty($search)): ?>
                        <a href="prodtype.php" class="btn btn-outline-light" title="ล้างการค้นหา">
                          <i class="bi bi-x-lg"></i>
                        </a>
                      <?php endif; ?>
                    </div>
                  </form>

                  <a href="add_prodtype.php" class="btn btn-warning text-dark" style="z-index: 1;">
                    <i class="bi bi-plus-circle me-1"></i> เพิ่มประเภท
                  </a>
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
          <?php if (isset($_SESSION['warning'])): ?>
            <div class="alert alert-warning alert-dismissible fade show">
              <i class="bi bi-exclamation-triangle-fill me-2"></i>
              <?php echo $_SESSION['warning'];
              unset($_SESSION['warning']); ?>
              <?php if (isset($_SESSION['errors'])): ?>
                <ul>
                  <?php foreach ($_SESSION['errors'] as $error): ?>
                    <li><?php echo $error; ?></li>
                  <?php endforeach; ?>
                </ul>
                <?php unset($_SESSION['errors']); ?>
              <?php endif; ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <div class="card table-card shadow-sm">
            <div class="card-body p-2">
              <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="table-responsive">
                  <table class="table table-hover table-sm align-middle mb-0 text-nowrap w-auto mx-auto">
                    <thead class="table-light">
                      <tr class="small">
                        <th class="text-center" width="10%">ลำดับ</th>
                        <th width="15%">
                          <a href="?sort=type_id&order=<?php echo ($sort_by == 'type_id' && $order == 'ASC') ? 'desc' : 'asc'; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&page=<?php echo $page; ?>" class="sort-link">
                            รหัส <i class="bi bi-arrow-down-up"></i>
                          </a>
                        </th>
                        <th width="30%">
                          <a href="?sort=type_name_th&order=<?php echo ($sort_by == 'type_name_th' && $order == 'ASC') ? 'desc' : 'asc'; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&page=<?php echo $page; ?>" class="sort-link">
                            ชื่อประเภท (ไทย) <i class="bi bi-arrow-down-up"></i>
                          </a>
                        </th>
                        <th width="30%">
                          <a href="?sort=type_name_en&order=<?php echo ($sort_by == 'type_name_en' && $order == 'ASC') ? 'desc' : 'asc'; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&page=<?php echo $page; ?>" class="sort-link">
                            ชื่อประเภท (อังกฤษ) <i class="bi bi-arrow-down-up"></i>
                          </a>
                        </th>
                        <th class="text-center" width="15%">จัดการ</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      $index = ($page - 1) * $items_per_page + 1;
                      while ($row = mysqli_fetch_assoc($result)):
                      ?>
                        <tr class="small">
                          <td class="text-center">
                            <span class="badge bg-secondary"><?php echo $index++; ?></span>
                          </td>
                          <td class="text-center">
                            <span class="badge bg-info">#<?php echo $row['type_id']; ?></span>
                          </td>
                          <td>
                            <div class="fw-bold"><?php echo htmlspecialchars($row['type_name_th']); ?></div>
                          </td>
                          <td>
                            <?php echo htmlspecialchars($row['type_name_en']); ?>
                          </td>
                          <td class="text-center">

                            <?php
                            // [เช็คเงื่อนไขแสดงปุ่ม] เป็นของร้านเรา หรือ มีสิทธิ์ "centralinf"
                            if ($row['shop_info_shop_id'] != 0 || $has_centralinf_permission):
                            ?>
                              <div class="d-flex justify-content-center gap-1">
                                <a href="edit_prodtype.php?id=<?php echo $row['type_id']; ?>"
                                  class="btn btn-warning btn-sm text-dark" title="แก้ไข">
                                  <i class="bi bi-pencil-square"></i>
                                </a>
                                <button type="button" class="btn btn-danger btn-sm"
                                  onclick="confirmDelete('<?php echo $row['type_id']; ?>', '<?php echo addslashes($row['type_name_th']); ?>')"
                                  title="ลบ">
                                  <i class="bi bi-trash3-fill"></i>
                                </button>
                              </div>
                            <?php else: ?>
                              <span class="badge bg-secondary bg-opacity-75 text-white" style="cursor: default;" title="ข้อมูลส่วนกลาง">
                                <i class="bi bi-globe2 me-1"></i>ส่วนกลาง
                              </span>
                            <?php endif; ?>

                          </td>
                        </tr>
                      <?php endwhile; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="empty-state text-center py-5 text-muted small">
                  <i class="bi bi-inbox-fill fa-2x mb-2"></i>
                  <h5>ไม่พบข้อมูลประเภทสินค้า</h5>
                  <p class="mb-3">ไม่มีประเภทที่ตรงกับเงื่อนไขการค้นหา</p>
                  <a href="add_prodtype.php" class="btn btn-success btn-sm">
                    <i class="bi bi-plus-circle me-1"></i>เพิ่มประเภท
                  </a>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-3">
              <nav>
                <ul class="pagination pagination-sm mb-0">
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
                  for ($i = $start; $i <= $end; $i++) {
                    echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . build_query_string(['page']) . '">' . $i . '</a></li>';
                  }
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

            <div class="text-center mt-2 text-muted small">
              แสดง <?php echo (($page - 1) * $items_per_page) + 1; ?> -
              <?php echo min($page * $items_per_page, $total_types); ?>
              จาก <?php echo number_format($total_types); ?> รายการ
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
                <p>คุณต้องการลบประเภท <strong id="typeName"></strong> ใช่หรือไม่?</p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                  <i class="bi bi-x-lg me-1"></i>ยกเลิก
                </button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-delete">
                  <i class="bi bi-trash3-fill me-1"></i>ลบประเภท
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
      document.getElementById('typeName').textContent = name;
      document.getElementById('confirmDeleteBtn').href = 'prodtype.php?delete_id=' + id;
      new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
  </script>
</body>

</html>