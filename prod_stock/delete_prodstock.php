<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'delete_prodstock');

// (FIXED: 1) รับ ID สต็อกที่ต้องการลบ (ใช้ $_GET ตามไฟล์เดิมของคุณ)
$stock_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$stock_id) {
    $_SESSION['error'] = 'ไม่พบรหัสสต็อกที่ต้องการลบ';
    header('Location: prod_stock.php');
    exit;
}

// (FIXED: 2) ดึงข้อมูลสต็อก (ใช้คอลัมน์ใหม่จาก prod_stocks.sql)
$stock_sql = "SELECT 
                ps.stock_id,
                ps.stock_status,
                ps.image_path,
                p.prod_name
            FROM prod_stocks ps
            LEFT JOIN products p ON ps.products_prod_id = p.prod_id
            WHERE ps.stock_id = $stock_id";

$result = mysqli_query($conn, $stock_sql);
$stock_data = mysqli_fetch_assoc($result);

if (!$stock_data) {
    $_SESSION['error'] = 'ไม่พบข้อมูลสต็อกที่ต้องการลบ';
    header('Location: prod_stock.php');
    exit;
}

// (FIXED: 3) ตรวจสอบเงื่อนไขการลบ (ใช้ 'Sold' ตัวพิมพ์ใหญ่)
$can_delete = true;
$error_message = '';

if ($stock_data['stock_status'] === 'Sold') {
    $can_delete = false;
    $error_message = 'ไม่สามารถลบสินค้าที่ขายแล้ว (สถานะ Sold)';
}

// (FIXED: 4) จัดการการลบ (ส่วนนี้คือส่วนที่โค้ดใน prod_stock.php ใช้)
// (ไฟล์นี้จะทำงานเมื่อมีการยืนยันจากหน้า UI ที่ส่ง POST มา)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {

    // (ดึง ID จาก POST อีกครั้งเพื่อความปลอดภัย)
    $confirmed_stock_id = (int)$_POST['stock_id'];

    if ($confirmed_stock_id != $stock_id) {
        $_SESSION['error'] = 'รหัสสต็อกไม่ตรงกัน';
        header('Location: prod_stock.php');
        exit;
    }

    if (!$can_delete) {
        $_SESSION['error'] = $error_message;
        header('Location: prod_stock.php');
        exit;
    }

    // เริ่ม Transaction
    mysqli_autocommit($conn, false);

    try {
        // (FIXED: 5) ลบรูปภาพ (ใช้ image_path และไม่ใช่ JSON)
        if (!empty($stock_data['image_path'])) {
            $image_path = '../uploads/products/' . $stock_data['image_path'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }

        // (*** CRITICAL FIX ***: ลบจาก stock_movements ก่อน)
        $delete_movements_sql = "DELETE FROM stock_movements WHERE prod_stocks_stock_id = $stock_id";
        if (!mysqli_query($conn, $delete_movements_sql)) {
            // (อนุญาตให้ผ่านได้ แม้จะไม่มี movement)
        }

        // (FIXED: 6) ลบการรับประกัน (ลบส่วนนี้ทิ้ง เพราะไม่มีใน DB ใหม่)

        // (FIXED: 7) ลบสต็อก (ใช้ stock_id)
        $delete_stock_sql = "DELETE FROM prod_stocks WHERE stock_id = $stock_id";
        if (!mysqli_query($conn, $delete_stock_sql)) {
            throw new Exception('ไม่สามารถลบข้อมูลสต็อกได้ (DB Error)');
        }

        mysqli_commit($conn);
        $_SESSION['success'] = 'ลบสินค้า (ID: ' . $stock_id . ') ออกจากสต็อกเรียบร้อยแล้ว';
        header('Location: prod_stock.php');
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = 'เกิดข้อผิดพลาดในการลบ: ' . $e->getMessage();
    }

    mysqli_autocommit($conn, true);
    // (ถ้า Error ให้กลับไปหน้า prod_stock.php)
    header('Location: prod_stock.php');
    exit;
}

// -----------------------------------------------------------------------------
// 6. HTML (หน้าสำหรับยืนยันการลบ)
// (โค้ดนี้จะแสดงหน้า UI ให้กดยืนยันก่อน)
// -----------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลบสินค้าจากสต็อก - ระบบจัดการร้านค้ามือถือ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Prompt', sans-serif;
        }

        .main-header {
            background-color: #dc3545;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .delete-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            max-width: 600px;
            margin: auto;
        }

        .card-header {
            background-color: #dc3545;
            color: white;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
            font-weight: 600;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
        }

        .info-value {
            color: #212529;
            font-weight: 500;
        }

        .highlight-value {
            color: #dc3545;
            font-weight: 700;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-sold {
            background-color: #f8d7da;
            color: #721c24;
        }

        .error-box {
            background-color: #f8d7da;
            border: 2px solid #dc3545;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            max-width: 600px;
            margin: 2rem auto;
        }

        .btn-danger {
            background-color: #dc3545;
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
        }

        .btn-secondary {
            background-color: #6c757d;
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="main-header">
        <div class="container">
            <h1>
                <i class="fas fa-trash-alt me-3"></i>
                ลบสินค้าจากสต็อก
            </h1>
        </div>
    </div>

    <div class="container">
        <?php if (!$can_delete): ?>
            <div class="error-box">
                <h4 class="text-danger mb-3">
                    <i class="fas fa-ban me-2"></i>ไม่สามารถลบสินค้านี้ได้
                </h4>
                <p class="text-danger fw-bold mb-0"><?= $error_message ?></p>
                <div class="text-center mt-3">
                    <a href="prod_stock.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>ย้อนกลับ
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="delete-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>ยืนยันข้อมูลสินค้าที่จะลบ
                    </h5>
                </div>
                <div class="card-body">
                    <div class="info-item">
                        <span class="info-label">รหัสสต็อก:</span>
                        <span class="info-value highlight-value"><?= str_pad($stock_data['stock_id'], 6, '0', STR_PAD_LEFT) ?></span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">สินค้า:</span>
                        <span class="info-value"><?= htmlspecialchars($stock_data['prod_name']) ?></span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">สถานะ:</span>
                        <span class="info-value">
                            <?= htmlspecialchars($stock_data['stock_status']) ?>
                        </span>
                    </div>

                    <div class="text-center mt-4">
                        <form method="POST" action="delete_prodstock.php?id=<?= $stock_id ?>" style="display: inline;">
                            <input type="hidden" name="stock_id" value="<?= $stock_id ?>">
                            <button type="submit" name="confirm_delete" class="btn btn-danger me-3"
                                onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบสินค้านี้ (ID: <?= $stock_id ?>)?\nการลบจะไม่สามารถกู้คืนได้!')">
                                <i class="fas fa-trash-alt me-2"></i>ยืนยันการลบ
                            </button>
                        </form>

                        <a href="prod_stock.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>ย้อนกลับ
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>