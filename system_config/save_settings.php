<?php
session_start();
require '../config/config.php';
// checkPageAccess($conn, 'save_settings');

// (1) เปลี่ยนตัวแปรที่รับค่า POST จาก employees_id เป็น user_id
$user_id            = $_POST['user_id'] ?? 0;
$theme_color        = $_POST['theme_color'] ?? '#198754';
$background_color   = $_POST['background_color'] ?? '#ffffff';
$text_color         = $_POST['text_color'] ?? '#000000';
$font_style         = $_POST['font_style'] ?? 'Prompt';
$header_bg_color    = $_POST['header_bg_color'] ?? '#198754';
$header_text_color  = $_POST['header_text_color'] ?? '#ffffff';

// (2) แก้ SQL check ให้ใช้ user_id
$sql_check = "SELECT * FROM systemconfig WHERE user_id = $user_id";
$result = mysqli_query($conn, $sql_check);

if (mysqli_num_rows($result) > 0) {
  // (3) แก้ SQL UPDATE ให้ใช้ user_id
  // หมายเหตุ: โค้ดเดิมของคุณบันทึกแค่ 6 ค่า
  // ผมจะอัปเดตแค่ 6 ค่าตามโค้ดเดิมของคุณนะครับ
  $sql = "UPDATE systemconfig SET 
    theme_color = '$theme_color',
    background_color = '$background_color',
    text_color = '$text_color',
    font_style = '$font_style',
    header_bg_color = '$header_bg_color',
    header_text_color = '$header_text_color'
    WHERE user_id = $user_id"; // <-- แก้ WHERE
} else {
  // (4) แก้ SQL INSERT ให้ใช้ user_id
  // (INSERT ก็จะบันทึกแค่ 6 ค่าตามฟอร์ม)
  $sql = "INSERT INTO systemconfig (
    user_id, theme_color, background_color, text_color, font_style, header_bg_color, header_text_color
  ) VALUES (
    $user_id, '$theme_color', '$background_color', '$text_color', '$font_style', '$header_bg_color', '$header_text_color'
  )";
}

if (mysqli_query($conn, $sql)) {
  echo "<script>alert('บันทึกสำเร็จ'); window.location.href='settings.php';</script>";
} else {
  echo "<script>alert('ผิดพลาด: " . mysqli_error($conn) . "'); window.history.back();</script>";
}
