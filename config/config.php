<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mobileshop_db";
 
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ตั้งค่าภาษาไทย 
$conn->set_charset("utf8mb4");

// ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// เรียกใช้ไฟล์ฟังก์ชันรวม (เช่น ระบบสิทธิ์)
require_once __DIR__ . '/functions.php';
