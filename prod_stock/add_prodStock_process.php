<?php
session_start();
require '../config/config.php';

// ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];
$is_admin = false;
// ตรวจสอบว่าเป็น Admin หรือไม่
$chk_sql = "SELECT r.role_name FROM roles r JOIN user_roles ur ON r.role_id = ur.roles_role_id WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt = $conn->prepare($chk_sql)) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $is_admin = true;
    $stmt->close();
}

// =============================================================================
//  AJAX HANDLERS (ส่วนรับส่งข้อมูลเบื้องหลัง)
// =============================================================================
if (isset($_GET['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax_action'];

    // 1. ดึงข้อมูลสาขา (Branches)
    // เงื่อนไข: Admin ดึงได้ทุกร้านตาม shop_id ที่ส่งมา / User ดึงได้แค่ร้านตัวเอง
    if ($action == 'get_branches') {
        $shop_id = isset($_GET['shop_id']) ? intval($_GET['shop_id']) : 0;
        
        if (!$is_admin && $shop_id != $_SESSION['shop_id']) { 
            echo json_encode([]); exit; 
        }
        
        $sql = "SELECT branch_id, branch_name FROM branches WHERE shop_info_shop_id = ? ORDER BY branch_name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $shop_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = [];
        while ($row = $res->fetch_assoc()) $data[] = $row;
        echo json_encode($data);
        exit;
    }
    // 2. ดึงข้อมูลสินค้า (Products)
    if ($action == 'get_products') {
        $target_shop_id = isset($_GET['shop_id']) ? intval($_GET['shop_id']) : 0;
        
        // Security Check: ถ้าไม่ใช่ Admin ห้ามแอบดึงสินค้าของร้านอื่น
        if (!$is_admin && $target_shop_id != $_SESSION['shop_id']) { 
            echo json_encode([]); exit; 
        }

        // SQL Logic:
        // เลือกสินค้าที่ (เป็นของร้านที่เลือก OR เป็นของส่วนกลาง shop_id=0)
        $sql = "SELECT p.prod_id, p.prod_name, p.model_name, p.prod_price, 
                       pb.brand_name_th as brand_name, pt.type_name_th 
                FROM products p 
                LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id 
                LEFT JOIN prod_types pt ON p.prod_types_type_id = pt.type_id
                WHERE (p.shop_info_shop_id = ? OR p.shop_info_shop_id = 0)";

        // กรองประเภทสินค้าบริการออก (ถ้าไม่ใช่ Admin)
        if (!$is_admin) {
            $sql .= " AND p.prod_types_type_id != 4";
        }

        $sql .= " ORDER BY p.prod_name";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $target_shop_id); // ผูกค่า $target_shop_id ไปกับ SQL
        $stmt->execute();
        $res = $stmt->get_result();
        $data = [];
        while ($row = $res->fetch_assoc()) $data[] = $row;
        echo json_encode($data);
        exit;
    }

    // 3. เช็ค Serial Number ซ้ำ
    if ($action == 'check_serial') {
        $serial = mysqli_real_escape_string($conn, $_POST['serial_no']);
        // เช็คทั่วทั้งระบบว่า Serial นี้มีอยู่แล้วหรือไม่
        $sql = "SELECT stock_id FROM prod_stocks WHERE serial_no = '$serial'";
        $result = mysqli_query($conn, $sql);
        echo json_encode(['success' => true, 'exists' => (mysqli_num_rows($result) > 0)]);
        exit;
    }
}

// =============================================================================
//  FORM SUBMISSION (บันทึกข้อมูลเมื่อกดปุ่ม Save)
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    
    // กำหนดสาขาที่จะบันทึก stock เข้าไป
    $target_branch_id = $is_admin ? intval($_POST['branch_id']) : $_SESSION['branch_id'];
    
    $date_in = !empty($_POST['date_in']) ? mysqli_real_escape_string($conn, $_POST['date_in']) : date('Y-m-d');
    
    // จัดการรูปภาพ (ใช้รูปแรกเป็นปก)
    $first_image_name = NULL;
    if (isset($_FILES['prod_image']) && $_FILES['prod_image']['error'][0] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/products/'; 
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['prod_image']['name'][0], PATHINFO_EXTENSION);
        $new_filename = time() . '_0.' . $ext;
        if (move_uploaded_file($_FILES['prod_image']['tmp_name'][0], $upload_dir . $new_filename)) {
            $first_image_name = $new_filename;
        }
    }

    mysqli_autocommit($conn, false);
    $success_count = 0;

    try {
        $products_prod_id = mysqli_real_escape_string($conn, $_POST['products_prod_id']);
        $price = floatval($_POST['price']);
        $serial_list = $_POST['serial_no']; 
        $ref_table = mysqli_real_escape_string($conn, $_POST['manual_reason']); 

        // if (empty($target_branch_id)) throw new Exception('กรุณาระบุสาขา');
        if (empty($products_prod_id) || empty($serial_list) || $price <= 0) throw new Exception('ข้อมูลไม่ครบถ้วน');
        if (count($serial_list) !== count(array_unique($serial_list))) throw new Exception('Serial Number ซ้ำกันในรายการที่กรอก');

        foreach ($serial_list as $serial) {
            $serial = trim($serial);
            if(empty($serial)) continue;

            $chk = $conn->query("SELECT stock_id FROM prod_stocks WHERE serial_no = '$serial'");
            if($chk->num_rows > 0) throw new Exception("Serial $serial มีอยู่ในระบบแล้ว");

            // หา ID ถัดไปสำหรับ prod_stocks
            $res = mysqli_query($conn, "SELECT IFNULL(MAX(stock_id), 100000) + 1 as next FROM prod_stocks");
            $stock_id = mysqli_fetch_assoc($res)['next'];
            
            // Insert Stock: ระบุ branch_id ตามที่เลือก (ทำให้มองเห็นเฉพาะสาขานั้นๆ ตาม Logic ฐานข้อมูล)
            $sql = "INSERT INTO prod_stocks (stock_id, serial_no, price, stock_status, image_path, create_at, update_at, products_prod_id, branches_branch_id) 
                    VALUES (?, ?, ?, 'In Stock', ?, NOW(), NOW(), ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isdsii", $stock_id, $serial, $price, $first_image_name, $products_prod_id, $target_branch_id);
            if (!$stmt->execute()) throw new Exception('Insert Stock Error: ' . $stmt->error);
            $stmt->close();

            // หา ID ถัดไปสำหรับ stock_movements
            $res_move = mysqli_query($conn, "SELECT IFNULL(MAX(movement_id), 0) + 1 as next FROM stock_movements");
            $move_id = mysqli_fetch_assoc($res_move)['next'];
            
            // Insert Movement Log
            $sql_move = "INSERT INTO stock_movements (movement_id, movement_type, ref_table, prod_stocks_stock_id, create_at) VALUES (?, 'IN', ?, ?, NOW())";
            $stmt_move = $conn->prepare($sql_move);
            $stmt_move->bind_param("isi", $move_id, $ref_table, $stock_id);
            if (!$stmt_move->execute()) throw new Exception('Insert Movement Error: ' . $stmt_move->error);
            $stmt_move->close();

            $success_count++;
        }

        mysqli_commit($conn);
        $_SESSION['success'] = "เพิ่มสินค้าสำเร็จ $success_count ชิ้น";
        header('Location: prod_stock.php');
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        header('Location: add_prodStock.php');
        exit;
    }
}
?>