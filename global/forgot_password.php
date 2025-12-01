<?php
session_start();
require '../config/config.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// =============================================================================
// BACKEND: AJAX HANDLER
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json'); // บอก Browser ว่าส่งกลับเป็น JSON
    $action = $_POST['action'];
    $response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาด'];

    // --- 1. ส่ง OTP (Send OTP) ---
    if ($action === 'send_otp') {
        $email = trim($_POST['email']);

        // เช็คอีเมลใน DB
        $stmt = $conn->prepare("SELECT u.user_id, e.firstname_th FROM employees e JOIN users u ON e.users_user_id = u.user_id WHERE e.emp_email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            $otp = rand(100000, 999999);

            // เก็บลง Session
            $_SESSION['reset_otp'] = $otp;
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_user_id'] = $user['user_id'];
            $_SESSION['reset_expiry'] = time() + 900; // 15 นาที

            // ส่งเมล
            try {
                $shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_info LIMIT 1"));
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = $shop['shop_email'];
                $mail->Password = $shop['shop_app_password'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->CharSet = 'UTF-8';
                $mail->setFrom($shop['shop_email'], $shop['shop_name']);
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = "รหัส OTP ของคุณคือ $otp";
                $mail->Body = "<h3>รหัสยืนยันตัวตน</h3><h1>$otp</h1><p>รหัสมีอายุ 15 นาที</p>";
                $mail->send();

                $response = ['status' => 'success', 'message' => 'ส่ง OTP แล้ว'];
            } catch (Exception $e) {
                $response = ['status' => 'error', 'message' => 'ส่งเมลไม่ผ่าน: ' . $mail->ErrorInfo];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'ไม่พบอีเมลนี้ในระบบ'];
        }
    }

    // --- 2. ยืนยัน OTP (Verify OTP) ---
    elseif ($action === 'verify_otp') {
        $input_otp = trim($_POST['otp_code']);

        if (!isset($_SESSION['reset_otp'])) {
            $response = ['status' => 'error', 'message' => 'Session หมดอายุ กรุณาเริ่มใหม่'];
        } elseif (time() > $_SESSION['reset_expiry']) {
            $response = ['status' => 'error', 'message' => 'รหัส OTP หมดอายุแล้ว'];
        } elseif ($input_otp != $_SESSION['reset_otp']) {
            $response = ['status' => 'error', 'message' => 'รหัส OTP ไม่ถูกต้อง'];
        } else {
            // ผ่าน
            $_SESSION['allow_reset'] = true;
            unset($_SESSION['reset_otp']); // ลบ OTP ทิ้ง
            $response = ['status' => 'success', 'redirect' => 'reset_new_password.php'];
        }
    }

    echo json_encode($response);
    exit; // จบการทำงาน PHP ตรงนี้ (ไม่แสดง HTML ต่อ)
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ลืมรหัสผ่าน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: #ecfdf5;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-custom {
            width: 100%;
            max-width: 400px;
            padding: 30px;
            border-radius: 15px;
            background: white;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .btn-success {
            background-color: #10b981;
            border: none;
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            font-weight: bold;
        }

        .btn-success:hover {
            background-color: #059669;
        }

        .otp-input {
            letter-spacing: 8px;
            font-size: 1.5rem;
            text-align: center;
            font-weight: bold;
        }

        /* Loading Overlay */
        .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
    </style>
</head>

<body>

    <div class="card-custom">
        <div class="overlay" id="loadingOverlay">
            <div class="spinner-border text-success" role="status"></div>
        </div>

        <div id="step-email">
            <i class="fas fa-envelope-open-text fa-3x text-success mb-3"></i>
            <h4 class="fw-bold">ลืมรหัสผ่าน?</h4>
            <p class="text-muted small mb-4">กรอกอีเมลเพื่อรับรหัส OTP</p>

            <form id="formEmail">
                <input type="hidden" name="action" value="send_otp">
                <div class="mb-3 text-start">
                    <input type="email" name="email" class="form-control" placeholder="ระบุอีเมลของคุณ" required>
                </div>
                <button type="submit" class="btn btn-success">ส่งรหัส OTP</button>
            </form>
            <a href="login.php" class="d-block mt-3 text-secondary small text-decoration-none">กลับหน้าเข้าสู่ระบบ</a>
        </div>

        <div id="step-otp" style="display: none;">
            <i class="fas fa-shield-alt fa-3x text-success mb-3"></i>
            <h4 class="fw-bold">ยืนยันรหัส OTP</h4>
            <p class="text-muted small mb-4">รหัสถูกส่งไปที่อีเมลของคุณแล้ว</p>

            <form id="formOTP">
                <input type="hidden" name="action" value="verify_otp">
                <div class="mb-3">
                    <input type="text" name="otp_code" class="form-control otp-input" maxlength="6" placeholder="000000" required>
                </div>
                <button type="submit" class="btn btn-success">ยืนยัน</button>
            </form>
            <button onclick="location.reload()" class="btn btn-link text-secondary small text-decoration-none mt-2">เปลี่ยนอีเมล / ส่งใหม่</button>
        </div>

    </div>

    <script>
        const stepEmail = document.getElementById('step-email');
        const stepOtp = document.getElementById('step-otp');
        const overlay = document.getElementById('loadingOverlay');

        // 1. จัดการฟอร์มส่งอีเมล
        document.getElementById('formEmail').addEventListener('submit', function(e) {
            e.preventDefault();
            overlay.style.display = 'flex'; // โชว์โหลด

            const formData = new FormData(this);

            fetch('forgot_password.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    overlay.style.display = 'none';
                    if (data.status === 'success') {
                        // สลับหน้าจอไป Step 2
                        stepEmail.style.display = 'none';
                        stepOtp.style.display = 'block';
                        Swal.fire('สำเร็จ', 'ส่งรหัส OTP ไปที่อีเมลแล้ว', 'success');
                    } else {
                        Swal.fire('ผิดพลาด', data.message, 'error');
                    }
                })
                .catch(err => {
                    overlay.style.display = 'none';
                    Swal.fire('Error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
                });
        });

        // 2. จัดการฟอร์มยืนยัน OTP
        document.getElementById('formOTP').addEventListener('submit', function(e) {
            e.preventDefault();
            overlay.style.display = 'flex';

            const formData = new FormData(this);

            fetch('forgot_password.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    overlay.style.display = 'none';
                    if (data.status === 'success') {
                        // ถ้าผ่าน ให้ย้ายหน้า
                        window.location.href = data.redirect;
                    } else {
                        Swal.fire('ผิดพลาด', data.message, 'error');
                    }
                })
                .catch(err => {
                    overlay.style.display = 'none';
                    Swal.fire('Error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
                });
        });
    </script>

</body>

</html>