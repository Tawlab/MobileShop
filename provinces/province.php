<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'province');

// ดึงข้อมูลจังหวัด
$sql = "SELECT province_id, province_name_th, province_name_en FROM provinces ORDER BY province_id ASC";
$result = mysqli_query($conn, $sql);

// ตรวจสอบข้อความจาก URL
$alert_message = '';
$alert_type = '';
if (isset($_GET['success']) && $_GET['success'] == '1') {
  $alert_message = 'ลบข้อมูลจังหวัดสำเร็จ';
  $alert_type = 'success';
} elseif (isset($_GET['error'])) {
  if ($_GET['error'] == 'has_districts') {
    $alert_message = 'ไม่สามารถลบจังหวัดได้ เนื่องจากมีข้อมูลอำเภออ้างอิงอยู่';
  } else {
    $alert_message = 'เกิดข้อผิดพลาดในการลบข้อมูล';
  }
  $alert_type = 'danger';
} elseif (isset($_GET['add_success']) && $_GET['add_success'] == 'true') {
  $alert_message = 'เพิ่มจังหวัดใหม่สำเร็จ';
  $alert_type = 'success';
} elseif (isset($_GET['edit_success']) && $_GET['edit_success'] == 'true') {
  $alert_message = 'แก้ไขข้อมูลจังหวัดสำเร็จ';
  $alert_type = 'success';
}

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>จัดการจังหวัด</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        /* **[เพิ่ม]** CSS ทั่วไปเพื่อป้องกันการล้นจอ */
        *, *::before, *::after {
            box-sizing: border-box; 
        }

        body {
            background-color: #f4f7f6;
            margin: 0; 
            overflow-x: hidden; 
        }

        .container {
             max-width: 1200px; /* **[เพิ่ม]** กำหนด Max Width ให้เหมาะสมกับหน้าตาราง */
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
        
        /* -------------------------------------------------------------------- */
        /* --- **[เพิ่ม]** Responsive Override สำหรับ Mobile (จอเล็กกว่า 768px) --- */
        /* -------------------------------------------------------------------- */
        @media (max-width: 767.98px) {
            .container {
                /* เพิ่ม Padding ด้านข้างบนมือถือ */
                padding-left: 10px;
                padding-right: 10px;
            }

            /* 1. ปรับ Table Cell/Font */
            .table th, .table td {
                padding: 0.6rem 0.5rem; /* ลด Padding ด้านข้าง */
                font-size: 0.8rem; /* ลดขนาด Font เล็กน้อย */
                white-space: nowrap; /* ป้องกันไม่ให้ข้อความยาวๆ ขึ้นบรรทัดใหม่ในตาราง Responsive */
            }
            
            /* 2. จัดการคอลัมน์ Action ในตาราง (คอลัมน์สุดท้าย) */
            .table td:last-child {
                display: flex;
                gap: 5px; 
                justify-content: center;
                align-items: center;
                flex-wrap: nowrap;
            }
        }
    </style>
</head>

<body>
  <div class="d-flex" id="wrapper">
    <?php include '../global/sidebar.php'; ?>
    <div class="main-content w-100">
      <div class="container-fluid py-4">
        <div class="container py-4">
          <div class="card shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h4 class="m-0 text-success"><i class="bi bi-geo-alt-fill me-2"></i>รายการจังหวัด</h4>
              <a href="add_province.php" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i> เพิ่มจังหวัด
              </a>
            </div>

            <?php if ($alert_message): ?>
              <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $alert_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
            <?php endif; ?>

            <div class="table-responsive">
              <table class="table table-hover align-middle text-center">
                <thead class="align-middle">
                  <tr>
                    <th style="width: 10%;">ลำดับ</th>
                    <th style="width: 15%;">รหัส</th>
                    <th style="width: 30%;">ชื่อจังหวัด (ไทย)</th>
                    <th style="width: 30%;">ชื่อจังหวัด (อังกฤษ)</th>
                    <th style="width: 15%;">การจัดการ</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $i = 1;
                  while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                      <td><?= $i++ ?></td>
                      <td><?= htmlspecialchars($row['province_id']) ?></td>
                      <td class="text-start"><?= htmlspecialchars($row['province_name_th']) ?></td>
                      <td class="text-start"><?= htmlspecialchars($row['province_name_en']) ?></td>
                      <td>
                        <a href="edit_province.php?id=<?= $row['province_id'] ?>" class="btn btn-outline-warning btn-sm me-1" title="แก้ไข">
                          <i class="bi bi-pencil-square"></i>
                        </a>
                        <button class="btn btn-outline-danger btn-sm delete-btn"
                          data-id="<?= $row['province_id'] ?>"
                          data-name="<?= htmlspecialchars($row['province_name_th']) ?>"
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
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>ยืนยันการลบจังหวัด</h5>
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
      // Clear URL 
      if (window.location.search.includes('success=') || window.location.search.includes('error=')) {
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
          deleteMessage.innerHTML = `คุณแน่ใจหรือไม่ว่าจะลบจังหวัด "<strong>${name}</strong>" (รหัส ${id}) ?<br><span class='text-danger'>การกระทำนี้ไม่สามารถย้อนกลับได้</span>`;
          confirmBtn.href = `delete_province.php?id=${encodeURIComponent(id)}`;
          modal.show();
        });
      });
    });
  </script>
</body>

</html>