<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_prodbrand');
require '../config/load_theme.php';

// ฟังก์ชันสำหรับดึงรหัสล่าสุดและสร้างรหัสใหม่
function getNextBrandId($conn)
{
  $query = "SELECT brand_id FROM prod_brands ORDER BY brand_id DESC LIMIT 1";
  $result = mysqli_query($conn, $query);

  if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $lastId = intval($row['brand_id']);
    return str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);
  }
  return '0001'; // ถ้ายังไม่มีข้อมูล เริ่มที่ 0001
}

// ฟังก์ชันสำหรับดึงรหัสถัดไป n รหัส
function getNextBrandIds($conn, $count)
{
  $ids = array();
  $query = "SELECT brand_id FROM prod_brands ORDER BY brand_id DESC LIMIT 1";
  $result = mysqli_query($conn, $query);

  $startId = 1;
  if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $startId = intval($row['brand_id']) + 1;
  }

  for ($i = 0; $i < $count; $i++) {
    $ids[] = str_pad($startId + $i, 4, '0', STR_PAD_LEFT);
  }

  return $ids;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $brands = $_POST['brands'] ?? [];
  $success_count = 0;
  $errors = [];

  foreach ($brands as $index => $brand) {
    $brand_name_th = trim($brand['brand_name_th'] ?? '');
    $brand_name_en = trim($brand['brand_name_en'] ?? '');
    $brand_id = trim($brand['brand_id'] ?? '');

    // ข้ามถ้าไม่มีข้อมูล
    if (empty($brand_name_th) && empty($brand_name_en)) {
      continue;
    }

    // ตรวจสอบค่าที่จำเป็น
    if (empty($brand_name_th) || empty($brand_name_en) || empty($brand_id)) {
      $errors[] = "แถวที่ " . ($index + 1) . ": กรุณากรอกข้อมูลให้ครบ (รหัส, ไทย, อังกฤษ)";
      continue;
    }

    // ตรวจสอบ ID ซ้ำ
    $stmt_check_id = $conn->prepare("SELECT COUNT(*) FROM prod_brands WHERE brand_id = ?");
    $stmt_check_id->bind_param("s", $brand_id);
    $stmt_check_id->execute();
    $stmt_check_id->bind_result($count_id);
    $stmt_check_id->fetch();
    $stmt_check_id->close();
    if ($count_id > 0) {
      $errors[] = "แถวที่ " . ($index + 1) . ": รหัสยี่ห้อ '$brand_id' ซ้ำในระบบ (โปรดรีเฟรชหน้า)";
      continue;
    }

    // ตรวจสอบค่าซ้ำ (ชื่อไทย/อังกฤษ)
    $stmt_check_name = $conn->prepare("SELECT COUNT(*) FROM prod_brands WHERE brand_name_th = ? OR brand_name_en = ?");
    $stmt_check_name->bind_param("ss", $brand_name_th, $brand_name_en);
    $stmt_check_name->execute();
    $stmt_check_name->bind_result($count_name);
    $stmt_check_name->fetch();
    $stmt_check_name->close();

    if ($count_name > 0) {
      $errors[] = "แถวที่ " . ($index + 1) . ": ชื่อยี่ห้อ '$brand_name_th' หรือ '$brand_name_en' ซ้ำในระบบ";
      continue;
    }

    // เพิ่มข้อมูล
    $stmt = $conn->prepare("INSERT INTO prod_brands (brand_id, brand_name_th, brand_name_en) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $brand_id, $brand_name_th, $brand_name_en);

    if ($stmt->execute()) {
      $success_count++;
    } else {
      $errors[] = "แถวที่ " . ($index + 1) . ": เกิดข้อผิดพลาดในการบันทึก";
    }

    $stmt->close();
  }

  // แสดงผลลัพธ์
  if ($success_count > 0 && empty($errors)) {
    $_SESSION['success'] = "เพิ่มยี่ห้อสินค้าสำเร็จ จำนวน $success_count รายการ";
    echo "<script>window.location.href='prodbrand.php';</script>";
    exit();
  } elseif ($success_count > 0 && !empty($errors)) {
    $_SESSION['warning'] = "เพิ่มยี่ห้อสำเร็จ $success_count รายการ แต่มีข้อผิดพลาดบางรายการ";
    $_SESSION['errors'] = $errors; 
    echo "<script>window.location.href='prodbrand.php';</script>";
    exit();
  } else {
    // กรณีล้มเหลวทั้งหมด
    $_SESSION['error'] = "ไม่สามารถเพิ่มยี่ห้อได้";
    $_SESSION['errors'] = $errors;
    echo "<script>window.location.href='add_prodbrand.php?count=$form_count';</script>";
    exit();
  }
}

