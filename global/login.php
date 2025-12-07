<?php
session_start();
require '../config/config.php';

// 1. ตรวจสอบ Session: ถ้าล็อกอินอยู่แล้ว ให้ไปที่หน้า Home (Portal)
if (isset($_SESSION['user_id'])) {
    header("Location: ../home/home.php"); // [แก้ไข] เปลี่ยนจุดหมายเป็น home.php
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } else {
        $stmt = $conn->prepare("SELECT user_id, username, password, user_status FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                if ($user['user_status'] === 'Active') {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    
                    // 2. ล็อกอินสำเร็จ: ส่งไปหน้า Home (Portal)
                    header("Location: home.php"); // [แก้ไข] เปลี่ยนจุดหมายเป็น home.php
                    exit;
                } else {
                    $error = "บัญชีนี้ถูกระงับการใช้งาน";
                }
            } else {
                $error = "รหัสผ่านไม่ถูกต้อง";
            }
        } else {
            $error = "ไม่พบชื่อผู้ใช้นี้ในระบบ";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #10b981;
            --secondary-color: #059669;
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
            overflow: hidden;
            position: relative;
        }

        /* --- Background Animation --- */
        .bg-shape {
            position: absolute;
            background: rgba(16, 185, 129, 0.15);
            border-radius: 50%;
            z-index: -1;
            animation: float 6s ease-in-out infinite;
        }

        .shape-1 {
            width: 400px;
            height: 400px;
            top: -100px;
            right: -50px;
            animation-delay: 0s;
        }

        .shape-2 {
            width: 300px;
            height: 300px;
            bottom: -50px;
            left: -100px;
            animation-delay: 3s;
        }

        .shape-3 {
            width: 150px;
            height: 150px;
            top: 20%;
            left: 10%;
            background: rgba(52, 211, 153, 0.1);
            animation-delay: 1.5s;
        }

        @keyframes float {
            0% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-20px);
            }

            100% {
                transform: translateY(0px);
            }
        }

        /* --- Login Card --- */
        .login-card {
            width: 100%;
            max-width: 420px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            /* เอฟเฟกต์กระจก */
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.6);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s;
        }

        /* แถบสีด้านบน */
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, var(--primary-color), #34d399);
        }

        .logo-circle {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 20px;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
            animation: pulse 3s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4);
            }

            70% {
                box-shadow: 0 0 0 15px rgba(16, 185, 129, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }

        .form-control {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px 15px 12px 45px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            background: #fff;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            transition: 0.3s;
            font-size: 1.1rem;
            z-index: 5;
        }

        .form-control:focus+.input-icon {
            color: var(--primary-color);
        }

        .btn-login {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 12px;
            padding: 12px;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
            transition: all 0.3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }

        .forgot-link {
            color: #6b7280;
            text-decoration: none;
            font-size: 0.9rem;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
        }

        .forgot-link:hover {
            color: var(--primary-color);
            transform: translateX(3px);
        }

        .footer-text {
            font-size: 0.8rem;
            color: #9ca3af;
            text-align: center;
            margin-top: 25px;
        }
    </style>
</head>

<body>

    <div class="bg-shape shape-1"></div>
    <div class="bg-shape shape-2"></div>
    <div class="bg-shape shape-3"></div>

    <div class="login-card">
        <div class="logo-circle"><i class="fas fa-mobile-alt"></i></div>
        <h4 class="text-center fw-bold mb-1 text-dark">Mobile Shop</h4>
        <p class="text-center text-muted small mb-4">ระบบจัดการร้านมือถือครบวงจร</p>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 text-center border-0 rounded-3 shadow-sm mb-4">
                <i class="fas fa-exclamation-triangle me-1"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3 position-relative">
                <input type="text" name="username" class="form-control" placeholder="ชื่อผู้ใช้ (Username)" required autofocus>
                <i class="fas fa-user input-icon"></i>
            </div>

            <div class="mb-2 position-relative">
                <input type="password" name="password" class="form-control" placeholder="รหัสผ่าน (Password)" required>
                <i class="fas fa-lock input-icon"></i>
            </div>

            <div class="d-flex justify-content-end mb-4">
                <a href="forgot_password.php" class="forgot-link">
                    ลืมรหัสผ่าน? <i class="fas fa-arrow-right ms-1" style="font-size: 0.8em;"></i>
                </a>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-login text-white">
                    เข้าสู่ระบบ
                </button>
            </div>
        </form>

        <div class="footer-text">
            &copy; <?= date('Y') ?> Mobile Shop System. All rights reserved.
        </div>
    </div>

</body>

</html>