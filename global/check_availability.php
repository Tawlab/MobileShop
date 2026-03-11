<?php
require '../config/config.php';

header('Content-Type: application/json');
$action = $_POST['action'] ?? '';

// ตรวจสอบ Username ซ้ำ และมีความยาวมากกว่า 6 ตัว
if ($action === 'check_username') {
    $username = trim($_POST['username']);
    
    if (strlen($username) < 6) {
        echo json_encode(['status' => 'invalid', 'message' => 'ชื่อผู้ใช้งานต้องมีความยาวอย่างน้อย 6 ตัวอักษร']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'taken', 'message' => 'ชื่อผู้ใช้งานนี้มีคนใช้ไปแล้ว']);
    } else {
        echo json_encode(['status' => 'available']);
    }
    $stmt->close();
    exit;
}

// ตรวจสอบ Password
if ($action === 'check_password') {
    $password = trim($_POST['password']);
    if (strlen($password) < 6) {
         echo json_encode(['status' => 'invalid', 'message' => 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร']);
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
         echo json_encode(['status' => 'invalid', 'message' => 'รหัสผ่านต้องประกอบไปด้วยตัวอักษรและตัวเลข']);
    } else {
         echo json_encode(['status' => 'available']);
    }
    exit;
}

// ตรวจสอบชื่อร้านค้าซ้ำ
if ($action === 'check_shop_name') {
    $shop_name = trim($_POST['shop_name']);
    $stmt = $conn->prepare("SELECT shop_id, shop_name, tax_id FROM shop_info WHERE shop_name = ? LIMIT 1");
    $stmt->bind_param("s", $shop_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
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

// ตรวจสอบชื่อสาขาซ้ำ
if ($action === 'check_branch_duplicate') {
    $shop_id = (int)$_POST['shop_id'];
    $branch_name = trim($_POST['branch_name']);
    if ($shop_id > 0) {
        $stmt = $conn->prepare("SELECT branch_id FROM branches WHERE shop_info_shop_id = ? AND branch_name = ?");
        $stmt->bind_param("is", $shop_id, $branch_name);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['status' => 'taken', 'message' => 'ชื่อสาขานี้มีอยู่แล้วในร้านค้านี้']);
        } else {
            echo json_encode(['status' => 'available']);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'available']);
    }
    exit;
}

// ตรวจสอบเบอร์โทรซ้ำ (เช็คทั้งพนักงานและร้านค้า)
if ($action === 'check_phone') {
    $phone = trim($_POST['phone']);
    $stmt = $conn->prepare("SELECT emp_id FROM employees WHERE emp_phone_no = ? UNION SELECT shop_id FROM shop_info WHERE shop_phone = ?");
    $stmt->bind_param("ss", $phone, $phone);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'taken', 'message' => 'เบอร์โทรศัพท์นี้มีผู้ใช้งานในระบบแล้ว']);
    } else {
        echo json_encode(['status' => 'available']);
    }
    $stmt->close();
    exit;
}

// ตรวจสอบอีเมลซ้ำ
if ($action === 'check_email') {
    $email = trim($_POST['email']);
    $stmt = $conn->prepare("SELECT shop_id FROM shop_info WHERE shop_email = ? UNION SELECT emp_id FROM employees WHERE emp_email = ?");
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'taken', 'message' => 'อีเมลนี้ถูกใช้งานแล้ว']);
    } else {
        echo json_encode(['status' => 'available']);
    }
    $stmt->close();
    exit;
}

// ตรวจสอบเลขผู้เสียภาษี
if ($action === 'check_tax_id') {
    $tax_id = trim($_POST['tax_id']);
    $stmt = $conn->prepare("SELECT shop_id FROM shop_info WHERE tax_id = ? AND tax_id != '' AND tax_id != '-'");
    $stmt->bind_param("s", $tax_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'taken', 'message' => 'เลขผู้เสียภาษีนี้ถูกลงทะเบียนแล้ว']);
    } else {
        echo json_encode(['status' => 'available']);
    }
    $stmt->close();
    exit;
}
?>