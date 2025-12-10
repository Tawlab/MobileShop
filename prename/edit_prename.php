<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'edit_prename');

// ตรวจสอบ ID ที่ส่งมา
if (!isset($_GET['id']) || empty($_GET['id'])) {
  echo "<script>alert('ไม่พบรหัสคำนำหน้า'); window.location.href='prename.php';</script>";
  exit();
}

$prefix_id_to_edit = $_GET['id'];
$sql = "SELECT * FROM prefixs WHERE prefix_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $prefix_id_to_edit);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
  echo "<script>alert('ไม่พบข้อมูลคำนำหน้านี้'); window.location.href='prename.php';</script>";
  exit();
}

// ตรวจสอบการส่งฟอร์ม (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $prefix_th = trim($_POST['prefix_th']);
  $prefix_th_abbr = trim($_POST['prefix_th_abbr']);
  $prefix_en = trim($_POST['prefix_en']);
  $prefix_en_abbr = trim($_POST['prefix_en_abbr']);

  // รับ ID เดิม
  $original_prefix_id = trim($_POST['original_prefix_id']);

  if (empty($prefix_th)) {
    echo "<script>alert('กรุณากรอกข้อมูลให้ครบทุกช่อง'); window.history.back();</script>";
    exit();
  }

  $update_sql = "UPDATE prefixs 
                   SET prefix_th = ?, prefix_th_abbr = ?, prefix_en = ?, prefix_en_abbr = ?
                   WHERE prefix_id = ?";
  $update_stmt = $conn->prepare($update_sql);
  $update_stmt->bind_param("sssss", $prefix_th, $prefix_th_abbr, $prefix_en, $prefix_en_abbr, $original_prefix_id);

  if ($update_stmt->execute()) {
    echo "<script>alert('บันทึกข้อมูลสำเร็จ'); window.location.href='prename.php?success=แก้ไขข้อมูลสำเร็จ';</script>";
  } else {
    $error = $update_stmt->error;
    if (strpos($error, 'Duplicate entry') !== false) {
      echo "<script>alert('เกิดข้อผิดพลาด: ชื่อไทย หรือ ชื่ออังกฤษ ซ้ำกับข้อมูลที่มีอยู่'); window.history.back();</script>";
    } else {
      echo "<script>alert('เกิดข้อผิดพลาด: " . $error . "'); window.history.back();</script>";
    }
  }
  $update_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>แก้ไขคำนำหน้า</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"> 
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
            vertical-align: top; /* สำหรับ Desktop */
        }

        /* -------------------------------------------------------------------- */
        /* --- Responsive Override สำหรับ Mobile (จอเล็กกว่า 768px) --- */
        /* -------------------------------------------------------------------- */
        @media (max-width: 767.98px) {
            .container {
                /* เพิ่มขอบด้านข้างบนมือถือ */
                padding-left: 15px;
                padding-right: 15px;
            }

            /* ยกเลิก Layout ตาราง */
            table {
                display: block;
                border-spacing: 0;
            }

            tbody, tr {
                display: block;
                width: 100%;
            }

            /* ทำให้แต่ละเซลล์แสดงผลเป็นบล็อก (เรียงซ้อนกัน) */
            td {
                display: block;
                width: 100%;
                /* ลบ vertical-align: top ออก และปรับ Padding ใหม่ */
                vertical-align: unset; 
                padding-top: 5px !important;
                padding-bottom: 5px !important;
            }

            /* ปรับให้ Label (td ตัวแรก) ดูเป็นหัวข้อ */
            tr td:first-child {
                font-weight: 600;
                padding-bottom: 0 !important;
            }

            /* เพิ่ม Margin ด้านล่างให้ Input Field (td ตัวสุดท้าย) */
            tr td:last-child {
                margin-bottom: 15px;
            }
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
            <h4 class="mb-4"><i class="bi bi-pencil-square me-2"></i>แก้ไขคำนำหน้า</h4>

            <form method="post" class="needs-validation" novalidate>

              <input type="hidden" name="original_prefix_id" value="<?= htmlspecialchars($row['prefix_id']) ?>">

              <table class="table table-borderless">
                <tbody>
                  <tr>
                    <td colspan="2">
                      <label class="form-label">รหัสคำนำหน้า (6 หลักตัวเลข)</label>
                      <input type="text" name="prefix_id" class="form-control" maxlength="6" pattern="\d{6}" required
                        value="<?= htmlspecialchars($row['prefix_id']) ?>"
                        readonly title="รหัสคำนำหน้าไม่สามารถแก้ไขได้">
                      <div class="invalid-feedback">กรุณากรอกตัวเลข 6 หลักเท่านั้น</div>
                    </td>
                  </tr>
                  <tr>
                    <td class="pe-2">
                      <label class="form-label">ชื่อคำนำหน้า (ภาษาไทย)</label>
                      <input type="text" name="prefix_th" class="form-control" maxlength="20" pattern="^[ก-๙\s.]+$" required
                        value="<?= htmlspecialchars($row['prefix_th']) ?>"
                        oninput="this.value = this.value.replace(/[^ก-๙\s.]/g, '')"
                        title="กรุณากรอกเฉพาะอักษรไทย">
                      <div class="invalid-feedback">กรุณากรอกเฉพาะอักษรไทย ไม่เกิน 20 ตัวอักษร</div>
                    </td>
                    <td class="ps-2">
                      <label class="form-label">ชื่อย่อ (ภาษาไทย)</label>
                      <input type="text" name="prefix_th_abbr" class="form-control" maxlength="20" pattern="^[ก-๙\s.]+$"
                        value="<?= htmlspecialchars($row['prefix_th_abbr']) ?>"
                        oninput="this.value = this.value.replace(/[^ก-๙\s.]/g, '')"
                        title="กรุณากรอกเฉพาะอักษรไทย">
                    </td>
                  </tr>
                  <tr>
                    <td class="pe-2">
                      <label class="form-label">ชื่อคำนำหน้า (ภาษาอังกฤษ)</label>
                      <input type="text" name="prefix_en" class="form-control" maxlength="20" pattern="^[A-Za-z\s.]+$"
                        value="<?= htmlspecialchars($row['prefix_en']) ?>"
                        oninput="this.value = this.value.replace(/[^a-zA-Z\s.]/g, '')"
                        title="กรุณากรอกเฉพาะอักษรภาษาอังกฤษ">
                    </td>
                    <td class="ps-2">
                      <label class="form-label">ชื่อย่อ (ภาษาอังกฤษ)</label>
                      <input type="text" name="prefix_en_abbr" class="form-control" maxlength="20" pattern="^[A-Za-z\s.]+$"
                        value="<?= htmlspecialchars($row['prefix_en_abbr']) ?>"
                        oninput="this.value = this.value.replace(/[^a-zA-Z\s.]/g, '')"
                        title="กรุณากรอกเฉพาะอักษรภาษาอังกฤษ">
                    </td>
                  </tr>
                </tbody>
              </table>

              <div class="d-flex justify-content-between mt-4">
                <a href="prename.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> ย้อนกลับ</a>
                <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> บันทึกการเปลี่ยนแปลง</button>
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