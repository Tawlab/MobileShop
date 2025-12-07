<?php
session_start();
require '../config/config.php';

// ตรวจสอบสิทธิ์ (ใช้ชื่อสิทธิ์ del_branch หรือตามที่คุณตั้ง)
checkPageAccess($conn, 'delete_branch');

// ตรวจสอบ ID
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ไม่พบรหัสสาขา";
    header('Location: branch.php');
    exit;
}

$branch_id = (int)$_GET['id'];

// ดึงข้อมูลสาขาก่อนลบ (เพื่อเอาชื่อและ ID ที่อยู่)
$sql = "SELECT branch_name, Addresses_address_id FROM branches WHERE branch_id = $branch_id";
$result = mysqli_query($conn, $sql);
$branch = mysqli_fetch_assoc($result);

if (!$branch) {
    $_SESSION['error'] = "ไม่พบข้อมูลสาขาในระบบ";
    header('Location: branch.php');
    exit;
}

$addr_id = $branch['Addresses_address_id'];
$branch_name = $branch['branch_name'];
mysqli_autocommit($conn, false);

try {
    $sql_del_branch = "DELETE FROM branches WHERE branch_id = $branch_id";
    if (!mysqli_query($conn, $sql_del_branch)) {
        throw new Exception(mysqli_error($conn), mysqli_errno($conn));
    }

    // ลบข้อมูลที่อยู่ (Addresses)
    if ($addr_id > 0) {
        $sql_del_addr = "DELETE FROM addresses WHERE address_id = $addr_id";
        mysqli_query($conn, $sql_del_addr);
    }

    mysqli_commit($conn);
    $_SESSION['success'] = "ลบสาขา '$branch_name' เรียบร้อยแล้ว";
} catch (Exception $e) {
    mysqli_rollback($conn);

    // (Foreign Key Constraint Fails)
    if ($e->getCode() == 1451) {
        $_SESSION['error'] = "ไม่สามารถลบสาขา '$branch_name' ได้ <br>เนื่องจากมีพนักงาน, บิลขาย หรือใบสั่งซื้อ อ้างอิงถึงสาขานี้อยู่";
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// กลับไปหน้าตาราง
header('Location: branch.php');
exit;
