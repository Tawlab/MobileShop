<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_prodtype');
// (1) โหลดธีมก่อน
require '../config/load_theme.php';

// (ฟังก์ชัน getNextTypeId, getNextTypeIds เหมือนเดิม)
function getNextTypeId($conn)
{
  $query = "SELECT type_id FROM prod_types ORDER BY type_id DESC LIMIT 1";
  $result = mysqli_query($conn, $query);
  if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $lastId = intval($row['type_id']);
    return str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);
  }
  return '0001';
}
function getNextTypeIds($conn, $count)
{
  $ids = array();
  $query = "SELECT type_id FROM prod_types ORDER BY type_id DESC LIMIT 1";
  $result = mysqli_query($conn, $query);
  $startId = 1;
  if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $startId = intval($row['type_id']) + 1;
  }
  for ($i = 0; $i < $count; $i++) {
    $ids[] = str_pad($startId + $i, 4, '0', STR_PAD_LEFT);
  }
  return $ids;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $types = $_POST['types'] ?? [];
  $success_count = 0;
  $errors = [];

  foreach ($types as $index => $type) {
    $type_name_th = trim($type['type_name_th'] ?? '');
    // (2) รับค่าชื่ออังกฤษ (ถ้าว่าง ให้เป็น NULL)
    $type_name_en_input = trim($type['type_name_en'] ?? '');
    $type_name_en = !empty($type_name_en_input) ? $type_name_en_input : NULL;

    $type_id = trim($type['type_id'] ?? '');

    // ข้ามถ้าไม่มีข้อมูล (เช็คแค่ชื่อไทย)
    if (empty($type_name_th)) {
      continue;
    }

    // (3) ตรวจสอบค่าที่จำเป็น (แก้การตรวจสอบ: เอาชื่ออังกฤษออก)
    if (empty($type_id)) {
      $errors[] = "แถวที่ " . ($index + 1) . ": เกิดข้อผิดพลาด ไม่พบรหัสประเภท";
      continue;
    }

    // (4) ตรวจสอบ ID ซ้ำ (เหมือนเดิม)
    $stmt_check_id = $conn->prepare("SELECT COUNT(*) FROM prod_types WHERE type_id = ?");
    $stmt_check_id->bind_param("s", $type_id);
    $stmt_check_id->execute();
    $stmt_check_id->bind_result($count_id);
    $stmt_check_id->fetch();
    $stmt_check_id->close();
    if ($count_id > 0) {
      $errors[] = "แถวที่ " . ($index + 1) . ": รหัสประเภท '$type_id' ซ้ำในระบบ (โปรดรีเฟรชหน้า)";
      continue;
    }

    // (5) ตรวจสอบชื่อไทยซ้ำ (บังคับ)
    $stmt_check_th = $conn->prepare("SELECT COUNT(*) FROM prod_types WHERE type_name_th = ?");
    $stmt_check_th->bind_param("s", $type_name_th);
    $stmt_check_th->execute();
    $stmt_check_th->bind_result($count_th);
    $stmt_check_th->fetch();
    $stmt_check_th->close();

    if ($count_th > 0) {
      $errors[] = "แถวที่ " . ($index + 1) . ": ชื่อประเภท (ไทย) '$type_name_th' ซ้ำในระบบ";
      continue;
    }

    // (6) ตรวจสอบชื่ออังกฤษซ้ำ (เฉพาะเมื่อกรอก)
    if ($type_name_en !== NULL) {
      $stmt_check_en = $conn->prepare("SELECT COUNT(*) FROM prod_types WHERE type_name_en = ?");
      $stmt_check_en->bind_param("s", $type_name_en);
      $stmt_check_en->execute();
      $stmt_check_en->bind_result($count_en);
      $stmt_check_en->fetch();
      $stmt_check_en->close();

      if ($count_en > 0) {
        $errors[] = "แถวที่ " . ($index + 1) . ": ชื่อประเภท (อังกฤษ) '$type_name_en' ซ้ำในระบบ";
        continue;
      }
    }

    // (7) เพิ่มข้อมูล (แก้ bind_param เป็น sss)
    $stmt = $conn->prepare("INSERT INTO prod_types (type_id, type_name_th, type_name_en) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $type_id, $type_name_th, $type_name_en); // $type_name_en อาจเป็น NULL

    if ($stmt->execute()) {
      $success_count++;
    } else {
      $errors[] = "แถวที่ " . ($index + 1) . ": เกิดข้อผิดพลาดในการบันทึก (" . $stmt->error . ")";
    }

    $stmt->close();
  }

  // (แสดงผลลัพธ์เหมือนเดิม)
  if ($success_count > 0 && empty($errors)) {
    $_SESSION['success'] = "เพิ่มประเภทสินค้าสำเร็จ จำนวน $success_count รายการ";
    echo "<script>window.location.href='prodtype.php';</script>";
    exit();
  } elseif ($success_count > 0 && !empty($errors)) {
    $_SESSION['warning'] = "เพิ่มประเภทสำเร็จ $success_count รายการ แต่มีข้อผิดพลาดบางรายการ";
    $_SESSION['errors'] = $errors;
    echo "<script>window.location.href='prodtype.php';</script>";
    exit();
  } else {
    $_SESSION['error'] = "ไม่สามารถเพิ่มประเภทได้";
    $_SESSION['errors'] = $errors;
    echo "<script>window.location.href='add_prodtype.php?count=$form_count';</script>";
    exit();
  }
}

