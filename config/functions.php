<?php
// เรียกใช้ Namespace ของ PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// =============================================================================
// ฟังก์ชันตรวจสอบสิทธิ์ (Check Permission) - Logic ใหม่ (Dept > Role)
// =============================================================================
function hasPermission($conn, $user_id, $permission_name)
{
    // 1. ถ้าไม่มี User ID ส่งมา (ยังไม่ล็อกอิน)
    if (empty($user_id)) return false;

    // 2. ดึงข้อมูล Dept ID และ Role ID ของ User
    $sql_user = "SELECT e.departments_dept_id, ur.roles_role_id 
                 FROM employees e 
                 JOIN user_roles ur ON e.users_user_id = ur.users_user_id 
                 WHERE e.users_user_id = ?";
    $stmt = $conn->prepare($sql_user);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res_user = $stmt->get_result();
    
    // ถ้าไม่พบข้อมูลพนักงาน
    if ($res_user->num_rows == 0) { 
        $stmt->close(); 
        return false; 
    }
    
    $user_data = $res_user->fetch_assoc();
    $dept_id = $user_data['departments_dept_id'];
    $role_id = $user_data['roles_role_id'];
    $stmt->close();

    // 3. ตรวจสอบว่า "แผนก" นี้มีการกำหนดสิทธิ์ไว้หรือไม่?
    // (เช็คว่ามี record ใน dept_permissions ของแผนกนี้บ้างไหม)
    $sql_check_dept = "SELECT 1 FROM dept_permissions WHERE departments_dept_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql_check_dept);
    $stmt->bind_param("i", $dept_id);
    $stmt->execute();
    $dept_has_rules = ($stmt->get_result()->num_rows > 0);
    $stmt->close();

    // 4. เริ่มตรวจสอบสิทธิ์ตามลำดับความสำคัญ
    if ($dept_has_rules) {
        // [กรณี A] แผนกมีการกำหนดสิทธิ์ไว้ -> "บังคับใช้สิทธิ์ของแผนกเท่านั้น"
        // เช็คว่า User มีสิทธิ์ $permission_name หรือไม่ จากตาราง dept_permissions
        $sql = "SELECT 1
                FROM dept_permissions dp
                JOIN permissions p ON dp.permissions_permission_id = p.permission_id
                WHERE dp.departments_dept_id = ? 
                AND (p.permission_name = ? OR p.permission_name = 'all_access')
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $dept_id, $permission_name);

    } else {
        // [กรณี B] แผนก "ไม่มี" การกำหนดสิทธิ์ -> "ให้ไปใช้สิทธิ์ตาม Role (ตำแหน่ง)"
        // เช็คว่า User มีสิทธิ์ $permission_name หรือไม่ จากตาราง role_permissions
        $sql = "SELECT 1
                FROM role_permissions rp
                JOIN permissions p ON rp.permissions_permission_id = p.permission_id
                WHERE rp.roles_role_id = ? 
                AND (p.permission_name = ? OR p.permission_name = 'all_access')
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $role_id, $permission_name);
    }

    // 5. ประมวลผลลัพธ์
    $stmt->execute();
    $result = $stmt->get_result();
    $has_perm = ($result->num_rows > 0);
    $stmt->close();

    return $has_perm;
}

// =============================================================================
// ฟังก์ชันตรวจสอบสิทธิ์เข้าใช้งานหน้าเว็บ (Page Access Guard)
// =============================================================================
function checkPageAccess($conn, $permission_name)
{
    // เช็คว่าล็อกอินหรือยัง
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../global/login.php');
        exit;
    }

    // เช็คสิทธิ์ด้วยฟังก์ชัน hasPermission ที่อัปเดต Logic แล้ว
    if (!hasPermission($conn, $_SESSION['user_id'], $permission_name)) {
        // แจ้งเตือนและเด้งกลับ (History Back) หรือไปหน้า Access Denied
        echo "<script>
            alert('คุณไม่มีสิทธิ์เข้าถึงหน้านี้ ($permission_name)');
            window.location.href = '../access_denied.php'; 
        </script>";
        exit;
    }
}

