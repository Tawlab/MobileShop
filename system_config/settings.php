<?php
session_start();
require '../config/config.php';

$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM systemconfig WHERE user_id = $user_id";
$result = mysqli_query($conn, $sql);
$config = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>ตั้งค่าธีม</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=<?= $config['font_style'] ?? 'Prompt' ?>&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    body {
      background-color: <?= $config['background_color'] ?? '#ffffff' ?>;
      color: <?= $config['text_color'] ?? '#000000' ?>;
      font-family: '<?= $config['font_style'] ?? 'Prompt' ?>', sans-serif;
    }

    h4,
    h5 {
      color: <?= $config['theme_color'] ?? '#198754' ?>;
    }

    .btn-success {
      background-color: <?= $config['theme_color'] ?? '#198754' ?> !important;
      border-color: <?= $config['theme_color'] ?? '#198754' ?> !important;
      color: #fff !important;
    }

    .form-label {
      font-weight: 500;
    }

    .container {
      max-width: 900px;
    }
  </style>
</head>

<body>
  <div class="d-flex" id="wrapper">
    <?php include '../global/sidebar.php'; ?>
    <div class="main-content w-100">
      <div class="container-fluid py-4">

        <div class="container py-5">
          <div class="card shadow-lg rounded-4 p-4">
            <h4 class="mb-4"><i class="bi bi-palette me-2"></i> ตั้งค่ารูปแบบเว็บไซต์</h4>

            <form method="POST" action="save_settings.php">

              <input type="hidden" name="user_id" value="<?= $user_id ?>">

              <div class="row g-4">
                <div class="col-md-6">
                  <label class="form-label">สีธีม (ปุ่ม/หัวเรื่อง)</label>
                  <input type="color" name="theme_color" class="form-control form-control-color"
                    value="<?= $config['theme_color'] ?? '#198754' ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label">สีพื้นหลัง</label>
                  <input type="color" name="background_color" class="form-control form-control-color"
                    value="<?= $config['background_color'] ?? '#ffffff' ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label">สีข้อความ</label>
                  <input type="color" name="text_color" class="form-control form-control-color"
                    value="<?= $config['text_color'] ?? '#000000' ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label">ฟอนต์</label>
                  <select name="font_style" class="form-select">
                    <?php
                    $fonts = ['Prompt', 'Sarabun', 'Kanit', 'Mitr'];
                    foreach ($fonts as $font) {
                      $selected = ($config['font_style'] ?? '') == $font ? 'selected' : '';
                      echo "<option value='$font' $selected>$font</option>";
                    }
                    ?>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label">สีหัวคอลัมน์</label>
                  <input type="color" name="header_bg_color" class="form-control form-control-color"
                    value="<?= $config['header_bg_color'] ?? '#198754' ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label">สีตัวอักษรหัวคอลัมน์</label>
                  <input type="color" name="header_text_color" class="form-control form-control-color"
                    value="<?= $config['header_text_color'] ?? '#ffffff' ?>">
                </div>
              </div>

              <div class="mt-4 d-flex justify-content-between">
                <button type="submit" class="btn btn-success px-4">
                  <i class="bi bi-save me-1"></i> บันทึก
                </button>

                <a href="reset_settings.php?user_id=<?= $user_id ?>" class="btn btn-outline-secondary">
                  <i class="bi bi-arrow-counterclockwise me-1"></i> คืนค่าเริ่มต้น
                </a>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>

</html>