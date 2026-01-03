<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'prodtype');

// [1] รับค่าพื้นฐานจาก Session
$shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// [2] ตรวจสอบสิทธิ์ผู้ดูแลระบบ (Admin)
$is_super_admin = false;
$check_admin_sql = "SELECT r.role_name FROM roles r 
                    JOIN user_roles ur ON r.role_id = ur.roles_role_id 
                    WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt_admin = $conn->prepare($check_admin_sql)) {
  $stmt_admin->bind_param("i", $current_user_id);
  $stmt_admin->execute();
  if ($stmt_admin->get_result()->num_rows > 0) $is_super_admin = true;
  $stmt_admin->close();
}

// ==========================================
// [3] ส่วนประมวลผล AJAX (ทำงานเมื่อเรียกผ่าน Fetch API)
// ==========================================
if (isset($_GET['ajax'])) {
  $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
  $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
  $limit = 20; // 2. แสดงรายการ 20 รายการต่อหน้า
  $offset = ($page - 1) * $limit;

  // 3. กรองตามสิทธิ์ (ร้านค้าเห็นของตนเอง+ส่วนกลาง / แอดมินเห็นทั้งหมด)
  $conditions = [];
  if (!$is_super_admin) {
    $conditions[] = "(pt.shop_info_shop_id = 0 OR pt.shop_info_shop_id = '$shop_id')";
  }
  if (!empty($search)) {
    $conditions[] = "(pt.type_name_th LIKE '%$search%' OR pt.type_name_en LIKE '%$search%' OR pt.type_id LIKE '%$search%')";
  }

  $where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

  // นับจำนวนทั้งหมดเพื่อคำนวณหน้า
  $count_sql = "SELECT COUNT(*) as total FROM prod_types pt $where_sql";
  $total_items = $conn->query($count_sql)->fetch_assoc()['total'];
  $total_pages = ceil($total_items / $limit);

  // ดึงข้อมูลประเภทสินค้าพร้อมชื่อร้านผู้เพิ่ม
  $sql = "SELECT pt.*, sh.shop_name 
            FROM prod_types pt
            LEFT JOIN shop_info sh ON pt.shop_info_shop_id = sh.shop_id
            $where_sql 
            ORDER BY pt.shop_info_shop_id ASC, pt.type_id DESC 
            LIMIT $limit OFFSET $offset";
  $result = $conn->query($sql);
?>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="text-center" width="5%">#</th>
          <th width="10%">รหัส</th>
          <th width="30%">ชื่อประเภท (TH / EN)</th>
          <th width="20%" class="text-center">ผู้เพิ่มข้อมูล</th>
          <th width="15%" class="text-center">จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result->num_rows > 0): $idx = $offset + 1;
          while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td class="text-center text-muted fw-bold"><?= $idx++ ?></td>
              <td class="text-center small"><span class="badge bg-light text-dark border">#<?= $row['type_id'] ?></span></td>
              <td>
                <div class="fw-bold text-dark"><?= htmlspecialchars($row['type_name_th']) ?></div>
                <div class="small text-muted"><?= htmlspecialchars($row['type_name_en'] ?: '-') ?></div>
              </td>
              <td class="text-center">
                <?php if ($row['shop_info_shop_id'] == 0): ?>
                  <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3">
                    <i class="bi bi-globe2 me-1"></i> ส่วนกลาง
                  </span>
                <?php else: ?>
                  <span class="text-primary small fw-bold">
                    <i class="bi bi-shop me-1"></i> <?= htmlspecialchars($row['shop_name'] ?? 'ไม่ระบุร้าน') ?>
                  </span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <div class="btn-group gap-1">
                  <?php if ($is_super_admin || $row['shop_info_shop_id'] == $shop_id): ?>
                    <a href="edit_prodtype.php?id=<?= $row['type_id'] ?>" class="btn btn-outline-warning btn-sm border-0" title="แก้ไข"><i class="bi bi-pencil-square fs-5"></i></a>
                    <button onclick="confirmDelete(<?= $row['type_id'] ?>, '<?= addslashes($row['type_name_th']) ?>')" class="btn btn-outline-danger btn-sm border-0" title="ลบ"><i class="bi bi-trash3-fill fs-5"></i></button>
                  <?php else: ?>
                    <i class="bi bi-lock-fill text-muted" title="ข้อมูลส่วนกลาง (อ่านอย่างเดียว)"></i>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endwhile;
        else: ?>
          <tr>
            <td colspan="5" class="text-center py-5 text-muted">-- ไม่พบข้อมูลประเภทสินค้า --</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($total_pages > 1): ?>
    <nav class="mt-4">
      <ul class="pagination justify-content-center pagination-sm">
        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="1" title="หน้าแรก"><i class="bi bi-chevron-double-left"></i></a></li>
        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="<?= $page - 1 ?>"><i class="bi bi-chevron-left"></i></a></li>
        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
          <li class="page-item <?= ($page == $i) ? 'active' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="<?= $page + 1 ?>"><i class="bi bi-chevron-right"></i></a></li>
        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>"><a class="page-link ajax-page-link" href="#" data-page="<?= $total_pages ?>"><i class="bi bi-chevron-double-right"></i></a></li>
      </ul>
    </nav>
    <div class="d-flex justify-content-center mt-2 gap-2 align-items-center">
      <div class="input-group input-group-sm" style="max-width: 150px;">
        <input type="number" id="jumpPageInput" class="form-control text-center" placeholder="ไปหน้า" min="1" max="<?= $total_pages ?>">
        <button class="btn btn-success text-white" type="button" id="btnJumpPage">ไป</button>
      </div>
      <div class="small text-muted">หน้า <?= $page ?> / <?= $total_pages ?> (รวม <?= number_format($total_items) ?> รายการ)</div>
    </div>
