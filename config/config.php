<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mobileshop_db";

// 1. เชื่อมต่อฐานข้อมูล
$conn = new mysqli($servername, $username, $password, $dbname);

// 2. ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 3. ตั้งค่าภาษาไทย (สำคัญมาก เพื่อไม่ให้เป็นต่างดาว ???)
$conn->set_charset("utf8mb4");

// 4. ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// 5. เรียกใช้ไฟล์ฟังก์ชันรวม (เช่น ระบบสิทธิ์)
// ใช้ __DIR__ เพื่อให้หาไฟล์เจอเสมอ ไม่ว่าจะ include มาจากโฟลเดอร์ไหน
require_once __DIR__ . '/functions.php';
//ทดสอบ