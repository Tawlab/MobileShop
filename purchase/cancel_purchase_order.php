<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'cancel_purchase_order');

// ตรวจสอบว่าเป็น POST Request หรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // รับค่า po_id และ comment จากฟอร์ม
    $po_id = isset($_POST['po_id']) ? (int)$_POST['po_id'] : 0;
    $comment = isset($_POST['cancel_comment']) ? trim($_POST['cancel_comment']) : '';

    // ตรวจสอบข้อมูล
    if ($po_id > 0 && !empty($comment)) {
        $sql = "UPDATE purchase_orders 
                SET 
                    po_status = 'Cancelled', 
                    cancel_comment = ? 
                WHERE 
                    purchase_id = ?";

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("si", $comment, $po_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "ยกเลิก PO #$po_id เรียบร้อยแล้ว";
            } else {
                $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปเดตฐานข้อมูล: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการเตรียม SQL: " . $conn->error;
        }
    } else {
        // ถ้าข้อมูลที่ส่งมาไม่ครบ
        $_SESSION['error'] = "ข้อมูลไม่ครบถ้วน กรุณากรอกเหตุผลการยกเลิก";
    }
} else {
    // ถ้าเข้าหน้านี้โดยตรง ไม่ผ่านฟอร์ม
    $_SESSION['error'] = "Invalid request method.";
}

// ส่งผู้ใช้กลับไปหน้ารายการ PO
header('Location: purchase_order.php');
exit;
