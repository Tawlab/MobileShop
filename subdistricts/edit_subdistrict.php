<?php
// === File: subdistricts/edit_subdistrict.php ===
session_start();
require '../config/config.php';
checkPageAccess($conn, 'edit_subdistrict');
// require '../config/load_theme.php'; // ธีมจะถูกกำหนดในไฟล์นี้โดยตรง

$error = '';
$subdistrict_id = '';
$subdistrict_name_th = '';
$subdistrict_name_en = '';
$zip_code = '';
$current_district_id = '';

// 1. ตรวจสอบว่ามี ID ส่งมาหรือไม่ (แก้ไขเป็น subdistrict_id)
if (!isset($_GET['id']) || empty($_GET['id'])) {
  header("Location: subdistricts.php?error=not_found");
  exit();
}

$id_to_edit = $_GET['id'];

// 2. ดึงข้อมูลตำบลเดิม (แก้ไขชื่อฟิลด์)
$stmt_select = $conn->prepare("SELECT subdistrict_id, subdistrict_name_th, subdistrict_name_en, zip_code, districts_district_id FROM subdistricts WHERE subdistrict_id = ?");
$stmt_select->bind_param("s", $id_to_edit);
$stmt_select->execute();
$result = $stmt_select->get_result();
$row = $result->fetch_assoc();
$stmt_select->close();

if (!$row) {
  // ไม่พบข้อมูล
  header("Location: subdistricts.php?error=not_found");
  exit();
}

// 3. ดึงรายการอำเภอ (แก้ไขชื่อฟิลด์)
$districts_result = mysqli_query($conn, "SELECT district_id, district_name_th FROM districts ORDER BY district_name_th ASC");

