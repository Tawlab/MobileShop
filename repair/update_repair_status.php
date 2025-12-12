<?php
session_start();
require '../config/config.php';
require '../vendor/autoload.php'; 
checkPageAccess($conn, 'update_repair_status');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ----------------------------------------------------------------------------
// ฟังก์ชันส่งอีเมลแจ้งเตือนลูกค้า
// ----------------------------------------------------------------------------
function sendStatusUpdateEmail($to_email, $customer_name, $repair_id, $device_name, $new_status, $comment, $shop_name, $sender_email, $sender_password)
{
    $mail = new PHPMailer(true);
    $status_colors = [
        'รับเครื่อง' => '#6c757d',
        'ประเมิน' => '#17a2b8',
        'รออะไหล่' => '#ffc107',
        'กำลังซ่อม' => '#007bff',
        'ซ่อมเสร็จ' => '#28a745',
        'ส่งมอบ' => '#343a40',
        'ยกเลิก' => '#dc3545'
    ];
    $color = $status_colors[$new_status] ?? '#000';

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $sender_email;
        $mail->Password   = $sender_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($sender_email, $shop_name);
        $mail->addAddress($to_email, $customer_name);

        $mail->isHTML(true);
        $mail->Subject = "อัปเดตสถานะงานซ่อม / Repair Status Update (JOB #$repair_id)";

        $body = "
        <html>
        <body style='font-family: Sarabun, Arial, sans-serif; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px;'>
                <div style='border-bottom: 2px solid $color; padding-bottom: 10px; text-align: center;'>
                    <h2 style='margin: 0; color: $color;'>$shop_name</h2>
                    <p style='margin: 5px 0 0;'>แจ้งอัปเดตสถานะงานซ่อม</p>
                </div>
                <div style='padding: 20px 0;'>
                    <p>เรียนคุณ <strong>$customer_name</strong>,</p>
                    <p>งานซ่อมหมายเลข <strong>#$repair_id</strong> ($device_name) ของท่าน มีการอัปเดตสถานะดังนี้:</p>
                    
                    <div style='background-color: #f8f9fa; padding: 15px; border-left: 5px solid $color; margin: 20px 0;'>
                        <p style='margin: 0; font-size: 0.9em; color: #666;'>สถานะล่าสุด:</p>
                        <h2 style='margin: 5px 0; color: $color;'>$new_status</h2>
                        " . (!empty($comment) ? "<p style='margin-top: 10px;'><strong>หมายเหตุ:</strong> $comment</p>" : "") . "
                    </div>
                    
                    <p>ขอบคุณที่ใช้บริการครับ</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->Body = $body;
        $mail->AltBody = "สถานะงานซ่อม #$repair_id เปลี่ยนเป็น: $new_status";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ----------------------------------------------------------------------------
// MAIN LOGIC
// ----------------------------------------------------------------------------

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ไม่พบรหัสงานซ่อม";
    header('Location: repair_list.php');
    exit;
}

$repair_id = (int)$_GET['id'];
$current_emp_id = $_SESSION['emp_id'] ?? 1;

// ดึงข้อมูลงานซ่อม + สถานะบิล
$sql = "SELECT r.*, 
        c.firstname_th AS cus_name, c.lastname_th AS cus_lastname, c.cs_email,
        p.prod_name, p.model_name, b.brand_name_th, s.serial_no,
        bh.bill_status, bh.bill_id
        FROM repairs r
        LEFT JOIN customers c ON r.customers_cs_id = c.cs_id
        LEFT JOIN prod_stocks s ON r.prod_stocks_stock_id = s.stock_id
        LEFT JOIN products p ON s.products_prod_id = p.prod_id
        LEFT JOIN prod_brands b ON p.prod_brands_brand_id = b.brand_id
        LEFT JOIN bill_headers bh ON r.bill_headers_bill_id = bh.bill_id
        WHERE r.repair_id = $repair_id";
$result = mysqli_query($conn, $sql);
$repair = mysqli_fetch_assoc($result);

