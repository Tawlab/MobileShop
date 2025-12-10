<?php
session_start();
require '../config/config.php';
// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: ../global/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

//  Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_pass = $_POST['current_password'];
    $new_pass     = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!password_verify($current_pass, $user['password'])) {
        $_SESSION['error'] = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
    } elseif ($new_pass !== $confirm_pass) {
        $_SESSION['error'] = "รหัสผ่านใหม่ และ ยืนยันรหัสผ่าน ไม่ตรงกัน";
    } elseif (strlen($new_pass) < 6) {
        $_SESSION['error'] = "รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
    } else {
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ?, update_at = NOW() WHERE user_id = ?");
        $update->bind_param("si", $new_hash, $user_id);

        if ($update->execute()) {
            $_SESSION['success'] = "เปลี่ยนรหัสผ่านสำเร็จ";
            header("Location: change_password.php");
            exit;
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
<<<<<<< HEAD
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
=======
>>>>>>> 87d2bdcaa5a9158c74359bf647e536fa344f68ca
    <title>เปลี่ยนรหัสผ่าน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: #f8f9fa;
        }

        .card-custom {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            background: white;
            overflow: hidden;
        }

        .card-header-custom {
            background-color: #198754;
            color: white;
            padding: 25px;
            text-align: center;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.95rem;
            color: #495057;
        }

        .btn-success-custom {
            background-color: #198754;
            border-color: #198754;
            padding: 12px;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .btn-success-custom:hover {
            background-color: #157347;
            border-color: #146c43;
        }

        .icon-box {
            font-size: 3rem;
            margin-bottom: 10px;
            opacity: 0.9;
        }
    </style>
</head>

<body>

    <div class="d-flex" id="wrapper">

        <?php include '../global/sidebar.php'; ?>

        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container" style="max-width: 600px; margin-top: 20px;"> <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4">
                            <i class="fas fa-exclamation-circle me-2"></i> <?= $_SESSION['error'];
                                                                                        unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4">
                            <i class="fas fa-check-circle me-2"></i> <?= $_SESSION['success'];
                                                                        unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card card-custom">
                        <div class="card-header-custom">
                            <div class="icon-box"><i class="fas fa-lock"></i></div>
                            <h4 class="mb-0 fw-bold text-light">เปลี่ยนรหัสผ่าน</h4>
                        </div>
                        <div class="card-body p-5">
                            <form method="POST">
                                <div class="mb-4">
                                    <label class="form-label">รหัสผ่านปัจจุบัน</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fas fa-key text-secondary"></i></span>
                                        <input type="password" name="current_password" class="form-control form-control-lg" required>
                                    </div>
                                </div>
                                <hr class="my-4 text-muted">
                                <div class="mb-3">
                                    <label class="form-label text-success">รหัสผ่านใหม่</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fas fa-unlock-alt text-success"></i></span>
                                        <input type="password" name="new_password" class="form-control form-control-lg" required minlength="6">
                                    </div>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label text-success">ยืนยันรหัสผ่านใหม่</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fas fa-check-circle text-success"></i></span>
                                        <input type="password" name="confirm_password" class="form-control form-control-lg" required minlength="6">
                                    </div>
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success-custom shadow-sm text-light">
                                        <i class="fas fa-save me-2"></i> ยืนยัน
                                    </button>
                                    <a href="../home/dashboard.php" class="btn btn-light text-secondary mt-2">
                                        <i class="fas fa-home me-1"></i> กลับหน้าหลัก
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>