// กำหนดจำนวนฟอร์มที่จะแสดง
$form_count = isset($_GET['count']) ? max(1, intval($_GET['count'])) : 1;
$next_ids = getNextBrandIds($conn, $form_count);
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>เพิ่มยี่ห้อสินค้า</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background-color: <?= $background_color ?>;
      color: <?= $text_color ?>;
      font-family: '<?= $font_style ?>', sans-serif;
    }

    .main-card {
      background: white;
      border-radius: 20px;
      box-shadow: 0 5px 30px rgba(0, 0, 0, 0.08);
      overflow: hidden;
    }

    .card-header {
      background: <?= $theme_color ?>;
      color: white;
      padding: 1.5rem 2rem;
      border-bottom: none;
    }

    .card-header h4 {
      margin: 0;
      font-weight: 600;
      font-size: 1.5rem;
    }

    .form-control {
      border-radius: 10px;
      border: 1px solid #e9ecef;
      padding: 0.6rem 1rem;
      transition: all 0.3s ease;
    }

    .form-control:focus {
      border-color: <?= $theme_color ?>;
      box-shadow: 0 0 0 0.2rem <?= $theme_color ?>40;
    }

    .required-label::after {
      content: " *";
      color: #dc3545;
    }

    .btn {
      border-radius: 10px;
      padding: 0.5rem 1.5rem;
      font-weight: 500;
      transition: all 0.3s ease;
      border: none;
    }

    .btn-success {
      background: <?= $btn_add_color ?>;
      color: white !important;
      box-shadow: 0 4px 15px <?= $btn_add_color ?>40;
    }

    .btn-success:hover {
      filter: brightness(90%);
      transform: translateY(-2px);
    }

    .btn-primary {
      background: <?= $theme_color ?>;
      color: white !important;
      box-shadow: 0 4px 15px <?= $theme_color ?>40;
    }

    .btn-primary:hover {
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
    }

    .brand-row {
      background: #f8f9fa;
      border-radius: 15px;
      padding: 1.5rem;
      margin-bottom: 1rem;
      border: 1px solid #e9ecef;
      transition: all 0.3s ease;
    }

    .brand-row:hover {
      border-color: <?= $theme_color ?>;
      box-shadow: 0 3px 15px <?= $theme_color ?>20;
    }

    .row-number {
      background: <?= $theme_color ?>;
      color: white;
      width: 35px;
      height: 35px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      box-shadow: 0 3px 10px <?= $theme_color ?>40;
    }

    .count-selector {
      background: #f8f9fa;
      border-radius: 15px;
      padding: 1.5rem;
      margin-bottom: 2rem;
      border: 1px solid #e9ecef;
    }

    .invalid-feedback {
      font-size: 0.875rem;
    }

    @media (max-width: 768px) {
      .brand-row {
        padding: 1rem;
      }

      .form-label {
        font-size: 0.875rem;
      }

      .btn {
        padding: 0.4rem 1rem;
        font-size: 0.875rem;
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
              <h4 class="text-light"><i class="bi bi-tags-fill me-2"></i>เพิ่มยี่ห้อสินค้า</h4>
            </div>

            <div class="card-body p-4">

              <?php if (!empty($_SESSION['errors'])): ?>
                <div class="alert alert-danger" role="alert">
                  <strong>เกิดข้อผิดพลาด:</strong>
                  <ul>
                    <?php foreach ($_SESSION['errors'] as $error): ?>
                      <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
                <?php unset($_SESSION['errors']);
                unset($_SESSION['error']); ?>
              <?php endif; ?>


              <div class="count-selector">
                <div class="row align-items-center">
                  <div class="col-md-8">
                    <label class="form-label fw-bold mb-0">
                      <i class="bi bi-list-ol me-2"></i>จำนวนยี่ห้อที่ต้องการเพิ่ม
                    </label>
                    <small class="text-muted d-block">เลือกจำนวนยี่ห้อที่ต้องการเพิ่มพร้อมกัน</small>
                  </div>
                  <div class="col-md-4">
                    <div class="input-group">
                      <input type="number" id="brandCount" class="form-control" value="<?php echo $form_count; ?>" min="1" max="10">
                      <button class="btn btn-primary" onclick="updateFormCount()">
                        <i class="bi bi-arrow-clockwise me-1"></i> อัพเดทฟอร์ม
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <form method="post" class="needs-validation" novalidate>
                <?php for ($i = 0; $i < $form_count; $i++): ?>
                  <div class="brand-row">
                    <div class="row align-items-start g-3">
                      <div class="col-auto">
                        <div class="row-number"><?php echo $i + 1; ?></div>
                      </div>

                      <div class="col">
                        <div class="row g-3">
                          <input type="hidden" name="brands[<?php echo $i; ?>][brand_id]" value="<?php echo $next_ids[$i]; ?>">

                          <div class="col-md-6">
                            <label class="form-label required-label">ชื่อยี่ห้อ (ภาษาไทย)</label>
                            <input type="text" name="brands[<?php echo $i; ?>][brand_name_th]"
                              class="form-control border-secondary"
                              maxlength="50"
                              pattern="^[ก-๙\s.]+$"
                              title="กรุณากรอกชื่อยี่ห้อเป็นภาษาไทย">
                            <div class="invalid-feedback">ชื่อยี่ห้อต้องเป็นภาษาไทย</div>
                          </div>

                          <div class="col-md-6">
                            <label class="form-label required-label">ชื่อยี่ห้อ (ภาษาอังกฤษ)</label>
                            <input type="text" name="brands[<?php echo $i; ?>][brand_name_en]"
                              class="form-control border-secondary"
                              maxlength="50"
                              pattern="^[A-Za-z\s.]+$"
                              title="กรุณากรอกชื่อยี่ห้อเป็นภาษาอังกฤษ">
                            <div class="invalid-feedback">ชื่อยี่ห้อต้องเป็นภาษาอังกฤษ</div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endfor; ?>

                <div class="d-flex justify-content-between mt-4">
                  <a href="prodbrand.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>ย้อนกลับ
                  </a>
                  <button type="submit" class="btn btn-success btn-lg">
                    <i class="bi bi-save me-2"></i>บันทึกทั้งหมด
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
    // ฟังก์ชันอัพเดทจำนวนฟอร์ม
    function updateFormCount() {
      const count = document.getElementById('brandCount').value;
      window.location.href = `add_prodbrand.php?count=${count}`;
    }

    // การตรวจสอบฟอร์ม
    (() => {
      'use strict';
      const forms = document.querySelectorAll('.needs-validation');
      Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
          let hasData = false;
          let formValid = true;

          const rows = form.querySelectorAll('.brand-row');

          rows.forEach(row => {
            const nameThInput = row.querySelector('input[name*="[brand_name_th]"]');
            const nameEnInput = row.querySelector('input[name*="[brand_name_en]"]');

            const nameTh = nameThInput.value.trim();
            const nameEn = nameEnInput.value.trim();

            if (nameTh || nameEn) { 
              hasData = true;

              // ตรวจสอบว่ากรอกครบ
              if (!nameTh) {
                nameThInput.setCustomValidity('กรุณากรอกชื่อภาษาไทย');
                formValid = false;
              } else {
                nameThInput.setCustomValidity('');
              }

              if (!nameEn) {
                nameEnInput.setCustomValidity('กรุณากรอกชื่อภาษาอังกฤษ');
                formValid = false;
              } else {
                nameEnInput.setCustomValidity('');
              }
            } else {
              nameThInput.setCustomValidity('');
              nameEnInput.setCustomValidity('');
            }
          });

          if (!hasData && rows.length > 0) {
            event.preventDefault();
            event.stopPropagation();
            alert('กรุณากรอกข้อมูลอย่างน้อย 1 ยี่ห้อ');
            return;
          }

          if (!form.checkValidity() || !formValid) {
            event.preventDefault();
            event.stopPropagation();
          }

          form.classList.add('was-validated');
        }, false);
      });
    })();

    // Enter key เพื่อเปลี่ยนจำนวน
    document.getElementById('brandCount').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        updateFormCount();
      }
    });
  </script>

</body>

</html>