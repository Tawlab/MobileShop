<?php
session_start();
require '../config/config.php';
header('Content-Type: application/json');

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

// รับค่า ID (รองรับทั้งแบบ POST และ GET )
$emp_id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

if ($emp_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสพนักงานที่ต้องการลบ (Missing employee ID)']);
    exit;
}

$current_user_id = $_SESSION['user_id'];

try {
    // ดึงข้อมูลพนักงานเพื่อตรวจสอบก่อนลบ
    $sql_get = "SELECT users_user_id, Addresses_address_id, emp_image, firstname_th FROM employees WHERE emp_id = ?";
    $stmt_get = $conn->prepare($sql_get);
    $stmt_get->bind_param("i", $emp_id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("ไม่พบข้อมูลพนักงานในระบบ");
    }

    $row = $result->fetch_assoc();
    $target_user_id = $row['users_user_id'];
    $address_id = $row['Addresses_address_id'];
    $image_path = $row['emp_image'];
    $emp_name = $row['firstname_th'];
    $stmt_get->close();

    // ห้ามลบข้อมูลของตัวเอง
    if ($target_user_id == $current_user_id) {
        echo json_encode(['status' => 'warning', 'message' => 'คุณไม่สามารถลบบัญชีของตัวเองที่กำลังใช้งานอยู่ได้']);
        exit;
    }

    // มีประวัติการทำรายการหรือไม่
    $sql_check = "SELECT (SELECT COUNT(*) FROM bill_headers WHERE employees_emp_id = ?) as bill_count,
                        (SELECT COUNT(*) FROM repairs WHERE employees_emp_id = ? OR assigned_employee_id = ?) as repair_count";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("iii", $emp_id, $emp_id, $emp_id);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($res_check['bill_count'] > 0 || $res_check['repair_count'] > 0) {
        echo json_encode([
            'status' => 'warning', 
            'message' => "ไม่สามารถลบคุณ $emp_name ได้ เนื่องจากมีประวัติการขายหรือซ่อมในระบบแล้ว แนะนำให้เปลี่ยนสถานะเป็น 'ลาออก' แทน"
        ]);
        exit;
    }

    // เริ่มกระบวนการลบ (Transaction)
    $conn->begin_transaction();

    // ลบสิทธิ์การใช้งาน (User Roles)
    if ($target_user_id) {
        $stmt_role = $conn->prepare("DELETE FROM user_roles WHERE users_user_id = ?");
        $stmt_role->bind_param("i", $target_user_id);
        $stmt_role->execute();
    }

    // ลบข้อมูลพนักงาน
    $stmt_emp = $conn->prepare("DELETE FROM employees WHERE emp_id = ?");
    $stmt_emp->bind_param("i", $emp_id);
    $stmt_emp->execute();

    // ลบบัญชีผู้ใช้
    if ($target_user_id) {
        $stmt_user = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt_user->bind_param("i", $target_user_id);
        $stmt_user->execute();
    }

    // ลบที่อยู่
    if ($address_id) {
        $stmt_addr = $conn->prepare("DELETE FROM addresses WHERE address_id = ?");
        $stmt_addr->bind_param("i", $address_id);
        $stmt_addr->execute();
    }

    $conn->commit();

    // ลบไฟล์รูปภาพ
    if (!empty($image_path)) {
        $full_path = "../uploads/employees/" . $image_path;
        if (file_exists($full_path)) { @unlink($full_path); }
    }

    echo json_encode(['status' => 'success', 'message' => "ลบข้อมูลพนักงาน $emp_name เรียบร้อยแล้ว"]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดทางระบบ: ' . $e->getMessage()]);
}
?>