<?php
session_start();
ob_start();

require '../config/config.php';
checkPageAccess($conn, 'add_prodtype');
require '../config/load_theme.php';

// [1] รับค่า Shop ID และ User ID
$shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// [2] ตรวจสอบสิทธิ์ "centralinf" (จัดการข้อมูลส่วนกลาง)
$has_centralinf_permission = false;
$check_perm_sql = "SELECT p.permission_id 
                   FROM permissions p
                   JOIN role_permissions rp ON p.permission_id = rp.permissions_permission_id
                   JOIN user_roles ur ON rp.roles_role_id = ur.roles_role_id
                   WHERE ur.users_user_id = ? 
                   AND p.permission_name = 'centralinf' 
                   LIMIT 1";

if ($stmt_perm = mysqli_prepare($conn, $check_perm_sql)) {
    mysqli_stmt_bind_param($stmt_perm, "i", $current_user_id);
    mysqli_stmt_execute($stmt_perm);
    mysqli_stmt_store_result($stmt_perm);
    if (mysqli_stmt_num_rows($stmt_perm) > 0) {
        $has_centralinf_permission = true;
    }
    mysqli_stmt_close($stmt_perm);
}

// ฟังก์ชันสำหรับดึงรหัสถัดไป n รหัส
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
        $type_name_en_input = trim($type['type_name_en'] ?? '');
        $type_name_en = !empty($type_name_en_input) ? $type_name_en_input : NULL;
        $type_id = trim($type['type_id'] ?? '');

        // [3] รับค่า Checkbox และกำหนด Shop ID
        $is_central = isset($type['is_central']) && $type['is_central'] == '1';
        
        if ($has_centralinf_permission && $is_central) {
            $save_shop_id = 0; // ส่วนกลาง
        } else {
            $save_shop_id = $shop_id; // ร้านตัวเอง
        }

        // ข้ามถ้าไม่มีข้อมูล (เช็คแค่ชื่อไทย)
        if (empty($type_name_th)) {
            continue;
        }

        // ตรวจสอบค่าที่จำเป็น 
        if (empty($type_id)) {
            $errors[] = "แถวที่ " . ($index + 1) . ": เกิดข้อผิดพลาด ไม่พบรหัสประเภท";
            continue;
        }

        // ตรวจสอบ ID ซ้ำ (Global Check)
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

        // [4] ตรวจสอบชื่อไทยซ้ำ (Hybrid Check: ร้านเรา หรือ ส่วนกลาง)
        $stmt_check_th = $conn->prepare("SELECT COUNT(*) FROM prod_types WHERE type_name_th = ? AND (shop_info_shop_id = 0 OR shop_info_shop_id = ?)");
        $stmt_check_th->bind_param("si", $type_name_th, $shop_id);
        $stmt_check_th->execute();
        $stmt_check_th->bind_result($count_th);
        $stmt_check_th->fetch();
        $stmt_check_th->close();

        if ($count_th > 0) {
            $errors[] = "แถวที่ " . ($index + 1) . ": ชื่อประเภท (ไทย) '$type_name_th' มีอยู่แล้วในระบบ";
            continue;
        }

        // ตรวจสอบชื่ออังกฤษซ้ำ (Hybrid Check)
        if ($type_name_en !== NULL) {
            $stmt_check_en = $conn->prepare("SELECT COUNT(*) FROM prod_types WHERE type_name_en = ? AND (shop_info_shop_id = 0 OR shop_info_shop_id = ?)");
            $stmt_check_en->bind_param("si", $type_name_en, $shop_id);
            $stmt_check_en->execute();
            $stmt_check_en->bind_result($count_en);
            $stmt_check_en->fetch();
            $stmt_check_en->close();

            if ($count_en > 0) {
                $errors[] = "แถวที่ " . ($index + 1) . ": ชื่อประเภท (อังกฤษ) '$type_name_en' มีอยู่แล้วในระบบ";
                continue;
            }
        }

        // [5] เพิ่มข้อมูล (เพิ่ม shop_info_shop_id)
        $stmt = $conn->prepare("INSERT INTO prod_types (type_id, type_name_th, type_name_en, shop_info_shop_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $type_id, $type_name_th, $type_name_en, $save_shop_id);

        if ($stmt->execute()) {
            $success_count++;
        } else {
            $errors[] = "แถวที่ " . ($index + 1) . ": เกิดข้อผิดพลาดในการบันทึก (" . $stmt->error . ")";
        }

        $stmt->close();
    }

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
        $form_count = isset($_GET['count']) ? max(1, intval($_GET['count'])) : 1;
        echo "<script>window.location.href='add_prodtype.php?count=$form_count';</script>";
        exit();
    }
}

$form_count = isset($_GET['count']) ? max(1, intval($_GET['count'])) : 1;
$next_ids = getNextTypeIds($conn, $form_count);

ob_end_flush();
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

                          <div class="col-md-4">
                            <label class="form-label required-label">ชื่อประเภท (ภาษาไทย)</label>
                            <input type="text" name="types[<?php echo $i; ?>][type_name_th]"
                              class="form-control border-secondary"
                              maxlength="50"
                              pattern="^[ก-๙\s.]+$"
                              title="กรุณากรอกชื่อประเภทเป็นภาษาไทย"
                              required>
                            <div class="invalid-feedback">ชื่อประเภทต้องเป็นภาษาไทย</div>
                          </div>

                          <div class="col-md-4">
                            <label class="form-label">ชื่อประเภท (ภาษาอังกฤษ)</label>
                            <input type="text" name="types[<?php echo $i; ?>][type_name_en]"
                              class="form-control border-secondary"
                              maxlength="50"
                              pattern="^[A-Za-z\s.]+$"
                              title="กรุณากรอกชื่อประเภทเป็นภาษาอังกฤษ (ถ้ามี)">
                            <div class="invalid-feedback">ชื่อประเภทต้องเป็นภาษาอังกฤษ (ถ้ามี)</div>
                          </div>

                          <div class="col-md-4 d-flex align-items-end">
                              <?php if ($has_centralinf_permission): ?>
                                  <div class="form-check mb-2">
                                      <input class="form-check-input border-secondary" type="checkbox" 
                                              name="types[<?php echo $i; ?>][is_central]" 
                                              value="1" 
                                              id="is_central_<?php echo $i; ?>">
                                      <label class="form-check-label fw-bold text-primary" for="is_central_<?php echo $i; ?>">
                                          <i class="bi bi-globe2 me-1"></i>ตั้งเป็นประเภทส่วนกลาง
                                      </label>
                                  </div>
                              <?php endif; ?>
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

            // ถ้ามีการกรอก (ไทย หรือ อังกฤษ)
            if (nameTh || nameEn) {
              hasData = true;

              // บังคับเฉพาะชื่อไทย
              if (!nameTh) {
                nameThInput.setCustomValidity('กรุณากรอกชื่อภาษาไทย');
                formValid = false;
              } else {
                // ตรวจสอบ pattern ภาษาไทย
                if (nameThInput.validity.patternMismatch) {
                  nameThInput.setCustomValidity('ต้องเป็นภาษาไทยเท่านั้น');
                  formValid = false;
                } else {
                  nameThInput.setCustomValidity('');
                }
              }

              // ไม่บังคับชื่ออังกฤษ แต่ถ้ากรอก ต้องถูก pattern
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

          // ใช้ form.checkValidity()
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