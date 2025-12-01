<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'toggle_prename_status');

if (isset($_POST['id']) && isset($_POST['status'])) {
    $id = $_POST['id'];
    $status = $_POST['status'] == '1' ? 1 : 0;

    $stmt = $conn->prepare("UPDATE prefixs SET is_active = ? WHERE prefix_id = ?");
    $stmt->bind_param("is", $status, $id);
    $stmt->execute();
    echo "updated";
}
?>
