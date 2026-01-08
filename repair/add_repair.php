<?php
session_start();

// 1. เรียกใช้ Config และ Autoload
require '../config/config.php';
require '../vendor/autoload.php';

// เรียกไฟล์ PHPMailer โดยตรง (ป้องกัน Error Class not found ในบาง Server)
$vendorDir = '../vendor/phpmailer/phpmailer/src/';
if (file_exists($vendorDir . 'Exception.php')) {
    require_once $vendorDir . 'Exception.php';
    require_once $vendorDir . 'PHPMailer.php';
    require_once $vendorDir . 'SMTP.php';
}

// ตรวจสอบสิทธิ์การเข้าถึงหน้าเว็บ
checkPageAccess($conn, 'add_repair');

// รับค่า Branch ID และ Shop ID ปัจจุบันของผู้ใช้จาก Session
$current_branch_id = $_SESSION['branch_id'] ?? 0;
$current_shop_id = $_SESSION['shop_id'] ?? 0;
$current_user_id = $_SESSION['user_id'] ?? 0;

// ตรวจสอบว่าเป็น Admin หรือไม่
$is_admin = false;
$chk_sql = "SELECT r.role_name FROM roles r 
            JOIN user_roles ur ON r.role_id = ur.roles_role_id 
            WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt = $conn->prepare($chk_sql)) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $is_admin = true;
    $stmt->close();
}

// [Admin Only] ดึงรายชื่อร้านค้าและสาขาทั้งหมดเตรียมไว้สำหรับ Dropdown
$shops_list = [];
$branches_list = [];
if ($is_admin) {
    // ดึงร้านค้า
    $shop_res = $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name");
    while ($row = $shop_res->fetch_assoc()) {
        $shops_list[] = $row;
    }
    // ดึงสาขา
    $branch_res = $conn->query("SELECT branch_id, branch_name, shop_info_shop_id FROM branches ORDER BY branch_name");
    while ($row = $branch_res->fetch_assoc()) {
        $branches_list[] = $row;
    }
}

// --- ดึงข้อมูลสำหรับ Dropdown ---
// Logic: ถ้าเป็น Admin จะยังไม่โหลดลูกค้า/พนักงาน (รอเลือกสาขาผ่าน AJAX)
// แต่ถ้าเป็น User ทั่วไป ให้โหลดข้อมูลของสาขาตัวเองทันที
$customers_list = [];
$employees_list = [];

if (!$is_admin) {
    // 1. ดึงรายชื่อลูกค้า (เฉพาะสาขาตัวเอง)
    $sql_cust = "SELECT cs_id, firstname_th, lastname_th, cs_phone_no, cs_email 
                 FROM customers 
                 WHERE branches_branch_id = '$current_branch_id' 
                 ORDER BY firstname_th ASC";
    $res_cust = $conn->query($sql_cust);
    while ($row = $res_cust->fetch_assoc()) $customers_list[] = $row;

    // 2. ดึงรายชื่อพนักงาน (เฉพาะสาขาตัวเอง)
    $sql_emp = "SELECT emp_id, emp_code, firstname_th, lastname_th 
                FROM employees 
                WHERE emp_status = 'Active' AND branches_branch_id = '$current_branch_id' 
                ORDER BY firstname_th ASC";
    $res_emp = $conn->query($sql_emp);
    while ($row = $res_emp->fetch_assoc()) $employees_list[] = $row;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// -----------------------------------------------------------------------------
//  HELPER FUNCTIONS (ฟังก์ชันช่วยงานต่างๆ)
// -----------------------------------------------------------------------------

function getNextRepairId($conn)
{
    $sql = "SELECT IFNULL(MAX(repair_id), 100000) + 1 as next_id FROM repairs";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result)['next_id'];
}

function getNextStockId($conn)
{
    $sql = "SELECT IFNULL(MAX(stock_id), 100000) + 1 as next_id FROM prod_stocks";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result)['next_id'];
}

function getNextMovementId($conn)
{
    $sql = "SELECT IFNULL(MAX(movement_id), 0) + 1 as next_id FROM stock_movements";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result)['next_id'];
}

function getNextBillId($conn)
{
    $sql = "SELECT IFNULL(MAX(bill_id), 0) + 1 as next_id FROM bill_headers";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result)['next_id'];
}

