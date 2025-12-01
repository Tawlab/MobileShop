<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'delete_department');

// (1) ตรวจสอบว่ามี ID ส่งมาหรือไม่
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: department.php?error=invalid_id");
    exit();
}

$dept_id = $_GET['id'];

// (2) ตรวจสอบว่ามีพนักงานในแผนกนี้หรือไม่ (อ้างอิงจาก FK: departments_dept_id)
$stmt_check = $conn->prepare("SELECT COUNT(*) as emp_count FROM employees WHERE departments_dept_id = ?");
$stmt_check->bind_param("s", $dept_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();

if ($result_check['emp_count'] > 0) {
    // (3) ถ้ามีพนักงาน, ห้ามลบ
    header("Location: department.php?error=has_employees");
    exit();
} else {
    // (4) ถ้าไม่มีพนักงาน, ลบได้
    $stmt_delete = $conn->prepare("DELETE FROM departments WHERE dept_id = ?");
    $stmt_delete->bind_param("s", $dept_id);

    if ($stmt_delete->execute()) {
        // (5) ลบสำเร็จ
        header("Location: department.php?success=delete");
        exit();
    } else {
        // (6) ลบไม่สำเร็จ
        header("Location: department.php?error=delete_failed");
        exit();
    }
    $stmt_delete->close();
}