if (!$repair) {
    $_SESSION['error'] = "ไม่พบข้อมูลงานซ่อม";
    header('Location: repair_list.php');
    exit;
}

// ดึงรายชื่อช่าง
$emp_sql = "SELECT emp_id, firstname_th, lastname_th, emp_code FROM employees WHERE emp_status = 'Active'";
$emp_result = mysqli_query($conn, $emp_sql);

// ============================================================================
// HANDLE POST REQUEST
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = $_POST['status'];
    $assigned_emp = (int)$_POST['assigned_emp'];
    $cost = (float)$_POST['estimated_cost'];
    $comment = mysqli_real_escape_string($conn, trim($_POST['comment']));
    $old_status = $repair['repair_status'];

    // ตรวจสอบก่อนส่งมอบ
    if ($new_status === 'ส่งมอบ') {
        // ถ้าสถานะเดิมคือ 'ยกเลิก' อนุญาตให้ส่งมอบคืนได้เลย
        $is_cancelled = ($old_status === 'ยกเลิก');

        // ถ้างานปกติ (ไม่ยกเลิก) ต้องเช็คว่าจ่ายเงินหรือยัง
        $is_paid = ($repair['bill_status'] === 'Completed');

        if (!$is_cancelled && !$is_paid) {
            $_SESSION['error'] = "❌ ไม่สามารถส่งมอบได้: ลูกค้ายังไม่ได้ชำระเงิน";
            header("Location: update_repair_status.php?id=$repair_id");
            exit;
        }
    }

    mysqli_autocommit($conn, false);
    try {
        //  อัปเดตสถานะงานซ่อม
        $sql_update = "UPDATE repairs SET 
                       repair_status = ?, 
                       assigned_employee_id = ?, 
                       estimated_cost = ?, 
                       update_at = NOW() 
                       WHERE repair_id = ?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("sidi", $new_status, $assigned_emp, $cost, $repair_id);
        if (!$stmt->execute()) throw new Exception("อัปเดตสถานะไม่สำเร็จ");
        $stmt->close();

        //บันทึก Log
        if ($new_status !== $old_status || !empty($comment)) {
            $log_sql = "INSERT INTO repair_status_log 
                        (repairs_repair_id, old_status, new_status, update_by_employee_id, comment, update_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt_log = $conn->prepare($log_sql);
            $stmt_log->bind_param("issis", $repair_id, $old_status, $new_status, $current_emp_id, $comment);
            $stmt_log->execute();
            $stmt_log->close();
        }

        // ตัดสต็อก เมื่อส่งมอบสำเร็จ 
        if ($new_status === 'ส่งมอบ') {
            $stock_id = $repair['prod_stocks_stock_id'];

            // เปลี่ยนสถานะสต็อกเป็น 'Sold' 
            $conn->query("UPDATE prod_stocks SET stock_status = 'Sold', update_at = NOW() WHERE stock_id = $stock_id");

            //  บันทึก Movement (OUT)
            $sql_move_id = "SELECT IFNULL(MAX(movement_id), 0) + 1 as next_id FROM stock_movements";
            $move_id = mysqli_fetch_assoc(mysqli_query($conn, $sql_move_id))['next_id'];

            // ระบุเหตุผลใน Log ว่าเป็นการคืนเครื่องแบบไหน
            $ref_remark = ($old_status === 'ยกเลิก') ? 'return_cancelled_device' : 'deliver_repaired_job';

            $move_sql = "INSERT INTO stock_movements (movement_id, movement_type, ref_table, ref_id, create_at, prod_stocks_stock_id) 
                         VALUES (?, 'OUT', ?, ?, NOW(), ?)";
            $stmt_move = $conn->prepare($move_sql);
            $stmt_move->bind_param("isii", $move_id, $ref_remark, $repair_id, $stock_id);
            $stmt_move->execute();
        }

        mysqli_commit($conn);

        //  ส่งอีเมล 
        if ($new_status !== $old_status && !empty($repair['cs_email'])) {
            $shop_res = mysqli_query($conn, "SELECT shop_name, shop_email, shop_app_password FROM shop_info LIMIT 1");
            $shop_data = mysqli_fetch_assoc($shop_res);
            if ($shop_data && !empty($shop_data['shop_email'])) {
                $cust_name = $repair['cus_name'] . ' ' . $repair['cus_lastname'];
                @sendStatusUpdateEmail(
                    $repair['cs_email'],
                    $cust_name,
                    $repair_id,
                    $repair['prod_name'],
                    $new_status,
                    $comment,
                    $shop_data['shop_name'],
                    $shop_data['shop_email'],
                    $shop_data['shop_app_password']
                );
            }
        }

        $_SESSION['success'] = "✅ อัปเดตสถานะเป็น '$new_status' เรียบร้อยแล้ว";
        header("Location: view_repair.php?id=$repair_id");
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Job Order #<?= $repair_id ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>

    <style>
        :root {
            --theme-color: <?= $theme_color ?>;
            --bg-color: <?= $background_color ?>;
        }
        
        /* **[เพิ่ม]** CSS ทั่วไปเพื่อป้องกันการล้นจอ */
        *, *::before, *::after {
            box-sizing: border-box; 
        }

        body {
            background-color: var(--bg-color);
            color: #333;
            margin: 0; 
            overflow-x: hidden; 
        }

        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            background: #fff;
            transition: transform 0.2s;
        }

        .card-header-custom {
            background: linear-gradient(45deg, var(--theme-color), #146c43);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 15px;
            font-weight: 600;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 0.9rem;
        }

        .info-value {
            font-weight: 500;
            color: #000;
            font-size: 1rem;
        }

        .timeline {
            border-left: 2px solid #e9ecef;
            padding-left: 20px;
            margin-left: 10px;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 25px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--theme-color);
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #e9ecef;
        }

        /* -------------------------------------------------------------------- */
        /* --- **[เพิ่ม]** Responsive Override สำหรับ Mobile (จอเล็กกว่า 768px) --- */
        /* -------------------------------------------------------------------- */
        @media (max-width: 767.98px) {
            .container {
                max-width: 100%;
                padding: 0 15px !important;
            }

            .card-custom {
                border-radius: 0; /* ทำให้เต็มขอบจอ */
                box-shadow: none;
                margin-top: 10px;
                margin-bottom: 10px;
            }

            .card-body {
                padding: 15px; /* ลด Padding ใน Body Card */
            }
            
            /* 2. จัดการ Header/Title */
            .card-header-custom {
                font-size: 1rem;
                padding: 10px 15px;
            }

            /* 3. จัดการ Info Row */
            .row > div[class*='col-'] {
                margin-bottom: 5px; 
            }
            
            /* 4. จัดการ Info Label/Value */
            .info-label {
                font-size: 0.8rem;
            }

            .info-value {
                font-size: 0.9rem;
            }
            
            /* 5. จัดการ Timeline */
            .timeline {
                 margin-left: 0; /* ลบ margin เพื่อให้ Timeline ชิดซ้ายมากขึ้น */
                 padding-left: 15px;
            }
            
            .timeline-item {
                 margin-bottom: 15px; /* ลดระยะห่าง Timeline Item */
            }
            
            .timeline-item::before {
                 left: -17px; /* ปรับตำแหน่งวงกลม */
            }
            
            /* 6. ทำให้ปุ่มหลัก (ถ้ามี) เรียงเป็นแนวตั้ง */
            .d-flex.justify-content-end.no-print {
                flex-direction: column;
                gap: 10px;
                margin-top: 10px !important;
            }
            
            .d-flex.justify-content-end.no-print .btn {
                 width: 100%;
            }
        }


        /* --- PRINT CSS (A4 Standard) --- */
        @media print {
            /* ... โค้ด Print เดิม ... */
            @page {
                size: A4;
                margin: 10mm;
            }

            body {
                background: #fff;
                font-size: 12pt;
                line-height: 1.3;
                color: #000;
            }

            .no-print,
            .btn,
            .navbar,
            .card-header-custom,
            .timeline {
                display: none !important;
            }

            .container {
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100%;
            }

            .card-custom {
                box-shadow: none;
                border: 1px solid #000 !important;
                border-radius: 0;
                margin-bottom: 15px;
            }

            .card-body {
                padding: 10px !important;
            }

            .print-header {
                display: flex;
                justify-content: space-between;
                border-bottom: 2px solid #000;
                padding-bottom: 10px;
                margin-bottom: 15px;
            }

            .shop-info h3 {
                font-size: 18pt;
                font-weight: bold;
                margin: 0;
            }

            .job-title {
                font-size: 16pt;
                font-weight: bold;
                text-transform: uppercase;
                background: #eee;
                padding: 5px 10px;
                border: 1px solid #000;
                display: inline-block;
            }

            .print-row {
                display: flex;
                flex-wrap: wrap;
                margin: 0 -5px;
            }

            .print-col-6 {
                width: 50%;
                padding: 0 5px;
                box-sizing: border-box;
            }

            .print-col-12 {
                width: 100%;
                padding: 0 5px;
                box-sizing: border-box;
            }

            .section-box {
                border: 1px solid #000;
                padding: 10px;
                margin-bottom: 10px;
            }

            .section-title {
                font-weight: bold;
                border-bottom: 1px solid #ccc;
                padding-bottom: 5px;
                margin-bottom: 5px;
                font-size: 12pt;
            }

            .terms-box {
                font-size: 9pt;
                color: #444;
                margin-top: 15px;
                border: 1px dotted #999;
                padding: 8px;
                text-align: justify;
            }

            .signature-area {
                display: flex;
                justify-content: space-between;
                margin-top: 40px;
                margin-bottom: 20px;
            }

            .sign-box {
                width: 45%;
                text-align: center;
                border-top: 1px solid #000;
                padding-top: 5px;
            }

            .print-footer {
                border-top: 1px dashed #000;
                padding-top: 10px;
                margin-top: 10px;
                font-size: 10pt;
                text-align: center;
                font-style: italic;
            }
        }

        .print-only {
            display: none;
        }

        @media print {
            .print-only {
                display: block;
            }
        }
    </style>
</head>


<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container py-5">
                    <div class="row justify-content-center">
                        <div class="col-lg-8">

                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <i class="fas fa-exclamation-circle me-2"></i> <?= $_SESSION['error'];
                                                                                    unset($_SESSION['error']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <div class="card card-custom">
                                <div class="card-header bg-white border-0 pt-4 px-4">
                                    <h4 class="mb-0 fw-bold text-primary"><i class="fas fa-tasks me-2"></i>อัปเดตสถานะงานซ่อม #<?= $repair_id ?></h4>
                                    <hr>
                                </div>
                                <div class="card-body px-4 pb-4">

                                    <div class="alert alert-<?= $repair['bill_status'] == 'Completed' ? 'success' : 'warning' ?> d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-file-invoice-dollar me-2"></i>
                                            <strong>สถานะการชำระเงิน:</strong> <?= $repair['bill_status'] ?>
                                        </div>
                                        <?php if ($repair['bill_status'] != 'Completed'): ?>
                                            <?php if ($repair['repair_status'] != 'ยกเลิก'): ?>
                                                <a href="bill_repair.php?id=<?= $repair_id ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-hand-holding-usd"></i> ไปจัดการบิล
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">ยกเลิกงาน (ไม่ต้องชำระเงิน)</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-success"><i class="fas fa-check-circle"></i> ชำระครบแล้ว</span>
                                        <?php endif; ?>
                                    </div>

                                    <form method="POST" id="updateStatusForm">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">สถานะใหม่ <span class="text-danger">*</span></label>
                                                <select name="status" id="statusSelect" class="form-select form-select-lg" required>
                                                    <?php
                                                    if ($repair['repair_status'] == 'ยกเลิก') {
                                                        echo "<option value='ยกเลิก' selected>ยกเลิก (ปัจจุบัน)</option>";
                                                        echo "<option value='ส่งมอบ'>ส่งมอบ (คืนเครื่องลูกค้า)</option>";
                                                    } else {
                                                        $statuses = ['รับเครื่อง', 'ประเมิน', 'รออะไหล่', 'กำลังซ่อม', 'ซ่อมเสร็จ', 'ส่งมอบ', 'ยกเลิก'];
                                                        foreach ($statuses as $st) {
                                                            $selected = ($st == $repair['repair_status']) ? 'selected' : '';
                                                            echo "<option value='$st' $selected>$st</option>";
                                                        }
                                                    }
                                                    ?>
                                                </select>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">ช่างผู้รับผิดชอบ</label>
                                                <select name="assigned_emp" class="form-select form-select-lg">
                                                    <option value="">-- ระบุช่าง --</option>
                                                    <?php while ($emp = mysqli_fetch_assoc($emp_result)): ?>
                                                        <option value="<?= $emp['emp_id'] ?>" <?= ($emp['emp_id'] == $repair['assigned_employee_id']) ? 'selected' : '' ?>>
                                                            <?= $emp['firstname_th'] ?> <?= $emp['lastname_th'] ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>

                                            <div class="col-md-12">
                                                <label class="form-label fw-bold">ค่าซ่อมประเมิน (บาท)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">฿</span>
                                                    <input type="number" name="estimated_cost" class="form-control" value="<?= $repair['estimated_cost'] ?>" step="0.01" min="0">
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <label class="form-label fw-bold">หมายเหตุ / รายละเอียด</label>
                                                <textarea name="comment" class="form-control" rows="3" placeholder="ระบุรายละเอียดการซ่อม หรือหมายเหตุเพิ่มเติม..."></textarea>
                                            </div>
                                        </div>

                                        <hr class="my-4">

                                        <div class="d-flex justify-content-between">
                                            <a href="<?= (isset($_GET['return_to']) && $_GET['return_to'] == 'list') ? 'repair_list.php' : 'view_repair.php?id=' . $repair_id ?>" class="btn btn-secondary">
                                                <i class="fas fa-times me-2"></i> ยกเลิก
                                            </a>
                                            <button type="submit" class="btn btn-success px-5">
                                                <i class="fas fa-save me-2"></i> บันทึก
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const isPaid = <?= $repair['bill_status'] == 'Completed' ? 'true' : 'false' ?>;
        const isCancelled = <?= $repair['repair_status'] == 'ยกเลิก' ? 'true' : 'false' ?>;

        document.getElementById('updateStatusForm').addEventListener('submit', function(e) {
            const status = document.getElementById('statusSelect').value;

            if (status === 'ส่งมอบ') {
                // ถ้างานซ่อมปกติ แต่ยังไม่จ่ายเงิน -> ห้ามส่งมอบ
                if (!isCancelled && !isPaid) {
                    alert('⚠️ ไม่สามารถส่งมอบเครื่องได้!\n\nลูกค้ายังไม่ได้ชำระเงิน\n(กรุณาไปทำรายการชำระเงินก่อนส่งมอบ)');
                    e.preventDefault();
                    return;
                }

                let confirmMsg = "⚠️ ยืนยันการ 'ส่งมอบ' เครื่องคืนลูกค้า?\n\n" +
                    "- เครื่องซ่อมจะถูกตัดออกจากสต็อกทันที\n";

                if (isCancelled) {
                    confirmMsg += "- เป็นการคืนเครื่องงานที่ 'ยกเลิก' (ไม่มีค่าใช้จ่าย)";
                } else {
                    confirmMsg += "- ระบบจะปิดงานซ่อมอย่างสมบูรณ์";
                }

                if (!confirm(confirmMsg)) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>

</html>