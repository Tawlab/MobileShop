<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_districts');

// ดึงข้อมูลจังหวัดทั้งหมดสำหรับ Dropdown
$provinces_result = mysqli_query($conn, "SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th ASC");

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $district_id = trim($_POST['district_id']);
  $district_name_th = trim($_POST['district_name_th']);
  $district_name_en = trim($_POST['district_name_en']);
  $provinces_province_id = trim($_POST['provinces_province_id']);

  // --- การตรวจสอบข้อมูล (Server-side) ---
  if (empty($district_id) || empty($district_name_th) || empty($district_name_en) || empty($provinces_province_id)) {
    $error = 'กรุณากรอกข้อมูลให้ครบถ้วนทุกช่อง';
  } elseif (!preg_match('/^\d{1,4}$/', $district_id)) {
    $error = 'รหัสอำเภอต้องเป็นตัวเลข 1-4 หลัก';
  } elseif (!preg_match('/^[ก-๏\s]+$/u', $district_name_th)) {
    $error = 'ชื่ออำเภอ (ไทย) ต้องเป็นภาษาไทยเท่านั้น';
  } elseif (!preg_match('/^[A-Za-z\s]+$/', $district_name_en)) {
    $error = 'ชื่ออำเภอ (อังกฤษ) ต้องเป็นภาษาอังกฤษเท่านั้น';
  } else {
    // ตรวจสอบว่ามีข้อมูลซ้ำหรือไม่ (ทั้งรหัสอำเภอ หรือ ชื่ออำเภอในจังหวัดเดียวกัน)
    $stmt_check = $conn->prepare("SELECT district_id FROM districts WHERE district_id = ? OR (district_name_th = ? AND provinces_province_id = ?)");
    $stmt_check->bind_param("sss", $district_id, $district_name_th, $provinces_province_id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
      $error = 'รหัสอำเภอ หรือ ชื่ออำเภอนี้ มีอยู่แล้วในจังหวัดที่เลือก';
    } else {
      // บันทึกข้อมูล 
      $stmt_insert = $conn->prepare("INSERT INTO districts (district_id, district_name_th, district_name_en, provinces_province_id) VALUES (?, ?, ?, ?)");
      $stmt_insert->bind_param("ssss", $district_id, $district_name_th, $district_name_en, $provinces_province_id);

      if ($stmt_insert->execute()) {
        header('Location: districts.php?add_success=true');
        exit;
      } else {
        $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $stmt_insert->error;
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
  <title>เพิ่มอำเภอ</title>
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

<body >
  <div class="d-flex" id="wrapper">
    <?php include '../global/sidebar.php'; ?>
    <div class="main-content w-100">
      <div class="container-fluid py-4">

        <div class="container form-container">
          <div class="card shadow-sm">
            <div class="card-header text-center">
              <h4 class="mb-0"><i class="bi bi-building-add me-2"></i>เพิ่มข้อมูลอำเภอ</h4>
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
                  <label for="district_id" class="form-label required">รหัสอำเภอ</label>
                  <input type="text" name="district_id" id="district_id" class="form-control"
                    maxlength="4" pattern="\d{1,4}" required
                    placeholder="เช่น 1001 (ตัวเลข 1-4 หลัก)">
                  <div class="invalid-feedback">กรุณากรอกรหัสอำเภอเป็นตัวเลข 1–4 หลัก</div>
                </div>

                <div class="mb-3">
                  <label for="district_name_th" class="form-label required">ชื่ออำเภอ (ไทย)</label>
                  <input type="text" name="district_name_th" id="district_name_th" class="form-control"
                    maxlength="50" pattern="^[ก-๏\s]+$" required
                    placeholder="เช่น พระนคร">
                  <div class="invalid-feedback">ต้องเป็นอักษรไทยเท่านั้น (และเว้นวรรค)</div>
                </div>

                <div class="mb-3">
                  <label for="district_name_en" class="form-label required">ชื่ออำเภอ (อังกฤษ)</label>
                  <input type="text" name="district_name_en" id="district_name_en" class="form-control"
                    maxlength="50" pattern="^[A-Za-z\s]+$" required
                    placeholder="เช่น Phra Nakhon">
                  <div class="invalid-feedback">ต้องเป็นอักษรภาษาอังกฤษเท่านั้น (และเว้นวรรค)</div>
                </div>

                <div class="mb-4">
                  <label for="provinces_province_id" class="form-label required">จังหวัด</label>
                  <select name="provinces_province_id" id="provinces_province_id" class="form-select" required>
                    <option value="">-- เลือกจังหวัด --</option>
                    <?php while ($p = mysqli_fetch_assoc($provinces_result)): ?>
                      <option value="<?= $p['province_id'] ?>"><?= htmlspecialchars($p['province_name_th']) ?></option>
                    <?php endwhile; ?>
                  </select>
                  <div class="invalid-feedback">กรุณาเลือกจังหวัด</div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between">
                  <a href="districts.php" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i> ย้อนกลับ</a>
                  <button type="submit" class="btn btn-success"><i class="bi bi-save me-1"></i> บันทึกข้อมูล</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
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