// 4. เมื่อมีการส่งฟอร์ม (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // ดึงข้อมูลจากฟอร์ม (แก้ไขชื่อฟิลด์)
  $subdistrict_id_post = trim($_POST['subdistrict_id']); // ID ที่ส่งมาจาก form (readonly)
  $subdistrict_name_th_post = trim($_POST['subdistrict_name_th']);
  $subdistrict_name_en_post = trim($_POST['subdistrict_name_en']);
  $zip_code_post = trim($_POST['zip_code']);
  $districts_district_id_post = trim($_POST['districts_district_id']);

  // 5. ตรวจสอบความถูกต้องของข้อมูล
  if (empty($subdistrict_name_th_post) || empty($subdistrict_name_en_post) || empty($zip_code_post) || empty($districts_district_id_post)) {
    $error = 'กรุณากรอกข้อมูลที่มีเครื่องหมาย * ให้ครบถ้วน';
  } elseif (!preg_match('/^\d{5}$/', $zip_code_post)) {
    $error = 'รหัสไปรษณีย์ต้องเป็นตัวเลข 5 หลัก';
  } elseif (!preg_match('/^[ก-๏\s]+$/u', $subdistrict_name_th_post)) {
    $error = 'ชื่อตำบล (ไทย) ต้องเป็นภาษาไทยเท่านั้น';
  } elseif (!preg_match('/^[A-Za-z\s]+$/', $subdistrict_name_en_post)) {
    $error = 'ชื่อตำบล (อังกฤษ) ต้องเป็นภาษาอังกฤษเท่านั้น';
  } elseif ($subdistrict_id_post !== $id_to_edit) {
    $error = 'รหัสตำบลไม่ตรงกัน ไม่สามารถดำเนินการได้';
  } else {
    // 6. ตรวจสอบชื่อซ้ำ (เฉพาะกรณีที่ชื่อ หรือ อำเภอ มีการเปลี่ยนแปลง)
    if ($subdistrict_name_th_post != $row['subdistrict_name_th'] || $districts_district_id_post != $row['districts_district_id']) {
      $stmt_check = $conn->prepare("SELECT subdistrict_id FROM subdistricts WHERE subdistrict_name_th = ? AND districts_district_id = ?");
      $stmt_check->bind_param("ss", $subdistrict_name_th_post, $districts_district_id_post);
      $stmt_check->execute();
      $stmt_check->store_result();
      if ($stmt_check->num_rows > 0) {
        $error = 'ชื่อตำบลนี้มีอยู่แล้วในอำเภอที่เลือก';
      }
      $stmt_check->close();
    }

    // 7. ถ้าไม่มี error ให้ทำการอัปเดต (แก้ไขชื่อฟิลด์)
    if (empty($error)) {
      $stmt_update = $conn->prepare("UPDATE subdistricts SET subdistrict_name_th = ?, subdistrict_name_en = ?, zip_code = ?, districts_district_id = ? WHERE subdistrict_id = ?");
      $stmt_update->bind_param("sssss", $subdistrict_name_th_post, $subdistrict_name_en_post, $zip_code_post, $districts_district_id_post, $id_to_edit);

      if ($stmt_update->execute()) {
        header("Location: subdistricts.php?edit_success=true");
        exit();
      } else {
        $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $stmt_update->error;
      }
      $stmt_update->close();
    }
  }

  // ถ้ามี error ให้เติมค่าเดิม (ที่ผู้ใช้กรอก) กลับเข้าไปในฟอร์ม
  if (!empty($error)) {
    $subdistrict_id = $subdistrict_id_post;
    $subdistrict_name_th = $subdistrict_name_th_post;
    $subdistrict_name_en = $subdistrict_name_en_post;
    $zip_code = $zip_code_post;
    $current_district_id = $districts_district_id_post;
  }
} else {
  // 5. ถ้าไม่ใช่ POST (โหลดหน้าครั้งแรก) ให้แสดงข้อมูลเดิม
  $subdistrict_id = $row['subdistrict_id'];
  $subdistrict_name_th = $row['subdistrict_name_th'];
  $subdistrict_name_en = $row['subdistrict_name_en'];
  $zip_code = $row['zip_code'];
  $current_district_id = $row['districts_district_id'];
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>แก้ไขตำบล</title>
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
              <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>แก้ไขข้อมูลตำบล</h4>
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
                  <label for="subdistrict_id" class="form-label">รหัสตำบล (6 หลัก)</label>
                  <input type="text" name="subdistrict_id" id="subdistrict_id" class="form-control"
                    value="<?= htmlspecialchars($subdistrict_id) ?>"
                    maxlength="6" pattern="\d{6}" readonly>
                  <div class="form-text">รหัสตำบลไม่สามารถแก้ไขได้</div>
                </div>

                <div class="mb-3">
                  <label for="subdistrict_name_th" class="form-label required">ชื่อตำบล (ไทย)</label>
                  <input type="text" name="subdistrict_name_th" id="subdistrict_name_th" class="form-control"
                    value="<?= htmlspecialchars($subdistrict_name_th) ?>"
                    maxlength="50" pattern="^[ก-๏\s]+$" required>
                  <div class="invalid-feedback">ต้องเป็นอักษรไทยเท่านั้น (และเว้นวรรค)</div>
                </div>

                <div class="mb-3">
                  <label for="subdistrict_name_en" class="form-label required">ชื่อตำบล (อังกฤษ)</label>
                  <input type="text" name="subdistrict_name_en" id="subdistrict_name_en" class="form-control"
                    value="<?= htmlspecialchars($subdistrict_name_en) ?>"
                    maxlength="50" pattern="^[A-Za-z\s]+$" required>
                  <div class="invalid-feedback">ต้องเป็นอักษรภาษาอังกฤษเท่านั้น (และเว้นวรรค)</div>
                </div>

                <div class="mb-3">
                  <label for="zip_code" class="form-label required">รหัสไปรษณีย์</label>
                  <input type="text" name="zip_code" id="zip_code" class="form-control"
                    value="<?= htmlspecialchars($zip_code) ?>"
                    maxlength="5" pattern="\d{5}" required>
                  <div class="invalid-feedback">กรุณากรอกรหัสไปรษณีย์ 5 หลัก</div>
                </div>

                <div class="mb-4">
                  <label for="districts_district_id" class="form-label required">อำเภอ</label>
                  <select name="districts_district_id" id="districts_district_id" class="form-select" required>
                    <option value="">-- เลือกอำเภอ --</option>
                    <?php
                    mysqli_data_seek($districts_result, 0); // Reset pointer
                    while ($d = mysqli_fetch_assoc($districts_result)):
                      // แก้ไข $d['district_id'] และ $current_district_id
                      $selected = ($d['district_id'] == $current_district_id) ? 'selected' : '';
                    ?>
                      <option value="<?= $d['district_id'] ?>" <?= $selected ?>>
                        <?= htmlspecialchars($d['district_name_th']) ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                  <div class="invalid-feedback">กรุณาเลือกอำเภอ</div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between">
                  <a href="subdistricts.php" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i> ย้อนกลับ</a>
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
      'useSrict';
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