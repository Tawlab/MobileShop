<?php
// save_settings.php
ob_start(); // ป้องกัน HTML แทรก
session_start();
require '../config/config.php';

header('Content-Type: application/json'); // ระบุว่าส่งกลับเป็น JSON

try {
    $user_id = $_POST['user_id'] ?? 0;
    
    // รับค่าจากฟอร์ม
    $theme_color        = $_POST['theme_color'] ?? '#198754';
    $background_color   = $_POST['background_color'] ?? '#ffffff';
    $text_color         = $_POST['text_color'] ?? '#000000';
    $font_style         = $_POST['font_style'] ?? 'Prompt';
    $header_bg_color    = $_POST['header_bg_color'] ?? '#198754';
    $header_text_color  = $_POST['header_text_color'] ?? '#ffffff';

    // ตรวจสอบว่ามีข้อมูล user นี้อยู่แล้วหรือไม่
    $sql_check = "SELECT user_id FROM systemconfig WHERE user_id = $user_id";
    $result = mysqli_query($conn, $sql_check);

    if (mysqli_num_rows($result) > 0) {
        // มีอยู่แล้ว -> Update
        $sql = "UPDATE systemconfig SET 
            theme_color = '$theme_color',
            background_color = '$background_color',
            text_color = '$text_color',
            font_style = '$font_style',
            header_bg_color = '$header_bg_color',
            header_text_color = '$header_text_color'
            WHERE user_id = $user_id"; 
    } else {
        // ยังไม่มี -> Insert
        $sql = "INSERT INTO systemconfig (
            user_id, theme_color, background_color, text_color, font_style, header_bg_color, header_text_color
        ) VALUES (
            $user_id, '$theme_color', '$background_color', '$text_color', '$font_style', '$header_bg_color', '$header_text_color'
        )";
    }

    if (mysqli_query($conn, $sql)) {
        echo json_encode(['status' => 'success', 'message' => 'บันทึกการตั้งค่าเรียบร้อยแล้ว']);
    } else {
        throw new Exception(mysqli_error($conn));
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
ob_end_flush();
?>