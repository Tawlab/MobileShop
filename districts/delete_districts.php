<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'delete_districts');

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // ตรวจสอบว่าอำเภอมีตำบลผูกอยู่หรือไม่
    $check = $conn->prepare("SELECT COUNT(*) FROM subdistricts WHERE districts_district_id  = ?");
    $check->bind_param("s", $id);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();

    if ($count > 0) {
        // ห้ามลบ แจ้งเตือน
        header("Location: districts.php?error=has_subdistricts");
        exit();
    }

    // ลบได้
    $stmt = $conn->prepare("DELETE FROM districts WHERE district_id = ?");
    $stmt->bind_param("s", $id);
    if ($stmt->execute()) {
        header("Location: districts.php?success=1");
    } else {
        header("Location: districts.php?error=delete_failed");
    }
    $stmt->close();
}
