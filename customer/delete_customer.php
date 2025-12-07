<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'delete_customer');
// ตรวจสอบ ID
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ไม่พบรหัสลูกค้า";
    header('Location: customer_list.php');
    exit;
}

$cs_id = (int)$_GET['id'];

// ดึงข้อมูลลูกค้าก่อนลบ (เพื่อเอาชื่อและ ID ที่อยู่)
$sql = "SELECT firstname_th, lastname_th, Addresses_address_id FROM customers WHERE cs_id = $cs_id";
$result = mysqli_query($conn, $sql);
$customer = mysqli_fetch_assoc($result);

if (!$customer) {
    $_SESSION['error'] = "ไม่พบข้อมูลลูกค้าในระบบ";
    header('Location: customer_list.php');
    exit;
}

$addr_id = $customer['Addresses_address_id'];
$fullname = $customer['firstname_th'] . ' ' . $customer['lastname_th'];
mysqli_autocommit($conn, false);

try {
    // ลบข้อมูลลูกค้า (Customers)
    $sql_del_cus = "DELETE FROM customers WHERE cs_id = $cs_id";
    if (!mysqli_query($conn, $sql_del_cus)) {
        throw new Exception(mysqli_error($conn), mysqli_errno($conn));
    }

    // ลบข้อมูลที่อยู่ (Addresses) ที่ผูกกัน
    if ($addr_id > 0) {
        $sql_del_addr = "DELETE FROM addresses WHERE address_id = $addr_id";
        mysqli_query($conn, $sql_del_addr);
    }

    mysqli_commit($conn);
    $_SESSION['success'] = "ลบข้อมูลลูกค้าคุณ $fullname เรียบร้อยแล้ว";
} catch (Exception $e) {
    mysqli_rollback($conn);

    //Foreign Key Constraint Fails
    if ($e->getCode() == 1451) {
        $_SESSION['error'] = "ไม่สามารถลบลูกค้าคุณ $fullname ได้ <br>เนื่องจากมีประวัติการซ่อมหรือการซื้อขายในระบบ";
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

header('Location: customer_list.php');
exit;
