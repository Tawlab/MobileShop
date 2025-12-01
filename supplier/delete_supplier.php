<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'delete_supplier');

// (1) ตรวจสอบว่ามี ID ส่งมาหรือไม่
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: supplier.php?error=invalid_id");
    exit();
}

$supplier_id = $_GET['id'];

// (2) ตรวจสอบว่ามี PO ที่อ้างอิงถึงซัพพลายเออร์นี้หรือไม่
$stmt_check = $conn->prepare("SELECT COUNT(*) as po_count FROM purchase_orders WHERE suppliers_supplier_id = ?");
$stmt_check->bind_param("s", $supplier_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();

if ($result_check['po_count'] > 0) {
    // (3) ถ้ามี PO, ห้ามลบ
    header("Location: supplier.php?error=has_po");
    exit();
}

// (4) ถ้าไม่มี PO, เริ่มกระบวนการลบ (Supplier และ Address)
$conn->begin_transaction();

try {
    // 4.1) ค้นหา Address ID ที่ผูกกับ Supplier นี้ก่อน
    $stmt_get_addr = $conn->prepare("SELECT Addresses_address_id FROM suppliers WHERE supplier_id = ?");
    $stmt_get_addr->bind_param("s", $supplier_id);
    $stmt_get_addr->execute();
    $result_addr = $stmt_get_addr->get_result();

    if ($result_addr->num_rows === 0) {
        throw new Exception("ไม่พบข้อมูลซัพพลายเออร์");
    }

    $address_id = $result_addr->fetch_assoc()['Addresses_address_id'];
    $stmt_get_addr->close();

    // 4.2) ลบ Supplier
    $stmt_del_supplier = $conn->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
    $stmt_del_supplier->bind_param("s", $supplier_id);
    if (!$stmt_del_supplier->execute()) {
        throw new Exception("ลบข้อมูล Supplier ล้มเหลว");
    }
    $stmt_del_supplier->close();

    // 4.3) ลบ Address ที่ผูกกัน (ถ้ามี)
    if ($address_id) {
        $stmt_del_addr = $conn->prepare("DELETE FROM addresses WHERE address_id = ?");
        $stmt_del_addr->bind_param("i", $address_id);
        if (!$stmt_del_addr->execute()) {
            throw new Exception("ลบข้อมูลที่อยู่ล้มเหลว");
        }
        $stmt_del_addr->close();
    }

    // (5) ถ้าสำเร็จทั้งหมด
    $conn->commit();
    header("Location: supplier.php?success=delete");
    exit();
} catch (Exception $e) {
    // (6) ถ้าเกิดข้อผิดพลาด
    $conn->rollback();
    // (สามารถ log $e->getMessage() ไว้ตรวจสอบได้)
    header("Location: supplier.php?error=delete_failed");
    exit();
}
