<?php
session_start();
require '../config/config.php';

// ตั้งค่าให้ตอบกลับเป็น JSON
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id'])) {
    echo json_encode(['can_delete' => false, 'reason' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

$target_user_id = (int)$_GET['id'];

// หา emp_id ของ user นี้ก่อน 
$emp_id = 0;
$stmt_emp = $conn->prepare("SELECT emp_id FROM employees WHERE users_user_id = ? LIMIT 1");
$stmt_emp->bind_param("i", $target_user_id);
$stmt_emp->execute();
$res_emp = $stmt_emp->get_result();
if ($row = $res_emp->fetch_assoc()) {
    $emp_id = $row['emp_id'];
}
$stmt_emp->close();

if ($emp_id > 0) {
    // ------------------------------------------------------------------------
    // ตรวจสอบประวัติการเปิดบิล (ตาราง bill_headers)
    // ------------------------------------------------------------------------
    $stmt_bills = $conn->prepare("SELECT bill_id FROM bill_headers WHERE employees_emp_id = ? LIMIT 1");
    $stmt_bills->bind_param("i", $emp_id);
    $stmt_bills->execute();
    if ($stmt_bills->get_result()->num_rows > 0) {
        echo json_encode(['can_delete' => false, 'reason' => ' "ทำรายการเปิดบิล/ขายสินค้า" ']);
        $stmt_bills->close();
        exit;
    }
    $stmt_bills->close();

    // ------------------------------------------------------------------------
    // ตรวจสอบประวัติการซ่อม (ตาราง repairs)
    // ------------------------------------------------------------------------
    $stmt_repairs = $conn->prepare("SELECT repair_id FROM repairs WHERE employees_emp_id = ? OR assigned_employee_id = ? LIMIT 1");
    $stmt_repairs->bind_param("ii", $emp_id, $emp_id);
    $stmt_repairs->execute();
    if ($stmt_repairs->get_result()->num_rows > 0) {
        echo json_encode(['can_delete' => false, 'reason' => ' "รับงานซ่อม หรือเป็นช่างที่รับผิดชอบงานซ่อม" ']);
        $stmt_repairs->close();
        exit;
    }
    $stmt_repairs->close();
}

// ถ้าตรวจสอบผ่านทั้งหมด ไม่มีประวัติใดๆ เลย -> อนุญาตให้ลบได้
echo json_encode(['can_delete' => true]);
?>