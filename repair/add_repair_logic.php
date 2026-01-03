<?php
session_start();

// 1. เรียกใช้ Config และ Autoload
require '../config/config.php';
require '../vendor/autoload.php';
checkPageAccess($conn, 'add_repair');

// รับค่า Branch ID และ Shop ID ปัจจุบันของผู้ใช้จาก Session
$current_branch_id = $_SESSION['branch_id'];
$current_shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

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

// ถ้าเป็น Admin ให้ดึงรายชื่อร้านค้าและสาขาทั้งหมดมาเตรียมไว้สำหรับ Dropdown
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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// -----------------------------------------------------------------------------
//  SHARED FUNCTIONS (Manual Auto-Increment Logic)
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

// [ฟังก์ชันเพิ่มตามโจทย์] บวก bill_id ทีละ 1
function getNextBillId($conn)
{
    $sql = "SELECT IFNULL(MAX(bill_id), 0) + 1 as next_id FROM bill_headers";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result)['next_id'];
}

function getStockIdBySerial($conn, $serial)
{
    $sql = "SELECT stock_id, stock_status FROM prod_stocks WHERE serial_no = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $serial);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// --- ฟังก์ชันส่งอีเมล ---
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
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// -----------------------------------------------------------------------------
// GET DATA (For View)
// -----------------------------------------------------------------------------

$symptoms_result = mysqli_query($conn, "SELECT symptom_id, symptom_name FROM symptoms ORDER BY symptom_name");

$products_result = mysqli_query($conn, "SELECT p.prod_id, p.prod_name, p.model_name, pb.brand_name_th 
                                        FROM products p 
                                        LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id 
                                        WHERE p.prod_types_type_id NOT IN (3, 4) AND p.shop_info_shop_id = '$current_shop_id'
                                        ORDER BY p.prod_name");
mysqli_data_seek($products_result, 0);

// -----------------------------------------------------------------------------
//  AJAX HANDLER
// -----------------------------------------------------------------------------
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false];
    switch ($_POST['action']) {
        case 'search_customer':
            $search = mysqli_real_escape_string($conn, $_POST['query']);
            $target_shop_id = isset($_POST['shop_id']) && !empty($_POST['shop_id']) ? (int)$_POST['shop_id'] : $current_shop_id;
            
            $sql = "SELECT cs_id, firstname_th, lastname_th, cs_phone_no, cs_email 
                    FROM customers 
                    WHERE (cs_phone_no LIKE '%$search%' OR firstname_th LIKE '%$search%' OR lastname_th LIKE '%$search%') 
                    AND shop_info_shop_id = '$target_shop_id' 
                    LIMIT 10";
            $result = mysqli_query($conn, $sql);
            $customers = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $customers[] = $row;
            }
            $response = ['success' => true, 'customers' => $customers];
            break;
        case 'search_employee':
            $search = mysqli_real_escape_string($conn, $_POST['query']);
            $target_shop_id = isset($_POST['shop_id']) && !empty($_POST['shop_id']) ? (int)$_POST['shop_id'] : $current_shop_id;

            $sql = "SELECT e.emp_id, e.firstname_th, e.lastname_th, e.emp_code 
                    FROM employees e
                    LEFT JOIN branches b ON e.branches_branch_id = b.branch_id
                    WHERE e.emp_status = 'Active' 
                    AND b.shop_info_shop_id = '$target_shop_id'
                    AND (e.firstname_th LIKE '%$search%' OR e.lastname_th LIKE '%$search%' OR e.emp_code LIKE '%$search%') 
                    LIMIT 10";
            $result = mysqli_query($conn, $sql);
            $employees = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $employees[] = $row;
            }
            $response = ['success' => true, 'employees' => $employees];
            break;
        case 'check_serial':
            $serial = mysqli_real_escape_string($conn, $_POST['serial_no']);
            $stock_info = getStockIdBySerial($conn, $serial);
            if ($stock_info) {
                $response['exists'] = true;
                $response['stock_id'] = $stock_info['stock_id'];
                $response['status'] = $stock_info['stock_status'];
            } else {
                $response['exists'] = false;
            }
            $response['success'] = true;
            break;
    }
    echo json_encode($response);
    exit;
}

