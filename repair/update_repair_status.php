<?php
// ไฟล์: update_repair_status.php
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
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $sender_email;
        $mail->Password   = $sender_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Recipients
        $mail->setFrom($sender_email, $shop_name);
        $mail->addAddress($to_email, $customer_name);

        // Content
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
                <div style='text-align: center; font-size: 12px; color: #999; margin-top: 20px;'>
                    อีเมลฉบับนี้ส่งโดยระบบอัตโนมัติ กรุณาอย่าตอบกลับ
                </div>
            </div>
        </body>
        </html>";

        $mail->Body = $body;
        $mail->AltBody = "สถานะงานซ่อม #$repair_id เปลี่ยนเป็น: $new_status";
        $mail->send();
        return true;
    } catch (Exception $e) {
        // สามารถเปิด error_log($mail->ErrorInfo); เพื่อดู log ใน server ได้
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
$current_user_id = $_SESSION['user_id']; 

// ดึงข้อมูลงานซ่อม + สถานะบิล + สาขา (branches_branch_id)
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

// --- ตรวจสอบ Admin เพื่อกำหนดการดึงรายชื่อช่าง ---
$is_admin = false;
$chk_sql = "SELECT r.role_name FROM roles r 
            JOIN user_roles ur ON r.role_id = ur.roles_role_id 
            WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt = $conn->prepare($chk_sql)) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $is_admin = true;
    $stmt->close();
}

// กำหนดสาขาที่จะดึงพนักงาน
if ($is_admin) {
    $target_branch_id = $repair['branches_branch_id'];
} else {
    $target_branch_id = $_SESSION['branch_id'];
}

// ดึงรายชื่อช่าง
$emp_sql = "SELECT emp_id, firstname_th, lastname_th, emp_code 
            FROM employees 
            WHERE emp_status = 'Active' AND branches_branch_id = '$target_branch_id'";
$emp_result = mysqli_query($conn, $emp_sql);

