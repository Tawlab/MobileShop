<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'department');
// require '../config/load_theme.php';

// ค่าเริ่มต้น
$search_term = '';
$sort_column = 'dept_id';
$sort_order = 'ASC';
$page = 1;
$limit = 10; // จำนวนรายการต่อหน้า

// การค้นหา
if (isset($_GET['search']) && !empty($_GET['search'])) {
  $search_term = $_GET['search'];
}

// การจัดเรียง
if (isset($_GET['sort'])) {
  $sort_column = $_GET['sort'];
  $sort_order = $_GET['order'] ?? 'ASC';
}

// การแบ่งหน้า
if (isset($_GET['page'])) {
  $page = (int)$_GET['page'];
}
$offset = ($page - 1) * $limit;

// --- (3) สร้าง SQL Query แบบไดนามิก ---
$sql_where = "";
if (!empty($search_term)) {
  // ค้นหาจาก รหัส หรือ ชื่อแผนก
  $sql_where = " WHERE (dept_id LIKE ? OR dept_name LIKE ?)";
}

$sql_order = " ORDER BY $sort_column $sort_order";
$sql_limit = " LIMIT $limit OFFSET $offset";

// Query หลักสำหรับดึงข้อมูล
$sql_data = "SELECT * FROM departments $sql_where $sql_order $sql_limit";

// Query สำหรับนับจำนวนทั้งหมด (สำหรับการแบ่งหน้า)
$sql_count = "SELECT COUNT(*) as total FROM departments $sql_where";

// เตรียม Statement
$stmt_data = $conn->prepare($sql_data);
$stmt_count = $conn->prepare($sql_count);

if (!empty($search_term)) {
  $search_like = "%{$search_term}%";
  $stmt_data->bind_param("ss", $search_like, $search_like);
  $stmt_count->bind_param("ss", $search_like, $search_like);
}

// Execute และดึงข้อมูล
$stmt_data->execute();
$result = $stmt_data->get_result();

$stmt_count->execute();
$total_rows = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>จัดการแผนก</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <?php include '../config/load_theme.php'; ?>
</head>

