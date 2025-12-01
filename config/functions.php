<?php
// เรียกใช้ Namespace ของ PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// =============================================================================
// 1. ฟังก์ชันตรวจสอบสิทธิ์ (Check Permission) - คืนค่า True/False
// =============================================================================
function hasPermission($conn, $user_id, $permission_name)
{
    // 1. ถ้าไม่มี User ID ส่งมา (ยังไม่ล็อกอิน) ให้เป็น false
    if (empty($user_id)) return false;

    // 2. Admin (User ID 1) ให้ผ่านตลอด (Optional: เผื่อไว้แก้ปัญหาฉุกเฉิน)
    // if ($user_id == 1) return true; 

    // 3. Query เช็คสิทธิ์
    $sql = "SELECT 1
            FROM user_roles ur
            JOIN role_permissions rp ON ur.roles_role_id = rp.roles_role_id
            JOIN permissions p ON rp.permissions_permission_id = p.permission_id
            WHERE ur.users_user_id = ? 
            AND p.permission_name = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $permission_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $has_perm = ($result->num_rows > 0);

    $stmt->close();

    return $has_perm;
}

// =============================================================================
// 2. ฟังก์ชันตรวจสอบสิทธิ์เข้าใช้งานหน้าเว็บ (Page Access Guard)
//    ใช้แปะหัวไฟล์: checkPageAccess($conn, 'add_product');
// =============================================================================
function checkPageAccess($conn, $permission_name)
{
    // 1. เช็คว่าล็อกอินหรือยัง
    if (!isset($_SESSION['user_id'])) {
        // ถ้ายังไม่ล็อกอิน ให้ดีดไปหน้า Login
        // (สมมติว่าไฟล์ที่เรียกใช้อยู่ในโฟลเดอร์ย่อย เช่น customer/, product/)
        header('Location: ../global/login.php');
        exit;
    }

    // 2. เช็คสิทธิ์
    if (!hasPermission($conn, $_SESSION['user_id'], $permission_name)) {
        // ถ้าล็อกอินแล้ว แต่ไม่มีสิทธิ์ ให้ดีดไปหน้าแจ้งเตือน
        header('Location: ../access_denied.php');
        exit;
    }
}

// =============================================================================
// 3. ฟังก์ชันส่งใบเสร็จรับเงินทางอีเมล (Send Receipt Email)
// =============================================================================
function sendReceiptEmail($conn, $bill_id)
{
    // 1. ดึงข้อมูลบิล, ลูกค้า และร้านค้า (รวมถึงรหัสผ่านแอป)
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

    // ถ้าไม่พบข้อมูล หรือลูกค้าไม่มีอีเมล หรือร้านไม่มีการตั้งค่าอีเมล -> จบการทำงาน (ไม่ส่ง)
    if (!$bill || empty($bill['cs_email']) || empty($bill['shop_email']) || empty($bill['shop_app_password'])) {
        return false;
    }

    // 2. ดึงรายการสินค้าในบิล
    $sql_items = "SELECT bd.price, bd.amount, p.prod_name, p.model_name 
                  FROM bill_details bd 
                  JOIN prod_stocks ps ON bd.prod_stocks_stock_id = ps.stock_id 
                  JOIN products p ON ps.products_prod_id = p.prod_id 
                  WHERE bd.bill_headers_bill_id = ?";
    $stmt2 = $conn->prepare($sql_items);
    $stmt2->bind_param("i", $bill_id);
    $stmt2->execute();
    $items_res = $stmt2->get_result();

    // 3. เตรียมเนื้อหา HTML สำหรับตารางรายการสินค้า
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

    // สร้าง Body HTML
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

    // 4. เริ่มส่งอีเมลด้วย PHPMailer
    $mail = new PHPMailer(true);
    try {
        // ตั้งค่า SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $bill['shop_email'];
        $mail->Password   = $bill['shop_app_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // ผู้รับ-ผู้ส่ง
        $mail->setFrom($bill['shop_email'], $bill['shop_name']);
        $mail->addAddress($bill['cs_email'], $customer_name);

        // เนื้อหา
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
