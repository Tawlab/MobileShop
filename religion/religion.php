<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'religion');
$sql = "SELECT * FROM religions ORDER BY religion_id ASC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>จัดการศาสนา</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <?php
  require '../config/load_theme.php';
  ?>

  <style>
    .container {
      max-width: 900px;
    }

    th,
    td {
      vertical-align: middle;
      text-align: center;
    }
  </style>
</head>

<body>
  <div class="d-flex" id="wrapper">
    <?php include '../global/sidebar.php'; ?>
    <div class="main-content w-100">
      <div class="container-fluid py-4">

        <div class="container py-4">
          <div class="card shadow rounded-4 p-4">

            <?php if (!empty($_SESSION['success'])): ?>
              <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success'];
                unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
              <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error'];
                unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-3">
              <h4 class="m-0"><i class="bi bi-book me-2"></i>รายการศาสนา</h4>
              <a href="add_religion.php" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i> เพิ่มศาสนา
              </a>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-hover align-middle">
                <thead>
                  <tr>
                    <th>ลำดับ</th>
                    <th>รหัส</th>
                    <th>ชื่อศาสนา (ไทย)</th>
                    <th>ชื่อศาสนา (อังกฤษ)</th>
                    <th>สถานะ</th>
                    <th>การจัดการ</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $i = 1;
                  while ($row = mysqli_fetch_assoc($result)):
                  ?>
                    <tr>
                      <td><?= $i++ ?></td>
                      <td><?= $row['religion_id'] ?></td>
                      <td><?= htmlspecialchars($row['religion_name_th']) ?></td>
                      <td><?= htmlspecialchars($row['religion_name_en']) ?></td>
                      <td>
                        <i class="bi bi-check-circle-fill status-icon on toggle-status <?= $row['is_active'] ? '' : 'inactive' ?>"
                          data-id="<?= $row['religion_id'] ?>" data-status="1" title="เปิดใช้งาน"></i>
                        <i class="bi bi-x-circle-fill status-icon off toggle-status <?= !$row['is_active'] ? '' : 'inactive' ?>"
                          data-id="<?= $row['religion_id'] ?>" data-status="0" title="ปิดการใช้งาน"></i>
                      </td>

                      <td>
                        <a href="edit_religion.php?id=<?= $row['religion_id'] ?>" class="btn btn-edit btn-sm me-1">
                          <i class="bi bi-pencil-square"></i>
                        </a>
                        <button class="btn btn-delete btn-sm delete-btn"
                          data-id="<?= $row['religion_id'] ?>"
                          data-name="<?= htmlspecialchars($row['religion_name_th']) ?>">
                          <i class="bi bi-trash3-fill"></i>
                        </button>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="modal fade" id="confirmDeleteModal" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title danger"><i class="bi bi-exclamation-circle me-2"></i>ยืนยันการลบศาสนา</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <p id="deleteMessage" class="mb-0">คุณแน่ใจหรือไม่ว่าต้องการลบ?</p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <a id="confirmDeleteBtn" href="#" class="btn btn-delete">ลบ</a>
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
          deleteMessage.textContent = `คุณแน่ใจหรือไม่ว่าจะลบศาสนา "${name}" (รหัส ${id}) ?`;
          confirmBtn.href = `delete_religion.php?id=${encodeURIComponent(id)}`;
          modal.show();
        });
      });
    });
  </script>

  <script>
    document.querySelectorAll('.toggle-status').forEach(icon => {
      icon.addEventListener('click', () => {
        const id = icon.dataset.id;
        const newStatus = icon.dataset.status;
        fetch('toggle_religion_status.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `id=${id}&status=${newStatus}`
          })
          .then(res => res.text())
          .then(text => {
            if (text.trim() === 'updated') {
              const iconsInGroup = document.querySelectorAll(`.toggle-status[data-id="${id}"]`);
              iconsInGroup.forEach(i => {
                if (i.dataset.status === newStatus) {
                  i.classList.remove('inactive');
                } else {
                  i.classList.add('inactive');
                }
              });
            }
          })
          .catch(err => console.error(err));
      });
    });
  </script>
</body>

</html>