// [UPDATED] ฟังก์ชันเช็คสต็อก โดยเพิ่ม parameter $branch_id
// เพื่อตรวจสอบว่า Serial นี้มีอยู่ในสาขานั้นๆ หรือยัง
function getStockIdBySerial($conn, $serial, $branch_id = null)
{
    $sql = "SELECT stock_id, stock_status, products_prod_id FROM prod_stocks WHERE serial_no = ?";

    // ถ้ามีการระบุสาขา ให้เพิ่มเงื่อนไข WHERE เช็คสาขาด้วย
    if ($branch_id) {
        $sql .= " AND branches_branch_id = " . (int)$branch_id;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $serial);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// --- ฟังก์ชันส่งอีเมล (คงเดิมตามต้นฉบับ) ---
function sendJobOrderEmail($to_email, $customer_name, $repair_id, $device_name, $serial_no, $symptoms_txt, $shop_name, $sender_email, $sender_password)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $sender_email;
        $mail->Password   = $sender_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($sender_email, $shop_name);
        $mail->addAddress($to_email, $customer_name);

        $mail->isHTML(true);
        $mail->Subject = "ใบรับซ่อม / Job Order Received (JOB #$repair_id)";

        // HTML Body Content
        $bodyContent = "
        <html>
        <head>
            <style>
                body { font-family: 'Sarabun', Arial, sans-serif; color: #333; }
                .container { width: 100%; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; }
                .header { background-color: #f8f9fa; padding: 10px; text-align: center; border-bottom: 3px solid #198754; }
                .content { padding: 20px 0; line-height: 1.6; }
                .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .table td { padding: 8px; border-bottom: 1px solid #eee; vertical-align: top; }
                .footer { font-size: 12px; color: #777; text-align: center; margin-top: 30px; border-top: 1px dashed #ccc; padding-top: 10px; }
                .badge { background-color: #0d6efd; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.9em; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2 style='margin:0; color:#198754;'>$shop_name</h2>
                    <p style='margin:5px 0 0;'>ใบรับซ่อม / JOB ORDER</p>
                </div>
                <div class='content'>
                    <p>เรียนคุณ <strong>$customer_name</strong>,</p>
                    <p>ทางร้านได้รับเครื่องของท่านเข้าระบบเรียบร้อยแล้ว รายละเอียดดังนี้:</p>
                    <table class='table'>
                        <tr><td width='35%'><strong>เลขที่ใบงาน:</strong></td><td><strong style='font-size:1.1em;'>#$repair_id</strong></td></tr>
                        <tr><td><strong>วันที่รับเครื่อง:</strong></td><td>" . date("d/m/Y H:i") . "</td></tr>
                        <tr><td><strong>อุปกรณ์:</strong></td><td>$device_name</td></tr>
                        <tr><td><strong>Serial/IMEI:</strong></td><td>$serial_no</td></tr>
                        <tr><td><strong>อาการเสีย:</strong></td><td>$symptoms_txt</td></tr>
                        <tr><td><strong>สถานะ:</strong></td><td><span class='badge'>รับเครื่อง (Received)</span></td></tr>
                    </table>
                    <div style='margin-top: 25px; background-color: #e9ecef; padding: 15px; border-radius: 5px;'>
                        ท่านสามารถนำเลขที่ใบงาน <strong>#$repair_id</strong> มาติดต่อสอบถามสถานะการซ่อมได้ที่ร้าน
                    </div>
                </div>
                <div class='footer'>
                    ขอบคุณที่ใช้บริการ <strong>$shop_name</strong><br>
                    (อีเมลฉบับนี้ส่งจากระบบอัตโนมัติ กรุณาอย่าตอบกลับ)
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->Body = $bodyContent;
        $mail->AltBody = "ได้รับเครื่อง $device_name (Job #$repair_id) เรียบร้อยแล้ว";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// -----------------------------------------------------------------------------
// GET INITIAL DATA (Product & Symptoms)
// -----------------------------------------------------------------------------
$symptoms_result = mysqli_query($conn, "SELECT symptom_id, symptom_name FROM symptoms ORDER BY symptom_name");

// ดึงสินค้า (สำหรับ Dropdown เลือกเครื่องใหม่)
// Admin เห็นสินค้าทั้งหมด, User เห็นเฉพาะสินค้าร้านตัวเอง
if ($is_admin) {
    $prod_sql = "SELECT p.prod_id, p.prod_name, p.model_name, pb.brand_name_th 
                 FROM products p 
                 LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id 
                 WHERE p.prod_types_type_id NOT IN (3, 4) 
                 ORDER BY p.prod_name";
} else {
    $prod_sql = "SELECT p.prod_id, p.prod_name, p.model_name, pb.brand_name_th 
                 FROM products p 
                 LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id 
                 WHERE p.prod_types_type_id NOT IN (3, 4) AND p.shop_info_shop_id = '$current_shop_id' 
                 ORDER BY p.prod_name";
}
$products_result = mysqli_query($conn, $prod_sql);
mysqli_data_seek($products_result, 0);


// -----------------------------------------------------------------------------
//  AJAX HANDLER (ส่วนจัดการคำขอจาก JavaScript)
// -----------------------------------------------------------------------------
if (isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');
    $response = ['success' => false];

    switch ($_POST['action']) {
        // Case 1: Admin เปลี่ยนสาขา -> โหลดข้อมูลลูกค้า/พนักงานใหม่
        case 'get_branch_data':
            $target_branch_id = (int)$_POST['branch_id'];

            // ดึงลูกค้าในสาขานั้น
            $cust_res = $conn->query("SELECT cs_id, firstname_th, lastname_th, cs_phone_no FROM customers WHERE branches_branch_id = '$target_branch_id' ORDER BY firstname_th ASC");
            $customers = [];
            while ($r = $cust_res->fetch_assoc()) $customers[] = $r;

            // ดึงพนักงานในสาขานั้น
            $emp_res = $conn->query("SELECT emp_id, emp_code, firstname_th, lastname_th FROM employees WHERE emp_status = 'Active' AND branches_branch_id = '$target_branch_id' ORDER BY firstname_th ASC");
            $employees = [];
            while ($r = $emp_res->fetch_assoc()) $employees[] = $r;

            $response = ['success' => true, 'customers' => $customers, 'employees' => $employees];
            break;

        // Case 2: ค้นหาลูกค้า (กรองตามสาขา)
        case 'search_customer':
            $search = mysqli_real_escape_string($conn, $_POST['query']);
            // รับค่า branch_id จากหน้าบ้าน (Admin เลือก หรือ User ส่งของตัวเอง)
            $target_branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : $current_branch_id;

            $sql = "SELECT cs_id, firstname_th, lastname_th, cs_phone_no, cs_email 
                    FROM customers 
                    WHERE (cs_phone_no LIKE '%$search%' OR firstname_th LIKE '%$search%' OR lastname_th LIKE '%$search%') 
                    AND branches_branch_id = '$target_branch_id' 
                    LIMIT 10";
            $result = mysqli_query($conn, $sql);
            $customers = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $customers[] = $row;
            }
            $response = ['success' => true, 'customers' => $customers];
            break;

        // Case 3: ค้นหาพนักงาน (กรองตามสาขา)
        case 'search_employee':
            $search = mysqli_real_escape_string($conn, $_POST['query']);
            $target_branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : $current_branch_id;

            $sql = "SELECT e.emp_id, e.firstname_th, e.lastname_th, e.emp_code 
                    FROM employees e
                    WHERE e.emp_status = 'Active' 
                    AND e.branches_branch_id = '$target_branch_id'
                    AND (e.firstname_th LIKE '%$search%' OR e.lastname_th LIKE '%$search%' OR e.emp_code LIKE '%$search%') 
                    LIMIT 10";
            $result = mysqli_query($conn, $sql);
            $employees = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $employees[] = $row;
            }
            $response = ['success' => true, 'employees' => $employees];
            break;

        // Case 4: ตรวจสอบ Serial Number (Logic ตามโจทย์ข้อ 2 และ 3)
        case 'check_serial':
            $serial = mysqli_real_escape_string($conn, $_POST['serial_no']);
            $branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;

            // ใช้ฟังก์ชัน getStockIdBySerial ที่เพิ่ม $branch_id เข้าไปแล้ว
            $stock_info = getStockIdBySerial($conn, $serial, $branch_id);

            if ($stock_info) {
                // เจอ Serial ในสาขานี้
                $response['exists'] = true;
                $response['stock_id'] = $stock_info['stock_id'];
                $response['status'] = $stock_info['stock_status'];

                // ดึงชื่อสินค้า
                $prod_id = $stock_info['products_prod_id'];
                $res_p = $conn->query("SELECT prod_name FROM products WHERE prod_id = '$prod_id'");
                if ($r_p = $res_p->fetch_assoc()) {
                    $response['prod_name'] = $r_p['prod_name'];
                }
            } else {
                // ไม่เจอ Serial ในสาขานี้
                $response['exists'] = false;
            }
            $response['success'] = true;
            break;
    }
    echo json_encode($response);
    exit;
}

// -----------------------------------------------------------------------------
// บันทึกข้อมูล (SAVE LOGIC)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. ตรวจสอบและรับค่า Shop/Branch (Logic ข้อ 1)
    if ($is_admin) {
        // Admin: รับค่าจาก Dropdown
        $target_shop_id = isset($_POST['selected_shop_id']) ? (int)$_POST['selected_shop_id'] : 0;
        $target_branch_id = isset($_POST['selected_branch_id']) ? (int)$_POST['selected_branch_id'] : 0;

        if ($target_shop_id == 0 || $target_branch_id == 0) {
            $_SESSION['error'] = 'กรุณาเลือกร้านค้าและสาขาให้ครบถ้วน';
            header('Location: add_repair.php');
            exit;
        }
    } else {
        // User: บังคับใช้ค่าจาก Session (ป้องกันการเปลี่ยนเอง)
        $target_shop_id = $current_shop_id;
        $target_branch_id = $current_branch_id;
    }

    // รับค่าอื่นๆ จากฟอร์ม
    $customer_id = (int)$_POST['customer_id'];
    $employee_id = (int)$_POST['employee_id'];
    $assigned_employee_id = (int)$_POST['assigned_employee_id'];
    $serial_no = mysqli_real_escape_string($conn, trim($_POST['serial_no']));
    $repair_desc = mysqli_real_escape_string($conn, trim($_POST['repair_desc']));
    $symptom_ids = isset($_POST['symptoms']) ? array_unique($_POST['symptoms']) : [];
    $is_new_device = (int)$_POST['is_new_device'];
    $new_product_id = (int)$_POST['new_product_id'];
    $device_description = mysqli_real_escape_string($conn, trim($_POST['device_description']));
    $estimated_cost = floatval($_POST['estimated_cost']);
    $accessories_list = mysqli_real_escape_string($conn, trim($_POST['accessories_list']));

    // Validation เบื้องต้น
    if ($customer_id <= 0 || $employee_id <= 0 || $assigned_employee_id <= 0 || empty($serial_no) || empty($symptom_ids)) {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลสำคัญให้ครบถ้วน (ลูกค้า, พนักงาน, อาการเสีย, Serial Number)';
        header('Location: add_repair.php');
        exit;
    }

    // เริ่ม Transaction
    mysqli_autocommit($conn, false);

    try {
        // [1] จัดการ Stock (ตรวจสอบตามสาขาเป้าหมาย $target_branch_id)
        $stock_info = getStockIdBySerial($conn, $serial_no, $target_branch_id);

        $stock_id = 0;
        $device_name_for_mail = "";

        if ($stock_info) {
            // กรณี A: เจอเครื่องเดิมในสาขา
            $stock_id = $stock_info['stock_id'];

            // อัปเดตสถานะเป็น 'Repair'
            $conn->query("UPDATE prod_stocks SET stock_status = 'Repair' WHERE stock_id = $stock_id");

            // ดึงชื่อสินค้าไว้ส่งเมล
            $prod_id_ex = $stock_info['products_prod_id'];
            $res_prod = $conn->query("SELECT prod_name FROM products WHERE prod_id = $prod_id_ex");
            if ($r = $res_prod->fetch_assoc()) $device_name_for_mail = $r['prod_name'];
        } else {
            // กรณี B: ไม่เจอในสาขา (ต้องเป็นการเพิ่มเครื่องใหม่)
            if ($is_new_device != 1 || $new_product_id <= 0) {
                throw new Exception('Serial Number นี้ไม่มีในระบบสาขา กรุณาเลือกรุ่นสินค้าเพื่อเพิ่มเข้าสู่ระบบ');
            }

            $stock_id = getNextStockId($conn);
            // สร้าง Stock ใหม่ ผูกกับสาขา $target_branch_id
            $stmt = $conn->prepare("INSERT INTO prod_stocks (stock_id, serial_no, price, stock_status, create_at, update_at, products_prod_id, branches_branch_id) VALUES (?, ?, 0.00, 'Repair', NOW(), NOW(), ?, ?)");
            $stmt->bind_param("isii", $stock_id, $serial_no, $new_product_id, $target_branch_id);

            if (!$stmt->execute()) throw new Exception("เพิ่มสต็อกสินค้าไม่สำเร็จ: " . $stmt->error);
            $stmt->close();

            // ดึงชื่อสินค้าไว้ส่งเมล
            $res_prod = $conn->query("SELECT prod_name FROM products WHERE prod_id = $new_product_id");
            if ($r = $res_prod->fetch_assoc()) $device_name_for_mail = $r['prod_name'];
        }

        // [2] สร้างบิลซ่อม (Bill Header)
        $bill_date = date('Y-m-d H:i:s');
        $bill_status = 'Pending';
        $bill_type = 'Repair';
        $bill_id = getNextBillId($conn);

        $sql_bill = "INSERT INTO bill_headers (
            bill_id, bill_date, receipt_date, payment_method, bill_status, vat, comment, discount, 
            customers_cs_id, bill_type, branches_branch_id, employees_emp_id, create_at, update_at
        ) VALUES (
            ?, ?, ?, 'Cash', ?, 7.00, 'เปิดบิลซ่อม (Job Order)', 0.00,
            ?, ?, ?, ?, NOW(), NOW()
        )";
        $stmt_bill = $conn->prepare($sql_bill);
        // บันทึก target_branch_id ลงในบิล
        $stmt_bill->bind_param("isssisii", $bill_id, $bill_date, $bill_date, $bill_status, $customer_id, $bill_type, $target_branch_id, $employee_id);

        if (!$stmt_bill->execute()) throw new Exception('สร้างบิลซ่อมไม่สำเร็จ: ' . $stmt_bill->error);
        $stmt_bill->close();

        // [3] สร้างใบงานซ่อม (Job Order)
        $repair_id = getNextRepairId($conn);
        $sql_repair = "INSERT INTO repairs (
            repair_id, customers_cs_id, employees_emp_id, assigned_employee_id, 
            prod_stocks_stock_id, repair_desc, device_description, estimated_cost, 
            accessories_list, repair_status, bill_headers_bill_id, branches_branch_id, create_at, update_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'รับเครื่อง', ?, ?, NOW(), NOW())";

        $stmt_repair = $conn->prepare($sql_repair);
        // บันทึก target_branch_id ลงในใบแจ้งซ่อม
        $stmt_repair->bind_param("iiiiissdsii", $repair_id, $customer_id, $employee_id, $assigned_employee_id, $stock_id, $repair_desc, $device_description, $estimated_cost, $accessories_list, $bill_id, $target_branch_id);

        if (!$stmt_repair->execute()) throw new Exception('บันทึกงานซ่อมไม่สำเร็จ: ' . $stmt_repair->error);
        $stmt_repair->close();

        // [4] Stock Movement Log
        $movement_id = getNextMovementId($conn);
        $sql_move = "INSERT INTO stock_movements (movement_id, movement_type, ref_table, ref_id, create_at, prod_stocks_stock_id) VALUES (?, 'IN', 'repairs', ?, NOW(), ?)";
        $stmt_move = $conn->prepare($sql_move);
        $stmt_move->bind_param("iii", $movement_id, $repair_id, $stock_id);
        $stmt_move->execute();
        $stmt_move->close();

        // [5] บันทึกอาการเสีย (Repair Symptoms)
        $sql_symptoms = "INSERT INTO repair_symptoms (repairs_repair_id, symptoms_symptom_id) VALUES ";
        $values = [];
        $params = [];
        $types = '';
        $symptoms_text_arr = [];

        foreach ($symptom_ids as $sid) {
            $values[] = "(?, ?)";
            $params[] = $repair_id;
            $params[] = (int)$sid;
            $types .= 'ii';
            // เก็บชื่ออาการไว้ส่งเมล
            $res_sym = $conn->query("SELECT symptom_name FROM symptoms WHERE symptom_id = " . (int)$sid);
            if ($row_sym = $res_sym->fetch_assoc()) $symptoms_text_arr[] = $row_sym['symptom_name'];
        }

        if (!empty($values)) {
            $sql_symptoms .= implode(', ', $values);
            $stmt_sym = $conn->prepare($sql_symptoms);
            $stmt_sym->bind_param($types, ...$params);
            $stmt_sym->execute();
            $stmt_sym->close();
        }

        // [6] Log Status (Repair Status Log)
        $conn->query("INSERT INTO repair_status_log (repairs_repair_id, new_status, update_by_employee_id, update_at) VALUES ($repair_id, 'รับเครื่อง', $employee_id, NOW())");

        // Commit Transaction
        mysqli_commit($conn);

        // [7] ส่งอีเมลแจ้งเตือนลูกค้า (Email Notification)
        try {
            $res_cust = $conn->query("SELECT firstname_th, lastname_th, cs_email FROM customers WHERE cs_id = $customer_id");
            $cust_data = $res_cust->fetch_assoc();
            $cust_email = $cust_data['cs_email'];
            $cust_name = $cust_data['firstname_th'] . " " . $cust_data['lastname_th'];

            // ดึงข้อมูลอีเมลร้านค้า (ผู้ส่ง) ตาม target_shop_id
            $res_shop = $conn->query("SELECT shop_name, shop_email, shop_app_password FROM shop_info WHERE shop_id = '$target_shop_id' LIMIT 1");
            $shop_data = $res_shop->fetch_assoc();

            if ($shop_data && !empty($cust_email)) {
                $symptoms_txt_mail = implode(", ", $symptoms_text_arr);
                if (!empty($repair_desc)) $symptoms_txt_mail .= " (เพิ่มเติม: $repair_desc)";

                @sendJobOrderEmail(
                    $cust_email,
                    $cust_name,
                    $repair_id,
                    $device_name_for_mail,
                    $serial_no,
                    $symptoms_txt_mail,
                    $shop_data['shop_name'],
                    $shop_data['shop_email'],
                    $shop_data['shop_app_password']
                );
            }
        } catch (Exception $mail_err) {
            // ไม่ต้องทำอะไรถ้าส่งเมลไม่ผ่าน
        }

        $_SESSION['success'] = "✅ รับเครื่องซ่อมสำเร็จ: Job #$repair_id";
        header("Location: view_repair.php?id=$repair_id"); // แก้ไขให้ Redirect ไปหน้าที่ต้องการ
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = '❌ เกิดข้อผิดพลาด: ' . $e->getMessage();
        header('Location: add_repair.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รับเครื่องซ่อมใหม่ (Job Order)</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <?php require '../config/load_theme.php'; ?>

    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            color: <?= $text_color ?>;
        }

        .container {
            max-width: 1200px;
        }

        h4 {
            font-weight: 700;
            color: <?= $theme_color ?>;
        }

        /* Card & Form Styles */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            background-color: #fff;
            border-bottom: 2px solid #f0f0f0;
            padding: 20px 25px;
            border-radius: 12px 12px 0 0 !important;
        }

        .form-section {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            border: 1px solid #eef2f6;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
            margin-bottom: 25px;
            position: relative;
        }

        .form-section h5 {
            margin-bottom: 20px;
            font-weight: 600;
            color: #495057;
            border-left: 4px solid <?= $theme_color ?>;
            padding-left: 10px;
        }

        /* Customer Info Box */
        .customer-info-box {
            background-color: #f8f9fa;
            border: 1px dashed #dee2e6;
            border-radius: 8px;
            padding: 15px;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* Serial Number Status Alerts */
        .serial-check-status {
            margin-top: 8px;
            padding: 10px 15px;
            border-radius: 8px;
            display: none;
            /* ซ่อนไว้ก่อน แสดงด้วย JS */
            font-size: 0.9rem;
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .serial-check-status.valid {
            background-color: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }

        .serial-check-status.new {
            background-color: #cff4fc;
            color: #055160;
            border: 1px solid #b6effb;
        }

        .serial-check-status.error {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }

        /* Symptom Grid Checkboxes */
        .symptom-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 12px;
            padding: 15px;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }

        .symptom-item {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            border: 1px solid #f0f0f0;
            border-radius: 6px;
            transition: all 0.2s;
            cursor: pointer;
            position: relative;
        }

        .symptom-item:hover {
            background-color: #f8f9fa;
            border-color: #dee2e6;
        }

        .form-check-input:checked+.form-check-label {
            font-weight: bold;
            color: <?= $theme_color ?>;
        }

        /* Select2 Customization */
        .select2-container--bootstrap-5 .select2-selection {
            border-color: #dee2e6;
            padding: 0.5rem 0.75rem;
            min-height: 45px;
        }

        /* Modal Custom */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background-color: <?= $theme_color ?>;
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>

        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container py-4">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">
                                <i class="fas fa-tools me-2"></i> ฟอร์มรับเครื่องซ่อม (Job Order)
                            </h4>
                        </div>

                        <div class="card-body p-4">

                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>ข้อผิดพลาด!</strong> <?php echo $_SESSION['error'];
                                                                    unset($_SESSION['error']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="add_repair.php" id="repairForm" novalidate>

                                <?php if ($is_admin): ?>
                                    <div class="form-section bg-light border-primary border-opacity-25">
                                        <h5 class="text-primary"><i class="fas fa-store me-2"></i>เลือกสาขาที่รับงาน (Admin)</h5>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">ร้านค้า <span class="text-danger">*</span></label>
                                                <select name="selected_shop_id" id="selected_shop_id" class="form-select select2" required>
                                                    <option value="">-- เลือกร้านค้า --</option>
                                                    <?php foreach ($shops_list as $shop): ?>
                                                        <option value="<?= $shop['shop_id'] ?>">
                                                            <?= htmlspecialchars($shop['shop_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">สาขา <span class="text-danger">*</span></label>
                                                <select name="selected_branch_id" id="selected_branch_id" class="form-select select2" required>
                                                    <option value="">-- เลือกสาขา --</option>
                                                    <?php foreach ($branches_list as $br): ?>
                                                        <option value="<?= $br['branch_id'] ?>" data-shop="<?= $br['shop_info_shop_id'] ?>">
                                                            <?= htmlspecialchars($br['branch_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <input type="hidden" name="selected_shop_id" id="selected_shop_id" value="<?= $current_shop_id ?>">
                                    <input type="hidden" name="selected_branch_id" id="selected_branch_id" value="<?= $current_branch_id ?>">
                                <?php endif; ?>


                                <div class="form-section">
                                    <h5><i class="fas fa-users me-2"></i>ข้อมูลผู้เกี่ยวข้อง</h5>
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label for="customer_id" class="form-label fw-bold">ลูกค้า <span class="text-danger">*</span></label>
                                            <div class="d-flex gap-2">
                                                <div class="flex-grow-1">
                                                    <select name="customer_id" id="customer_id" class="form-select select2" required>
                                                        <option value="">-- ค้นหาชื่อ หรือเบอร์โทรลูกค้า --</option>
                                                        <?php foreach ($customers_list as $cust): ?>
                                                            <option value="<?= $cust['cs_id'] ?>">
                                                                <?= htmlspecialchars($cust['firstname_th'] . ' ' . $cust['lastname_th']) ?> (<?= $cust['cs_phone_no'] ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <a href="../customer/add_customer.php?return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-outline-success" title="เพิ่มลูกค้าใหม่">
                                                    <i class="fas fa-user-plus"></i>
                                                </a>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="customer-info-box" id="customer_info_box">
                                                <p class="text-muted mb-0 small text-center"><i class="fas fa-info-circle me-1"></i>รายละเอียดลูกค้าจะแสดงที่นี่เมื่อเลือก</p>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="employee_id" class="form-label fw-bold">พนักงานผู้รับเรื่อง <span class="text-danger">*</span></label>
                                            <select name="employee_id" id="employee_id" class="form-select select2" required>
                                                <option value="">-- ระบุพนักงาน --</option>
                                                <?php foreach ($employees_list as $emp): ?>
                                                    <option value="<?= $emp['emp_id'] ?>" <?= ($emp['emp_id'] == $current_user_id) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($emp['firstname_th'] . ' ' . $emp['lastname_th']) ?> (<?= $emp['emp_code'] ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="assigned_employee_id" class="form-label fw-bold">ช่างผู้รับผิดชอบงานซ่อม <span class="text-danger">*</span></label>
                                            <select name="assigned_employee_id" id="assigned_employee_id" class="form-select select2" required>
                                                <option value="">-- ระบุช่างซ่อม --</option>
                                                <?php foreach ($employees_list as $emp): ?>
                                                    <option value="<?= $emp['emp_id'] ?>">
                                                        <?= htmlspecialchars($emp['firstname_th'] . ' ' . $emp['lastname_th']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>


                                <div class="form-section">
                                    <h5><i class="fas fa-mobile-alt me-2"></i>ข้อมูลเครื่องที่ส่งซ่อม</h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="serial_no" class="form-label fw-bold">Serial Number / IMEI <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-white"><i class="fas fa-barcode text-muted"></i></span>
                                                <input type="text" class="form-control" name="serial_no" id="serial_no" maxlength="50" required placeholder="ระบุเลขเครื่องเพื่อตรวจสอบในระบบ">
                                            </div>
                                            <div id="serial_status" class="serial-check-status"></div>

                                            <input type="hidden" name="is_new_device" id="is_new_device" value="0">
                                        </div>

                                        <div class="col-md-6" id="new_device_select" style="display:none;">
                                            <label for="new_product_id" class="form-label fw-bold text-primary">เลือกรุ่นสินค้า (เพิ่มสินค้าใหม่เข้าระบบ) <span class="text-danger">*</span></label>
                                            <select class="form-select select2" name="new_product_id" id="new_product_id">
                                                <option value="">-- ค้นหารุ่นสินค้า --</option>
                                                <?php while ($p = mysqli_fetch_assoc($products_result)): ?>
                                                    <option value="<?= $p['prod_id'] ?>">
                                                        <?= htmlspecialchars($p['prod_name']) ?> (<?= htmlspecialchars($p['brand_name_th']) ?>)
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                            <div class="form-text text-primary"><i class="fas fa-plus-circle me-1"></i> เนื่องจาก Serial นี้ไม่เคยมีในสาขา ระบบจะทำการเพิ่มเป็นสินค้าใหม่ให้อัตโนมัติ</div>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="estimated_cost" class="form-label fw-bold">ราคาประเมินเบื้องต้น (บาท) <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">฿</span>
                                                <input type="number" class="form-control" name="estimated_cost" id="estimated_cost" step="0.01" min="0" value="0.00" required>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="accessories_list" class="form-label fw-bold">อุปกรณ์ที่นำมาด้วย</label>
                                            <input type="text" class="form-control" name="accessories_list" id="accessories_list" maxlength="255" placeholder="เช่น: ตัวเครื่อง, สายชาร์จ, กล่อง, เคส">
                                        </div>

                                        <div class="col-md-12">
                                            <label for="device_description" class="form-label fw-bold">สภาพภายนอก / ตำหนิ</label>
                                            <textarea class="form-control" name="device_description" id="device_description" rows="2" maxlength="255" placeholder="เช่น: มีรอยร้าวที่มุมจอ, ฝาหลังถลอก, ปุ่มเปิดกดยาก"></textarea>
                                        </div>
                                    </div>
                                </div>


                                <div class="form-section">
                                    <h5><i class="fas fa-stethoscope me-2"></i>อาการเสีย</h5>

                                    <label class="form-label mb-2 fw-bold">เลือกอาการที่พบ (เลือกได้มากกว่า 1 ข้อ) <span class="text-danger">*</span></label>
                                    <div class="symptom-grid">
                                        <?php
                                        mysqli_data_seek($symptoms_result, 0);
                                        if (mysqli_num_rows($symptoms_result) > 0):
                                            while ($symp = mysqli_fetch_assoc($symptoms_result)):
                                        ?>
                                                <div class="form-check symptom-item">
                                                    <input class="form-check-input symptom-checkbox" type="checkbox" name="symptoms[]" value="<?= $symp['symptom_id'] ?>" id="symptom_<?= $symp['symptom_id'] ?>">
                                                    <label class="form-check-label w-100 stretched-link" for="symptom_<?= $symp['symptom_id'] ?>">
                                                        <?= htmlspecialchars($symp['symptom_name']) ?>
                                                    </label>
                                                </div>
                                            <?php
                                            endwhile;
                                        else:
                                            ?>
                                            <div class="text-danger p-3 text-center w-100">
                                                <i class="fas fa-exclamation-circle me-2"></i> ไม่พบข้อมูลอาการเสียในระบบ (กรุณาเพิ่มข้อมูลที่เมนูจัดการอาการเสีย)
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mt-4">
                                        <label for="repair_desc" class="form-label fw-bold">รายละเอียดอาการเพิ่มเติม (สิ่งที่ลูกค้าแจ้ง)</label>
                                        <textarea class="form-control" name="repair_desc" id="repair_desc" rows="3" maxlength="500" placeholder="ระบุอาการโดยละเอียดที่ลูกค้าเล่าให้ฟัง..."></textarea>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-4 pb-5">
                                    <a href="<?= isset($_GET['return_to']) ? urldecode($_GET['return_to']) : 'repair_list.php' ?>" class="btn btn-light btn-lg px-4 border">
                                        <i class="fas fa-times me-2"></i> ยกเลิก
                                    </a>
                                    <button type="submit" class="btn btn-success btn-lg px-5 shadow-sm fw-bold" id="submitBtn">
                                        <i class="fas fa-save me-2"></i> บันทึกรับเครื่อง
                                    </button>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="modal fade" id="customerSearchModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-users me-2"></i>ค้นหาลูกค้า</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="modal_customer_search" placeholder="พิมพ์ชื่อ, เบอร์โทร...">
                        <button class="btn btn-primary" id="modal_search_btn"><i class="fas fa-search"></i> ค้นหา</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <tbody id="modal_customer_results">
                                <tr>
                                    <td class="text-center text-muted py-4">พิมพ์คำค้นหาแล้วกดปุ่มค้นหา</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="employeeSearchModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-tie me-2"></i>ค้นหาพนักงาน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="modal_employee_search" placeholder="พิมพ์ชื่อ, รหัสพนักงาน...">
                        <button class="btn btn-primary" id="modal_employee_search_btn"><i class="fas fa-search"></i> ค้นหา</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <tbody id="modal_employee_results">
                                <tr>
                                    <td class="text-center text-muted py-4">พิมพ์คำค้นหาแล้วกดปุ่มค้นหา</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        // รับข้อมูลสาขาทั้งหมดจาก PHP (ใช้สำหรับ Logic กรองสาขาของ Admin)
        const allBranches = <?php echo json_encode($branches_list); ?>;

        // สถานะการเลือกพนักงาน (สำหรับ Modal Search)
        let isAssignedMode = false;

        // =============================================================================
        //  HELPER FUNCTIONS
        // =============================================================================

        // ฟังก์ชันดึง Branch ID ปัจจุบัน (ใช้ได้ทั้ง Admin และ User)
        function getCurrentBranchId() {
            // ถ้าเป็น Admin ให้ดึงจาก Dropdown, ถ้าเป็น User ให้ดึงจาก Hidden Input
            return $('#selected_branch_id').val();
        }

        // ตั้งค่าว่ากำลังค้นหาพนักงานช่องไหน (รับเรื่อง หรือ ช่างซ่อม)
        function setAssignedMode(state) {
            isAssignedMode = state;
        }

        $(document).ready(function() {
            // 1. เริ่มต้นใช้งาน Select2
            $('.select2').select2({
                theme: "bootstrap-5",
                width: '100%',
                placeholder: "กรุณาเลือกข้อมูล",
                allowClear: true
            });

            // =========================================================================
            //  ADMIN LOGIC: Shop & Branch Selection
            // =========================================================================
            const $shopSelect = $('#selected_shop_id');
            const $branchSelect = $('#selected_branch_id');

            // ถ้ามี Dropdown ร้านค้า (แสดงว่าเป็น Admin)
            if ($shopSelect.length > 0) {
                // เมื่อเปลี่ยนร้านค้า -> กรองสาขา
                $shopSelect.on('change', function() {
                    const selectedShopId = $(this).val();

                    // ล้างค่าสาขาเดิม
                    $branchSelect.empty().append('<option value="">-- เลือกสาขา --</option>');

                    if (selectedShopId && allBranches.length > 0) {
                        // กรองสาขาที่ shop_id ตรงกัน
                        const filteredBranches = allBranches.filter(b => b.shop_info_shop_id == selectedShopId);

                        filteredBranches.forEach(branch => {
                            const option = new Option(branch.branch_name, branch.branch_id);
                            $(option).attr('data-shop', branch.shop_info_shop_id);
                            $branchSelect.append(option);
                        });
                    }
                    // Trigger ให้ Select2 อัปเดตการแสดงผล
                    $branchSelect.trigger('change');
                });
            }

            // =========================================================================
            //  BRANCH CHANGE LOGIC (Load Customers & Employees)
            // =========================================================================
            // เมื่อเปลี่ยนสาขา (ทั้ง Admin เลือกเอง หรือ User โหลดมาตอนแรก)
            $branchSelect.on('change', function() {
                const branchId = $(this).val();

                // เคลียร์ข้อมูลเก่าใน Dropdown
                $('#customer_id').empty().append('<option value="">-- ค้นหาชื่อ หรือเบอร์โทรลูกค้า --</option>');
                $('#employee_id').empty().append('<option value="">-- ระบุพนักงาน --</option>');
                $('#assigned_employee_id').empty().append('<option value="">-- ระบุช่างซ่อม --</option>');

                // รีเซ็ต Info Box และ Serial
                $('#customer_info_box').html('<p class="text-muted mb-0 small text-center"><i class="fas fa-info-circle me-1"></i>กรุณาเลือกลูกค้าใหม่</p>');
                $('#serial_no').val('').trigger('input');

                if (branchId) {
                    // เรียก AJAX ไปดึงข้อมูลของสาขานั้นๆ
                    $.post('add_repair.php', {
                        action: 'get_branch_data',
                        branch_id: branchId
                    }, function(data) {
                        if (data.success) {
                            // 1. เติมข้อมูลลูกค้า
                            data.customers.forEach(c => {
                                const text = `${c.firstname_th} ${c.lastname_th} (${c.cs_phone_no})`;
                                $('#customer_id').append(new Option(text, c.cs_id));
                            });

                            // 2. เติมข้อมูลพนักงาน (ใส่ทั้ง 2 ช่อง)
                            data.employees.forEach(e => {
                                const text = `${e.firstname_th} ${e.lastname_th} (${e.emp_code})`;
                                $('#employee_id').append(new Option(text, e.emp_id));
                                $('#assigned_employee_id').append(new Option(text, e.emp_id));
                            });

                            // Refresh Select2
                            $('#customer_id, #employee_id, #assigned_employee_id').trigger('change');
                        }
                    }, 'json').fail(function() {
                        alert('ไม่สามารถโหลดข้อมูลสาขาได้ กรุณาลองใหม่อีกครั้ง');
                    });
                }
            });

            // =========================================================================
            //  CUSTOMER INFO DISPLAY
            // =========================================================================
            $('#customer_id').on('change', function() {
                // เนื่องจากเราใช้ Select2 ข้อมูล text จะอยู่ใน option ที่เลือก
                const text = $(this).find('option:selected').text();
                const val = $(this).val();

                if (val) {
                    // (Optional) ถ้าต้องการข้อมูลละเอียดอาจต้องยิง AJAX เพิ่ม 
                    // แต่เบื้องต้นแสดง text จาก Dropdown ก็เพียงพอสำหรับการยืนยัน
                    $('#customer_info_box').html(`
                        <div class="text-success text-center">
                            <i class="fas fa-check-circle fa-2x mb-2"></i><br>
                            <strong>เลือกลูกค้าแล้ว:</strong><br>${text}
                        </div>
                    `);
                } else {
                    $('#customer_info_box').html('<p class="text-muted mb-0 small text-center"><i class="fas fa-info-circle me-1"></i>รายละเอียดลูกค้าจะแสดงที่นี่เมื่อเลือก</p>');
                }
            });
        });

        // =============================================================================
        //  SERIAL NUMBER CHECK LOGIC (หัวใจสำคัญข้อ 2 และ 3)
        // =============================================================================
        const serialInput = document.getElementById('serial_no');
        const serialStatusDiv = document.getElementById('serial_status');
        const newDeviceSelectDiv = document.getElementById('new_device_select');
        const isNewDeviceInput = document.getElementById('is_new_device');
        const newProductSelect = document.getElementById('new_product_id');

        let typingTimer; // Timer identifier
        const doneTypingInterval = 500; // เวลาหน่วงหลังพิมพ์เสร็จ (ms)

        serialInput.addEventListener('input', function() {
            clearTimeout(typingTimer);
            const serial = this.value.trim();

            // รีเซ็ตหน้าจอ
            serialStatusDiv.style.display = 'none';
            serialStatusDiv.className = 'serial-check-status'; // ลบ class สีทั้งหมด
            newDeviceSelectDiv.style.display = 'none';
            isNewDeviceInput.value = '0';
            this.classList.remove('is-invalid');

            // ถ้าพิมพ์น้อยกว่า 3 ตัวยังไม่เช็ค
            if (serial.length < 3) return;

            // เริ่มนับเวลาหน่วง (Debounce) เพื่อไม่ให้ยิง AJAX ถี่เกินไป
            typingTimer = setTimeout(() => {
                checkSerial(serial);
            }, doneTypingInterval);
        });

        function checkSerial(serial) {
            const currentBranchId = getCurrentBranchId();

            if (!currentBranchId) {
                alert('กรุณาเลือกสาขาก่อนกรอก Serial Number');
                serialInput.value = '';
                return;
            }

            // แสดงสถานะกำลังตรวจสอบ...
            serialStatusDiv.style.display = 'block';
            serialStatusDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังตรวจสอบ...';

            fetch('add_repair.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=check_serial&serial_no=${encodeURIComponent(serial)}&branch_id=${currentBranchId}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (data.exists) {
                            // [กรณี 1] เจอ Serial ในระบบสาขานี้ -> ข้อ 2
                            serialStatusDiv.className = 'serial-check-status valid';
                            serialStatusDiv.innerHTML = `<i class="fas fa-check-circle me-1"></i> <strong>Serial นี้มีในระบบแล้ว</strong> (สินค้า: ${data.prod_name})`;

                            // ซ่อนช่องเลือกเครื่องใหม่
                            newDeviceSelectDiv.style.display = 'none';
                            isNewDeviceInput.value = '0';

                            // Clear invalid state
                            serialInput.classList.remove('is-invalid');
                        } else {
                            // [กรณี 2] ไม่เจอในระบบสาขานี้ -> ข้อ 3
                            serialStatusDiv.className = 'serial-check-status new';
                            serialStatusDiv.innerHTML = `<i class="fas fa-plus-circle me-1"></i> ไม่พบ Serial นี้ในสาขา (สินค้าใหม่) <strong>กรุณาเลือกรุ่นสินค้าด้านขวา</strong> เพื่อเพิ่มเข้าสู่ระบบ`;

                            // แสดงช่องเลือกเครื่องใหม่
                            newDeviceSelectDiv.style.display = 'block';
                            isNewDeviceInput.value = '1';

                            // Focus ไปที่ Dropdown เลือกสินค้า
                            $('#new_product_id').select2('open');
                        }
                    } else {
                        serialStatusDiv.className = 'serial-check-status error';
                        serialStatusDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> เกิดข้อผิดพลาดในการตรวจสอบ';
                    }
                })
                .catch(err => {
                    console.error(err);
                    serialStatusDiv.className = 'serial-check-status error';
                    serialStatusDiv.innerHTML = '<i class="fas fa-wifi"></i> การเชื่อมต่อขัดข้อง';
                });
        }

        // =============================================================================
        //  MODAL SEARCH LOGIC (Optional Features)
        // =============================================================================

        // --- Customer Search Modal ---
        const customerSearchModal = new bootstrap.Modal(document.getElementById('customerSearchModal'));

        // ปุ่มเปิด Modal (ถ้ามีปุ่ม Search ข้างๆ Dropdown)
        function openCustomerModal() {
            document.getElementById('modal_customer_search').value = '';
            document.getElementById('modal_customer_results').innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">พิมพ์คำค้นหาแล้วกดปุ่มค้นหา</td></tr>';
            customerSearchModal.show();
        }

        // กดปุ่มค้นหาใน Modal
        document.getElementById('modal_search_btn').addEventListener('click', function() {
            const query = document.getElementById('modal_customer_search').value.trim();
            if (query.length < 2) {
                alert('กรุณาพิมพ์อย่างน้อย 2 ตัวอักษร');
                return;
            }

            const branchId = getCurrentBranchId();
            if (!branchId) {
                alert('กรุณาเลือกสาขาก่อน');
                return;
            }

            const tbody = document.getElementById('modal_customer_results');
            tbody.innerHTML = '<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary"></div></td></tr>';

            fetch('add_repair.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=search_customer&query=${encodeURIComponent(query)}&branch_id=${branchId}`
                })
                .then(res => res.json())
                .then(data => {
                    tbody.innerHTML = '';
                    if (data.success && data.customers.length > 0) {
                        data.customers.forEach(c => {
                            tbody.innerHTML += `
                            <tr>
                                <td>${c.cs_id}</td>
                                <td>${c.firstname_th} ${c.lastname_th}</td>
                                <td>${c.cs_phone_no}</td>
                                <td>${c.cs_email || '-'}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" 
                                        onclick="selectCustomerFromModal(${c.cs_id})">เลือก</button>
                                </td>
                            </tr>
                        `;
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">ไม่พบข้อมูล</td></tr>';
                    }
                });
        });

        // ฟังก์ชันเลือกจาก Modal แล้วไปอัปเดต Select2
        window.selectCustomerFromModal = function(id) {
            // เช็คว่าใน Dropdown มี ID นี้ไหม
            if ($('#customer_id').find("option[value='" + id + "']").length) {
                $('#customer_id').val(id).trigger('change');
            } else {
                // ถ้าไม่มี (เช่น โหลดมาแค่ 10 คนแรก) อาจต้องเพิ่ม Option เข้าไปก่อน (Optional)
                alert('ข้อมูลลูกค้าอาจยังไม่ได้โหลดในหน้าหลัก กรุณาใช้ช่องค้นหาหลัก');
            }
            customerSearchModal.hide();
        }

        // --- Employee Search Modal ---
        const employeeSearchModal = new bootstrap.Modal(document.getElementById('employeeSearchModal'));

        function openEmployeeModal(isAssigned) {
            setAssignedMode(isAssigned);
            document.getElementById('modal_employee_search').value = '';
            document.getElementById('modal_employee_results').innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">พิมพ์คำค้นหาแล้วกดปุ่มค้นหา</td></tr>';
            employeeSearchModal.show();
        }

        document.getElementById('modal_employee_search_btn').addEventListener('click', function() {
            const query = document.getElementById('modal_employee_search').value.trim();
            if (query.length < 2) return;

            const branchId = getCurrentBranchId();
            const tbody = document.getElementById('modal_employee_results');
            tbody.innerHTML = '<tr><td colspan="4" class="text-center"><div class="spinner-border text-primary"></div></td></tr>';

            fetch('add_repair.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=search_employee&query=${encodeURIComponent(query)}&branch_id=${branchId}`
                })
                .then(res => res.json())
                .then(data => {
                    tbody.innerHTML = '';
                    if (data.success && data.employees.length > 0) {
                        data.employees.forEach(e => {
                            tbody.innerHTML += `
                            <tr>
                                <td>${e.emp_id}</td>
                                <td>${e.emp_code}</td>
                                <td>${e.firstname_th} ${e.lastname_th}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" 
                                        onclick="selectEmployeeFromModal(${e.emp_id})">เลือก</button>
                                </td>
                            </tr>
                        `;
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">ไม่พบข้อมูล</td></tr>';
                    }
                });
        });

        window.selectEmployeeFromModal = function(id) {
            const targetSelect = isAssignedMode ? $('#assigned_employee_id') : $('#employee_id');
            if (targetSelect.find("option[value='" + id + "']").length) {
                targetSelect.val(id).trigger('change');
            } else {
                alert('ข้อมูลพนักงานอาจยังไม่ได้โหลด');
            }
            employeeSearchModal.hide();
        }


        // =============================================================================
        //  FORM SUBMISSION VALIDATION
        // =============================================================================
        document.getElementById('repairForm').addEventListener('submit', function(e) {
            let isValid = true;
            let errorMsg = '';

            // 1. ตรวจสอบการเลือกสาขา (Admin)
            if ($('#selected_shop_id').length > 0 && !$('#selected_shop_id').val()) {
                isValid = false;
                errorMsg += '- กรุณาเลือกร้านค้า\n';
            }
            if ($('#selected_branch_id').length > 0 && !$('#selected_branch_id').val()) {
                isValid = false;
                errorMsg += '- กรุณาเลือกสาขา\n';
            }

            // 2. ตรวจสอบข้อมูลหลัก
            if (!$('#customer_id').val()) {
                isValid = false;
                errorMsg += '- กรุณาเลือกลูกค้า\n';
            }
            if (!$('#employee_id').val()) {
                isValid = false;
                errorMsg += '- กรุณาเลือกพนักงานรับเรื่อง\n';
            }
            if (!$('#assigned_employee_id').val()) {
                isValid = false;
                errorMsg += '- กรุณาเลือกช่างซ่อม\n';
            }
            if (!$('#serial_no').val().trim()) {
                isValid = false;
                errorMsg += '- กรุณากรอก Serial Number\n';
                $('#serial_no').addClass('is-invalid');
            }

            // 3. ตรวจสอบสินค้าใหม่ (ถ้าเป็นสินค้าใหม่ ต้องเลือกรุ่น)
            if ($('#is_new_device').val() === '1' && !$('#new_product_id').val()) {
                isValid = false;
                errorMsg += '- Serial นี้เป็นเครื่องใหม่ กรุณาเลือกรุ่นสินค้าด้วย\n';
                // Highlight Select2 container
                $('#new_product_id').next('.select2-container').find('.select2-selection').css('border-color', '#dc3545');
            } else {
                $('#new_product_id').next('.select2-container').find('.select2-selection').css('border-color', '#dee2e6');
            }

            // 4. ตรวจสอบอาการเสีย
            if (document.querySelectorAll('.symptom-checkbox:checked').length === 0) {
                isValid = false;
                errorMsg += '- กรุณาเลือกอาการเสียอย่างน้อย 1 ข้อ\n';
                document.querySelector('.symptom-grid').style.borderColor = '#dc3545';
            } else {
                document.querySelector('.symptom-grid').style.borderColor = '#dee2e6';
            }

            // 5. ตรวจสอบราคา
            const cost = parseFloat($('#estimated_cost').val());
            if (isNaN(cost) || cost < 0) {
                isValid = false;
                errorMsg += '- ราคาประเมินไม่ถูกต้อง\n';
            }

            if (!isValid) {
                e.preventDefault();
                alert('กรุณาตรวจสอบข้อมูล:\n' + errorMsg);
            } else {
                // Confirm Submit
                if (!confirm('ยืนยันการเปิดใบรับซ่อม?')) {
                    e.preventDefault();
                } else {
                    // Loading State
                    const btn = document.getElementById('submitBtn');
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> กำลังบันทึก...';
                }
            }
        });
    </script>
</body>

</html>