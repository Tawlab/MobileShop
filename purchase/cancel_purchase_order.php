<?php
session_start();
// (ตั้งค่า Path ให้ถูกต้องตามโครงสร้างโฟลเดอร์ของคุณ)
require '../config/config.php';
checkPageAccess($conn, 'cancel_purchase_order');

// 1. ตรวจสอบว่าเป็น POST Request หรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 2. รับค่า po_id และ comment จากฟอร์ม
    $po_id = isset($_POST['po_id']) ? (int)$_POST['po_id'] : 0;
    $comment = isset($_POST['cancel_comment']) ? trim($_POST['cancel_comment']) : '';

    // 3. ตรวจสอบข้อมูล
    if ($po_id > 0 && !empty($comment)) {

        // 4. เตรียมคำสั่ง SQL (ใช้ Prepared Statement เพื่อความปลอดภัย)
        $sql = "UPDATE purchase_orders 
                SET 
                    po_status = 'Cancelled', 
                    cancel_comment = ? 
                WHERE 
                    purchase_id = ?";

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            // "si" = String (สำหรับ comment), Integer (สำหรับ po_id)
            $stmt->bind_param("si", $comment, $po_id);

            // 5. สั่ง Execute
            if ($stmt->execute()) {
                // (สำเร็จ)
                $_SESSION['success'] = "ยกเลิก PO #$po_id เรียบร้อยแล้ว";
            } else {
                // (ไม่สำเร็จ)
                $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปเดตฐานข้อมูล: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการเตรียม SQL: " . $conn->error;
        }
    } else {
        // (ถ้าข้อมูลที่ส่งมาไม่ครบ)
        $_SESSION['error'] = "ข้อมูลไม่ครบถ้วน กรุณากรอกเหตุผลการยกเลิก";
    }
} else {
    // (ถ้าเข้าหน้านี้โดยตรง ไม่ผ่านฟอร์ม)
    $_SESSION['error'] = "Invalid request method.";
}

// 6. ส่งผู้ใช้กลับไปหน้ารายการ PO
header('Location: purchase_order.php');
exit;
