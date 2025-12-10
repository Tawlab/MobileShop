<?php
require_once '../config/config.php';

$province_id = $_GET['province_id'];
$sql = "SELECT id, name_th FROM districts WHERE provinces_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $province_id);
$stmt->execute();
$result = $stmt->get_result();

$districts = [];
while ($row = $result->fetch_assoc()) {
    $districts[] = $row;
}

header('Content-Type: application/json');
echo json_encode($districts);
?>
