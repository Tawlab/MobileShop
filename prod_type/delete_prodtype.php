<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'prodtype'); // ตรวจสอบสิทธิ์การเข้าถึงหน้าเพจ

// [1] รับค่า Shop ID และ User ID จาก Session
$shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// [2] ตรวจสอบสิทธิ์ "centralinf" (ผู้ดูแลระบบส่วนกลาง)
$has_central_perm = false;
$check_perm_sql = "SELECT p.permission_id 
                   FROM permissions p
                   JOIN role_permissions rp ON p.permission_id = rp.permissions_permission_id
                   JOIN user_roles ur ON rp.roles_role_id = ur.roles_role_id
                   WHERE ur.users_user_id = ? AND p.permission_name = 'centralinf' LIMIT 1";

if ($stmt_perm = $conn->prepare($check_perm_sql)) {
    $stmt_perm->bind_param("i", $current_user_id);
    $stmt_perm->execute();
    if ($stmt_perm->get_result()->num_rows > 0) $has_central_perm = true;
    $stmt_perm->close();
}

// [3] ตรวจสอบ ID ที่ต้องการลบ
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $delete_id = mysqli_real_escape_string($conn, $_GET['id']);

    // [4] เตรียมคำสั่ง SQL ตามเงื่อนไขสิทธิ์
    if ($has_central_perm) {
        // แอดมินลบได้ทุกรายการ (ทั้งส่วนกลางและของร้านค้า)
        $sql = "DELETE FROM prod_types WHERE type_id = ?";
    } else {
        // ร้านค้าทั่วไปลบได้เฉพาะรายการที่เป็นของ "ตนเอง" เท่านั้น (shop_id ตรงกัน)
        $sql = "DELETE FROM prod_types WHERE type_id = ? AND shop_info_shop_id = ?";
    }

    if ($stmt = $conn->prepare($sql)) {
        if ($has_central_perm) {
            $stmt->bind_param("s", $delete_id);
        } else {
            $stmt->bind_param("si", $delete_id, $shop_id);
        }

        try {
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $_SESSION['success'] = "ลบประเภทสินค้าเรียบร้อยแล้ว";
                } else {
                    $_SESSION['error'] = "ไม่สามารถลบได้: คุณไม่มีสิทธิ์ลบรายการนี้ หรือรายการนี้เป็นข้อมูลส่วนกลาง";
                }
            }
        } catch (mysqli_sql_exception $e) {
            // [5] จัดการกรณีติดเงื่อนไข Foreign Key (เช่น มีสินค้าที่ใช้ประเภทนี้อยู่)
            if ($e->getCode() == 1451) {
                $_SESSION['error'] = "ไม่สามารถลบได้เนื่องจากมีข้อมูลสินค้าที่ผูกกับประเภทนี้อยู่ในระบบ";
            } else {
                $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        }
        $stmt->close();
    }
} else {
    $_SESSION['error'] = "ระบุรหัสที่ต้องการลบไม่ถูกต้อง";
}

// [6] ส่งกลับไปหน้าหลัก
header("Location: prodtype.php");
exit();