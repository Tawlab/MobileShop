<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'edit_prodbrand');
// (1) โหลดธีมก่อน
require '../config/load_theme.php';

// (2) ตรวจสอบ ID ที่ส่งมา
if (!isset($_GET['id']) || empty($_GET['id'])) {
  $_SESSION['error'] = "ไม่พบรหัสยี่ห้อสินค้า";
  header('Location: prodbrand.php');
  exit();
}

$brand_id_to_edit = $_GET['id'];
// (3) แก้ไข SQL SELECT ให้ตรงกับ DB
$stmt = $conn->prepare("SELECT * FROM prod_brands WHERE brand_id = ?");
$stmt->bind_param("s", $brand_id_to_edit);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
  $_SESSION['error'] = "ไม่พบข้อมูลยี่ห้อนี้";
  header('Location: prodbrand.php');
  exit();
}
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  // (4) รับ ID เดิม (ห้ามเปลี่ยน) และชื่อใหม่
  $original_brand_id = trim($_POST['original_brand_id']);
  $brand_name_th = trim($_POST['brand_name_th']);
  $brand_name_en = trim($_POST['brand_name_en']);

  // ตรวจสอบ input
  if (empty($brand_name_th) || empty($brand_name_en)) {
    echo "<script>alert('กรุณากรอกชื่อทั้งภาษาไทยและอังกฤษ'); window.history.back();</script>";
    exit();
  }

  // (5) ลบการตรวจสอบ Regex ของ ID (เพราะเราไม่เปลี่ยน ID)
  // (6) ลบการตรวจสอบ ID ซ้ำ (เพราะเราไม่เปลี่ยน ID)

  // (7) ตรวจสอบชื่อซ้ำ (ยกเว้นตัวเอง)
  $check_stmt = $conn->prepare("SELECT brand_id FROM prod_brands WHERE (brand_name_th = ? OR brand_name_en = ?) AND brand_id != ?");
  $check_stmt->bind_param("sss", $brand_name_th, $brand_name_en, $original_brand_id);
  $check_stmt->execute();
  $check_result = $check_stmt->get_result();
  if ($check_result->num_rows > 0) {
    echo "<script>alert('ชื่อยี่ห้อนี้มีอยู่แล้ว กรุณาใช้ชื่ออื่น'); window.history.back();</script>";
    exit();
  }
  $check_stmt->close();

  // (8) อัพเดทข้อมูล (แก้ SQL - ไม่อัปเดต ID)
  $stmt = $conn->prepare("UPDATE prod_brands SET brand_name_th = ?, brand_name_en = ? WHERE brand_id = ?");
  $stmt->bind_param("sss", $brand_name_th, $brand_name_en, $original_brand_id);

  if ($stmt->execute()) {
    $_SESSION['success'] = "แก้ไขยี่ห้อสินค้าสำเร็จ";
    header('Location: prodbrand.php');
    exit();
  } else {
    echo "<script>alert('เกิดข้อผิดพลาด: " . $stmt->error . "'); window.history.back();</script>";
  }
  $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>แก้ไขยี่ห้อสินค้า</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background-color: <?= $background_color ?>;
      /* Theme */
      color: <?= $text_color ?>;
      /* Theme */
      font-family: '<?= $font_style ?>', sans-serif;
      /* Theme */
      min-height: 100vh;
      display: flex;
      align-items: center;
    }

    .main-card {
      background: white;
      border-radius: 20px;
      box-shadow: 0 5px 30px rgba(0, 0, 0, 0.08);
      overflow: hidden;
      max-width: 700px;
      margin: 0 auto;
    }

    .card-header {
      background: <?= $theme_color ?>;
      /* Theme */
      color: white;
      padding: 2rem;
      border-bottom: none;
      position: relative;
      overflow: hidden;
    }

    .card-header h4 {
      margin: 0;
      font-weight: 600;
      font-size: 1.75rem;
      position: relative;
      z-index: 1;
    }

    .form-section {
      padding: 2rem;
    }

    .form-control {
      border-radius: 12px;
      border: 2px solid #e9ecef;
      padding: 0.75rem 1rem;
      transition: all 0.3s ease;
      font-size: 1rem;
    }

    .form-control:focus {
      border-color: <?= $theme_color ?>;
      /* Theme */
      box-shadow: 0 0 0 0.2rem <?= $theme_color ?>40;
      /* Theme (with opacity) */
    }

    .form-label {
      font-weight: 500;
      color: #495057;
      margin-bottom: 0.5rem;
    }

    .required-label::after {
      content: " *";
      color: #dc3545;
    }

    .btn {
      border-radius: 12px;
      padding: 0.75rem 2rem;
      font-weight: 500;
      transition: all 0.3s ease;
      border: none;
    }

    /* (10) ใช้ .btn-success จาก Theme */
    .btn-success {
      background: <?= $btn_add_color ?>;
      /* Theme */
      color: white !important;
      box-shadow: 0 4px 15px <?= $btn_add_color ?>40;
    }

    .btn-success:hover {
      filter: brightness(90%);
      transform: translateY(-2px);
    }

    .btn-outline-secondary {
      color: #6c757d;
      border: 2px solid #dee2e6;
      background: white;
    }

    .btn-outline-secondary:hover {
      background: #f8f9fa;
      border-color: #adb5bd;
      color: #495057;
      transform: translateY(-1px);
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    .invalid-feedback {
      font-size: 0.875rem;
      margin-top: 0.25rem;
    }

    .alert-warning {
      background-color: <?= $warning_bg_color ?>;
      /* Theme */
      border: 1px solid #ffeaa7;
      border-radius: 12px;
      color: #856404;
      padding: 1rem;
      margin-bottom: 1.5rem;
    }

    @media (max-width: 768px) {
      .card-header {
        padding: 1.5rem;
      }

      .card-header h4 {
        font-size: 1.5rem;
      }

      .form-section {
        padding: 1.5rem;
      }

      .btn {
        padding: 0.6rem 1.5rem;
        font-size: 0.9rem;
      }
    }
  </style>
</head>

<body>
  <div class="d-flex" id="wrapper">
    <?php include '../global/sidebar.php'; ?>
    <div class="main-content w-100">
      <div class="container-fluid py-4">

        <div class="container py-5">
          <div class="main-card">
            <div class="card-header">
              <h4 class="text-light"><i class="bi bi-pencil-square me-2"></i>แก้ไขยี่ห้อสินค้า</h4>
            </div>

            <div class="form-section">
              <form method="post" class="needs-validation" novalidate>

                <input type="hidden" name="original_brand_id" value="<?= htmlspecialchars($row['brand_id']) ?>">

                <div class="form-group">
                  <label class="form-label required-label">รหัสยี่ห้อ (4 หลัก)</label>
                  <input type="text" name="brand_id" class="form-control"
                    maxlength="4"
                    pattern="\d{4}"
                    required
                    readonly
                    value="<?php echo htmlspecialchars($row['brand_id']); ?>"
                    title="รหัสยี่ห้อไม่สามารถแก้ไขได้">
                  <div class="invalid-feedback">
                    กรุณากรอกตัวเลข 4 หลัก เช่น 0001
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label required-label">ชื่อยี่ห้อ (ภาษาไทย)</label>
                      <input type="text" name="brand_name_th" class="form-control border-secondary"
                        maxlength="50"
                        pattern="^[ก-๙\s.]+$"
                        required
                        value="<?php echo htmlspecialchars($row['brand_name_th']); ?>"
                        title="กรุณากรอกชื่อยี่ห้อเป็นภาษาไทย">
                      <div class="invalid-feedback">
                        ชื่อยี่ห้อต้องเป็นภาษาไทยเท่านั้น
                      </div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label required-label">ชื่อยี่ห้อ (ภาษาอังกฤษ)</label>
                      <input type="text" name="brand_name_en" class="form-control border-secondary"
                        maxlength="50"
                        pattern="^[A-Za-z\s.]+$"
                        required
                        value="<?php echo htmlspecialchars($row['brand_name_en']); ?>"
                        title="กรุณากรอกชื่อยี่ห้อเป็นภาษาอังกฤษ">
                      <div class="invalid-feedback">
                        ชื่อยี่ห้อต้องเป็นภาษาอังกฤษเท่านั้น
                      </div>
                    </div>
                  </div>
                </div>

                <div class="alert alert-warning" role="alert">
                  <i class="bi bi-info-circle me-1"></i>
                  หมายเหตุ: รหัสยี่ห้อไม่สามารถแก้ไขได้เพื่อป้องกันข้อมูลเสียหาย
                </div>

                <div class="d-flex justify-content-between mt-4">
                  <a href="prodbrand.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> ย้อนกลับ
                  </a>
                  <button type="submit" class="btn btn-success">
                    <i class="bi bi-save"></i> บันทึกการเปลี่ยนแปลง
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
    // Bootstrap validation
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