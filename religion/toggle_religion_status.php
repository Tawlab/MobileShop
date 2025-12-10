<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'toggle_religion_status');

// เปลี่ยนจาก GET เป็น POST เพื่อรับค่าจาก fetch
if (isset($_POST['id']) && isset($_POST['status'])) {

    // รับ religion_id จาก 'id' ที่ส่งมา
    $id = $_POST['id'];

    // แปลง status ที่ส่งมา ( '1' หรือ '0' ) ให้เป็น integer 1 หรือ 0
    $new_status = $_POST['status'] == '1' ? 1 : 0;
    $stmt = $conn->prepare("UPDATE religions SET is_active = ? WHERE religion_id = ?");
    $stmt->bind_param("is", $new_status, $id);

    if ($stmt->execute()) {
        //  ส่ง 'updated' กลับไปให้ JavaScript (fetch) เพื่อยืนยัน
        echo "updated";
    } else {
        echo "error: " . $stmt->error;
    }
    $stmt->close();
} else {
    echo "invalid request";
}
