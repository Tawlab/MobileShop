<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'delete_prodbrand');

// ตรวจสอบว่ามีการส่ง id มาหรือไม่
if (!isset($_GET['id'])) {
    header("Location: prodbrand.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

// ลบข้อมูลจากฐานข้อมูล
$sql = "DELETE FROM prod_brands WHERE brand_id = '$id'";
if (mysqli_query($conn, $sql)) {
    header("Location: prodbrand.php");
    exit();
} else {
    echo "Error: " . mysqli_error($conn);
}
?>