<body>
  <div class="d-flex" id="wrapper">
    <?php include '../global/sidebar.php'; ?>
    <div class="main-content w-100">
      <div class="container-fluid py-4">
        <div class="container py-5">
          <div class="card shadow-lg rounded-4 p-4">

            <div class="d-flex justify-content-between align-items-center mb-4">
              <h4 class="mb-0"><i class="bi bi-diagram-3-fill me-2"></i>จัดการข้อมูลแผนก</h4>
              <a href="add_department.php" class="btn btn-add">
                <i class="bi bi-plus-circle-fill me-1"></i> เพิ่มแผนก
              </a>
            </div>

            <?php if (isset($_GET['success'])): ?>
              <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php
                if ($_GET['success'] == 'add') echo 'เพิ่มข้อมูลแผนกเรียบร้อย';
                if ($_GET['success'] == 'edit') echo 'แก้ไขข้อมูลแผนกเรียบร้อย';
                if ($_GET['success'] == 'delete') echo 'ลบข้อมูลแผนกเรียบร้อย';
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
            <?php elseif (isset($_GET['error'])): ?>
              <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php
                if ($_GET['error'] == 'delete_failed') echo 'ลบแผนกล้มเหลว';
                if ($_GET['error'] == 'has_employees') echo 'ไม่สามารถลบแผนกได้ เนื่องจากยังมีพนักงานในแผนกนี้';
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
            <?php endif; ?>


            <div class="row g-3 mb-3">
              <div class="col-md-12">
                <form method="GET" action="department.php">
                  <div class="input-group">
                    <input type="text" name="search" class="form-control"
                      placeholder="ค้นหารหัส หรือ ชื่อแผนก..."
                      value="<?= htmlspecialchars($search_term) ?>">

                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_column) ?>">
                    <input type="hidden" name="order" value="<?= htmlspecialchars($sort_order) ?>">

                    <button class="btn btn-outline-secondary" type="submit">
                      <i class="bi bi-search"></i> ค้นหา
                    </button>
                    <?php if (!empty($search_term)): ?>
                      <a href="department.php" class="btn btn-outline-danger">
                        <i class="bi bi-x-lg"></i> ล้าง
                      </a>
                    <?php endif; ?>
                  </div>
                </form>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-hover align-middle text-center">
                <thead class="table-light">
                  <tr>
                    <th>#</th>
                    <th>รหัสแผนก</th>
                    <th>ชื่อแผนก</th>
                    <th>รายละเอียด</th>
                    <th style="width: 150px;">จัดการ</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($result->num_rows > 0): ?>
                    <?php $index = $offset + 1;
                    while ($row = $result->fetch_assoc()): ?>
                      <tr>
                        <td><?= $index++ ?></td>
                        <td><?= htmlspecialchars($row['dept_id']) ?></td>
                        <td class="text-start"><?= htmlspecialchars($row['dept_name']) ?></td>
                        <td class="text-start"><?= htmlspecialchars($row['dept_desc'] ?? '-') ?></td>
                        <td>
                          <a href="edit_department.php?id=<?= $row['dept_id'] ?>"
                            class="btn btn-edit btn-sm" title="แก้ไข">
                            <i class="bi bi-pencil-fill"></i>
                          </a>
                          <button class="btn btn-delete btn-sm delete-btn"
                            data-id="<?= $row['dept_id'] ?>"
                            data-name="<?= htmlspecialchars($row['dept_name']) ?>"
                            title="ลบ">
                            <i class="bi bi-trash3-fill"></i>
                          </button>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="5" class="text-center text-muted py-3">
                        <i class="bi bi-info-circle me-1"></i> ไม่พบข้อมูล
                        <?php if (!empty($search_term)): ?>
                          (สำหรับคำค้นหา "<?= htmlspecialchars($search_term) ?>")
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <?php if ($total_pages > 1): ?>
              <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                  <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= $search_term ?>&sort=<?= $sort_column ?>&order=<?= $sort_order ?>">
                      <i class="bi bi-chevron-left"></i>
                    </a>
                  </li>

                  <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                      <a class="page-link" href="?page=<?= $i ?>&search=<?= $search_term ?>&sort=<?= $sort_column ?>&order=<?= $sort_order ?>">
                        <?= $i ?>
                      </a>
                    </li>
                  <?php endfor; ?>

                  <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= $search_term ?>&sort=<?= $sort_column ?>&order=<?= $sort_order ?>">
                      <i class="bi bi-chevron-right"></i>
                    </a>
                  </li>
                </ul>
              </nav>
            <?php endif; ?>

          </div>
        </div>

        <div class="modal fade" id="confirmDeleteModal" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title text-danger">
                  <i class="bi bi-exclamation-triangle-fill me-2"></i> ยืนยันการลบ
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <p id="deleteMessage" class="mb-0">คุณแน่ใจหรือไม่ว่าต้องการลบแผนกนี้?</p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                  <i class="bi bi-x-circle me-1"></i> ยกเลิก
                </button>
                <a id="confirmDeleteBtn" href="#" class="btn btn-delete">
                  <i class="bi bi-trash3-fill me-1"></i> ยืนยันการลบ
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
    document.addEventListener('DOMContentLoaded', function() {
      const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
      const deleteButtons = document.querySelectorAll('.delete-btn');
      const deleteMessage = document.getElementById('deleteMessage');
      const confirmBtn = document.getElementById('confirmDeleteBtn');

      deleteButtons.forEach(button => {
        button.addEventListener('click', () => {
          const id = button.getAttribute('data-id');
          const name = button.getAttribute('data-name');

          deleteMessage.innerHTML = `คุณต้องการลบแผนก <strong>"${name}"</strong> (รหัส ${id}) ใช่หรือไม่?`;
          confirmBtn.href = `delete_department.php?id=${encodeURIComponent(id)}`;

          modal.show();
        });
      });

      // ปิด Alert อัตโนมัติ
      setTimeout(() => {
        const alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(alert => {
          new bootstrap.Alert(alert).close();
        });
      }, 4000); // 4 วินาที
    });
  </script>
</body>

</html>