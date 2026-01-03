<?php
session_start();
require '../config/config.php';

// ตรวจสอบสิทธิ์
checkPageAccess($conn, 'menu_manage_users');

if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    $current_user_id = $_SESSION['user_id'];

    // 1. ป้องกันการลบตัวเอง
    if ($user_id == $current_user_id) {
        echo "<script>
            alert('ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้');
            window.location.href = 'user_list.php';
        </script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        // 2. ปลดลิงก์พนักงาน (Set users_user_id เป็น NULL ในตาราง employees)
        $sql_unlink = "UPDATE employees SET users_user_id = NULL WHERE users_user_id = ?";
        $stmt = $conn->prepare($sql_unlink);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // 3. ลบ Role ที่เกี่ยวข้อง
        $sql_del_role = "DELETE FROM user_roles WHERE users_user_id = ?";
        $stmt = $conn->prepare($sql_del_role);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // 4. ลบ Config ส่วนตัว
        $sql_del_conf = "DELETE FROM systemconfig WHERE user_id = ?";
        $stmt = $conn->prepare($sql_del_conf);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // 5. ลบ User
        $sql_del_user = "DELETE FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql_del_user);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $conn->commit();
            // ลบสำเร็จ ให้กลับไปหน้ารายการ
            header("Location: user_list.php"); 
        } else {
            throw new Exception("ไม่พบผู้ใช้งานหรือลบไม่สำเร็จ");
        }
        $stmt->close();

    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>
            alert('เกิดข้อผิดพลาด: " . $e->getMessage() . "');
            window.location.href = 'user_list.php';
        </script>";
    }
} else {
    header("Location: user_list.php");
}
?>