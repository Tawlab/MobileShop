<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_permission');

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $prefix_id = trim($_POST['prefix_id']);
  $prefix_th = trim($_POST['prefix_th']);
  $prefix_th_abbr = trim($_POST['prefix_th_abbr']);
  $prefix_en = trim($_POST['prefix_en']);
  $prefix_en_abbr = trim($_POST['prefix_en_abbr']);

  // ตรวจสอบว่าฟิลด์ทั้งหมดไม่ว่าง
  if (empty($prefix_id) || empty($prefix_th)) {
    echo "<script>alert('กรุณากรอกข้อมูลในช่องที่มี * ให้ครบ'); window.history.back();</script>";
    exit();
  }

  $sql = "SELECT COUNT(*) FROM prefixs WHERE prefix_id = ? OR prefix_th = ? OR prefix_en = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("sss", $prefix_id, $prefix_th, $prefix_en);
  $stmt->execute();
  $stmt->bind_result($count);
  $stmt->fetch();
  $stmt->close();

  // ถ้ามีข้อมูลซ้ำ
  if ($count > 0) {
    echo "<script>alert('มีรหัสคำนำหน้า, ชื่อไทย, หรือชื่ออังกฤษนี้อยู่แล้ว!'); window.history.back();</script>";
  } else {
    $stmt = $conn->prepare("INSERT INTO prefixs (prefix_id, prefix_th, prefix_th_abbr, prefix_en, prefix_en_abbr) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $prefix_id, $prefix_th, $prefix_th_abbr, $prefix_en, $prefix_en_abbr);

    if ($stmt->execute()) {
      echo "<script>window.location.href='prename.php?success=เพิ่มข้อมูลสำเร็จ';</script>";
    } else {
      echo "<script>alert('เกิดข้อผิดพลาด: " . $stmt->error . "'); window.history.back();</script>";
    }
    $stmt->close();
  }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>เพิ่มคำนำหน้าชื่อ</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">


  <?php
  require '../config/load_theme.php';
  ?>

  <style>
    .container {
      max-width: 800px;
    }

    td {
      vertical-align: top;
    }

  </style>
</head>

<body>
  <div class="d-flex" id="wrapper">
    <?php include '../global/sidebar.php'; ?>
    <div class="main-content w-100">
      <div class="container-fluid py-4">

        <div class="container py-3">
          <div class="card shadow-lg rounded-4 p-4">
            <h4 class="mb-4"><i class="bi bi-person-plus me-2"></i>เพิ่มคำนำหน้า</h4>

            <form method="post" class="needs-validation" novalidate>
              <table class="table table-borderless">
                <tbody>
                  <tr>
                    <td colspan="2">
                      <label class="form-label">รหัสคำนำหน้า (6 หลักตัวเลข)</label>
                      <input type="text" name="prefix_id" class="form-control" maxlength="6" pattern="\d{6}" required
                        title="กรุณากรอกตัวเลข 6 หลัก">
                      <div class="invalid-feedback">กรุณากรอกตัวเลข 6 หลักเท่านั้น</div>
                    </td>
                  </tr>
                  <tr>
                    <td class="pe-3 text-nowrap">
                      <label class="form-label">ชื่อคำนำหน้า (ภาษาไทย)</label>
                      <input type="text" name="prefix_th" class="form-control" maxlength="20" pattern="^[ก-๙\s.]+$" required
                        oninput="this.value = this.value.replace(/[^ก-๙\s.]/g, '')"
                        title="กรุณากรอกเฉพาะอักษรไทย">
                      <div class="invalid-feedback">กรุณากรอกเฉพาะอักษรไทย ไม่เกิน 20 ตัวอักษร</div>
                    </td>
                    <td class="ps-3 text-nowrap">
                      <label class="form-label">ชื่อย่อ (ภาษาไทย)</label>
                      <input type="text" name="prefix_th_abbr" class="form-control" maxlength="20" pattern="^[ก-๙\s.]+$"
                        oninput="this.value = this.value.replace(/[^ก-๙\s.]/g, '')"
                        title="กรุณากรอกเฉพาะอักษรไทย">
                      <div class="invalid-feedback">กรุณากรอกเฉพาะอักษรไทย ไม่เกิน 20 ตัวอักษร</div>
                    </td>
                  </tr>
                  <tr>
                    <td class="pe-3 text-nowrap">
                      <label class="form-label">ชื่อคำนำหน้า (ภาษาอังกฤษ)</label>
                      <input type="text" name="prefix_en" class="form-control" maxlength="20" pattern="^[A-Za-z\s.]+$"
                        oninput="this.value = this.value.replace(/[^a-zA-Z\s.]/g, '')"
                        title="กรุณากรอกเฉพาะอักษรภาษาอังกฤษ">
                    </td>
                    <td class="ps-3 text-nowrap">
                      <label class="form-label">ชื่อย่อ (ภาษาอังกฤษ)</label>
                      <input type="text" name="prefix_en_abbr" class="form-control" maxlength="20" pattern="^[A-Za-z\s.]+$"
                        oninput="this.value = this.value.replace(/[^a-zA-Z\s.]/g, '')"
                        title="กรุณากรอกเฉพาะอักษรภาษาอังกฤษ">
                    </td>
                  </tr>
                </tbody>
              </table>

              <div class="d-flex justify-content-between mt-4">
                <a href="prename.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> ย้อนกลับ</a>
                <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> บันทึก</button>
              </div>
            </form>
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
          // เพิ่มการตรวจสอบ pattern ของรหัส
          const idInput = form.querySelector('[name="prefix_id"]');
          if (idInput.value.length !== 6 || !/^\d{6}$/.test(idInput.value)) {
            idInput.setCustomValidity('Invalid'); 
          } else {
            idInput.setCustomValidity('');
          }

          // ตรวจสอบ form validation 
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