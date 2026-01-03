<?php
session_start();
require '../config/config.php';

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$is_admin = false;

// เช็ค Admin
$chk_sql = "SELECT r.role_name FROM roles r 
            JOIN user_roles ur ON r.role_id = ur.roles_role_id 
            WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt = $conn->prepare($chk_sql)) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $is_admin = true;
    $stmt->close();
}

// --- Helper Functions ---
function getNextStockId($conn) {
    $sql = "SELECT IFNULL(MAX(stock_id), 100000) + 1 as next_id FROM prod_stocks";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result)['next_id'];
}

function checkSerialExists($conn, $serial) {
    $sql = "SELECT stock_id FROM prod_stocks WHERE serial_no = '" . mysqli_real_escape_string($conn, $serial) . "'";
    $result = mysqli_query($conn, $sql);
    return mysqli_num_rows($result) > 0;
}

function getNextMovementId($conn) {
    $move_sql = "SELECT IFNULL(MAX(movement_id), 0) + 1 as next_move_id FROM stock_movements";
    $move_result = mysqli_query($conn, $move_sql);
    return mysqli_fetch_assoc($move_result)['next_move_id'];
}

function handleBatchImageUpload($file_key_name) {
    if (isset($_FILES[$file_key_name]) && $_FILES[$file_key_name]['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/products/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $tmp_name = $_FILES[$file_key_name]['tmp_name'];
        $ext = pathinfo($_FILES[$file_key_name]['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid('stock_', true) . '.' . $ext;
        
        if (move_uploaded_file($tmp_name, $upload_dir . $new_filename)) {
            return $new_filename; 
        }
    }
    return NULL;
}

// --- AJAX Handler: Check Serial ---
if (isset($_POST['action']) && $_POST['action'] == 'check_serial') {
    header('Content-Type: application/json');
    $serial = mysqli_real_escape_string($conn, $_POST['serial_no']);
    echo json_encode(['success' => true, 'exists' => checkSerialExists($conn, $serial)]);
    exit;
}

// --- POST Handler: Save Receive ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['po_id']) && !isset($_POST['action'])) {
    header('Content-Type: application/json'); // บังคับส่ง JSON กลับ

    $po_id = (int)$_POST['po_id'];
    
    // ดึงสาขาปลายทาง
    $po_info_sql = "SELECT branches_branch_id FROM purchase_orders WHERE purchase_id = ?";
    $stmt_po = $conn->prepare($po_info_sql);
    $stmt_po->bind_param("i", $po_id);
    $stmt_po->execute();
    $res_po = $stmt_po->get_result();
    
    if($res_po->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลใบสั่งซื้อ']);
        exit;
    }
    $target_branch_id = $res_po->fetch_assoc()['branches_branch_id'];
    $stmt_po->close();

    mysqli_autocommit($conn, false);
    $success_count = 0;
    
    try {
        if (!isset($_POST['items'])) throw new Exception('ไม่พบรายการสินค้าที่ส่งมา');
        $items_posted = $_POST['items'];

        foreach ($items_posted as $order_detail_id => $batches) {
            if (!is_array($batches)) continue;

            foreach ($batches as $batch_id => $batch_data) {
                $qty = (int)$batch_data['quantity'];
                if ($qty <= 0) continue;

                $prod_id = (int)$batch_data['product_id'];
                $price = floatval($batch_data['selling_price']);
                $serials = $batch_data['serial_no'] ?? [];

                if (count($serials) != $qty) throw new Exception("จำนวน Serial ไม่ตรงกับจำนวนรับ (Item ID: $order_detail_id)");

                $img = handleBatchImageUpload("batch_image_{$batch_id}");

                foreach ($serials as $serial) {
                    $serial = trim($serial);
                    if (empty($serial)) throw new Exception("Serial ห้ามว่างเปล่า");
                    if (checkSerialExists($conn, $serial)) throw new Exception("Serial $serial ซ้ำในระบบ");

                    $stock_id = getNextStockId($conn);
                    
                    // Insert Stock
                    $sql_ins = "INSERT INTO prod_stocks (
                                    stock_id, branches_branch_id, serial_no, price, stock_status, 
                                    image_path, create_at, update_at, products_prod_id
                                ) VALUES (?, ?, ?, ?, 'In Stock', ?, NOW(), NOW(), ?)";
                    
                    $stmt = $conn->prepare($sql_ins);
                    $stmt->bind_param("iisdsi", $stock_id, $target_branch_id, $serial, $price, $img, $prod_id);
                    if (!$stmt->execute()) throw new Exception("เพิ่มสต็อกไม่สำเร็จ: " . $stmt->error);
                    $stmt->close();

                    // Movement Log
                    $move_id = getNextMovementId($conn);
                    $sql_mov = "INSERT INTO stock_movements (movement_id, movement_type, ref_table, ref_id, prod_stocks_stock_id, create_at) 
                                VALUES (?, 'IN', 'order_details', ?, ?, NOW())";
                    $stmt_m = $conn->prepare($sql_mov);
                    $stmt_m->bind_param("iii", $move_id, $order_detail_id, $stock_id);
                    if (!$stmt_m->execute()) throw new Exception("บันทึก Movement ไม่สำเร็จ");
                    $stmt_m->close();

                    $success_count++;
                }
            }
        }

        // Check Complete Status
        $chk_complete_sql = "SELECT od.order_id 
                             FROM order_details od 
                             WHERE od.purchase_orders_purchase_id = ? 
                             AND od.amount > (
                                 SELECT COUNT(*) FROM stock_movements sm 
                                 WHERE sm.ref_table = 'order_details' AND sm.ref_id = od.order_id
                             )";
        $stmt_chk = $conn->prepare($chk_complete_sql);
        $stmt_chk->bind_param("i", $po_id);
        $stmt_chk->execute();
        $res_chk = $stmt_chk->get_result();
        
        if ($res_chk->num_rows == 0) {
            $conn->query("UPDATE purchase_orders SET po_status = 'Completed', update_at = NOW() WHERE purchase_id = $po_id");
        }
        $stmt_chk->close();

        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => "รับสินค้าสำเร็จ $success_count รายการ"]);
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
        exit;
    }
}
?>