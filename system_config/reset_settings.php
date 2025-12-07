<?php
session_start();
require '../config/config.php';
// checkPageAccess($conn, 'reset_settings');

// (1) เปลี่ยนตัวแปรที่รับค่า GET จาก employees_id เป็น user_id
$user_id = $_GET['user_id'] ?? 0;
if (!$user_id) {
  // (2) เปลี่ยนข้อความแจ้งเตือน
  die("ไม่ได้ระบุรหัสผู้ใช้");
}

// ✅ ค่าเริ่มต้นของระบบ ครบทั้ง 14 ฟิลด์ (ตามโค้ดเดิม)
$default_theme_config = [
  'theme_color'        => '#198754',
  'background_color'   => '#ffffff',
  'font_style'         => 'Prompt',
  'text_color'         => '#000000',
  'header_bg_color'    => '#198754',
  'header_text_color'  => '#ffffff',
  'btn_add_color'      => '#198754',
  'btn_edit_color'     => '#ffc107',
  'btn_delete_color'   => '#dc3545',
  'status_on_color'    => '#198754',
  'status_off_color'   => '#dc3545',
  'warning_bg_color'   => '#fff3cd',
  'danger_text_color'  => '#dc3545'
  // 'btn_back_color' ไม่มีในนี้ (และไม่มีใน DB)
];

// (3) แก้ SQL REPLACE ให้ใช้ user_id เป็นคอลัมน์แรก
$sql = "REPLACE INTO systemconfig (
  user_id,
  theme_color, background_color, font_style, text_color,
  header_bg_color, header_text_color,
  btn_add_color, btn_edit_color, btn_delete_color,
  status_on_color, status_off_color,
  warning_bg_color, danger_text_color
) VALUES (
  $user_id,
  '{$default_theme_config['theme_color']}',
  '{$default_theme_config['background_color']}',
  '{$default_theme_config['font_style']}',
  '{$default_theme_config['text_color']}',
  '{$default_theme_config['header_bg_color']}',
  '{$default_theme_config['header_text_color']}',
  '{$default_theme_config['btn_add_color']}',
  '{$default_theme_config['btn_edit_color']}',
  '{$default_theme_config['btn_delete_color']}',
  '{$default_theme_config['status_on_color']}',
  '{$default_theme_config['status_off_color']}',
  '{$default_theme_config['warning_bg_color']}',
  '{$default_theme_config['danger_text_color']}'
)";

if (mysqli_query($conn, $sql)) {
  echo "<script>alert('คืนค่าธีมเป็นค่าเริ่มต้นแล้ว'); window.location.href='settings.php';</script>";
} else {
  echo "<script>alert('เกิดข้อผิดพลาด: " . mysqli_error($conn) . "'); window.history.back();</script>";
}
