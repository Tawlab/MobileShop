<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'delete_subdistrict');

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // ตรวจสอบว่ามีพนักงานผูกอยู่หรือไม่
    $check = $conn->prepare("SELECT COUNT(*) FROM addresses WHERE subdistricts_subdistrict_id = ?");
    $check->bind_param("s", $id);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();

    if ($count > 0) {
        // มีการผูกอยู่ ห้ามลบ
        header("Location: subdistricts.php?error=has_employees");
        exit();
    }

    // ลบได้
    $stmt = $conn->prepare("DELETE FROM subdistricts WHERE subdistrict_id = ?");
    $stmt->bind_param("s", $id);
    if ($stmt->execute()) {
        header("Location: subdistricts.php?success=1");
    } else {
        header("Location: subdistricts.php?error=delete_failed");
    }
    $stmt->close();
}
