<?php
session_start();
require '../config/config.php';

// Security Check: ต้องผ่านการยืนยัน OTP มาแล้วเท่านั้น
if (!isset($_SESSION['allow_reset']) || !isset($_SESSION['reset_user_id'])) {
    // ถ้าไม่มีตั๋วผ่านทาง ให้กลับไปหน้า Login
    header("Location: login.php");
    exit;
}

$error = '';
$user_id = $_SESSION['reset_user_id'];

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // Validation
    if (strlen($new_pass) < 6) {
        $error = "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "รหัสผ่านทั้งสองช่องไม่ตรงกัน";
    } else {
        // Update Password
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password = ?, update_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("si", $new_hash, $user_id);

        if ($stmt->execute()) {
            // สำเร็จ -> ล้าง Session ทิ้งทั้งหมด
            unset($_SESSION['allow_reset']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_otp']);
            unset($_SESSION['reset_expiry']);

            // แจ้งเตือนและกลับไปหน้า Login
            echo "<script>
                alert('✅ เปลี่ยนรหัสผ่านสำเร็จ! กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่');
                window.location.href='login.php';
            </script>";
            exit;
        } else {
            $error = "เกิดข้อผิดพลาดทางเทคนิค: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ตั้งรหัสผ่านใหม่</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #10b981;
            --bg-gradient: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: var(--bg-gradient);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .reset-card {
            width: 100%;
            max-width: 420px;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
        }

        /* แถบสีด้านบน */
        .reset-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #10b981, #34d399);
        }

        .icon-box {
            width: 70px;
            height: 70px;
            background: #d1fae5;
            color: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 20px;
        }

        .btn-success {
            background: #10b981;
            border: none;
            padding: 12px;
            font-weight: bold;
            width: 100%;
            border-radius: 10px;
            transition: transform 0.2s;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .form-control {
            border-radius: 10px;
            padding: 12px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
        }

        .form-control:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
    </style>
</head>

<body>

    <div class="reset-card">
        <div class="icon-box"><i class="fas fa-key"></i></div>
        <h4 class="fw-bold text-center mb-2">ตั้งรหัสผ่านใหม่</h4>
        <p class="text-muted text-center small mb-4">กรุณากำหนดรหัสผ่านใหม่ของคุณ</p>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small text-center mb-4">
                <i class="fas fa-exclamation-circle me-1"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-bold small text-secondary">รหัสผ่านใหม่</label>
                <input type="password" name="new_password" class="form-control" placeholder="อย่างน้อย 6 ตัวอักษร" required autofocus>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold small text-secondary">ยืนยันรหัสผ่านใหม่</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="กรอกซ้ำอีกครั้ง" required>
            </div>

            <button type="submit" class="btn btn-success mb-3">
                <i class="fas fa-save me-2"></i> บันทึกรหัสผ่าน
            </button>

            <div class="text-center">
                <a href="login.php" class="text-decoration-none text-secondary small">ยกเลิก</a>
            </div>
        </form>
    </div>

</body>

</html>