// (ดึง $form_count และ $next_ids เหมือนเดิม)
$form_count = isset($_GET['count']) ? max(1, intval($_GET['count'])) : 1;
$next_ids = getNextTypeIds($conn, $form_count);
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>เพิ่มประเภทสินค้า</title>
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
    }

    .main-card {
      background: white;
      border-radius: 20px;
      box-shadow: 0 5px 30px rgba(0, 0, 0, 0.08);
      overflow: hidden;
    }

    .card-header {
      background: <?= $theme_color ?>;
      /* Theme */
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
      /* Theme */
      box-shadow: 0 0 0 0.2rem <?= $theme_color ?>40;
      /* Theme (with opacity) */
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
      /* Theme */
      color: white !important;
      box-shadow: 0 4px 15px <?= $btn_add_color ?>40;
    }

    .btn-success:hover {
      filter: brightness(90%);
      transform: translateY(-2px);
    }

    .btn-primary {
      background: <?= $theme_color ?>;
      /* Theme */
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

    .type-row {
      background: #f8f9fa;
      border-radius: 15px;
      padding: 1.5rem;
      margin-bottom: 1rem;
      border: 1px solid #e9ecef;
      transition: all 0.3s ease;
    }

    .type-row:hover {
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
      .type-row {
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
              <h4 class="text-light"><i class="bi bi-diagram-3 me-2"></i>เพิ่มประเภทสินค้า</h4>
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

              <!-- ส่วนเลือกจำนวน -->
              <div class="count-selector">
                <div class="row align-items-center">
                  <div class="col-md-8">
                    <label class="form-label fw-bold mb-0">
                      <i class="bi bi-list-ol me-2"></i>จำนวนประเภทที่ต้องการเพิ่ม
                    </label>
                    <small class="text-muted d-block">เลือกจำนวนประเภทที่ต้องการเพิ่มพร้อมกัน</small>
                  </div>
                  <div class="col-md-4">
                    <div class="input-group">
                      <input type="number" id="typeCount" class="form-control" value="<?php echo $form_count; ?>" min="1" max="10">
                      <button class="btn btn-primary" onclick="updateFormCount()">
                        <i class="bi bi-arrow-clockwise me-1"></i> อัพเดทฟอร์ม
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <form method="post" class="needs-validation" novalidate>
                <?php for ($i = 0; $i < $form_count; $i++): ?>
                  <div class="type-row">
                    <div class="row align-items-start g-3">
                      <div class="col-auto">
                        <div class="row-number"><?php echo $i + 1; ?></div>
                      </div>

                      <div class="col">
                        <div class="row g-3">
                          <input type="hidden" name="types[<?php echo $i; ?>][type_id]" value="<?php echo $next_ids[$i]; ?>">

                          <div class="col-md-6">
                            <!-- (8) ชื่อไทยยังคงบังคับกรอก -->
                            <label class="form-label required-label">ชื่อประเภท (ภาษาไทย)</label>
                            <input type="text" name="types[<?php echo $i; ?>][type_name_th]"
                              class="form-control border-secondary"
                              maxlength="50"
                              pattern="^[ก-๙\s.]+$"
                              title="กรุณากรอกชื่อประเภทเป็นภาษาไทย"
                              required> <!-- (9) เพิ่ม required ที่ HTML -->
                            <div class="invalid-feedback">ชื่อประเภทต้องเป็นภาษาไทย</div>
                          </div>

                          <div class="col-md-6">
                            <label class="form-label">ชื่อประเภท (ภาษาอังกฤษ)</label>
                            <input type="text" name="types[<?php echo $i; ?>][type_name_en]"
                              class="form-control border-secondary"
                              maxlength="50"
                              pattern="^[A-Za-z\s.]+$"
                              title="กรุณากรอกชื่อประเภทเป็นภาษาอังกฤษ (ถ้ามี)">
                            <div class="invalid-feedback">ชื่อประเภทต้องเป็นภาษาอังกฤษ (ถ้ามี)</div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endfor; ?>

                <div class="d-flex justify-content-between mt-4">
                  <a href="prodtype.php" class="btn btn-outline-secondary">
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
    function updateFormCount() {
      const count = document.getElementById('typeCount').value;
      window.location.href = `add_prodtype.php?count=${count}`;
    }

    (() => {
      'use strict';
      const forms = document.querySelectorAll('.needs-validation');
      Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
          let hasData = false;
          let formValid = true;

          const rows = form.querySelectorAll('.type-row');

          rows.forEach(row => {
            const nameThInput = row.querySelector('input[name*="[type_name_th]"]');
            const nameEnInput = row.querySelector('input[name*="[type_name_en]"]');

            const nameTh = nameThInput.value.trim();
            const nameEn = nameEnInput.value.trim();

            // (12) แก้ไข Logic: ถ้ามีการกรอก (ไทย หรือ อังกฤษ)
            if (nameTh || nameEn) {
              hasData = true;

              // (13) บังคับเฉพาะชื่อไทย
              if (!nameTh) {
                nameThInput.setCustomValidity('กรุณากรอกชื่อภาษาไทย');
                formValid = false;
              } else {
                // (14) ตรวจสอบ pattern ภาษาไทย
                if (nameThInput.validity.patternMismatch) {
                  nameThInput.setCustomValidity('ต้องเป็นภาษาไทยเท่านั้น');
                  formValid = false;
                } else {
                  nameThInput.setCustomValidity('');
                }
              }

              // (15) ไม่บังคับชื่ออังกฤษ แต่ถ้ากรอก ต้องถูก pattern
              if (nameEn && nameEnInput.validity.patternMismatch) {
                nameEnInput.setCustomValidity('ต้องเป็นภาษาอังกฤษเท่านั้น');
                formValid = false;
              } else {
                nameEnInput.setCustomValidity('');
              }

            } else {
              // ถ้าแถวนี้ว่าง (ไม่กรอกทั้งคู่)
              nameThInput.setCustomValidity('');
              nameEnInput.setCustomValidity('');
            }
          });

          if (!hasData && rows.length > 0) {
            event.preventDefault();
            event.stopPropagation();
            alert('กรุณากรอกข้อมูลอย่างน้อย 1 ประเภท');
            return;
          }

          // (16) ใช้ form.checkValidity() เพื่อทริกเกอร์ HTML required
          if (!form.checkValidity() || !formValid) {
            event.preventDefault();
            event.stopPropagation();
          }

          form.classList.add('was-validated');
        }, false);
      });
    })();

    document.getElementById('typeCount').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        updateFormCount();
      }
    });
  </script>

</body>

</html>