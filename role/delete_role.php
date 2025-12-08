<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'delete_role');

// ตรวจสอบว่ามี ID ส่งมาหรือไม่ 
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "ไม่ได้ระบุ ID บทบาทที่ต้องการลบ";
    $_SESSION['message_type'] = "danger";
    header("Location: role.php");
    exit();
}

$role_id = (int)$_GET['id'];

// ดึงชื่อมาก่อนลบ 
$perm_name = "(ID: $role_id)"; 
try {
    $stmt_get = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
    $stmt_get->bind_param("i", $role_id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    if ($result->num_rows > 0) {
        $perm_name = $result->fetch_assoc()['role_name'];
    }
    $stmt_get->close();
} catch (Exception $e) {

}


// เริ่มกระบวนการลบ 
try {
    $sql = "DELETE FROM roles WHERE role_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }

    $stmt->bind_param("i", $role_id);
    if ($stmt->execute()) {
        // ตรวจสอบว่าลบสำเร็จจริง
        if ($stmt->affected_rows > 0) {
            // สำเร็จ
            $_SESSION['message'] = "ลบบทบาท '$role_name' สำเร็จ (และลบบทบาทนี้ออกจากทุกบทบาทแล้ว)";
            $_SESSION['message_type'] = "success";
        } else {
            // ไม่สำเร็จ
            throw new Exception("ไม่พบบทบาท ID: $role_id ที่จะลบ");
        }
    } else {
        // ล้มเหลว 
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $stmt->close();
} catch (Exception $e) {
    // ถ้าเกิด Error
    $_SESSION['message'] = "ลบบทบาทล้มเหลว: " . $e->getMessage();
    $_SESSION['message_type'] = "danger";
}

$conn->close();
header("Location: role.php");
exit();
