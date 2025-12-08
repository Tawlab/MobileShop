<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'repair_list');

// ตรวจสอบ ID
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ไม่พบรหัสงานซ่อม";
    header('Location: repair_list.php');
    exit;
}

$repair_id = (int)$_GET['id'];
$emp_id = $_SESSION['emp_id'] ?? 1;

//  ดึงข้อมูล
$sql = "SELECT r.repair_id, r.repair_status, r.bill_headers_bill_id, r.prod_stocks_stock_id,
        c.firstname_th, c.lastname_th, p.prod_name
        FROM repairs r 
        LEFT JOIN customers c ON r.customers_cs_id = c.cs_id
        LEFT JOIN prod_stocks s ON r.prod_stocks_stock_id = s.stock_id
        LEFT JOIN products p ON s.products_prod_id = p.prod_id
        WHERE r.repair_id = $repair_id";
$res = mysqli_query($conn, $sql);
$repair = mysqli_fetch_assoc($res);

if (!$repair) {
    $_SESSION['error'] = "ไม่พบข้อมูลงานซ่อม";
    header('Location: repair_list.php');
    exit;
}

if ($repair['repair_status'] == 'ส่งมอบ') {
    $_SESSION['error'] = "งานซ่อมนี้ส่งมอบไปแล้ว ไม่สามารถยกเลิกได้";
    header('Location: repair_list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cancel_reason = mysqli_real_escape_string($conn, trim($_POST['cancel_reason']));

    if (empty($cancel_reason)) {
        $error_msg = "กรุณาระบุสาเหตุการยกเลิก";
    } else {
        mysqli_autocommit($conn, false);
        try {
            // อัปเดตสถานะงานซ่อม -> 'ยกเลิก'
            $conn->query("UPDATE repairs SET repair_status = 'ยกเลิก', update_at = NOW() WHERE repair_id = $repair_id");

            // บันทึก Log
            $old_status = $repair['repair_status'];
            $log_comment = "ยกเลิกงานซ่อม: " . $cancel_reason;
            $sql_log = "INSERT INTO repair_status_log (repairs_repair_id, old_status, new_status, update_by_employee_id, comment, update_at) 
                        VALUES ($repair_id, '$old_status', 'ยกเลิก', $emp_id, '$log_comment', NOW())";
            $conn->query($sql_log);

            // จัดการบิล (ถ้ามี) -> เปลี่ยนเป็น Canceled และคืนอะไหล่
            if (!empty($repair['bill_headers_bill_id'])) {
                $bill_id = $repair['bill_headers_bill_id'];
                
                $conn->query("UPDATE bill_headers SET bill_status = 'Canceled', comment = CONCAT(IFNULL(comment,''), ' [ยกเลิก: $cancel_reason]'), update_at = NOW() WHERE bill_id = $bill_id");

                // คืนสต็อกอะไหล่ (เฉพาะอะไหล่ที่เบิกไปซ่อม)
                $sql_details = "SELECT prod_stocks_stock_id FROM bill_details WHERE bill_headers_bill_id = $bill_id AND prod_stocks_stock_id IS NOT NULL";
                $res_details = mysqli_query($conn, $sql_details);
                
                while ($row = mysqli_fetch_assoc($res_details)) {
                    $stock_id = $row['prod_stocks_stock_id'];
                    // คืนสถานะเป็น In Stock
                    $conn->query("UPDATE prod_stocks SET stock_status = 'In Stock' WHERE stock_id = $stock_id");
                    
                    // บันทึก Movement ADJUST
                    $sql_move = "SELECT IFNULL(MAX(movement_id), 0) + 1 as next_id FROM stock_movements";
                    $move_id = mysqli_fetch_assoc(mysqli_query($conn, $sql_move))['next_id'];
                    $conn->query("INSERT INTO stock_movements (movement_id, movement_type, ref_table, ref_id, create_at, prod_stocks_stock_id) 
                                  VALUES ($move_id, 'ADJUST', 'cancel_repair_bill', $bill_id, NOW(), $stock_id)");
                }
            }

            mysqli_commit($conn);
            $_SESSION['success'] = "ยกเลิกงานซ่อม #$repair_id เรียบร้อยแล้ว (สินค้ารอการส่งมอบคืน)";
            header('Location: repair_list.php');
            exit;

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ยืนยันการยกเลิกงานซ่อม</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body { background-color: <?= $background_color ?>; font-family: '<?= $font_style ?>'; }
        .card-custom { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card card-custom">
                    <div class="card-header bg-danger text-white py-3 rounded-top-4">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> ยืนยันการยกเลิกงานซ่อม</h5>
                    </div>
                    <div class="card-body p-4">
                        
                        <?php if (isset($error_msg)): ?>
                            <div class="alert alert-danger"><?= $error_msg ?></div>
                        <?php endif; ?>

                        <div class="alert alert-light border mb-3">
                            <strong>Job ID:</strong> #<?= $repair['repair_id'] ?><br>
                            <strong>สถานะปัจจุบัน:</strong> <?= $repair['repair_status'] ?><br>
                            <strong>ลูกค้า:</strong> <?= $repair['firstname_th'] . ' ' . $repair['lastname_th'] ?><br>
                            <strong>อุปกรณ์:</strong> <?= $repair['prod_name'] ?>
                        </div>

                        <div class="alert alert-warning">
                            <small>
                                <i class="fas fa-info-circle"></i> การยกเลิกจะเปลี่ยนสถานะงานเป็น 'ยกเลิก' และคืนอะไหล่ที่เบิกไป <br>
                                <strong>*สินค้าจะยังคงอยู่ในระบบ เพื่อรอลูกค้ามารับคืน*</strong>
                            </small>
                        </div>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="cancel_reason" class="form-label fw-bold">ระบุสาเหตุการยกเลิก <span class="text-danger">*</span></label>
                                <textarea name="cancel_reason" id="cancel_reason" class="form-control" rows="3" required placeholder="เช่น ลูกค้าไม่ซ่อมเนื่องจากราคาแพง, ซ่อมไม่ได้..."></textarea>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <a href="repair_list.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i> ย้อนกลับ
                                </a>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-check me-1"></i> ยืนยันการยกเลิก
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>