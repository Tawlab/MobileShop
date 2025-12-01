<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'delete_province');

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // ตรวจสอบว่าจังหวัดนี้ถูกใช้งานใน districts หรือไม่
    $check = $conn->prepare("SELECT COUNT(*) FROM districts WHERE provinces_province_id = ?");
    $check->bind_param("s", $id);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();

    if ($count > 0) {
        header("Location: province.php?error=has_districts");
        exit();
    }

    // ลบจังหวัด
    $stmt = $conn->prepare("DELETE FROM provinces WHERE province_id = ?");
    $stmt->bind_param("s", $id);
    if ($stmt->execute()) {
        header("Location: province.php?success=1");
    } else {
        header("Location: province.php?error=delete_failed");
    }
    $stmt->close();
} else {
    header("Location: province.php");
    exit();
}