// =============================================================================
// ฟังก์ชันส่งใบเสร็จรับเงินทางอีเมล (สำหรับขายสินค้าทั่วไป) - คงเดิม
// =============================================================================
function sendReceiptEmail($conn, $bill_id)
{
    $sql = "SELECT bh.*, c.firstname_th, c.lastname_th, c.cs_email, 
                   s.shop_name, s.shop_email, s.shop_app_password 
            FROM bill_headers bh
            JOIN customers c ON bh.customers_cs_id = c.cs_id
            JOIN shop_info s ON 1=1 
            WHERE bh.bill_id = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();

    // ถ้าไม่พบข้อมูล หรือลูกค้าไม่มีอีเมล หรือร้านไม่มีการตั้งค่าอีเมล -> จบการทำงาน 
    if (!$bill || empty($bill['cs_email']) || empty($bill['shop_email']) || empty($bill['shop_app_password'])) {
        return false;
    }

    $sql_items = "SELECT bd.price, bd.amount, p.prod_name, p.model_name 
                  FROM bill_details bd 
                  JOIN prod_stocks ps ON bd.prod_stocks_stock_id = ps.stock_id 
                  JOIN products p ON ps.products_prod_id = p.prod_id 
                  WHERE bd.bill_headers_bill_id = ?";
    $stmt2 = $conn->prepare($sql_items);
    $stmt2->bind_param("i", $bill_id);
    $stmt2->execute();
    $items_res = $stmt2->get_result();

    // สำหรับตารางรายการสินค้า
    $customer_name = $bill['firstname_th'] . " " . $bill['lastname_th'];
    $total = 0;
    $rows_html = "";

    while ($row = $items_res->fetch_assoc()) {
        $sum = $row['price'] * $row['amount'];
        $total += $sum;
        $rows_html .= "
            <tr>
                <td style='padding:8px; border-bottom:1px solid #eee;'>{$row['prod_name']} {$row['model_name']}</td>
                <td style='padding:8px; border-bottom:1px solid #eee; text-align:center;'>{$row['amount']}</td>
                <td style='padding:8px; border-bottom:1px solid #eee; text-align:right;'>" . number_format($row['price'], 2) . "</td>
            </tr>";
    }

    // คำนวณยอดสุทธิ (Grand Total)
    $vat_amount = $total * ($bill['vat'] / 100);
    $grand_total = $total + $vat_amount - $bill['discount'];

    // สร้าง Body HTML (ตัดทอนเพื่อความกระชับ - ใช้ Logic เดียวกับฟังก์ชันข้างล่าง)
    // ... (คงเดิมตามที่คุณมี) ...
    
    // หมายเหตุ: เพื่อไม่ให้โค้ดยาวเกินไป ผมขออนุญาตใช้ Template เดียวกับด้านล่างในการ implement จริง
    // แต่ในโค้ดนี้ผมจะคง Logic เดิมของคุณไว้ตามที่ขอ แล้วไปเพิ่มฟังก์ชันใหม่ด้านล่างครับ
    
    // (ขออนุญาต Copy HTML Template จากโค้ดเดิมของคุณมาใส่ตรงนี้เพื่อให้สมบูรณ์)
    $bodyContent = "
    <html>
    <body style='font-family: Sarabun, Arial, sans-serif; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px;'>
            <div style='text-align: center; border-bottom: 2px solid #198754; padding-bottom: 15px;'>
                <h2 style='color: #198754; margin: 0;'>{$bill['shop_name']}</h2>
                <p style='margin: 5px 0 0; color: #666;'>ใบเสร็จรับเงิน / Electronic Receipt</p>
            </div>
            <div style='padding: 20px 0;'>
                <p>เรียนคุณ <strong>$customer_name</strong>,</p>
                <p>ขอบคุณที่ใช้บริการ ทางร้านขอส่งรายละเอียดใบเสร็จรับเงินดังนี้:</p>
                <div style='background: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>
                    <strong>เลขที่:</strong> INV-" . str_pad($bill_id, 6, '0', STR_PAD_LEFT) . "<br>
                    <strong>วันที่:</strong> " . date('d/m/Y H:i') . "<br>
                    <strong>ชำระโดย:</strong> {$bill['payment_method']}
                </div>
                <table width='100%' cellspacing='0' style='font-size: 14px;'>
                    <tr style='background:#198754; color:white;'>
                        <th style='padding:8px; text-align:left;'>รายการ (Item)</th>
                        <th style='padding:8px; text-align:center;'>จำนวน</th>
                        <th style='padding:8px; text-align:right;'>ราคา</th>
                    </tr>
                    $rows_html
                    <tr>
                        <td colspan='2' style='padding:10px; text-align:right; border-top:2px solid #ddd;'><strong>รวมเป็นเงิน:</strong></td>
                        <td style='padding:10px; text-align:right; border-top:2px solid #ddd;'>" . number_format($total, 2) . "</td>
                    </tr>
                    <tr>
                        <td colspan='2' style='padding:5px 10px; text-align:right;'>VAT ({$bill['vat']}%):</td>
                        <td style='padding:5px 10px; text-align:right;'>" . number_format($vat_amount, 2) . "</td>
                    </tr>
                    " . ($bill['discount'] > 0 ? "
                    <tr>
                        <td colspan='2' style='padding:5px 10px; text-align:right; color:red;'>ส่วนลด:</td>
                        <td style='padding:5px 10px; text-align:right; color:red;'>-" . number_format($bill['discount'], 2) . "</td>
                    </tr>" : "") . "
                    <tr style='font-size: 1.2em;'>
                        <td colspan='2' style='padding:10px; text-align:right; color:#198754;'><strong>ยอดสุทธิ (Grand Total):</strong></td>
                        <td style='padding:10px; text-align:right; color:#198754;'><strong>" . number_format($grand_total, 2) . " ฿</strong></td>
                    </tr>
                </table>
            </div>
            <div style='text-align: center; margin-top: 30px; border-top: 1px dashed #ccc; padding-top: 15px; font-size: 12px; color: #777;'>
                <p>ขอบคุณที่ไว้วางใจ <strong>{$bill['shop_name']}</strong></p>
                (อีเมลฉบับนี้ส่งจากระบบอัตโนมัติ กรุณาอย่าตอบกลับ)
            </div>
        </div>
    </body>
    </html>";

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $bill['shop_email'];
        $mail->Password   = $bill['shop_app_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($bill['shop_email'], $bill['shop_name']);
        $mail->addAddress($bill['cs_email'], $customer_name);
        $mail->isHTML(true);
        $mail->Subject = "ใบเสร็จรับเงิน / Receipt INV-" . str_pad($bill_id, 6, '0', STR_PAD_LEFT);
        $mail->Body    = $bodyContent;
        $mail->AltBody = "ขอบคุณที่ใช้บริการ ยอดชำระ " . number_format($grand_total, 2) . " บาท";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// =============================================================================
// [NEW] ฟังก์ชันส่งใบเสร็จรับเงินสำหรับ **งานซ่อม** (Repair Receipt)
// =============================================================================
function sendRepairReceiptEmail($conn, $bill_id)
{
    // 1. ดึงข้อมูลบิล + ลูกค้า + ข้อมูลร้าน + **ข้อมูลงานซ่อม (repairs)**
    // แก้ไข: ใช้ r.* เพื่อดึงทุกคอลัมน์จากตาราง repairs (ป้องกัน Error ชื่อคอลัมน์ไม่ตรง)
    $sql = "SELECT bh.*, c.firstname_th, c.lastname_th, c.cs_email, 
                   s.shop_name, s.shop_email, s.shop_app_password,
                   r.* FROM bill_headers bh
            JOIN customers c ON bh.customers_cs_id = c.cs_id
            JOIN shop_info s ON 1=1 
            LEFT JOIN repairs r ON r.bill_headers_bill_id = bh.bill_id
            WHERE bh.bill_id = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();

    if (!$bill || empty($bill['cs_email']) || empty($bill['shop_email']) || empty($bill['shop_app_password'])) {
        return false; 
    }

    // 2. ดึงรายการค่าใช้จ่าย (คงเดิม)
    $sql_items = "SELECT bd.price, bd.amount, bd.prod_stocks_stock_id, 
                         p.prod_name, p.model_name
                  FROM bill_details bd 
                  LEFT JOIN prod_stocks ps ON bd.prod_stocks_stock_id = ps.stock_id 
                  LEFT JOIN products p ON ps.products_prod_id = p.prod_id 
                  WHERE bd.bill_headers_bill_id = ?";
    
    $stmt2 = $conn->prepare($sql_items);
    $stmt2->bind_param("i", $bill_id);
    $stmt2->execute();
    $items_res = $stmt2->get_result();

    $customer_name = $bill['firstname_th'] . " " . $bill['lastname_th'];
    $total = 0;
    $rows_html = "";

    while ($row = $items_res->fetch_assoc()) {
        $sum = $row['price'] * $row['amount'];
        $total += $sum;
        $item_name = (!empty($row['prod_name'])) ? $row['prod_name'] . " " . $row['model_name'] : "ค่าบริการ / อะไหล่";
        
        $rows_html .= "
            <tr>
                <td style='padding:10px; border-bottom:1px solid #eee; color:#555;'>{$item_name}</td>
                <td style='padding:10px; border-bottom:1px solid #eee; text-align:center; color:#555;'>{$row['amount']}</td>
                <td style='padding:10px; border-bottom:1px solid #eee; text-align:right; color:#555;'>" . number_format($row['price'], 2) . "</td>
            </tr>";
    }

    $vat_amount = $total * ($bill['vat'] / 100);
    $grand_total = $total + $vat_amount - $bill['discount'];

    // ** ส่วนที่ต้องตรวจสอบชื่อคอลัมน์ (ลองเดาชื่อที่พบบ่อย) **
    // หากข้อมูลอาการเสียไม่แสดง ให้มาแก้คำว่า 'symptom' ตรงนี้ให้ตรงกับ DB ของคุณ
    $symptom_text = $bill['symptom'] ?? $bill['repair_symptom'] ?? $bill['problem'] ?? '-';
    $device_text  = $bill['device_name'] ?? $bill['device_model'] ?? '-';
    $serial_text  = $bill['serial_number'] ?? $bill['imei'] ?? '-';

    // 3. สร้าง HTML Template
    $bodyContent = "
    <html>
    <head>
        <style>
            body { font-family: 'Sarabun', sans-serif; background-color: #f4f4f4; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #198754, #20c997); color: white; padding: 25px; text-align: center; }
            .content { padding: 25px; }
            .repair-info { background: #e9ecef; border-left: 5px solid #198754; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
            .table-items { width: 100%; border-collapse: collapse; font-size: 14px; }
            .table-items th { background: #f8f9fa; padding: 10px; text-align: left; color: #444; }
            .footer { background: #343a40; color: #adb5bd; text-align: center; padding: 15px; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2 style='margin:0;'>{$bill['shop_name']}</h2>
                <p style='margin:5px 0 0; opacity:0.9;'>ใบเสร็จรับเงินค่าซ่อม / Repair Invoice</p>
            </div>
            
            <div class='content'>
                <p>เรียนคุณ <strong>$customer_name</strong>,</p>
                <p>งานซ่อมของคุณดำเนินการเสร็จสิ้นและชำระเงินเรียบร้อยแล้ว รายละเอียดดังนี้:</p>

                <table width='100%' style='margin-bottom: 20px;'>
                    <tr>
                        <td valign='top' width='50%'>
                            <strong>เลขที่บิล:</strong> #INV-" . str_pad($bill_id, 6, '0', STR_PAD_LEFT) . "<br>
                            <strong>Job ID:</strong> JOB-" . str_pad($bill['repair_id'] ?? 0, 6, '0', STR_PAD_LEFT) . "<br>
                            <strong>วันที่:</strong> " . date('d/m/Y H:i') . "
                        </td>
                        <td valign='top' width='50%' style='text-align: right;'>
                            <strong>วิธีชำระ:</strong> {$bill['payment_method']}
                        </td>
                    </tr>
                </table>

                <div class='repair-info'>
                    <h4 style='margin: 0 0 10px 0; color: #198754;'>รายละเอียดอุปกรณ์ (Device Info)</h4>
                    <strong>รุ่น/ยี่ห้อ:</strong> {$device_text}<br>
                    <strong>Serial/IMEI:</strong> {$serial_text}<br>
                    <strong>อาการเสีย:</strong> {$symptom_text}
                </div>

                <table class='table-items'>
                    <thead>
                        <tr>
                            <th width='50%'>รายการ (Description)</th>
                            <th width='15%' style='text-align: center;'>จำนวน</th>
                            <th width='35%' style='text-align: right;'>ราคา</th>
                        </tr>
                    </thead>
                    <tbody>
                        $rows_html
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan='2' style='padding:10px; text-align:right; border-top:2px solid #ddd;'>รวมเป็นเงิน:</td>
                            <td style='padding:10px; text-align:right; border-top:2px solid #ddd;'>" . number_format($total, 2) . "</td>
                        </tr>
                        <tr>
                            <td colspan='2' style='padding:5px 10px; text-align:right;'>VAT ({$bill['vat']}%):</td>
                            <td style='padding:5px 10px; text-align:right;'>" . number_format($vat_amount, 2) . "</td>
                        </tr>
                        " . ($bill['discount'] > 0 ? "
                        <tr>
                            <td colspan='2' style='padding:5px 10px; text-align:right; color:#dc3545;'>ส่วนลด:</td>
                            <td style='padding:5px 10px; text-align:right; color:#dc3545;'>-" . number_format($bill['discount'], 2) . "</td>
                        </tr>" : "") . "
                        <tr>
                            <td colspan='2' style='padding:15px 10px; text-align:right; font-size:18px; color:#198754; font-weight:bold;'>ยอดสุทธิ:</td>
                            <td style='padding:15px 10px; text-align:right; font-size:18px; color:#198754; font-weight:bold;'>" . number_format($grand_total, 2) . " ฿</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class='footer'>
                <p>ขอบคุณที่ใช้บริการ <strong>{$bill['shop_name']}</strong></p>
                <p>หากมีปัญหาหลังการซ่อม กรุณาติดต่อกลับพร้อมใบเสร็จนี้</p>
                <small>(อีเมลอัตโนมัติ ไม่ต้องตอบกลับ)</small>
            </div>
        </div>
    </body>
    </html>";

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $bill['shop_email'];
        $mail->Password   = $bill['shop_app_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($bill['shop_email'], $bill['shop_name']);
        $mail->addAddress($bill['cs_email'], $customer_name);

        $mail->isHTML(true);
        $mail->Subject = "ใบเสร็จค่าซ่อม (Repair Invoice) #INV-" . str_pad($bill_id, 6, '0', STR_PAD_LEFT);
        $mail->Body    = $bodyContent;
        $mail->AltBody = "บิลค่าซ่อม INV-$bill_id ยอดรวม " . number_format($grand_total, 2) . " บาท";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // ให้โชว์ Error ออกมาทางหน้าจอเลย
        echo "Mailer Error: " . $mail->ErrorInfo; 
        exit; // หยุดการทำงานเพื่อดู Error
        // return false; 
    }
}
?>