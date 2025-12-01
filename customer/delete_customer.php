<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'delete_customer');
// 1. ตรวจสอบ ID
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ไม่พบรหัสลูกค้า";
    header('Location: customer_list.php');
    exit;
}

$cs_id = (int)$_GET['id'];

// 2. ดึงข้อมูลลูกค้าก่อนลบ (เพื่อเอาชื่อและ ID ที่อยู่)
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

// 3. เริ่มกระบวนการลบ
mysqli_autocommit($conn, false);

try {
    // A. ลบข้อมูลลูกค้า (Customers)
    // ถ้าลูกค้ามีประวัติการซ่อม/ซื้อ จะติด Error ตรงนี้ (MySQL Error 1451)
    $sql_del_cus = "DELETE FROM customers WHERE cs_id = $cs_id";
    if (!mysqli_query($conn, $sql_del_cus)) {
        throw new Exception(mysqli_error($conn), mysqli_errno($conn));
    }

    // B. ลบข้อมูลที่อยู่ (Addresses) ที่ผูกกัน
    if ($addr_id > 0) {
        $sql_del_addr = "DELETE FROM addresses WHERE address_id = $addr_id";
        mysqli_query($conn, $sql_del_addr);
    }

    // สำเร็จ
    mysqli_commit($conn);
    $_SESSION['success'] = "ลบข้อมูลลูกค้าคุณ $fullname เรียบร้อยแล้ว";
} catch (Exception $e) {
    mysqli_rollback($conn);

    // เช็ค Error Code 1451 (Foreign Key Constraint Fails)
    if ($e->getCode() == 1451) {
        $_SESSION['error'] = "ไม่สามารถลบลูกค้าคุณ $fullname ได้ <br>เนื่องจากมีประวัติการซ่อมหรือการซื้อขายในระบบ";
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// กลับไปหน้าตาราง
header('Location: customer_list.php');
exit;
