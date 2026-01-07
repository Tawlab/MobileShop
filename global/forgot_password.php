<?php
session_start();
require '../config/config.php';

// ส่วนจัดการ AJAX Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาด'];

    try {
        // --- 1. ตรวจสอบข้อมูล (Verify Identity) ---
        if ($action === 'verify_identity') {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);

            // ตรวจสอบว่ามีคู่ Username และ Email นี้จริงหรือไม่
            $sql = "SELECT u.user_id, e.firstname_th 
                    FROM users u 
                    JOIN employees e ON u.user_id = e.users_user_id 
                    WHERE u.username = ? AND e.emp_email = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
                $user = $res->fetch_assoc();
                
                // เก็บ Session ชั่วคราวเพื่ออนุญาตให้เปลี่ยนรหัส
                $_SESSION['reset_user_id'] = $user['user_id'];
                $_SESSION['allow_reset'] = true;

                $response = [
                    'status' => 'success', 
                    'message' => 'ตรวจสอบข้อมูลถูกต้อง',
                    'user_name' => $user['firstname_th']
                ];
            } else {
                $response = ['status' => 'error', 'message' => 'ไม่พบข้อมูล Username หรือ Email นี้ในระบบ'];
            }
        }

        // --- 2. เปลี่ยนรหัสผ่าน (Update Password) ---
        elseif ($action === 'update_password') {
            if (!isset($_SESSION['allow_reset']) || !isset($_SESSION['reset_user_id'])) {
                throw new Exception('Session หมดอายุ กรุณาทำรายการใหม่');
            }

            $new_pass = $_POST['new_password'];
            $confirm_pass = $_POST['confirm_password'];

            if (strlen($new_pass) < 6) {
                throw new Exception('รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร');
            }

            if ($new_pass !== $confirm_pass) {
                throw new Exception('รหัสผ่านยืนยันไม่ตรงกัน');
            }

            // Hash รหัสผ่านใหม่
            $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);
            $user_id = $_SESSION['reset_user_id'];

            // อัปเดตลงฐานข้อมูล
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);

            if ($stmt->execute()) {
                // ล้าง Session
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['allow_reset']);
                
                $response = ['status' => 'success', 'message' => 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว'];
            } else {
                throw new Exception('เกิดข้อผิดพลาดในการบันทึกข้อมูล');
            }
        }

    } catch (Exception $e) {
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    }

    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน / กู้คืนบัญชี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: #f3f4f6; /* สีพื้นหลังเรียบๆ */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-custom {
            width: 100%;
            max-width: 420px;
            padding: 40px 30px;
            border-radius: 20px;
            background: white;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            position: relative;
            text-align: center;
        }

        .icon-header {
            width: 80px;
            height: 80px;
            background: #e0f2fe;
            color: #0284c7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
        }

        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #e5e7eb;
            background-color: #f9fafb;
        }

        .form-control:focus {
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.1);
            border-color: #0284c7;
        }

        .btn-primary {
            background-color: #0284c7;
            border: none;
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            margin-top: 10px;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background-color: #0369a1;
            transform: translateY(-2px);
        }

        .step-container {
            transition: opacity 0.3s ease;
        }
    </style>
</head>

