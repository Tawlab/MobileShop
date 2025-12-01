<?php
// --- role/delete_role.php ---
session_start();
require '../config/config.php'; // (ตรวจสอบว่า Path 'config.php' ถูกต้อง)
checkPageAccess($conn, 'delete_role');

// --- 1. ตรวจสอบว่ามี ID ส่งมาหรือไม่ ---
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "ไม่ได้ระบุ ID บทบาทที่ต้องการลบ";
    $_SESSION['message_type'] = "danger"; // (ใช้ danger สำหรับ error)
    header("Location: role.php");
    exit();
}

$role_id = (int)$_GET['id'];

// --- 2. (เผื่อไว้) ดึงชื่อมาก่อนลบ เพื่อใช้ในข้อความแจ้งเตือน ---
$perm_name = "(ID: $role_id)"; // (ชื่อเริ่มต้น)
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
    // (ไม่เป็นไรถ้าดึงชื่อไม่สำเร็จ ก็แค่ใช้ ID)
}


// --- 3. เริ่มกระบวนการลบ ---
try {
    // --- (เตรียม SQL DELETE) ---
    $sql = "DELETE FROM roles WHERE role_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }

    $stmt->bind_param("i", $role_id);

    // --- (รันคำสั่ง) ---
    if ($stmt->execute()) {
        // --- (ตรวจสอบว่าลบสำเร็จจริง) ---
        if ($stmt->affected_rows > 0) {
            // --- (สำเร็จ) ---
            $_SESSION['message'] = "ลบบทบาท '$role_name' สำเร็จ (และลบบทบาทนี้ออกจากทุกบทบาทแล้ว)";
            $_SESSION['message_type'] = "success";
        } else {
            // --- (ไม่สำเร็จ - ไม่พบ ID) ---
            throw new Exception("ไม่พบบทบาท ID: $role_id ที่จะลบ");
        }
    } else {
        // --- (ล้มเหลว - Execute) ---
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $stmt->close();
} catch (Exception $e) {
    // --- (ถ้าเกิด Error) ---
    // (แม้จะมี ON DELETE CASCADE แต่เราก็ควรดักจับ Error อื่นๆ ที่อาจเกิดขึ้น)
    $_SESSION['message'] = "ลบบทบาทล้มเหลว: " . $e->getMessage();
    $_SESSION['message_type'] = "danger";
}

$conn->close();

// --- 4. กลับไปหน้ารายการเสมอ ---
header("Location: role.php");
exit();
