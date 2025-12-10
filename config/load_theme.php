<?php
if (!isset($conn)) {
  require 'config.php';
}

// ดึง user_id จาก session ที่ login (ถ้าไม่มี ให้ใช้ 1 เป็น default)
$user_id = $_SESSION['user_id'] ?? 1;
$theme_sql = "SELECT * FROM systemconfig WHERE user_id = $user_id";
$theme_result = mysqli_query($conn, $theme_sql);
$theme = mysqli_fetch_assoc($theme_result);

// Theme variables
$theme_color        = $theme['theme_color'] ?? '#198754';
$background_color   = $theme['background_color'] ?? '#ffffff';
$font_style         = $theme['font_style'] ?? 'Prompt';
$text_color         = $theme['text_color'] ?? '#000000';
$header_bg_color    = $theme['header_bg_color'] ?? '#198754';
$header_text_color  = $theme['header_text_color'] ?? '#ffffff';

$btn_add_color      = $theme['btn_add_color'] ?? '#198754';
$btn_edit_color     = $theme['btn_edit_color'] ?? '#ffc107';
$btn_delete_color   = $theme['btn_delete_color'] ?? '#dc3545';
$btn_back_color = $theme['btn_back_color'] ?? '#ffc107';

$status_on_color    = $theme['status_on_color'] ?? '#198754';
$status_off_color   = $theme['status_off_color'] ?? '#dc3545';

$warning_bg_color   = $theme['warning_bg_color'] ?? '#fff3cd';
$danger_text_color  = $theme['danger_text_color'] ?? '#dc3545';


function adjustBrightness($hex, $steps)
{
  $hex = str_replace('#', '', $hex);

  if (strlen($hex) == 3) {
    $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
  }

  $r = hexdec(substr($hex, 0, 2));
  $g = hexdec(substr($hex, 2, 2));
  $b = hexdec(substr($hex, 4, 2));

  $r = max(0, min(255, $r + $steps));
  $g = max(0, min(255, $g + $steps));
  $b = max(0, min(255, $b + $steps));

  return sprintf("#%02x%02x%02x", $r, $g, $b);
}

// ตรวจสอบว่าสีเข้มหรืออ่อนโดยดูค่า Luminance (YIQ formula)
function isDarkColor($hex)
{
  $hex = str_replace('#', '', $hex);
  if (strlen($hex) == 3) {
    $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
  }

  $r = hexdec(substr($hex, 0, 2));
  $g = hexdec(substr($hex, 2, 2));
  $b = hexdec(substr($hex, 4, 2));

  $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b);
  return $luminance < 128; // true = เข้ม
}

// กำหนดสีตัวอักษรเมื่อ hover
if (isDarkColor($theme_color)) {
  $text_on_hover = adjustBrightness($theme_color, 10); // สีเข้ม → ทำให้อ่อน
  $bg_on_hover = adjustBrightness($theme_color, 100);
} else {
  $text_on_hover = adjustBrightness($theme_color, -10); // สีอ่อน → ทำให้เข้ม
  $bg_on_hover = adjustBrightness($theme_color, -100);
}
?>

<link href="https://fonts.googleapis.com/css2?family=<?= $font_style ?>&display=swap" rel="stylesheet">

<style>
  body,
  td,
  th,
  p,
  .input,
  .form-label {
    color: <?= $text_color ?> !important;
  }

  body {
    background-color: <?= $background_color ?> !important;
    color: <?= $text_color ?> !important;
    font-family: '<?= $font_style ?>', sans-serif;
  }

  /* หัวเรื่อง */
  h1,
  h2,
  h3,
  h4,
  h5,
  h6 .card-title,
  .text-success {
    color: <?= $theme_color ?> !important;
  }

  #title {
    border-bottom: 2px solid <?= $theme_color ?> !important;
  }

  #btn {
    background-color: <?= $theme_color ?> !important;
    border: none;
  }

  #send-otp {
    border-color: <?= $theme_color ?> !important;
    color: <?= $theme_color ?> !important;
  }

  #send-otp:hover {
    background-color: <?= $bg_on_hover ?> !important;
    color: <?= $text_on_hover ?> !important;
  }

  /* หัวคอลัมน์ */
  .table thead tr th {
    background-color: <?= $header_bg_color ?> !important;
    color: <?= $header_text_color ?> !important;
  }

  /* ปุ่มเพิ่ม (ใช้กับ .btn-success หรือ .btn-add) */
  .btn-success {
    background-color: <?= $btn_add_color ?> !important;
    border-color: <?= $btn_add_color ?> !important;
    color: #fff !important;
  }

  #btn,
  .btn-add {
    background-color: <?= $theme_color ?> !important;
    border-color: <?= $theme_color ?> !important;
    color: #fff !important;
  }

  .btn-edit {
    background-color: <?= $btn_edit_color ?> !important;
    border-color: <?= $btn_edit_color ?> !important;
    color: #fff !important;
  }

  .btn-delete {
    background-color: <?= $btn_delete_color ?> !important;
    border-color: <?= $btn_delete_color ?> !important;
    color: #fff !important;
  }

  .btn:hover {
    filter: brightness(90%);
  }

  /* ไอคอนสถานะ */
  .status-icon {
    font-size: 1.3rem;
    cursor: pointer;
    text-decoration: none !important;
  }

  .status-icon.on {
    color: <?= $status_on_color ?> !important;
  }

  .status-icon.off {
    color: <?= $status_off_color ?> !important;
  }

  .inactive {
    opacity: 0.25 !important;
    /* pointer-events: none; <-- บรรทัดนี้ถูกลบออก */
    filter: grayscale(100%) brightness(0.8);
  }


  /* พื้นหลังแจ้งเตือน */
  .card-warning {
    background-color: <?= $warning_bg_color ?> !important;
  }

  /* ข้อความ modal */
  .text-danger,
  .modal-title.danger {
    color: <?= $danger_text_color ?> !important;
  }

  .btn-add,
  .btn-edit,
  .btn-delete {
    cursor: pointer;
  }

  .btn,
  .status-icon {
    transition: all 0.2s ease;
  }
</style>