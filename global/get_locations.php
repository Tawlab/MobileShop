<?php
// get_locations.php
require '../config/config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';

$data = [];

if ($action == 'get_provinces') {
    $sql = "SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th ASC";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
} elseif ($action == 'get_districts' && $id) {
    $stmt = $conn->prepare("SELECT district_id, district_name_th FROM districts WHERE provinces_province_id = ? ORDER BY district_name_th ASC");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
} elseif ($action == 'get_subdistricts' && $id) {
    $stmt = $conn->prepare("SELECT subdistrict_id, subdistrict_name_th, zip_code FROM subdistricts WHERE districts_district_id = ? ORDER BY subdistrict_name_th ASC");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

echo json_encode($data);
?>