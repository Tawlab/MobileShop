<?php
require_once '../config/config.php';

$district_id = $_GET['district_id'];
$sql = "SELECT id, name_th, zip_code FROM subdistricts WHERE districts_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $district_id);
$stmt->execute();
$result = $stmt->get_result();

$subdistricts = [];
while ($row = $result->fetch_assoc()) {
    $subdistricts[] = $row;
}

header('Content-Type: application/json');
echo json_encode($subdistricts);
?>
