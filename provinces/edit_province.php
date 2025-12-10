<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'edit_province');

$error = '';
$province_id = '';
$province_name_th = '';
$province_name_en = '';

// ตรวจสอบว่ามี ID ส่งมาหรือไม่
if (!isset($_GET['id']) || empty($_GET['id'])) {
  header("Location: province.php?error=not_found");
  exit();
}

$id_to_edit = $_GET['id'];

//  ดึงข้อมูลเดิม
$stmt_select = $conn->prepare("SELECT province_id, province_name_th, province_name_en FROM provinces WHERE province_id = ?");
$stmt_select->bind_param("s", $id_to_edit);
$stmt_select->execute();
$result = $stmt_select->get_result();
$row = $result->fetch_assoc();
$stmt_select->close();

if (!$row) {
  // ไม่พบข้อมูล
  header("Location: province.php?error=not_found");
  exit();
}

// เมื่อมีการส่งฟอร์ม (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // ดึงข้อมูลจากฟอร์ม
  $province_id_post = trim($_POST['province_id']); 
  $province_name_th_post = trim($_POST['province_name_th']);
  $province_name_en_post = trim($_POST['province_name_en']);

  // ตรวจสอบความถูกต้องของข้อมูล 
  if (empty($province_name_th_post) || empty($province_name_en_post)) {
    $error = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
    $province_id = $province_id_post;
    $province_name_th = $province_name_th_post;
    $province_name_en = $province_name_en_post;
  } elseif (!preg_match('/^[ก-๏\s]+$/u', $province_name_th_post)) {
    $error = 'ชื่อจังหวัด (ไทย) ต้องเป็นภาษาไทยเท่านั้น';
    $province_id = $province_id_post;
    $province_name_th = $province_name_th_post;
    $province_name_en = $province_name_en_post;
  } elseif (!preg_match('/^[A-Za-z\s]+$/', $province_name_en_post)) {
    $error = 'ชื่อจังหวัด (อังกฤษ) ต้องเป็นภาษาอังกฤษเท่านั้น';
    $province_id = $province_id_post;
    $province_name_th = $province_name_th_post;
    $province_name_en = $province_name_en_post;
  } elseif ($province_id_post !== $id_to_edit) {
    $error = 'รหัสจังหวัดไม่ตรงกัน ไม่สามารถดำเนินการได้';
    $province_name_th = $province_name_th_post;
    $province_name_en = $province_name_en_post;
  } else {
    //  อัปเดตข้อมูล
    $stmt_update = $conn->prepare("UPDATE provinces SET province_name_th = ?, province_name_en = ? WHERE province_id = ?");
    $stmt_update->bind_param("sss", $province_name_th_post, $province_name_en_post, $id_to_edit);

    if ($stmt_update->execute()) {
      header("Location: province.php?edit_success=true");
      exit();
    } else {
      $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $stmt_update->error;
    }
    $stmt_update->close();
  }
} else {
  // ถ้าไม่ใช่ POST (โหลดหน้าครั้งแรก) ให้แสดงข้อมูลเดิม
  $province_id = $row['province_id'];
  $province_name_th = $row['province_name_th'];
  $province_name_en = $row['province_name_en'];
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>แก้ไขจังหวัด</title>
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

    /* สไตล์สำหรับช่อง readonly */
    input[readonly] {
      background-color: #e9ecef;
      cursor: not-allowed;
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
              <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>แก้ไขข้อมูลจังหวัด</h4>
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
                  <label for="provinceId" class="form-label">รหัสจังหวัด</label>
                  <input type="text" name="province_id" id="provinceId" class="form-control"
                    value="<?= htmlspecialchars($province_id) ?>"
                    readonly>
                  <div class="form-text">รหัสจังหวัดไม่สามารถแก้ไขได้</div>
                </div>

                <div class="mb-3">
                  <label for="nameTh" class="form-label required">ชื่อจังหวัด (ไทย)</label>
                  <input type="text" name="province_name_th" id="nameTh" class="form-control"
                    required maxlength="50" pattern="^[ก-๏\s]+$"
                    value="<?= htmlspecialchars($province_name_th) ?>"
                    placeholder="เช่น กรุงเทพมหานคร">
                  <div class="invalid-feedback">กรุณากรอกชื่อจังหวัดเป็นภาษาไทยเท่านั้น</div>
                </div>

                <div class="mb-3">
                  <label for="nameEn" class="form-label required">ชื่อจังหวัด (อังกฤษ)</label>
                  <input type="text" name="province_name_en" id="nameEn" class="form-control"
                    required maxlength="50" pattern="^[A-Za-z\s]+$"
                    value="<?= htmlspecialchars($province_name_en) ?>"
                    placeholder="เช่น Bangkok">
                  <div class="invalid-feedback">กรุณากรอกชื่อจังหวัดเป็นภาษาอังกฤษเท่านั้น</div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between">
                  <a href="province.php" class="btn btn-outline-secondary">
                    <i class="bi bi-chevron-left"></i> ย้อนกลับ
                  </a>
                  <button type="submit" class="btn btn-success">
                    <i class="bi bi-save me-1"></i> บันทึกการเปลี่ยนแปลง
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