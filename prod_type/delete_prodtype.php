<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'delete_prodtype');

// ตรวจสอบว่ามีการส่ง id มาหรือไม่
if (!isset($_GET['id'])) {
    header("Location: prodtype.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

// ลบข้อมูลจากฐานข้อมูล
$sql = "DELETE FROM prod_types WHERE type_id = '$id'";
if (mysqli_query($conn, $sql)) {
    header("Location: prodtype.php");
    exit();
} else {
    echo "Error: " . mysqli_error($conn);
}
