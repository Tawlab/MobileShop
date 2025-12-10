<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_sale');
$bill_id = $_POST['bill_id'] ?? null;
$stock_ids = $_POST['stock_ids'] ?? [];
$prices = $_POST['prices'] ?? [];
$amounts = $_POST['amounts'] ?? [];

if (!$bill_id) {
    // error
    header("Location: add_sale.php");
    exit;
}

// เพิ่มสินค้า
for ($i = 0; $i < count($stock_ids); $i++) {
    $price = (float)$prices[$i];
    $amount = (int)$amounts[$i];
    $sid = (int)$stock_ids[$i];

    $sql_details = "INSERT INTO bill_details (price, amount, bill_headers_id)
                    VALUES ('$price', '$amount', '$bill_id')";
    mysqli_query($conn, $sql_details);

    mysqli_query($conn, "UPDATE prod_stocks SET proout_types_id = 2 WHERE id = '$sid'");
}

// อัปเดตสถานะบิล
mysqli_query($conn, "UPDATE bill_headers SET bill_status='completed' WHERE id='$bill_id'");

header("Location: payment_sale.php?id=$bill_id");
exit;
