<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'cancel_sale');
// Helper: Movement ID
function getNextMovementId($conn)
{
    $sql = "SELECT IFNULL(MAX(movement_id), 0) + 1 as next_id FROM stock_movements";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result)['next_id'];
}

// 1. ตรวจสอบ ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ไม่พบรหัสบิล";
    header("Location: sale_list.php");
    exit;
}

$bill_id = (int)$_GET['id'];

// 2. ตรวจสอบสถานะปัจจุบัน
$chk_sql = "SELECT bill_status FROM bill_headers WHERE bill_id = $bill_id";
$chk_res = mysqli_query($conn, $chk_sql);
$bill = mysqli_fetch_assoc($chk_res);

if (!$bill) {
    $_SESSION['error'] = "ไม่พบข้อมูลบิล";
    header("Location: sale_list.php");
    exit;
}

if ($bill['bill_status'] == 'Canceled') {
    $_SESSION['error'] = "บิลนี้ถูกยกเลิกไปแล้ว";
    header("Location: sale_list.php");
    exit;
}

// 3. ดำเนินการยกเลิก (Transaction)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cancel_reason = mysqli_real_escape_string($conn, trim($_POST['comment']));

    if (empty($cancel_reason)) {
        $error = "กรุณาระบุเหตุผลในการยกเลิก";
    } else {
        mysqli_autocommit($conn, false);
        try {
            // A. อัปเดตสถานะบิล
            $sql_update = "UPDATE bill_headers SET bill_status = 'Canceled', comment = CONCAT(IFNULL(comment, ''), ' [ยกเลิก: $cancel_reason]'), update_at = NOW() WHERE bill_id = ?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param('i', $bill_id);
            if (!$stmt->execute()) throw new Exception("อัปเดตสถานะบิลไม่สำเร็จ");
            $stmt->close();

            // B. ดึงรายการสินค้าในบิลนี้ เพื่อคืนสต็อก
            $sql_items = "SELECT prod_stocks_stock_id FROM bill_details WHERE bill_headers_bill_id = $bill_id";
            $res_items = mysqli_query($conn, $sql_items);

            while ($row = mysqli_fetch_assoc($res_items)) {
                $stock_id = $row['prod_stocks_stock_id'];

                // C. คืนสถานะสินค้า -> In Stock
                $sql_restock = "UPDATE prod_stocks SET stock_status = 'In Stock', update_at = NOW() WHERE stock_id = $stock_id";
                if (!mysqli_query($conn, $sql_restock)) throw new Exception("คืนสต็อกสินค้า ID $stock_id ไม่สำเร็จ");

                // D. บันทึก Movement (ADJUST) เป็นหลักฐานการคืน
                $move_id = getNextMovementId($conn);
                $sql_move = "INSERT INTO stock_movements (movement_id, movement_type, ref_table, ref_id, create_at, prod_stocks_stock_id) 
                             VALUES ($move_id, 'ADJUST', 'bill_headers (Cancel)', $bill_id, NOW(), $stock_id)";
                if (!mysqli_query($conn, $sql_move)) throw new Exception("บันทึก Movement ไม่สำเร็จ");
            }

            mysqli_commit($conn);
            $_SESSION['success'] = "ยกเลิกบิล #$bill_id และคืนสินค้าเข้าสต็อกเรียบร้อยแล้ว";
            header("Location: sale_list.php");
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ยกเลิกรายการขาย</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background: <?= $background_color ?>;
            font-family: '<?= $font_style ?>';
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: none;
        }
    </style>
</head>

<body>
    <div class="container mt-5" style="max-width: 600px;">

        <div class="card">
            <div class="card-header bg-danger text-white py-3">
                <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>ยืนยันการยกเลิกบิล #<?= $bill_id ?></h4>
            </div>
            <div class="card-body p-4">

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <div class="alert alert-warning">
                    <strong>คำเตือน:</strong> การดำเนินการนี้จะเปลี่ยนสถานะบิลเป็น "ยกเลิก" และคืนสินค้าทั้งหมดในบิลกลับเข้าสู่สต็อก "พร้อมขาย" ทันที
                </div>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label fw-bold">ระบุเหตุผลในการยกเลิก <span class="text-danger">*</span></label>
                        <textarea name="comment" class="form-control" rows="3" required placeholder="เช่น ลูกค้าเปลี่ยนใจ, คิดเงินผิด, สินค้าชำรุด..."></textarea>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="sale_list.php" class="btn btn-secondary"><i class="fas fa-times me-1"></i> กลับ</a>
                        <button type="submit" class="btn btn-danger px-4"><i class="fas fa-check me-1"></i> ยืนยันยกเลิกบิล</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</body>

</html>