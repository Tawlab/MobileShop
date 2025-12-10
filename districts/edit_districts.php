<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'edit_department');

$error = '';
$district_id = '';
$district_name_th = '';
$district_name_en = '';
$current_province_id = '';

// ตรวจสอบว่ามี ID ส่งมาหรือไม่
if (!isset($_GET['id']) || empty($_GET['id'])) {
  header("Location: districts.php?error=not_found");
  exit();
}

$id_to_edit = $_GET['id'];

// ดึงข้อมูลอำเภอเดิม
$stmt_select = $conn->prepare("SELECT district_id, district_name_th, district_name_en, provinces_province_id FROM districts WHERE district_id = ?");
$stmt_select->bind_param("s", $id_to_edit);
$stmt_select->execute();
$result = $stmt_select->get_result();
$row = $result->fetch_assoc();
$stmt_select->close();

if (!$row) {
  // ไม่พบข้อมูล
  header("Location: districts.php?error=not_found");
  exit();
}

// ดึงรายการจังหวัด
$provinces_result = mysqli_query($conn, "SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th ASC");

// เมื่อมีการส่งฟอร์ม (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // ดึงข้อมูลจากฟอร์ม 
  $district_id_post = trim($_POST['district_id']); // ID ที่ส่งมาจาก form (readonly)
  $district_name_th_post = trim($_POST['district_name_th']);
  $district_name_en_post = trim($_POST['district_name_en']);
  $provinces_province_id_post = trim($_POST['provinces_province_id']);

  // ตรวจสอบความถูกต้องของข้อมูล
  if (empty($district_name_th_post) || empty($district_name_en_post) || empty($provinces_province_id_post)) {
    $error = 'กรุณากรอกข้อมูลที่มีเครื่องหมาย * ให้ครบถ้วน';
    $district_id = $district_id_post;
    $district_name_th = $district_name_th_post;
    $district_name_en = $district_name_en_post;
    $current_province_id = $provinces_province_id_post;
  } elseif (!preg_match('/^[ก-๏\s]+$/u', $district_name_th_post)) {
    $error = 'ชื่ออำเภอ (ไทย) ต้องเป็นภาษาไทยเท่านั้น';
    $district_id = $district_id_post;
    $district_name_th = $district_name_th_post;
    $district_name_en = $district_name_en_post;
    $current_province_id = $provinces_province_id_post;
  } elseif (!preg_match('/^[A-Za-z\s]+$/', $district_name_en_post)) {
    $error = 'ชื่ออำเภอ (อังกฤษ) ต้องเป็นภาษาอังกฤษเท่านั้น';
    $district_id = $district_id_post;
    $district_name_th = $district_name_th_post;
    $district_name_en = $district_name_en_post;
    $current_province_id = $provinces_province_id_post;
  } elseif ($district_id_post !== $id_to_edit) {
    $error = 'รหัสอำเภอไม่ตรงกัน ไม่สามารถดำเนินการได้';
    $district_id = $id_to_edit;
    $district_name_th = $district_name_th_post;
    $district_name_en = $district_name_en_post;
    $current_province_id = $provinces_province_id_post;
  } else {
    // ตรวจสอบชื่อซ้ำ
    if ($district_name_th_post != $row['district_name_th'] || $provinces_province_id_post != $row['provinces_province_id']) {
      $stmt_check = $conn->prepare("SELECT district_id FROM districts WHERE district_name_th = ? AND provinces_province_id = ?");
      $stmt_check->bind_param("ss", $district_name_th_post, $provinces_province_id_post);
      $stmt_check->execute();
      $stmt_check->store_result();
      if ($stmt_check->num_rows > 0) {
        $error = 'ชื่ออำเภอนี้มีอยู่แล้วในจังหวัดที่เลือก';
        $district_id = $district_id_post;
        $district_name_th = $district_name_th_post;
        $district_name_en = $district_name_en_post;
        $current_province_id = $provinces_province_id_post;
      }
      $stmt_check->close();
    }

    if (empty($error)) {
      $stmt_update = $conn->prepare("UPDATE districts SET district_name_th = ?, district_name_en = ?, provinces_province_id = ? WHERE district_id = ?");
      $stmt_update->bind_param("ssss", $district_name_th_post, $district_name_en_post, $provinces_province_id_post, $id_to_edit);

      if ($stmt_update->execute()) {
        header("Location: districts.php?edit_success=true");
        exit();
      } else {
        $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $stmt_update->error;
      }
      $stmt_update->close();
    }
  }
} else {
  $district_id = $row['district_id'];
  $district_name_th = $row['district_name_th'];
  $district_name_en = $row['district_name_en'];
  $current_province_id = $row['provinces_province_id'];
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>แก้ไขอำเภอ</title>
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

    input[readonly] {
      background-color: #e9ecef;
      cursor: not-allowed;
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
              <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>แก้ไขข้อมูลอำเภอ</h4>
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
                  <label for="district_id" class="form-label">รหัสอำเภอ</label>
                  <input type="text" name="district_id" id="district_id" class="form-control"
                    value="<?= htmlspecialchars($district_id) ?>"
                    readonly>
                  <div class="form-text">รหัสอำเภอไม่สามารถแก้ไขได้</div>
                </div>

                <div class="mb-3">
                  <label for="district_name_th" class="form-label required">ชื่ออำเภอ (ไทย)</label>
                  <input type="text" name="district_name_th" id="district_name_th" class="form-control"
                    value="<?= htmlspecialchars($district_name_th) ?>"
                    maxlength="50" pattern="^[ก-๏\s]+$" required>
                  <div class="invalid-feedback">ต้องเป็นอักษรไทยเท่านั้น (และเว้นวรรค)</div>
                </div>

                <div class="mb-3">
                  <label for="district_name_en" class="form-label required">ชื่ออำเภอ (อังกฤษ)</label>
                  <input type="text" name="district_name_en" id="district_name_en" class="form-control"
                    value="<?= htmlspecialchars($district_name_en) ?>"
                    maxlength="50" pattern="^[A-Za-z\s]+$" required>
                  <div class="invalid-feedback">ต้องเป็นอักษรภาษาอังกฤษเท่านั้น (และเว้นวรรค)</div>
                </div>

                <div class="mb-4">
                  <label for="provinces_province_id" class="form-label required">จังหวัด</label>
                  <select name="provinces_province_id" id="provinces_province_id" class="form-select" required>
                    <option value="">-- เลือกจังหวัด --</option>
                    <?php
                    mysqli_data_seek($provinces_result, 0); // Reset pointer
                    while ($p = mysqli_fetch_assoc($provinces_result)):
                      // แก้ไข $p['province_id'] และ $current_province_id
                      $selected = ($p['province_id'] == $current_province_id) ? 'selected' : '';
                    ?>
                      <option value="<?= $p['province_id'] ?>" <?= $selected ?>>
                        <?= htmlspecialchars($p['province_name_th']) ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                  <div class="invalid-feedback">กรุณาเลือกจังหวัด</div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between">
                  <a href="districts.php" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i> ย้อนกลับ</a>
                  <button type="submit" class="btn btn-success"><i class="bi bi-save me-1"></i> บันทึกการเปลี่ยนแปลง</button>
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