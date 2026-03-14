<?php
// เรียกไฟล์ PHPMailer
$vendorDir = '../vendor/phpmailer/phpmailer/src/';
if (file_exists($vendorDir . 'Exception.php')) {
    require_once $vendorDir . 'Exception.php';
    require_once $vendorDir . 'PHPMailer.php';
    require_once $vendorDir . 'SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

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
?>