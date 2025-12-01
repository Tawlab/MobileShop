<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'delete_shop');

// (1) ตรวจสอบว่ามี ID ส่งมาหรือไม่
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: shop.php?error=invalid_id");
    exit();
}

$shop_id = $_GET['id'];

// (2) ตรวจสอบว่ามี "สาขา" (branches) อ้างอิงถึงร้านค้านี้หรือไม่
// (อ้างอิงจาก FK: shop_info_shop_id ในตาราง branches)
$stmt_check = $conn->prepare("SELECT COUNT(*) as branch_count FROM branches WHERE shop_info_shop_id = ?");
$stmt_check->bind_param("i", $shop_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();

if ($result_check['branch_count'] > 0) {
    // (3) ถ้ามีสาขา, ห้ามลบ
    $_SESSION['error_message'] = "ไม่สามารถลบร้านค้านี้ได้ เนื่องจากยังมีสาขา (" . $result_check['branch_count'] . " สาขา) อ้างอิงอยู่";
    header("Location: shop.php");
    exit();
}

// (4) ถ้าไม่มีสาขา, เริ่มกระบวนการลบ (Shop และ Address)
$conn->begin_transaction();

try {
    // 4.1) ค้นหา Address ID ที่ผูกกับ Shop นี้ก่อน
    $stmt_get_addr = $conn->prepare("SELECT Addresses_address_id FROM shop_info WHERE shop_id = ?");
    $stmt_get_addr->bind_param("i", $shop_id);
    $stmt_get_addr->execute();
    $result_addr = $stmt_get_addr->get_result();

    if ($result_addr->num_rows === 0) {
        throw new Exception("ไม่พบข้อมูลร้านค้า (ID: $shop_id)");
    }

    $address_id = $result_addr->fetch_assoc()['Addresses_address_id'];
    $stmt_get_addr->close();

    $stmt_del_shop = $conn->prepare("DELETE FROM shop_info WHERE shop_id = ?");
    $stmt_del_shop->bind_param("i", $shop_id);
    if (!$stmt_del_shop->execute()) {
        throw new Exception("ลบข้อมูล Shop Info ล้มเหลว");
    }
    $stmt_del_shop->close();

    // (6) ถ้าสำเร็จทั้งหมด
    $conn->commit();
    $_SESSION['success_message'] = "ลบร้านค้า (ID: $shop_id) เรียบร้อยแล้ว";
    header("Location: shop.php");
    exit();
} catch (Exception $e) {
    // (7) ถ้าเกิดข้อผิดพลาด
    $conn->rollback();
    $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการลบ: " . $e->getMessage();
    header("Location: shop.php");
    exit();
}