// ตัวแปรสำหรับ SweetAlert
$alert_status = null; 
$alert_message = "";
$redirect_url = "view_repair.php?id=$repair_id";

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
    $error_occurred = false;
    if ($new_status === 'ส่งมอบ') {
        $is_cancelled = ($old_status === 'ยกเลิก');
        $is_paid = ($repair['bill_status'] === 'Completed');

        if (!$is_cancelled && !$is_paid) {
            $alert_status = 'error';
            $alert_message = "❌ ไม่สามารถส่งมอบได้: ลูกค้ายังไม่ได้ชำระเงิน";
            $redirect_url = ""; // ไม่ Redirect เพื่อให้แก้ข้อมูล
            $error_occurred = true;
        }
    }

    if (!$error_occurred) {
        mysqli_autocommit($conn, false);
        try {
            // 1. อัปเดตสถานะงานซ่อม
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

            // 2. บันทึก Log
            if ($new_status !== $old_status || !empty($comment)) {
                $log_sql = "INSERT INTO repair_status_log 
                            (repairs_repair_id, old_status, new_status, update_by_employee_id, comment, update_at) 
                            VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt_log = $conn->prepare($log_sql);
                $stmt_log->bind_param("issis", $repair_id, $old_status, $new_status, $current_emp_id, $comment);
                $stmt_log->execute();
                $stmt_log->close();
            }

            // 3. ตัดสต็อก เมื่อส่งมอบสำเร็จ 
            if ($new_status === 'ส่งมอบ') {
                $stock_id = $repair['prod_stocks_stock_id'];

                $conn->query("UPDATE prod_stocks SET stock_status = 'Sold', update_at = NOW() WHERE stock_id = $stock_id");

                $sql_move_id = "SELECT IFNULL(MAX(movement_id), 0) + 1 as next_id FROM stock_movements";
                $move_id = mysqli_fetch_assoc(mysqli_query($conn, $sql_move_id))['next_id'];

                $ref_remark = ($old_status === 'ยกเลิก') ? 'return_cancelled_device' : 'deliver_repaired_job';

                $move_sql = "INSERT INTO stock_movements (movement_id, movement_type, ref_table, ref_id, create_at, prod_stocks_stock_id) 
                             VALUES (?, 'OUT', ?, ?, NOW(), ?)";
                $stmt_move = $conn->prepare($move_sql);
                $stmt_move->bind_param("isii", $move_id, $ref_remark, $repair_id, $stock_id);
                $stmt_move->execute();
            }

            mysqli_commit($conn);

            // 4. ส่งอีเมล (ทำงานหลังจาก Commit สำเร็จ)
            $mail_msg = "";
            // ต้องเช็คด้วยว่ามีอีเมลลูกค้าไหม
            if ($new_status !== $old_status && !empty($repair['cs_email'])) {
                
                // [แก้ไข] ดึงข้อมูล Shop ตามสาขาที่งานซ่อมสังกัดอยู่ เพื่อให้ได้อีเมลผู้ส่งที่ถูกต้อง
                // โดยการ Join กับตาราง branches ด้วย branches_branch_id จากตัวแปร $repair
                $shop_query = "SELECT s.shop_name, s.shop_email, s.shop_app_password 
                               FROM shop_info s
                               JOIN branches b ON s.shop_id = b.shop_info_shop_id
                               WHERE b.branch_id = ?";
                
                $stmt_shop = $conn->prepare($shop_query);
                $stmt_shop->bind_param("i", $repair['branches_branch_id']);
                $stmt_shop->execute();
                $shop_res = $stmt_shop->get_result();
                $shop_data = $shop_res->fetch_assoc();
                $stmt_shop->close();
                
                if ($shop_data && !empty($shop_data['shop_email'])) {
                    $cust_name = $repair['cus_name'] . ' ' . $repair['cus_lastname'];
                    
                    // [แก้ไข] ลบเครื่องหมาย @ ออก และรับค่าผลลัพธ์มาเช็ค
                    $mail_sent = sendStatusUpdateEmail(
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

                    if ($mail_sent) {
                        $mail_msg = " และส่งอีเมลแจ้งลูกค้าเรียบร้อยแล้ว";
                    } else {
                        $mail_msg = " <br><span class='text-warning'>(แต่ส่งอีเมลไม่สำเร็จ กรุณาตรวจสอบการตั้งค่า Email)</span>";
                    }
                }
            }

            // ตั้งค่า SweetAlert Success
            $alert_status = 'success';
            $alert_message = "บันทึกสถานะเป็น '$new_status' สำเร็จ" . $mail_msg;

        } catch (Exception $e) {
            mysqli_rollback($conn);
            // ตั้งค่า SweetAlert Error
            $alert_status = 'error';
            $alert_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
            $redirect_url = "";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Job Order #<?= $repair_id ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <?php require '../config/load_theme.php'; ?>

    <style>
        :root {
            --theme-color: <?= $theme_color ?>;
            --bg-color: <?= $background_color ?>;
        }
        body {
            background-color: var(--bg-color);
            color: #333;
        }
        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            background: #fff;
        }
        .card-header-custom {
            background: linear-gradient(45deg, var(--theme-color), #146c43);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 15px;
            font-weight: 600;
        }
        /* Select2 Style Override */
        .select2-container .select2-selection--single {
            height: 48px;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: 46px;
            padding-left: 12px;
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
            height: 46px;
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
                                    <i class="fas fa-exclamation-circle me-2"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
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
                                                <select name="assigned_emp" class="form-select form-select-lg select2">
                                                    <option value="">-- ระบุช่าง --</option>
                                                    <?php while ($emp = mysqli_fetch_assoc($emp_result)): ?>
                                                        <option value="<?= $emp['emp_id'] ?>" <?= ($emp['emp_id'] == $repair['assigned_employee_id']) ? 'selected' : '' ?>>
                                                            <?= $emp['firstname_th'] ?> <?= $emp['lastname_th'] ?> (<?= $emp['emp_code'] ?>)
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
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: '-- ค้นหาช่าง --',
                allowClear: true
            });
        });

        // --------------------------------------------------------
        // ส่วนจัดการ SweetAlert หลังจาก PHP ทำงานเสร็จ
        // --------------------------------------------------------
        <?php if ($alert_status): ?>
            Swal.fire({
                icon: '<?= $alert_status ?>',
                title: '<?= $alert_status == 'success' ? 'สำเร็จ' : 'แจ้งเตือน' ?>',
                html: '<?= $alert_message ?>', // ใช้ html เพื่อรองรับ tag <br>
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#198754'
            }).then((result) => {
                <?php if (!empty($redirect_url)): ?>
                    if (result.isConfirmed || result.isDismissed) {
                        window.location.href = '<?= $redirect_url ?>';
                    }
                <?php endif; ?>
            });
        <?php endif; ?>

        // --------------------------------------------------------
        // ส่วนตรวจสอบก่อนกด Submit (Frontend)
        // --------------------------------------------------------
        const isPaid = <?= $repair['bill_status'] == 'Completed' ? 'true' : 'false' ?>;
        const isCancelled = <?= $repair['repair_status'] == 'ยกเลิก' ? 'true' : 'false' ?>;

        document.getElementById('updateStatusForm').addEventListener('submit', function(e) {
            e.preventDefault(); // หยุดการส่งฟอร์มไว้ก่อน

            const status = document.getElementById('statusSelect').value;

            if (status === 'ส่งมอบ') {
                // Validation: งานซ่อมปกติ แต่ยังไม่จ่ายเงิน -> ห้ามส่งมอบ
                if (!isCancelled && !isPaid) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ไม่สามารถส่งมอบได้',
                        html: 'ลูกค้ายังไม่ได้ชำระเงิน<br>(กรุณาไปทำรายการชำระเงินก่อนส่งมอบ)',
                        confirmButtonText: 'เข้าใจแล้ว'
                    });
                    return;
                }

                let confirmText = "- เครื่องซ่อมจะถูกตัดออกจากสต็อกทันที";
                if (isCancelled) {
                    confirmText += "<br>- เป็นการคืนเครื่องงานที่ 'ยกเลิก' (ไม่มีค่าใช้จ่าย)";
                } else {
                    confirmText += "<br>- ระบบจะปิดงานซ่อมอย่างสมบูรณ์";
                }

                // ใช้ SweetAlert Confirm แทน window.confirm
                Swal.fire({
                    title: 'ยืนยันการ "ส่งมอบ" คืนลูกค้า?',
                    html: confirmText,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'ยืนยันส่งมอบ',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.submit(); // ส่งฟอร์มจริง
                    }
                });
            } else {
                // กรณีสถานะอื่นๆ บันทึกได้เลย
                this.submit();
            }
        });
    </script>
</body>

</html>