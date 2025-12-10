<?php
session_start();
require '../config/config.php';

$user_id            = $_POST['user_id'] ?? 0;
$theme_color        = $_POST['theme_color'] ?? '#198754';
$background_color   = $_POST['background_color'] ?? '#ffffff';
$text_color         = $_POST['text_color'] ?? '#000000';
$font_style         = $_POST['font_style'] ?? 'Prompt';
$header_bg_color    = $_POST['header_bg_color'] ?? '#198754';
$header_text_color  = $_POST['header_text_color'] ?? '#ffffff';
$sql_check = "SELECT * FROM systemconfig WHERE user_id = $user_id";
$result = mysqli_query($conn, $sql_check);

if (mysqli_num_rows($result) > 0) {
  $sql = "UPDATE systemconfig SET 
    theme_color = '$theme_color',
    background_color = '$background_color',
    text_color = '$text_color',
    font_style = '$font_style',
    header_bg_color = '$header_bg_color',
    header_text_color = '$header_text_color'
    WHERE user_id = $user_id"; 
} else {
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
