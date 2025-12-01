<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'toggle_religion_status');

// (1) เปลี่ยนจาก GET เป็น POST เพื่อรับค่าจาก fetch
if (isset($_POST['id']) && isset($_POST['status'])) {

    // (2) รับ religion_id จาก 'id' ที่ส่งมา
    $id = $_POST['id'];

    // (3) แปลง status ที่ส่งมา ( '1' หรือ '0' ) ให้เป็น integer 1 หรือ 0
    // นี่คือ "สถานะใหม่" ที่ต้องการ ไม่ต้องสลับค่าแล้ว
    $new_status = $_POST['status'] == '1' ? 1 : 0;

    // (4) แก้ไข SQL UPDATE ให้ตรงกับ DB
    // (ตาราง religions, คอลัมน์ is_active, คอลัมน์ religion_id)
    $stmt = $conn->prepare("UPDATE religions SET is_active = ? WHERE religion_id = ?");

    // (5) Bind parameter: 'i' = integer (สำหรับ $new_status), 's' = string (สำหรับ $id)
    $stmt->bind_param("is", $new_status, $id);

    if ($stmt->execute()) {
        // (6) ส่ง 'updated' กลับไปให้ JavaScript (fetch) เพื่อยืนยัน
        echo "updated";
    } else {
        echo "error: " . $stmt->error;
    }
    $stmt->close();
} else {
    // (7) ถ้าเข้าถึงไฟล์นี้ตรงๆ (ไม่ใช่ POST)
    echo "invalid request";
}
