<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_department');

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $dept_id = trim($_POST['dept_id']);
  $dept_name = trim($_POST['dept_name']);
  $dept_desc = trim($_POST['dept_desc']);

  // (2) Server-side Validation
  if (empty($dept_id) || empty($dept_name)) {
    $error_message = 'กรุณากรอกข้อมูลที่มีเครื่องหมาย * ให้ครบถ้วน';
  } elseif (!preg_match('/^[0-9]{1,11}$/', $dept_id)) {
    // อิงตามฐานข้อมูล `dept_id` int(11)
    $error_message = 'รหัสแผนกต้องเป็นตัวเลขเท่านั้น (ไม่เกิน 11 หลัก)';
  } elseif (mb_strlen($dept_name) > 50) {
    // อิงตามฐานข้อมูล `dept_name` varchar(50)
    $error_message = 'ชื่อแผนกต้องไม่เกิน 50 ตัวอักษร';
  } elseif (mb_strlen($dept_desc) > 100) {
    // อิงตามฐานข้อมูล `dept_desc` varchar(100)
    $error_message = 'รายละเอียดต้องไม่เกิน 100 ตัวอักษร';
  } else {

    // (3) ตรวจสอบรหัสและชื่อซ้ำ
    $stmt_check = $conn->prepare("SELECT dept_id FROM departments WHERE dept_id = ? OR dept_name = ?");
    $stmt_check->bind_param("ss", $dept_id, $dept_name);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
      $existing = $result_check->fetch_assoc();
      if ($existing['dept_id'] == $dept_id) {
        $error_message = 'รหัสแผนก (ID: ' . $dept_id . ') นี้มีอยู่แล้วในระบบ';
      } else {
        $error_message = 'ชื่อแผนก "' . $dept_name . '" นี้มีอยู่แล้วในระบบ';
      }
    } else {
      // (4) บันทึกข้อมูล
      $sql = "INSERT INTO departments (dept_id, dept_name, dept_desc, create_at, update_at) 
                    VALUES (?, ?, ?, NOW(), NOW())";

      $stmt_insert = $conn->prepare($sql);
      $stmt_insert->bind_param("sss", $dept_id, $dept_name, $dept_desc);

      if ($stmt_insert->execute()) {
        // ส่งกลับไปหน้าหลักพร้อมข้อความสำเร็จ
        header("Location: department.php?success=add");
        exit();
      } else {
        $error_message = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $conn->error;
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>เพิ่มแผนก</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <?php include '../config/load_theme.php'; ?>

  <style>
    .required-label::after {
      content: " *";
      color: red;
    }
  </style>
</head>

<body>
  <div class="d-flex" id="wrapper">
    <?php include '../global/sidebar.php'; ?>
    <div class="main-content w-100">
      <div class="container-fluid py-4">

        <div class="container py-5" style="max-width: 700px;">
          <div class="card shadow-lg rounded-4 p-4">

            <h4 class="mb-4"><i class="bi bi-plus-circle-fill me-2"></i>เพิ่มข้อมูลแผนก</h4>

            <?php if (!empty($error_message)): ?>
              <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($error_message) ?>
              </div>
            <?php endif; ?>

            <form method="POST" action="add_department.php" class="needs-validation" novalidate>
              <div class="mb-3">
                <label for="dept_id" class="form-label required-label">รหัสแผนก</label>
                <input type="text" class="form-control" id="dept_id" name="dept_id"
                  pattern="[0-9]{1,11}" maxlength="11" required
                  value="<?= htmlspecialchars($_POST['dept_id'] ?? '') ?>">
                <div class="invalid-feedback">
                  กรุณากรอกรหัสแผนกเป็นตัวเลข (ไม่เกิน 11 หลัก)
                </div>
              </div>

              <div class="mb-3">
                <label for="dept_name" class="form-label required-label">ชื่อแผนก</label>
                <input type="text" class="form-control" id="dept_name" name="dept_name"
                  maxlength="50" required
                  value="<?= htmlspecialchars($_POST['dept_name'] ?? '') ?>">
                <div class="invalid-feedback">
                  กรุณากรอกชื่อแผนก (ไม่เกิน 50 ตัวอักษร)
                </div>
              </div>

              <div class="mb-3">
                <label for="dept_desc" class="form-label">รายละเอียด (ถ้ามี)</label>
                <textarea class="form-control" id="dept_desc" name="dept_desc"
                  rows="3" maxlength="100"><?= htmlspecialchars($_POST['dept_desc'] ?? '') ?></textarea>
                <div class="form-text">
                  ข้อมูลเพิ่มเติมเกี่ยวกับแผนก (ไม่เกิน 100 ตัวอักษร)
                </div>
              </div>

              <hr class="my-4">

              <div classs="d-flex justify-content-between">
                <a href="department.php" class="btn btn-outline-secondary">
                  <i class="bi bi-chevron-left me-1"></i> ย้อนกลับ
                </a>
                <button typeS="submit" class="btn btn-add float-end">
                  <i class="bi bi-save-fill me-1"></i> บันทึกข้อมูล
                </button>
              </div>
            </form>

          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // (9) เปิดใช้งาน Client-side Validation ของ Bootstrap
    (() => {
      'use strict'
      const forms = document.querySelectorAll('.needs-validation')
      Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
          if (!form.checkValidity()) {
            event.preventDefault()
            event.stopPropagation()
          }
          form.classList.add('was-validated')
        }, false)
      })
    })()
  </script>
</body>

</html>