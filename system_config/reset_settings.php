<?php
// reset_settings.php
ob_start();
session_start();
require '../config/config.php';

header('Content-Type: application/json');

// ใช้ user_id จาก Session เพื่อความปลอดภัย
$user_id = $_SESSION['user_id'] ?? 0;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลผู้ใช้งาน']);
    exit;
}

// ค่าเริ่มต้น
$default = [
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
];

// ตรวจสอบว่ามีข้อมูลหรือไม่
$chk = mysqli_query($conn, "SELECT user_id FROM systemconfig WHERE user_id = $user_id");

if (mysqli_num_rows($chk) > 0) {
    // UPDATE
    $sql = "UPDATE systemconfig SET
      theme_color       = '{$default['theme_color']}',
      background_color  = '{$default['background_color']}',
      font_style        = '{$default['font_style']}',
      text_color        = '{$default['text_color']}',
      header_bg_color   = '{$default['header_bg_color']}',
      header_text_color = '{$default['header_text_color']}',
      btn_add_color     = '{$default['btn_add_color']}',
      btn_edit_color    = '{$default['btn_edit_color']}',
      btn_delete_color  = '{$default['btn_delete_color']}',
      status_on_color   = '{$default['status_on_color']}',
      status_off_color  = '{$default['status_off_color']}',
      warning_bg_color  = '{$default['warning_bg_color']}',
      danger_text_color = '{$default['danger_text_color']}'
      WHERE user_id = $user_id";
} else {
    // INSERT (กรณี User ใหม่ยังไม่เคยมี Config)
    $sql = "INSERT INTO systemconfig (user_id, theme_color, background_color, font_style, text_color, header_bg_color, header_text_color) 
            VALUES ($user_id, '{$default['theme_color']}', '{$default['background_color']}', '{$default['font_style']}', '{$default['text_color']}', '{$default['header_bg_color']}', '{$default['header_text_color']}')";
}

if (mysqli_query($conn, $sql)) {
    echo json_encode(['status' => 'success', 'message' => 'คืนค่าเริ่มต้นเรียบร้อยแล้ว']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . mysqli_error($conn)]);
}
ob_end_flush();
?>