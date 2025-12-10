<?php
session_start();
require '../config/config.php';

// ถ้าไม่มีอีเมลส่งมา (ไม่ได้เข้าตามขั้นตอน) ให้กลับไปหน้าลืมรหัส
if (!isset($_SESSION['otp_email'])) {
    header("Location: forgot_password.php");
    exit;
}

$email = $_SESSION['otp_email'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_otp = trim($_POST['otp_code']);

    // ตรวจสอบ OTP จาก Session
    if (time() > $_SESSION['reset_expiry']) {
        $error = "รหัส OTP หมดอายุแล้ว กรุณาขอใหม่";
    } elseif ($input_otp != $_SESSION['reset_otp']) {
        $error = "รหัส OTP ไม่ถูกต้อง";
    } else {
        // ผ่าน
        $_SESSION['allow_reset'] = true;

        // ล้าง OTP ทิ้ง
        unset($_SESSION['reset_otp']);
        unset($_SESSION['reset_expiry']);

        header("Location: reset_new_password.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ยืนยัน OTP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            background: #ecfdf5;
            font-family: 'Sarabun', sans-serif;
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
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            background: white;
            text-align: center;
        }

        .otp-input {
            letter-spacing: 8px;
            font-size: 1.5rem;
            text-align: center;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <div class="card-custom">
        <h4 class="fw-bold mb-3">ยืนยันรหัส OTP</h4>
        <p class="text-muted small">รหัสถูกส่งไปที่ <strong><?= htmlspecialchars($email) ?></strong><br>(กรุณาอย่าปิดหน้านี้)</p>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
                <input type="text" name="otp_code" class="form-control otp-input" maxlength="6" placeholder="XXXXXX" required autofocus>
            </div>
            <button type="submit" class="btn btn-success w-100 mb-3" style="background-color: #10b981; border: none;">ยืนยันรหัส</button>
            <a href="forgot_password.php" class="text-secondary small text-decoration-none">ขอรหัสใหม่</a>
        </form>
    </div>

</body>

</html>