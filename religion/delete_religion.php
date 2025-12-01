<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'delete_religion');

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // ตรวจสอบว่ามีพนักงานที่อ้างอิงศาสนานี้อยู่หรือไม่
    $check = mysqli_query($conn, "SELECT COUNT(*) AS total FROM employees WHERE religions_religion_id = '$id'");
    $data = mysqli_fetch_assoc($check);

    if ($data['total'] > 0) {
        session_start();
        $_SESSION['error'] = "ไม่สามารถลบศาสนานี้ได้ เนื่องจากมีพนักงานใช้อยู่";
        header("Location: religion.php");
        exit();
    }

    // ถ้าไม่มีพนักงานอ้างอิง จึงลบได้
    $sql = "DELETE FROM religions WHERE religion_id = '$id'";
    if (mysqli_query($conn, $sql)) {
        session_start();
        $_SESSION['success'] = "ลบศาสนาเรียบร้อยแล้ว";
        header("Location: religion.php");
        exit();
    } else {
        session_start();
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการลบ";
        header("Location: religion.php");
        exit();
    }
}
