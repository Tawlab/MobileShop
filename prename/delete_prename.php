<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'delete_prename');

// ตรวจสอบว่ามีการส่ง id มาหรือไม่
if (!isset($_GET['id'])) {
    header("Location: prename.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

// ลบข้อมูลจากฐานข้อมูล
$sql = "DELETE FROM prefixs WHERE prefix_id = '$id'";
if (mysqli_query($conn, $sql)) {
    header("Location: prename.php");
    exit();
} else {
    echo "Error: " . mysqli_error($conn);
}
