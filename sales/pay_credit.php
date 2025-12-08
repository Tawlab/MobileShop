<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_sale'); 

// รับ ID บิล เพื่อให้กดกลับไปเลือกวิธีชำระอื่นได้ถูกบิล
$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ชำระผ่านบัตรเครดิต - Mobile Shop</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <?php require '../config/load_theme.php'; ?>

    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            color: <?= $text_color ?>;
        }

        .maintenance-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            padding: 4rem 2rem;
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
            border-bottom: 5px solid <?= $theme_color ?>;
        }

        .icon-container {
            position: relative;
            display: inline-block;
            margin-bottom: 2rem;
        }

        .main-icon {
            font-size: 5rem;
            color: #cbd5e1; /* สีเทาจางๆ */
        }

        .status-icon {
            position: absolute;
            bottom: -10px;
            right: -10px;
            background: #fff;
            border-radius: 50%;
            padding: 5px;
            color: #f59e0b; /* สีเหลือง Warning */
            font-size: 2rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        h3 {
            font-weight: 700;
            color: <?= $text_color ?>;
            margin-bottom: 1rem;
        }

        p {
            color: #64748b;
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }

        .btn-back {
            background-color: <?= $theme_color ?>;
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            color: white;
            filter: brightness(90%);
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>

        <div class="main-content w-100">
            <div class="container-fluid py-5">
                
                <div class="container">
                    <div class="maintenance-card fade-in">
                        
                        <div class="icon-container">
                            <i class="fas fa-credit-card main-icon"></i>
                            <i class="fas fa-tools status-icon"></i>
                        </div>

                        <h3>ขออภัย บริการนี้ยังไม่เปิดใช้งาน</h3>
                        <p>
                            ระบบรับชำระเงินผ่านบัตรเครดิตกำลังอยู่ในระหว่างการพัฒนา <br>
                            กรุณาเลือกช่องทางการชำระเงินอื่น
                        </p>

                        <div class="d-flex justify-content-center gap-3">
                            <a href="payment_select.php?id=<?= $bill_id ?>" class="btn btn-back">
                                <i class="fas fa-undo me-2"></i> เลือกวิธีชำระเงินอื่น
                            </a>
                            
                            <a href="sale_list.php" class="btn btn-outline-secondary rounded-pill px-4 py-2">
                                <i class="fas fa-home me-2"></i> กลับหน้าหลัก
                            </a>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>