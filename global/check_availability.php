<?php
// check_availability.php
require '../config/config.php';

header('Content-Type: application/json');

// รับค่า Action
$action = $_POST['action'] ?? '';

// 1. ตรวจสอบ Username ซ้ำ
if ($action === 'check_username') {
    $username = trim($_POST['username']);
    
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'taken', 'message' => 'ชื่อผู้ใช้งานนี้ถูกใช้ไปแล้ว']);
    } else {
        echo json_encode(['status' => 'available', 'message' => 'สามารถใช้งานได้']);
    }
    $stmt->close();
    exit;
}

// 2. ตรวจสอบชื่อร้านค้าซ้ำ
if ($action === 'check_shop_name') {
    $shop_name = trim($_POST['shop_name']);
    
    $stmt = $conn->prepare("SELECT shop_id, shop_name, tax_id FROM shop_info WHERE shop_name = ? LIMIT 1");
    $stmt->bind_param("s", $shop_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // คืนค่าข้อมูลร้านเดิมกลับไปเพื่อให้ Frontend ถามผู้ใช้
        echo json_encode([
            'status' => 'exists', 
            'shop_id' => $row['shop_id'],
            'shop_name' => $row['shop_name'],
            'tax_id' => $row['tax_id']
        ]);
    } else {
        echo json_encode(['status' => 'available']);
    }
    $stmt->close();
    exit;
}

// 3. ตรวจสอบชื่อสาขาซ้ำ (ภายในร้านเดิม)
if ($action === 'check_branch_duplicate') {
    $shop_id = (int)$_POST['shop_id'];
    $branch_name = trim($_POST['branch_name']);
    
    if ($shop_id > 0) {
        $stmt = $conn->prepare("SELECT branch_id FROM branches WHERE shop_info_shop_id = ? AND branch_name = ?");
        $stmt->bind_param("is", $shop_id, $branch_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['status' => 'taken', 'message' => 'ชื่อสาขานี้มีอยู่แล้วในร้านค้านี้']);
        } else {
            echo json_encode(['status' => 'available']);
        }
        $stmt->close();
    } else {
        // ถ้าร้านใหม่ (ไม่มี shop_id) ถือว่าสาขาไม่ซ้ำ (เพราะร้านยังไม่เกิด)
        echo json_encode(['status' => 'available']);
    }
    exit;
}
?>