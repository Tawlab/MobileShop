<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_province');
// require '../config/load_theme.php'; // ธีมจะถูกกำหนดในไฟล์นี้โดยตรง

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $province_id = trim($_POST['province_id']);
  $province_name_th = trim($_POST['province_name_th']);
  $province_name_en = trim($_POST['province_name_en']);

  // --- การตรวจสอบข้อมูล (Server-side) ---
  if (empty($province_id) || empty($province_name_th) || empty($province_name_en)) {
    $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
  } elseif (!preg_match('/^\d{2}$/', $province_id)) {
    $error = 'รหัสจังหวัดต้องเป็นตัวเลข 2 หลักเท่านั้น (เช่น 01)';
  } elseif (!preg_match('/^[ก-๏\s]+$/u', $province_name_th)) {
    $error = 'ชื่อจังหวัด (ไทย) ต้องเป็นภาษาไทยเท่านั้น';
  } elseif (!preg_match('/^[A-Za-z\s]+$/', $province_name_en)) {
    $error = 'ชื่อจังหวัด (อังกฤษ) ต้องเป็นภาษาอังกฤษเท่านั้น';
  } else {
    // ตรวจสอบรหัสจังหวัดซ้ำ
    $stmt_check = $conn->prepare("SELECT province_id FROM provinces WHERE province_id = ?");
    $stmt_check->bind_param("s", $province_id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
      $error = 'รหัสจังหวัดนี้มีอยู่แล้วในระบบ';
    } else {
      // บันทึกข้อมูล
      $stmt_insert = $conn->prepare("INSERT INTO provinces (province_id, province_name_th, province_name_en) VALUES (?, ?, ?)");
      $stmt_insert->bind_param("sss", $province_id, $province_name_th, $province_name_en);

      if ($stmt_insert->execute()) {
        header('Location: province.php?add_success=true');
        exit;
      } else {
        $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
      }
      $stmt_insert->close();
    }
    $stmt_check->close();
  }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>เพิ่มจังหวัด</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <?php require '../config/load_theme.php'; ?>
  <style>
    body {
      background-color: #f4f7f6;
    }

    .form-container {
      max-width: 500px;
      margin: 40px auto;
    }

    .card {
      border: none;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .card-header {
      background-color: #e6f7ee;
      color: #004d40;
      border-bottom: 0;
      border-top-left-radius: 12px;
      border-top-right-radius: 12px;
      padding-top: 1.25rem;
      padding-bottom: 1.25rem;
    }

    .form-label.required::after {
      content: " *";
      color: #dc3545;
    }

    .btn-success {
      background-color: #28a745;
      border-color: #28a745;
    }

    .btn-success:hover {
      background-color: #218838;
      border-color: #1e7e34;
    }
  </style>
</head>

<body>
  <div class="d-flex" id="wrapper">
    <?php include '../global/sidebar.php'; ?>
    <div class="main-content w-100">
      <div class="container-fluid py-4">
        <div class="container form-container">
          <div class="card shadow-sm">
            <div class="card-header text-center">
              <h4 class="mb-0"><i class="bi bi-plus-circle-dotted me-2"></i>เพิ่มข้อมูลจังหวัด</h4>
            </div>
            <div class="card-body p-4 p-md-5">

              <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                  <i class="bi bi-exclamation-triangle-fill me-2"></i>
                  <?= htmlspecialchars($error) ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>

              <form method="POST" class="needs-validation" novalidate>
                <div class="mb-3">
                  <label for="provinceId" class="form-label required">รหัสจังหวัด</label>
                  <input type="text" name="province_id" id="provinceId" class="form-control"
                    required maxlength="2" pattern="^\d{2}$"
                    placeholder="เช่น 01, 10">
                  <div class="invalid-feedback">กรุณากรอกรหัสจังหวัดเป็นตัวเลข 2 หลัก</div>
                </div>

                <div class="mb-3">
                  <label for="nameTh" class="form-label required">ชื่อจังหวัด (ไทย)</label>
                  <input type="text" name="province_name_th" id="nameTh" class="form-control"
                    required maxlength="50" pattern="^[ก-๏\s]+$"
                    placeholder="เช่น กรุงเทพมหานคร">
                  <div class="invalid-feedback">กรุณากรอกชื่อจังหวัดเป็นภาษาไทยเท่านั้น</div>
                </div>

                <div class="mb-3">
                  <label for="nameEn" class="form-label required">ชื่อจังหวัด (อังกฤษ)</label>
                  <input type="text" name="province_name_en" id="nameEn" class="form-control"
                    required maxlength="50" pattern="^[A-Za-z\s]+$"
                    placeholder="เช่น Bangkok">
                  <div class="invalid-feedback">กรุณากรอกชื่อจังหวัดเป็นภาษาอังกฤษเท่านั้น</div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between">
                  <a href="province.php" class="btn btn-outline-secondary">
                    <i class="bi bi-chevron-left"></i> ย้อนกลับ
                  </a>
                  <button type="submit" class="btn btn-success">
                    <i class="bi bi-save me-1"></i> บันทึกข้อมูล
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // --- Bootstrap 5 Validation ---
    (() => {
      'use strict';
      const forms = document.querySelectorAll('.needs-validation');
      Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
          if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
          }
          form.classList.add('was-validated');
        }, false);
      });
    })();
  </script>
</body>

</html>