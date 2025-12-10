<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ไม่มีสิทธิ์เข้าถึง (Access Denied)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #fdf2f2 0%, #fff1f2 100%);
            font-family: 'Sarabun', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .error-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 90%;
            position: relative;
            border-bottom: 5px solid #dc3545;
        }

        .icon-box {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 1.5rem;
            animation: float 3s ease-in-out infinite;
            text-shadow: 0 10px 20px rgba(220, 53, 69, 0.2);
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        h1 {
            font-size: 4rem;
            font-weight: 800;
            color: #374151;
            margin-bottom: 0;
            line-height: 1;
        }

        h3 {
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1rem;
        }

        p {
            color: #6b7280;
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }

        .btn-home {
            background-color: #10b981;
            border-color: #10b981;
            color: white;
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-home:hover {
            background-color: #059669;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
            color: white;
        }

        .btn-back {
            color: #6b7280;
            text-decoration: none;
            font-weight: 600;
            margin-right: 20px;
            transition: 0.3s;
        }

        .btn-back:hover {
            color: #374151;
        }
    </style>
</head>

<body>
    <div class="error-card">
        <div class="icon-box">
            <i class="fas fa-lock"></i>
        </div>
        <h1>403</h1>
        <h3>ไม่มีสิทธิ์เข้าถึง (Access Denied)</h3>
        <p>ขออภัย คุณไม่มีสิทธิ์ใช้งานในส่วนนี้<br>กรุณาติดต่อผู้ดูแลระบบหากคุณต้องการสิทธิ์</p>
        
        <div class="d-flex justify-content-center align-items-center">
            <a href="javascript:history.back()" class="btn-back">
                <i class="fas fa-arrow-left me-1"></i> ย้อนกลับ
            </a>
            <a href="../home/home.php" class="btn btn-home">
                <i class="fas fa-home me-2"></i> กลับหน้าหลัก
            </a>
        </div>
    </div>
</body>
</html>