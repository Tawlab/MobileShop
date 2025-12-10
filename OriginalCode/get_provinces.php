<?php
require_once '../config/config.php'; // ใช้ path นี้ตามโครงสร้างของคุณ

$sql = "SELECT id, name_th FROM provinces ORDER BY name_th ASC";
$result = mysqli_query($conn, $sql);

$provinces = [];
while ($row = mysqli_fetch_assoc($result)) {
    $provinces[] = $row;
}

header('Content-Type: application/json');
echo json_encode($provinces);
?>