<body>

    <div class="card-custom">
        
        <div id="step-verify" class="step-container">
            <div class="icon-header">
                <i class="fas fa-user-lock"></i>
            </div>
            <h4 class="fw-bold mb-2">ลืมรหัสผ่าน?</h4>
            <p class="text-muted small mb-4">กรอก Username และ Email ให้ตรงกับระบบ<br>เพื่อยืนยันตัวตน</p>

            <form id="formVerify">
                <input type="hidden" name="action" value="verify_identity">
                
                <div class="mb-3 text-start">
                    <label class="form-label small fw-bold text-secondary ms-1">ชื่อผู้ใช้ (Username)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0"><i class="fas fa-user text-muted"></i></span>
                        <input type="text" name="username" class="form-control border-start-0" placeholder="Username" required>
                    </div>
                </div>

                <div class="mb-4 text-start">
                    <label class="form-label small fw-bold text-secondary ms-1">อีเมล (Email)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0"><i class="fas fa-envelope text-muted"></i></span>
                        <input type="email" name="email" class="form-control border-start-0" placeholder="name@example.com" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check-circle me-2"></i> ตรวจสอบข้อมูล
                </button>
            </form>
            
            <a href="login.php" class="d-block mt-4 text-secondary small text-decoration-none">
                <i class="fas fa-arrow-left me-1"></i> กลับหน้าเข้าสู่ระบบ
            </a>
        </div>

        <div id="step-reset" class="step-container" style="display: none;">
            <div class="icon-header" style="background: #dcfce7; color: #16a34a;">
                <i class="fas fa-key"></i>
            </div>
            <h4 class="fw-bold mb-2">ตั้งรหัสผ่านใหม่</h4>
            <p class="text-muted small mb-4">สวัสดีคุณ <span id="userNameDisplay" class="fw-bold text-dark"></span><br>กรุณากำหนดรหัสผ่านใหม่ของคุณ</p>

            <form id="formReset">
                <input type="hidden" name="action" value="update_password">
                
                <div class="mb-3 text-start">
                    <label class="form-label small fw-bold text-secondary ms-1">รหัสผ่านใหม่</label>
                    <input type="password" name="new_password" class="form-control" placeholder="อย่างน้อย 6 ตัวอักษร" required minlength="6">
                </div>

                <div class="mb-4 text-start">
                    <label class="form-label small fw-bold text-secondary ms-1">ยืนยันรหัสผ่านใหม่</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="กรอกรหัสผ่านอีกครั้ง" required minlength="6">
                </div>

                <button type="submit" class="btn btn-primary" style="background-color: #16a34a;">
                    <i class="fas fa-save me-2"></i> บันทึกรหัสผ่าน
                </button>
            </form>
        </div>

    </div>

    <script>
        // DOM Elements
        const stepVerify = document.getElementById('step-verify');
        const stepReset = document.getElementById('step-reset');
        const userNameDisplay = document.getElementById('userNameDisplay');

        // --- 1. จัดการฟอร์มตรวจสอบตัวตน ---
        document.getElementById('formVerify').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // แสดง Loading
            Swal.fire({
                title: 'กำลังตรวจสอบ...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            const formData = new FormData(this);

            fetch('forgot_password.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.close();
                    
                    // แสดงชื่อผู้ใช้และสลับไปฟอร์ม Reset
                    userNameDisplay.innerText = data.user_name;
                    stepVerify.style.display = 'none';
                    
                    // Animation Fade In
                    stepReset.style.opacity = 0;
                    stepReset.style.display = 'block';
                    setTimeout(() => { stepReset.style.opacity = 1; }, 50);

                    // แจ้งเตือนเล็กๆ มุมขวาบน
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                    Toast.fire({ icon: 'success', title: 'ยืนยันตัวตนสำเร็จ' });

                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'ข้อมูลไม่ถูกต้อง',
                        text: data.message,
                        confirmButtonColor: '#0284c7'
                    });
                }
            })
            .catch(err => {
                Swal.fire('Error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
            });
        });

        // --- 2. จัดการฟอร์มเปลี่ยนรหัสผ่าน ---
        document.getElementById('formReset').addEventListener('submit', function(e) {
            e.preventDefault();

            const pass1 = this.new_password.value;
            const pass2 = this.confirm_password.value;

            if (pass1 !== pass2) {
                Swal.fire('รหัสผ่านไม่ตรงกัน', 'กรุณากรอกรหัสผ่านยืนยันให้ถูกต้อง', 'warning');
                return;
            }

            Swal.fire({
                title: 'กำลังบันทึก...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            const formData = new FormData(this);

            fetch('forgot_password.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ!',
                        text: 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว กรุณาเข้าสู่ระบบใหม่',
                        confirmButtonText: 'ไปหน้าเข้าสู่ระบบ',
                        confirmButtonColor: '#16a34a',
                        allowOutsideClick: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'login.php';
                        }
                    });
                } else {
                    Swal.fire('ผิดพลาด', data.message, 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
            });
        });
    </script>

</body>
</html>