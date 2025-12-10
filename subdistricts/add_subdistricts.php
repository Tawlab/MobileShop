<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_subdistricts');

// ดึงรายชื่ออำเภอ 
$districts_result = mysqli_query($conn, "SELECT district_id, district_name_th FROM districts ORDER BY district_name_th ASC");

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  // แก้ไขชื่อฟิลด์
  $subdistrict_id = trim($_POST['subdistrict_id']);
  $subdistrict_name_th = trim($_POST['subdistrict_name_th']);
  $subdistrict_name_en = trim($_POST['subdistrict_name_en']);
  $zip_code = trim($_POST['zip_code']);
  $districts_district_id = trim($_POST['districts_district_id']);

  // การตรวจสอบข้อมูล
  if (empty($subdistrict_id) || empty($subdistrict_name_th) || empty($subdistrict_name_en) || empty($zip_code) || empty($districts_district_id)) {
    $error = 'กรุณากรอกข้อมูลให้ครบถ้วนทุกช่อง';
  } elseif (!preg_match('/^\d{6}$/', $subdistrict_id)) {
    $error = 'รหัสตำบลต้องเป็นตัวเลข 6 หลัก';
  } elseif (!preg_match('/^\d{5}$/', $zip_code)) {
    $error = 'รหัสไปรษณีย์ต้องเป็นตัวเลข 5 หลัก';
  } elseif (!preg_match('/^[ก-๏\s]+$/u', $subdistrict_name_th)) {
    $error = 'ชื่อตำบล (ไทย) ต้องเป็นภาษาไทยเท่านั้น';
  } elseif (!preg_match('/^[A-Za-z\s]+$/', $subdistrict_name_en)) {
    $error = 'ชื่อตำบล (อังกฤษ) ต้องเป็นภาษาอังกฤษเท่านั้น';
  } else {
    // ตรวจสอบซ้ำ 
    $stmt_check = $conn->prepare("SELECT subdistrict_id FROM subdistricts WHERE subdistrict_id = ? OR (subdistrict_name_th = ? AND districts_district_id = ?)");
    $stmt_check->bind_param("sss", $subdistrict_id, $subdistrict_name_th, $districts_district_id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
      $error = 'รหัสตำบล หรือ ชื่อตำบลนี้ มีอยู่แล้วในอำเภอที่เลือก';
    } else {
      // บันทึกข้อมูล
      $stmt_insert = $conn->prepare("INSERT INTO subdistricts (subdistrict_id, subdistrict_name_th, subdistrict_name_en, zip_code, districts_district_id) VALUES (?, ?, ?, ?, ?)");
      $stmt_insert->bind_param("sssss", $subdistrict_id, $subdistrict_name_th, $subdistrict_name_en, $zip_code, $districts_district_id);

      if ($stmt_insert->execute()) {
        header('Location: subdistricts.php?add_success=true');
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
  <title>เพิ่มตำบล</title>
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

<body class="bg-light">
  <div class="d-flex" id="wrapper">
    <?php include '../global/sidebar.php'; ?>
    <div class="main-content w-100">
      <div class="container-fluid py-4">
        <div class="container form-container">
          <div class="card shadow-sm">
            <div class="card-header text-center">
              <h4 class="mb-0"><i class="bi bi-geo-alt-fill me-2"></i>เพิ่มข้อมูลตำบล</h4>
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
                  <label for="subdistrict_id" class="form-label required">รหัสตำบล (6 หลัก)</label>
                  <input type="text" name="subdistrict_id" id="subdistrict_id" class="form-control"
                    maxlength="6" pattern="\d{6}" required
                    placeholder="เช่น 100101 (ตัวเลข 6 หลัก)">
                  <div class="invalid-feedback">กรุณากรอกรหัสตำบลเป็นตัวเลข 6 หลัก</div>
                </div>

                <div class="mb-3">
                  <label for="subdistrict_name_th" class="form-label required">ชื่อตำบล (ไทย)</label>
                  <input type="text" name="subdistrict_name_th" id="subdistrict_name_th" class="form-control"
                    maxlength="50" pattern="^[ก-๏\s]+$" required
                    placeholder="เช่น พระบรมมหาราชวัง">
                  <div class="invalid-feedback">ต้องเป็นอักษรไทยเท่านั้น (และเว้นวรรค)</div>
                </div>

                <div class="mb-3">
                  <label for="subdistrict_name_en" class="form-label required">ชื่อตำบล (อังกฤษ)</label>
                  <input type="text" name="subdistrict_name_en" id="subdistrict_name_en" class="form-control"
                    maxlength="50" pattern="^[A-Za-z\s]+$" required
                    placeholder="เช่น Phra Borom Maha Ratchawang">
                  <div class="invalid-feedback">ต้องเป็นอักษรภาษาอังกฤษเท่านั้น (และเว้นวรรค)</div>
                </div>

                <div class="mb-3">
                  <label for="zip_code" class="form-label required">รหัสไปรษณีย์</label>
                  <input type="text" name="zip_code" id="zip_code" class="form-control"
                    maxlength="5" pattern="\d{5}" required
                    placeholder="เช่น 10200 (ตัวเลข 5 หลัก)">
                  <div class="invalid-feedback">กรุณากรอกรหัสไปรษณีย์ 5 หลัก</div>
                </div>

                <div class="mb-4">
                  <label for="districts_district_id" class="form-label required">อำเภอ</label>
                  <select name="districts_district_id" id="districts_district_id" class="form-select" required>
                    <option value="">-- เลือกอำเภอ --</option>
                    <?php while ($d = mysqli_fetch_assoc($districts_result)): ?>
                      <option value="<?= $d['district_id'] ?>"><?= htmlspecialchars($d['district_name_th']) ?></option>
                    <?php endwhile; ?>
                  </select>
                  <div class="invalid-feedback">กรุณาเลือกอำเภอ</div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between">
                  <a href="subdistricts.php" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i> ย้อนกลับ</a>
                  <button type="submit" class="btn btn-success"><i class="bi bi-save me-1"></i> บันทึกข้อมูล</button>
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