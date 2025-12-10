<?php
session_start();
require '../config/config.php'; 
checkPageAccess($conn, 'delete_permission');

// --- ตรวจสอบว่ามี ID ส่งมาหรือไม่ ---
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "ไม่ได้ระบุ ID สิทธิ์ที่ต้องการลบ";
    $_SESSION['message_type'] = "danger"; 
    header("Location: permission.php");
    exit();
}

$permission_id = (int)$_GET['id'];

// --- ดึงชื่อมาก่อนลบ เพื่อใช้ในข้อความแจ้งเตือน ---
$perm_name = "(ID: $permission_id)";
try {
    $stmt_get = $conn->prepare("SELECT permission_name FROM permissions WHERE permission_id = ?");
    $stmt_get->bind_param("i", $permission_id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    if ($result->num_rows > 0) {
        $perm_name = $result->fetch_assoc()['permission_name'];
    }
    $stmt_get->close();
} catch (Exception $e) {
}

try {
    $sql = "DELETE FROM permissions WHERE permission_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }

    $stmt->bind_param("i", $permission_id);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['message'] = "ลบสิทธิ์ '$perm_name' สำเร็จ (และลบสิทธิ์นี้ออกจากทุกบทบาทแล้ว)";
            $_SESSION['message_type'] = "success";
        } else {
            throw new Exception("ไม่พบสิทธิ์ ID: $permission_id ที่จะลบ");
        }
    } else {
        // --- ล้มเหลว  ---
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['message'] = "ลบสิทธิ์ล้มเหลว: " . $e->getMessage();
    $_SESSION['message_type'] = "danger";
}
$conn->close();
header("Location: permission.php");
exit();