<?php endif;
  exit();
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>จัดการประเภทสินค้า - Mobile Shop</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <?php require '../config/load_theme.php'; ?>
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
        <div class="container py-2" style="max-width: 1100px;">

          <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom-0">
              <h4 class="mb-0 text-success fw-bold"><i class="bi bi-diagram-3 me-2"></i>จัดการประเภทสินค้า (Product Types)</h4>
              <a href="add_prodtype.php" class="btn btn-success btn-sm fw-bold px-3">
                <i class="bi bi-plus-circle me-1"></i> เพิ่มประเภทสินค้า
              </a>
            </div>

            <div class="card-body p-4">
              <div class="row mb-4">
                <div class="col-md-6">
                  <div class="input-group shadow-sm" style="border-radius: 10px; overflow: hidden;">
                    <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="searchInput" class="form-control border-0" placeholder="ค้นหาชื่อประเภทสินค้า (ไทย/อังกฤษ)...">
                  </div>
                </div>
              </div>

              <div id="tableContainer">
                <div class="text-center py-5">
                  <div class="spinner-border text-success"></div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-danger text-white border-0">
          <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>ยืนยันการลบ</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-center py-4">
          <p class="fs-5 mb-1">ต้องการลบประเภทสินค้า <strong id="delName"></strong> ?</p>
          <p class="text-danger small mb-0"><i class="bi bi-info-circle me-1"></i>โปรดตรวจสอบว่าไม่มีสินค้าที่ผูกกับประเภทนี้อยู่ในระบบ</p>
        </div>
        <div class="modal-footer border-0 justify-content-center bg-light">
          <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
          <a id="confirmDelBtn" href="#" class="btn btn-danger rounded-pill px-4 shadow-sm">ยืนยันการลบ</a>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function fetchTypeData(page = 1) {
      const search = document.getElementById('searchInput').value;
      fetch(`prodtype.php?ajax=1&page=${page}&search=${encodeURIComponent(search)}`)
        .then(res => res.text()).then(data => document.getElementById('tableContainer').innerHTML = data);
    }

    document.getElementById('searchInput').addEventListener('input', () => fetchTypeData(1));

    document.addEventListener('click', e => {
      const link = e.target.closest('.ajax-page-link');
      if (link) {
        e.preventDefault();
        fetchTypeData(link.dataset.page);
      }
      if (e.target.id === 'btnJumpPage') {
        const p = document.getElementById('jumpPageInput').value;
        if (p > 0) fetchTypeData(p);
      }
    });

    function confirmDelete(id, name) {
    document.getElementById('delName').innerText = name;
    document.getElementById('confirmDelBtn').href = `delete_prodtype.php?id=${id}`;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

    window.onload = () => fetchTypeData();
  </script>
</body>

</html>