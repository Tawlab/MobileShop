<?php
session_start();

// 1. เรียกใช้ Config และ Autoload
require '../config/config.php';
require '../vendor/autoload.php'; 

// [Manual Require] เรียกไฟล์ PHPMailer โดยตรงเพื่อป้องกัน Error Class not found
require '../vendor/phpmailer/phpmailer/src/Exception.php';
require '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require '../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

checkPageAccess($conn, 'add_repair');

$current_branch_id = $_SESSION['branch_id'];
$current_shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// ตรวจสอบ Admin
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

// ==========================================================================================
// [POST] บันทึกข้อมูล
// ==========================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่าจากฟอร์ม
    $customer_id = $_POST['customer_id']; 
    $employee_id = $_POST['employee_id']; 
    $serial_no = trim($_POST['serial_no']);
    $estimated_cost = (float)$_POST['estimated_cost'];
    $device_description = trim($_POST['device_description']);
    $accessories_list = trim($_POST['accessories_list']);
    $repair_desc = trim($_POST['repair_desc']);
    $symptoms = isset($_POST['symptoms']) ? $_POST['symptoms'] : [];
    $new_product_id = isset($_POST['new_product_id']) ? $_POST['new_product_id'] : '';

    // กำหนดสาขาปลายทาง
    if ($is_admin) {
        $target_shop_id = $_POST['selected_shop_id'];
        $target_branch_id = $_POST['selected_branch_id'];
    } else {
        $target_shop_id = $current_shop_id;
        $target_branch_id = $current_branch_id;
    }

    // 1. Validation พื้นฐาน
    if (empty($customer_id) || empty($serial_no) || empty($symptoms)) {
        $_SESSION['error'] = "กรุณากรอกข้อมูลให้ครบถ้วน (ลูกค้า, Serial Number, อาการเสีย)";
        header("Location: add_repair.php");
        exit;
    }

    // 2. [NEW LOGIC] ตรวจสอบ Serial Number ในสต็อกก่อน
    // เพื่อดูว่าเป็นเครื่องเก่าหรือเครื่องใหม่
    $existing_stock_data = null;
    $check_stock = $conn->prepare("SELECT ps.stock_id, ps.products_prod_id, p.prod_name 
                                   FROM prod_stocks ps
                                   LEFT JOIN products p ON ps.products_prod_id = p.prod_id
                                   WHERE ps.serial_no = ? AND ps.branches_branch_id = ?");
    $check_stock->bind_param("si", $serial_no, $target_branch_id);
    $check_stock->execute();
    $res_stock = $check_stock->get_result();

    if ($res_stock->num_rows > 0) {
        $existing_stock_data = $res_stock->fetch_assoc();
    }

    // 3. Validation เพิ่มเติม: ถ้าไม่เจอในสต็อก (เครื่องใหม่) ต้องเลือกสินค้า
    if (!$existing_stock_data && empty($new_product_id)) {
        $_SESSION['error'] = "⚠️ ไม่พบ Serial Number '$serial_no' ในระบบ (เป็นเครื่องใหม่) <br>กรุณาเลือก 'รุ่นสินค้า' ในกล่องค้นหาด้วยครับ";
        header("Location: add_repair.php");
        exit;
    }

    $conn->begin_transaction();
    try {
        // --- A. เตรียม ID ต่างๆ ---
        $res_id = $conn->query("SELECT MAX(repair_id) as max_id FROM repairs");
        $row_id = $res_id->fetch_assoc();
        $new_repair_id = ($row_id['max_id']) ? $row_id['max_id'] + 1 : 1;

        $stock_id = 0;
        $device_name_for_mail = "ไม่ระบุรุ่น";
        $stock_msg_log = ""; // ข้อความสำหรับแจ้งเตือนตอนจบ

        // --- B. จัดการสต็อก (แยกเคส เจอ/ไม่เจอ) ---
        if ($existing_stock_data) {
            // >>> กรณีเครื่องเดิม (Found Existing) <<<
            $stock_id = $existing_stock_data['stock_id'];
            $device_name_for_mail = $existing_stock_data['prod_name'];
            $stock_msg_log = " (พบสินค้าเดิมในสต็อก: $device_name_for_mail)";

            // อัปเดตสถานะเป็น Repair
            $conn->query("UPDATE prod_stocks SET stock_status = 'Repair' WHERE stock_id = $stock_id");

            // คำนวณ movement_id
            $res_mov = $conn->query("SELECT MAX(movement_id) as max_id FROM stock_movements");
            $new_movement_id = ($res_mov->fetch_assoc()['max_id']) ? $res_mov->fetch_assoc()['max_id'] + 1 : 1;

            // บันทึก Movement
            $conn->query("INSERT INTO stock_movements (movement_id, movement_type, ref_table, ref_id, prod_stocks_stock_id, create_at) 
                          VALUES ($new_movement_id, 'TRANSFER', 'repairs', $new_repair_id, $stock_id, NOW())");

        } else {
            // >>> กรณีเครื่องใหม่ (New Device) <<<
            $prod_id_for_stock = $new_product_id;
            
            // หาชื่อสินค้า
            $res_p_nm = $conn->query("SELECT prod_name FROM products WHERE prod_id = '$prod_id_for_stock'");
            if($r_nm = $res_p_nm->fetch_assoc()) $device_name_for_mail = $r_nm['prod_name'];
            $stock_msg_log = " (ลงทะเบียนเครื่องใหม่: $device_name_for_mail)";

            // สร้าง Stock ID ใหม่
            $res_stk_id = $conn->query("SELECT MAX(stock_id) as max_id FROM prod_stocks");
            $new_stock_id = $res_stk_id->fetch_assoc()['max_id'] + 1;

            // Insert prod_stocks
            $ins_stock = $conn->prepare("INSERT INTO prod_stocks (stock_id, serial_no, stock_status, products_prod_id, branches_branch_id, create_at, update_at) VALUES (?, ?, 'Repair', ?, ?, NOW(), NOW())");
            $ins_stock->bind_param("isii", $new_stock_id, $serial_no, $prod_id_for_stock, $target_branch_id);
            $ins_stock->execute();
            $stock_id = $new_stock_id;

            // คำนวณ movement_id
            $res_mov = $conn->query("SELECT MAX(movement_id) as max_id FROM stock_movements");
            $new_movement_id = ($res_mov->fetch_assoc()['max_id']) ? $res_mov->fetch_assoc()['max_id'] + 1 : 1;

            // Insert stock_movements
            $conn->query("INSERT INTO stock_movements (movement_id, movement_type, ref_table, ref_id, prod_stocks_stock_id, create_at) 
                          VALUES ($new_movement_id, 'IN', 'repairs_new', $new_repair_id, $stock_id, NOW())");
        }

        // --- C. บันทึก Repairs ---
        $sql_repair = "INSERT INTO repairs (
            repair_id, repair_status, estimated_cost, device_description, accessories_list,
            create_at, update_at, 
            customers_cs_id, prod_stocks_stock_id, employees_emp_id, branches_branch_id
        ) VALUES (?, 'Pending', ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?)";
        
        $stmt_rep = $conn->prepare($sql_repair);
        $stmt_rep->bind_param("idssiiii", $new_repair_id, $estimated_cost, $device_description, $accessories_list, $customer_id, $stock_id, $employee_id, $target_branch_id);
        $stmt_rep->execute();

        // --- D. บันทึก Symptoms ---
        $stmt_symp = $conn->prepare("INSERT INTO repair_symptoms (repairs_repair_id, symptoms_symptom_id) VALUES (?, ?)");
        $symptoms_text_arr = [];
        foreach ($symptoms as $symp_id) {
            $stmt_symp->bind_param("ii", $new_repair_id, $symp_id);
            $stmt_symp->execute();
            
            $res_nm = $conn->query("SELECT symptom_name FROM symptoms WHERE symptom_id = $symp_id");
            if($r = $res_nm->fetch_assoc()) $symptoms_text_arr[] = $r['symptom_name'];
        }

        $conn->commit();

        // --- E. ส่ง Email (ใช้ App Password จาก DB) ---
        try {
            // ดึงอีเมลลูกค้า
            $res_cust = $conn->query("SELECT firstname_th, lastname_th, cs_email FROM customers WHERE cs_id = $customer_id");
            $cust_data = $res_cust->fetch_assoc();
            
            // ดึงข้อมูลร้าน (และ App Password)
            $res_shop = $conn->query("SELECT shop_name, shop_email, shop_app_password FROM shop_info WHERE shop_id = '$target_shop_id' LIMIT 1");
            $shop_data = $res_shop->fetch_assoc();

            if ($shop_data && !empty($shop_data['shop_email']) && !empty($cust_data['cs_email'])) {
                // จัดเตรียมข้อความ
                $symptoms_txt_mail = implode(", ", $symptoms_text_arr);
                if (!empty($repair_desc)) $symptoms_txt_mail .= " (เพิ่มเติม: $repair_desc)";
                
                // ใช้ Password จาก DB
                $app_password_db = $shop_data['shop_app_password']; 

                if (!empty($app_password_db)) {
                    @sendJobOrderEmail(
                        $cust_data['cs_email'], 
                        $cust_data['firstname_th'] . " " . $cust_data['lastname_th'], 
                        $new_repair_id, 
                        $device_name_for_mail, 
                        $serial_no, 
                        $symptoms_txt_mail, 
                        $shop_data['shop_name'], 
                        $shop_data['shop_email'], 
                        $app_password_db // <-- ส่งตัวแปรนี้
                    );
                }
            }
        } catch (Exception $mail_e) {
            // ไม่ Throw error ใส่ User แต่เงียบไว้ (Log ลงไฟล์ได้ถ้าต้องการ)
        }

        $_SESSION['success'] = "✅ รับเครื่องซ่อมสำเร็จ: Job ID #$new_repair_id <br><small class='text-muted'>$stock_msg_log</small>";
        header("Location: repair_list.php");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        header("Location: add_repair.php");
        exit;
    }
}

// ==========================================================================================
// ฟังก์ชันส่งอีเมล
// ==========================================================================================
function sendJobOrderEmail($to_email, $customer_name, $repair_id, $device_name, $serial_no, $symptoms_txt, $shop_name, $sender_email, $sender_password)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $sender_email;
        $mail->Password   = $sender_password; // ใช้รหัสที่รับเข้ามาจาก DB
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
?>