// -----------------------------------------------------------------------------
// บันทึก (SAVE)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ตรวจสอบสิทธิ์และกำหนด Shop/Branch ปลายทาง
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
        // User: ใช้ค่าจาก Session เท่านั้น
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

    // Validation
    if ($customer_id <= 0 || $employee_id <= 0 || $assigned_employee_id <= 0 || empty($serial_no) || empty($symptom_ids)) {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลสำคัญให้ครบถ้วน';
        header('Location: add_repair.php');
        exit;
    }

    mysqli_autocommit($conn, false);

    try {
        // [1] จัดการ Stock (Prod_Stocks)
        $stock_info = getStockIdBySerial($conn, $serial_no);
        $stock_id = 0;
        $device_name_for_mail = "";

        if ($stock_info) {
            $stock_id = $stock_info['stock_id'];
            $conn->query("UPDATE prod_stocks SET stock_status = 'Repair' WHERE stock_id = $stock_id");
            $res_prod = $conn->query("SELECT p.prod_name FROM prod_stocks s JOIN products p ON s.products_prod_id = p.prod_id WHERE s.stock_id = $stock_id");
            $device_name_for_mail = $res_prod->fetch_assoc()['prod_name'];
        } else {
            if ($is_new_device != 1 || $new_product_id <= 0) throw new Exception('กรุณาเลือกรุ่นสินค้าสำหรับเครื่องใหม่');
            
            // บวก stock_id ทีละ 1
            $stock_id = getNextStockId($conn);
            
            $stmt = $conn->prepare("INSERT INTO prod_stocks (stock_id, serial_no, price, stock_status, create_at, update_at, products_prod_id, branches_branch_id) VALUES (?, ?, 0.00, 'Repair', NOW(), NOW(), ?, ?)");
            $stmt->bind_param("isii", $stock_id, $serial_no, $new_product_id, $target_branch_id);
            $stmt->execute();
            $stmt->close();
            $res_prod = $conn->query("SELECT prod_name FROM products WHERE prod_id = $new_product_id");
            $device_name_for_mail = $res_prod->fetch_assoc()['prod_name'];
        }

        // [2] สร้างบิลซ่อม (Bill Headers)
        $bill_date = date('Y-m-d H:i:s');
        $bill_status = 'Pending';
        $bill_type = 'Repair';
        
        // บวก bill_id ทีละ 1 (Manual Increment)
        $bill_id = getNextBillId($conn);

        $sql_bill = "INSERT INTO bill_headers (
            bill_id, bill_date, receipt_date, payment_method, bill_status, vat, comment, discount, 
            customers_cs_id, bill_type, branches_branch_id, employees_emp_id, create_at, update_at
        ) VALUES (
            ?, ?, ?, 'Cash', ?, 7.00, 'เปิดบิลซ่อม (Job Order)', 0.00,
            ?, ?, ?, ?, NOW(), NOW()
        )";

        $stmt_bill = $conn->prepare($sql_bill);
        $stmt_bill->bind_param("isssisii", $bill_id, $bill_date, $bill_date, $bill_status, $customer_id, $bill_type, $target_branch_id, $employee_id);

        if (!$stmt_bill->execute()) throw new Exception('สร้างบิลซ่อมไม่สำเร็จ: ' . $stmt_bill->error);
        $stmt_bill->close();

        // [3] Job Order (Repairs)
        // บวก repair_id ทีละ 1
        $repair_id = getNextRepairId($conn);
        
        $sql_repair = "INSERT INTO repairs (
            repair_id, customers_cs_id, employees_emp_id, assigned_employee_id, 
            prod_stocks_stock_id, repair_desc, device_description, estimated_cost, 
            accessories_list, repair_status, bill_headers_bill_id, branches_branch_id, create_at, update_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'รับเครื่อง', ?, ?, NOW(), NOW())";

        $stmt_repair = $conn->prepare($sql_repair);
        $stmt_repair->bind_param("iiiiissdsii", $repair_id, $customer_id, $employee_id, $assigned_employee_id, $stock_id, $repair_desc, $device_description, $estimated_cost, $accessories_list, $bill_id, $target_branch_id);

        if (!$stmt_repair->execute()) throw new Exception('บันทึกงานซ่อมไม่สำเร็จ: ' . $stmt_repair->error);
        $stmt_repair->close();

        // [4] Stock Movement 
        // บวก movement_id ทีละ 1
        $movement_id = getNextMovementId($conn);
        
        $sql_move = "INSERT INTO stock_movements (movement_id, movement_type, ref_table, ref_id, create_at, prod_stocks_stock_id) VALUES (?, 'IN', 'repairs', ?, NOW(), ?)";
        $stmt_move = $conn->prepare($sql_move);
        $stmt_move->bind_param("iii", $movement_id, $repair_id, $stock_id);
        $stmt_move->execute();
        $stmt_move->close();

        $conn->query("DELETE FROM repair_symptoms WHERE repairs_repair_id = $repair_id");
        // [5] Symptoms
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
            $res_sym = $conn->query("SELECT symptom_name FROM symptoms WHERE symptom_id = " . (int)$sid);
            if ($row_sym = $res_sym->fetch_assoc()) {
                $symptoms_text_arr[] = $row_sym['symptom_name'];
            }
        }
        if (!empty($values)) {
            $sql_symptoms .= implode(', ', $values);
            $stmt_sym = $conn->prepare($sql_symptoms);
            $stmt_sym->bind_param($types, ...$params);
            $stmt_sym->execute();
            $stmt_sym->close();
        }
        
        // [6] Log Status
        $conn->query("INSERT INTO repair_status_log (repairs_repair_id, new_status, update_by_employee_id) VALUES ($repair_id, 'รับเครื่อง', $employee_id)");
        
        mysqli_commit($conn);

        // [7] Email Sending
        try {
            // ดึงข้อมูลลูกค้า
            $res_cust = $conn->query("SELECT firstname_th, lastname_th, cs_email FROM customers WHERE cs_id = $customer_id");
            $cust_data = $res_cust->fetch_assoc();
            $cust_email = $cust_data['cs_email'];
            $cust_name = $cust_data['firstname_th'] . " " . $cust_data['lastname_th'];

            // ดึงข้อมูลร้านค้าปลายทาง ($target_shop_id)
            $res_shop = $conn->query("SELECT shop_name, shop_email, shop_app_password FROM shop_info WHERE shop_id = '$target_shop_id' LIMIT 1");
            $shop_data = $res_shop->fetch_assoc();
            
            if ($shop_data && !empty($cust_email)) {
                $symptoms_txt_mail = implode(", ", $symptoms_text_arr);
                if (!empty($repair_desc)) $symptoms_txt_mail .= " (เพิ่มเติม: $repair_desc)";

                @sendJobOrderEmail($cust_email, $cust_name, $repair_id, $device_name_for_mail, $serial_no, $symptoms_txt_mail, $shop_data['shop_name'], $shop_data['shop_email'], $shop_data['shop_app_password']);
            }
        } catch (Exception $mail_err) {
            // Ignore email error
        }

        $_SESSION['success'] = "✅ รับเครื่องซ่อมสำเร็จ: Job #$repair_id";
        header("Location: view_repair.php?id=$repair_id");
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = '❌ เกิดข้อผิดพลาด: ' . $e->getMessage();
        header('Location: add_repair.php');
        exit;
    }
}
?>