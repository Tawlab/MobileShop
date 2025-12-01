<?php
// === File: subdistricts/subdistricts.php ===
session_start();
require '../config/config.php';
checkPageAccess($conn, 'subdistricts');
// require '../config/load_theme.php'; // ธีมจะถูกกำหนดในไฟล์นี้โดยตรง

// แก้ไข SQL ให้ตรงกับ Schema
$sql = "SELECT s.subdistrict_id, s.subdistrict_name_th, s.subdistrict_name_en, s.zip_code, d.district_name_th
        FROM subdistricts s
        LEFT JOIN districts d ON s.districts_district_id = d.district_id
        ORDER BY d.district_name_th ASC, s.subdistrict_id ASC";
$result = $conn->query($sql);

// --- ระบบแจ้งเตือน (Alert System) ---
$alert_message = '';
$alert_type = '';
if (isset($_GET['add_success']) && $_GET['add_success'] == 'true') {
  $alert_message = 'เพิ่มข้อมูลตำบลใหม่สำเร็จ';
  $alert_type = 'success';
} elseif (isset($_GET['edit_success']) && $_GET['edit_success'] == 'true') {
  $alert_message = 'แก้ไขข้อมูลตำบลสำเร็จ';
  $alert_type = 'success';
} elseif (isset($_GET['delete_success']) && $_GET['delete_success'] == '1') {
  $alert_message = 'ลบข้อมูลตำบลสำเร็จ';
  $alert_type = 'success';
} elseif (isset($_GET['error'])) {
  // (ปรับปรุง error message จากไฟล์ต้นฉบับ)
  if ($_GET['error'] == 'has_addresses') {
    $alert_message = 'ไม่สามารถลบตำบลได้ เนื่องจากมีข้อมูลที่อยู่ (Addresses) อ้างอิงอยู่';
  } elseif ($_GET['error'] == 'not_found') {
    $alert_message = 'ไม่พบข้อมูลตำบลที่ต้องการ';
  } else {
    $alert_message = 'เกิดข้อผิดพลาดในการดำเนินการ';
  }
  $alert_type = 'danger';
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>จัดการตำบล</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <?php require '../config/load_theme.php'; ?>
  <style>
    body {
      background-color: #f4f7f6;
    }

    .card {
      border: none;
      border-radius: 12px;
    }

    .table thead th {
      background-color: #e6f7ee;
      color: #004d40;
      border-bottom-width: 0;
    }

    .table-hover tbody tr:hover {
      background-color: #f0fff4;
    }

    .btn-success {
      background-color: #28a745;
      border-color: #28a745;
    }

    .btn-success:hover {
      background-color: #218838;
      border-color: #1e7e34;
    }

    .modal-header .text-danger {
      color: #dc3545 !important;
    }
  </style>
</head>

<body class="bg-light">
  <div class="d-flex" id="wrapper">
    <?php include '../global/sidebar.php'; ?>
    <div class="main-content w-100">
      <div class="container-fluid py-4">
        <div class="container py-4">

          <?php if ($alert_message): ?>
            <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
              <?php echo $alert_message; ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <div class="card shadow-sm rounded-4 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h4 class="m-0 text-success"><i class="bi bi-geo-fill me-2"></i>รายการตำบล</h4>
              <a href="add_subdistricts.php" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i> เพิ่มตำบล
              </a>
            </div>

            <div class="table-responsive">
              <table class="table table-hover align-middle text-center">
                <thead class="align-middle">
                  <tr>
                    <th style="width: 8%;">ลำดับ</th>
                    <th style="width: 12%;">รหัสตำบล</th>
                    <th style="width: 20%;">ชื่อตำบล (ไทย)</th>
                    <th style="width: 20%;">ชื่อตำบล (อังกฤษ)</th>
                    <th style="width: 15%;">รหัสไปรษณีย์</th>
                    <th style="width: 15%;">อำเภอ</th>
                    <th style="width: 10%;">จัดการ</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $i = 1;
                  while ($row = $result->fetch_assoc()): ?>
                    <tr>
                      <td><?= $i++ ?></td>
                      <td><?= htmlspecialchars($row['subdistrict_id']) ?></td>
                      <td class="text-start"><?= htmlspecialchars($row['subdistrict_name_th']) ?></td>
                      <td class="text-start"><?= htmlspecialchars($row['subdistrict_name_en']) ?></td>
                      <td><?= htmlspecialchars($row['zip_code']) ?></td>
                      <td><?= htmlspecialchars($row['district_name_th']) ?></td>
                      <td>
                        <a href="edit_subdistrict.php?id=<?= $row['subdistrict_id'] ?>" class="btn btn-outline-warning btn-sm me-1" title="แก้ไข">
                          <i class="bi bi-pencil-square"></i>
                        </a>
                        <button class="btn btn-outline-danger btn-sm delete-btn"
                          data-id="<?= $row['subdistrict_id'] ?>"
                          data-name="<?= htmlspecialchars($row['subdistrict_name_th']) ?>"
                          title="ลบ">
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
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>ยืนยันการลบตำบล</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <p id="deleteMessage" class="mb-0">คุณแน่ใจหรือไม่ว่าต้องการลบ?</p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <a id="confirmDeleteBtn" href="#" class="btn btn-danger">ยืนยันการลบ</a>
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
      // Clear URL parameters after showing alert
      if (window.location.search.includes('success=') || window.location.search.includes('error=') || window.location.search.includes('add_success=') || window.location.search.includes('edit_success=')) {
        setTimeout(() => {
          window.history.replaceState(null, '', window.location.pathname);
        }, 3000);
      }

      const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
      const deleteButtons = document.querySelectorAll('.delete-btn');
      const deleteMessage = document.getElementById('deleteMessage');
      const confirmBtn = document.getElementById('confirmDeleteBtn');

      deleteButtons.forEach(button => {
        button.addEventListener('click', () => {
          const id = button.getAttribute('data-id');
          const name = button.getAttribute('data-name');
          deleteMessage.innerHTML = `คุณแน่ใจหรือไม่ว่าจะลบตำบล "<strong>${name}</strong>" (รหัส ${id}) ?<br><span class='text-danger'>การกระทำนี้ไม่สามารถย้อนกลับได้</span>`;
          // ไฟล์ delete_subdistrict.php นี้ คุณต้องสร้างขึ้นมาเองนะครับ
          confirmBtn.href = `delete_subdistrict.php?id=${encodeURIComponent(id)}`;
          modal.show();
        });
      });
    });
  </script>
</body>

</html>