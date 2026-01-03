<?php
session_start();

// 1. เรียกใช้ Config และ Autoload
require '../config/config.php';
require '../vendor/autoload.php';
checkPageAccess($conn, 'add_repair');

// รับค่า Branch ID และ Shop ID ปัจจุบันของผู้ใช้จาก Session
$current_branch_id = $_SESSION['branch_id'];
$current_shop_id = $_SESSION['shop_id'];
$current_user_id = $_SESSION['user_id'];

// ตรวจสอบว่าเป็น Admin หรือไม่
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

// ถ้าเป็น Admin ให้ดึงรายชื่อร้านค้าและสาขาทั้งหมดมาเตรียมไว้สำหรับ Dropdown
$shops_list = [];
$branches_list = [];
if ($is_admin) {
    // ดึงร้านค้า
    $shop_res = $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name");
    while ($row = $shop_res->fetch_assoc()) {
        $shops_list[] = $row;
    }
    // ดึงสาขา
    $branch_res = $conn->query("SELECT branch_id, branch_name, shop_info_shop_id FROM branches ORDER BY branch_name");
    while ($row = $branch_res->fetch_assoc()) {
        $branches_list[] = $row;
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// -----------------------------------------------------------------------------
//  SHARED FUNCTIONS
// -----------------------------------------------------------------------------

function getNextRepairId($conn)
{
    $sql = "SELECT IFNULL(MAX(repair_id), 100000) + 1 as next_id FROM repairs";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result)['next_id'];
}

function getNextStockId($conn)
{
    $sql = "SELECT IFNULL(MAX(stock_id), 100000) + 1 as next_id FROM prod_stocks";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result)['next_id'];
}

function getNextMovementId($conn)
{
    $sql = "SELECT IFNULL(MAX(movement_id), 0) + 1 as next_id FROM stock_movements";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result)['next_id'];
}

// [เพิ่ม] ฟังก์ชันหาเลขบิลถัดไป (สำหรับแก้ปัญหา Insert ID)
function getNextBillId($conn)
{
    $sql = "SELECT IFNULL(MAX(bill_id), 0) + 1 as next_id FROM bill_headers";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result)['next_id'];
}

function getStockIdBySerial($conn, $serial)
{
    $sql = "SELECT stock_id, stock_status FROM prod_stocks WHERE serial_no = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $serial);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// --- ฟังก์ชันส่งอีเมล ---
function sendJobOrderEmail($to_email, $customer_name, $repair_id, $device_name, $serial_no, $symptoms_txt, $shop_name, $sender_email, $sender_password)
{
    $mail = new PHPMailer(true);
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
        $mail->Subject = "ใบรับซ่อม / Job Order Received (JOB #$repair_id)";

        $bodyContent = "
        <html>
        <head>
            <style>
                body { font-family: 'Sarabun', Arial, sans-serif; color: #333; }
                .container { width: 100%; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; }
                .header { background-color: #f8f9fa; padding: 10px; text-align: center; border-bottom: 3px solid #198754; }
                .content { padding: 20px 0; line-height: 1.6; }
                .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .table td { padding: 8px; border-bottom: 1px solid #eee; vertical-align: top; }
                .footer { font-size: 12px; color: #777; text-align: center; margin-top: 30px; border-top: 1px dashed #ccc; padding-top: 10px; }
                .badge { background-color: #0d6efd; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.9em; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2 style='margin:0; color:#198754;'>$shop_name</h2>
                    <p style='margin:5px 0 0;'>ใบรับซ่อม / JOB ORDER</p>
                </div>
                <div class='content'>
                    <p>เรียนคุณ <strong>$customer_name</strong>,</p>
                    <p>ทางร้านได้รับเครื่องของท่านเข้าระบบเรียบร้อยแล้ว รายละเอียดดังนี้:</p>
                    <table class='table'>
                        <tr><td width='35%'><strong>เลขที่ใบงาน:</strong></td><td><strong style='font-size:1.1em;'>#$repair_id</strong></td></tr>
                        <tr><td><strong>วันที่รับเครื่อง:</strong></td><td>" . date("d/m/Y H:i") . "</td></tr>
                        <tr><td><strong>อุปกรณ์:</strong></td><td>$device_name</td></tr>
                        <tr><td><strong>Serial/IMEI:</strong></td><td>$serial_no</td></tr>
                        <tr><td><strong>อาการเสีย:</strong></td><td>$symptoms_txt</td></tr>
                        <tr><td><strong>สถานะ:</strong></td><td><span class='badge'>รับเครื่อง (Received)</span></td></tr>
                    </table>
                    <div style='margin-top: 25px; background-color: #e9ecef; padding: 15px; border-radius: 5px;'>
                        ท่านสามารถนำเลขที่ใบงาน <strong>#$repair_id</strong> มาติดต่อสอบถามสถานะการซ่อมได้ที่ร้าน
                    </div>
                </div>
                <div class='footer'>
                    ขอบคุณที่ใช้บริการ <strong>$shop_name</strong><br>
                    (อีเมลฉบับนี้ส่งจากระบบอัตโนมัติ กรุณาอย่าตอบกลับ)
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->Body = $bodyContent;
        $mail->AltBody = "ได้รับเครื่อง $device_name (Job #$repair_id) เรียบร้อยแล้ว";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// -----------------------------------------------------------------------------
// GET DATA
// -----------------------------------------------------------------------------

$symptoms_result = mysqli_query($conn, "SELECT symptom_id, symptom_name FROM symptoms ORDER BY symptom_name");

$products_result = mysqli_query($conn, "SELECT p.prod_id, p.prod_name, p.model_name, pb.brand_name_th 
                                        FROM products p 
                                        LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id 
                                        WHERE p.prod_types_type_id NOT IN (3, 4) AND p.shop_info_shop_id = '$current_shop_id'
                                        ORDER BY p.prod_name");
mysqli_data_seek($products_result, 0);

// -----------------------------------------------------------------------------
//  AJAX HANDLER
// -----------------------------------------------------------------------------
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false];
    switch ($_POST['action']) {
        case 'search_customer':
            $search = mysqli_real_escape_string($conn, $_POST['query']);
            $target_shop_id = isset($_POST['shop_id']) && !empty($_POST['shop_id']) ? (int)$_POST['shop_id'] : $current_shop_id;
            
            $sql = "SELECT cs_id, firstname_th, lastname_th, cs_phone_no, cs_email 
                    FROM customers 
                    WHERE (cs_phone_no LIKE '%$search%' OR firstname_th LIKE '%$search%' OR lastname_th LIKE '%$search%') 
                    AND shop_info_shop_id = '$target_shop_id' 
                    LIMIT 10";
            $result = mysqli_query($conn, $sql);
            $customers = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $customers[] = $row;
            }
            $response = ['success' => true, 'customers' => $customers];
            break;
        case 'search_employee':
            $search = mysqli_real_escape_string($conn, $_POST['query']);
            $target_shop_id = isset($_POST['shop_id']) && !empty($_POST['shop_id']) ? (int)$_POST['shop_id'] : $current_shop_id;

            $sql = "SELECT e.emp_id, e.firstname_th, e.lastname_th, e.emp_code 
                    FROM employees e
                    LEFT JOIN branches b ON e.branches_branch_id = b.branch_id
                    WHERE e.emp_status = 'Active' 
                    AND b.shop_info_shop_id = '$target_shop_id'
                    AND (e.firstname_th LIKE '%$search%' OR e.lastname_th LIKE '%$search%' OR e.emp_code LIKE '%$search%') 
                    LIMIT 10";
            $result = mysqli_query($conn, $sql);
            $employees = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $employees[] = $row;
            }
            $response = ['success' => true, 'employees' => $employees];
            break;
        case 'check_serial':
            $serial = mysqli_real_escape_string($conn, $_POST['serial_no']);
            $stock_info = getStockIdBySerial($conn, $serial);
            if ($stock_info) {
                $response['exists'] = true;
                $response['stock_id'] = $stock_info['stock_id'];
                $response['status'] = $stock_info['stock_status'];
            } else {
                $response['exists'] = false;
            }
            $response['success'] = true;
            break;
    }
    echo json_encode($response);
    exit;
}

// -----------------------------------------------------------------------------
// บันทึก (SAVE) - แก้ไขให้บันทึกสาขาที่เลือกได้ถูกต้อง
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ตรวจสอบและรับค่า Shop/Branch
    if ($is_admin) {
        // Admin: รับค่าจาก Dropdown
        $target_shop_id = isset($_POST['selected_shop_id']) ? (int)$_POST['selected_shop_id'] : 0;
        $target_branch_id = isset($_POST['selected_branch_id']) ? (int)$_POST['selected_branch_id'] : 0;
        
        // ตรวจสอบความถูกต้องเบื้องต้น (กันค่าเป็น 0)
        if ($target_shop_id == 0 || $target_branch_id == 0) {
             $_SESSION['error'] = 'กรุณาเลือกร้านค้าและสาขาให้ครบถ้วน';
             header('Location: add_repair.php');
             exit;
        }
    } else {
        // User: ใช้ค่าจาก Session
        $target_shop_id = $current_shop_id;
        $target_branch_id = $current_branch_id;
    }
    
    // รับค่าอื่นๆ
    $customer_id = (int)$_POST['customer_id'];
    $employee_id = (int)$_POST['employee_id'];
    $assigned_employee_id = (int)$_POST['assigned_employee_id'];
    $serial_no = mysqli_real_escape_string($conn, trim($_POST['serial_no']));
    $repair_desc = mysqli_real_escape_string($conn, trim($_POST['repair_desc']));
    $symptom_ids = isset($_POST['symptoms']) ? array_unique($_POST['symptoms']) : [];
    $is_new_device = (int)$_POST['is_new_device'];
    $new_product_id = (int)$_POST['new_product_id'];
    $device_description = mysqli_real_escape_string($conn, trim($_POST['device_description']));
    $estimated_cost = floatval($_POST['estimated_cost']);
    $accessories_list = mysqli_real_escape_string($conn, trim($_POST['accessories_list']));

    if ($customer_id <= 0 || $employee_id <= 0 || $assigned_employee_id <= 0 || empty($serial_no) || empty($symptom_ids)) {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลสำคัญให้ครบถ้วน';
        header('Location: add_repair.php');
        exit;
    }

    mysqli_autocommit($conn, false);

    try {
        // [1] จัดการ Stock (ใช้ $target_branch_id)
        $stock_info = getStockIdBySerial($conn, $serial_no);
        $stock_id = 0;
        $device_name_for_mail = "";

        if ($stock_info) {
            $stock_id = $stock_info['stock_id'];
            $conn->query("UPDATE prod_stocks SET stock_status = 'Repair' WHERE stock_id = $stock_id");
            $res_prod = $conn->query("SELECT p.prod_name FROM prod_stocks s JOIN products p ON s.products_prod_id = p.prod_id WHERE s.stock_id = $stock_id");
            $device_name_for_mail = $res_prod->fetch_assoc()['prod_name'];
        } else {
            if ($is_new_device != 1 || $new_product_id <= 0) throw new Exception('กรุณาเลือกรุ่นสินค้าสำหรับเครื่องใหม่');
            $stock_id = getNextStockId($conn);
            $stmt = $conn->prepare("INSERT INTO prod_stocks (stock_id, serial_no, price, stock_status, create_at, update_at, products_prod_id, branches_branch_id) VALUES (?, ?, 0.00, 'Repair', NOW(), NOW(), ?, ?)");
            // สังเกตตัวแปรตัวสุดท้าย ต้องเป็น $target_branch_id
            $stmt->bind_param("isii", $stock_id, $serial_no, $new_product_id, $target_branch_id);
            $stmt->execute();
            $stmt->close();
            $res_prod = $conn->query("SELECT prod_name FROM products WHERE prod_id = $new_product_id");
            $device_name_for_mail = $res_prod->fetch_assoc()['prod_name'];
        }

        // [2] สร้างบิลซ่อม (ใช้ $target_branch_id)
        $bill_date = date('Y-m-d H:i:s');
        $bill_status = 'Pending';
        $bill_type = 'Repair';
        $bill_id = getNextBillId($conn);

        $sql_bill = "INSERT INTO bill_headers (
            bill_id, bill_date, receipt_date, payment_method, bill_status, vat, comment, discount, 
            customers_cs_id, bill_type, branches_branch_id, employees_emp_id, create_at, update_at
        ) VALUES (
            ?, ?, ?, 'Cash', ?, 7.00, 'เปิดบิลซ่อม (Job Order)', 0.00,
            ?, ?, ?, ?, NOW(), NOW()
        )";

        $stmt_bill = $conn->prepare($sql_bill);
        // สังเกตตำแหน่งรองสุดท้าย คือ $target_branch_id
        $stmt_bill->bind_param("isssisii", $bill_id, $bill_date, $bill_date, $bill_status, $customer_id, $bill_type, $target_branch_id, $employee_id);

        if (!$stmt_bill->execute()) throw new Exception('สร้างบิลซ่อมไม่สำเร็จ: ' . $stmt_bill->error);
        $stmt_bill->close();

        // [3] Job Order (ใช้ $target_branch_id)
        $repair_id = getNextRepairId($conn);
        $sql_repair = "INSERT INTO repairs (
            repair_id, customers_cs_id, employees_emp_id, assigned_employee_id, 
            prod_stocks_stock_id, repair_desc, device_description, estimated_cost, 
            accessories_list, repair_status, bill_headers_bill_id, branches_branch_id, create_at, update_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'รับเครื่อง', ?, ?, NOW(), NOW())";

        $stmt_repair = $conn->prepare($sql_repair);
        // สังเกตตัวแปรสุดท้าย คือ $target_branch_id
        $stmt_repair->bind_param("iiiiissdsii", $repair_id, $customer_id, $employee_id, $assigned_employee_id, $stock_id, $repair_desc, $device_description, $estimated_cost, $accessories_list, $bill_id, $target_branch_id);

        if (!$stmt_repair->execute()) throw new Exception('บันทึกงานซ่อมไม่สำเร็จ: ' . $stmt_repair->error);
        $stmt_repair->close();

        // [4] Stock Movement 
        $movement_id = getNextMovementId($conn);
        $sql_move = "INSERT INTO stock_movements (movement_id, movement_type, ref_table, ref_id, create_at, prod_stocks_stock_id) VALUES (?, 'IN', 'repairs', ?, NOW(), ?)";
        $stmt_move = $conn->prepare($sql_move);
        $stmt_move->bind_param("iii", $movement_id, $repair_id, $stock_id);
        $stmt_move->execute();
        $stmt_move->close();

        // [5] Symptoms
        $sql_symptoms = "INSERT INTO repair_symptoms (repairs_repair_id, symptoms_symptom_id) VALUES ";
        $values = [];
        $params = [];
        $types = '';
        $symptoms_text_arr = [];

        foreach ($symptom_ids as $sid) {
            $values[] = "(?, ?)";
            $params[] = $repair_id;
            $params[] = (int)$sid;
            $types .= 'ii';
            $res_sym = $conn->query("SELECT symptom_name FROM symptoms WHERE symptom_id = " . (int)$sid);
            if ($row_sym = $res_sym->fetch_assoc()) $symptoms_text_arr[] = $row_sym['symptom_name'];
        }
        if (!empty($values)) {
            $sql_symptoms .= implode(', ', $values);
            $stmt_sym = $conn->prepare($sql_symptoms);
            $stmt_sym->bind_param($types, ...$params);
            $stmt_sym->execute();
            $stmt_sym->close();
        }
        
        // [6] Log Status
        $conn->query("INSERT INTO repair_status_log (repairs_repair_id, new_status, update_by_employee_id) VALUES ($repair_id, 'รับเครื่อง', $employee_id)");
        
        mysqli_commit($conn);

        // [7] Email
        try {
            $res_cust = $conn->query("SELECT firstname_th, lastname_th, cs_email FROM customers WHERE cs_id = $customer_id");
            $cust_data = $res_cust->fetch_assoc();
            $cust_email = $cust_data['cs_email'];
            $cust_name = $cust_data['firstname_th'] . " " . $cust_data['lastname_th'];

            $res_shop = $conn->query("SELECT shop_name, shop_email, shop_app_password FROM shop_info WHERE shop_id = '$target_shop_id' LIMIT 1");
            $shop_data = $res_shop->fetch_assoc();
            if ($shop_data && !empty($cust_email)) {
                $symptoms_txt_mail = implode(", ", $symptoms_text_arr);
                if (!empty($repair_desc)) $symptoms_txt_mail .= " (เพิ่มเติม: $repair_desc)";
                @sendJobOrderEmail($cust_email, $cust_name, $repair_id, $device_name_for_mail, $serial_no, $symptoms_txt_mail, $shop_data['shop_name'], $shop_data['shop_email'], $shop_data['shop_app_password']);
            }
        } catch (Exception $mail_err) {}

        $_SESSION['success'] = "✅ รับเครื่องซ่อมสำเร็จ: Job #$repair_id";
        header("Location: view_repair.php?id=$repair_id");
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = '❌ เกิดข้อผิดพลาด: ' . $e->getMessage();
        header('Location: add_repair.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รับเครื่องซ่อมใหม่ (Job Order)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>';
            color: <?= $text_color ?>;
        }

        .container {
            max-width: 1200px;
        }

        h4 {
            font-weight: 700;
            color: <?= $theme_color ?>;
        }

        .form-section {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }

        .btn-success {
            background-color: <?= $btn_add_color ?>;
            border-color: <?= $btn_add_color ?>;
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .customer-combo-box,
        .employee-combo-box {
            position: relative;
        }

        .customer-info-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            min-height: 100px;
        }

        .serial-check-status {
            margin-top: 10px;
            padding: 10px;
            border-radius: 6px;
            display: none;
            font-size: 0.9rem;
        }

        .serial-check-status.valid {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .serial-check-status.new {
            background-color: #d1edff;
            color: #0c63e4;
        }

        .serial-check-status.error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .symptom-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            padding: 10px 0;
        }

        .is-invalid {
            border-color: #dc3545;
        }

        .is-invalid+.invalid-feedback {
            display: block;
        }

        /* Combo box styling */
        #customer_results,
        #employee_results {
            position: absolute;
            z-index: 1000;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-top: none;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            background: white;
            border-radius: 0 0 8px 8px;
        }

        #customer_results .list-group-item,
        #employee_results .list-group-item {
            cursor: pointer;
        }

        .form-control[readonly] {
            background-color: #f0f0f0;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container py-5">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h4 class="mb-0" style="color: <?= $theme_color ?>;">
                                <i class="fas fa-file-alt me-2"></i>
                                ฟอร์มรับเครื่องซ่อม (Job Order)
                            </h4>
                        </div>

                        <div class="card-body">
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo $_SESSION['error'];
                                    unset($_SESSION['error']); ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="add_repair.php" id="repairForm" novalidate>

                                <?php if ($is_admin): ?>
                                    <div class="form-section bg-light border-primary">
                                        <h5 class="text-primary"><i class="fas fa-store me-2"></i>เลือกสาขาที่รับงาน (ผู้ดูแลระบบ)</h5>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">ร้านค้า <span class="text-danger">*</span></label>
                                                <select name="selected_shop_id" id="selected_shop_id" class="form-select" required>
                                                    <option value="">-- เลือกร้านค้า --</option>
                                                    <?php foreach ($shops_list as $shop): ?>
                                                        <option value="<?= $shop['shop_id'] ?>" <?= ($shop['shop_id'] == $current_shop_id) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($shop['shop_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">สาขา <span class="text-danger">*</span></label>
                                                <select name="selected_branch_id" id="selected_branch_id" class="form-select" required>
                                                    <option value="">-- เลือกสาขา --</option>
                                                    <?php foreach ($branches_list as $br): ?>
                                                        <option value="<?= $br['branch_id'] ?>" data-shop="<?= $br['shop_info_shop_id'] ?>">
                                                            <?= htmlspecialchars($br['branch_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <input type="hidden" name="selected_shop_id" value="<?= $current_shop_id ?>">
                                    <input type="hidden" name="selected_branch_id" value="<?= $current_branch_id ?>">
                                <?php endif; ?>

                                <div class="form-section">
                                    <h5><i class="fas fa-id-card-alt me-2"></i>ข้อมูลผู้เกี่ยวข้อง</h5>
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label for="customer_display" class="form-label">ลูกค้าที่นำเครื่องมาซ่อม <span class="text-danger">*</span></label>

                                            <div class="customer-combo-box input-group">
                                                <input type="text" class="form-control" id="customer_display" placeholder="คลิกปุ่มเพื่อค้นหาลูกค้า" readonly required>

                                                <input type="hidden" name="customer_id" id="customer_id" required>

                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerSearchModal" title="ค้นหาลูกค้า">
                                                    <i class="fas fa-search"></i>
                                                </button>

                                                <a href="../customer/add_customer.php?return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-outline-success" title="เพิ่มลูกค้าใหม่">
                                                    <i class="fas fa-user-plus"></i>
                                                </a>
                                            </div>
                                            <div class="invalid-feedback">กรุณาเลือก/ค้นหาลูกค้าจากรายการ</div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="customer-info-box" id="customer_info_box">
                                                <p class="text-muted mb-0"><i class="fas fa-info-circle me-1"></i>ข้อมูลลูกค้าจะปรากฏที่นี่</p>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="employee_display" class="form-label">พนักงานผู้รับเรื่อง <span class="text-danger">*</span></label>

                                            <div class="employee-combo-box input-group">
                                                <input type="text" class="form-control" id="employee_display" placeholder="คลิกปุ่มเพื่อค้นหาพนักงาน" readonly required>

                                                <input type="hidden" name="employee_id" id="employee_id" required>

                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#employeeSearchModal" title="ค้นหาพนักงาน" onclick="setAssignedMode(false)">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                            <div class="invalid-feedback">กรุณาเลือกพนักงานจากรายการ</div>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="assigned_employee_display" class="form-label">ช่างผู้รับผิดชอบงานซ่อม <span class="text-danger">*</span></label>

                                            <div class="employee-combo-box input-group">
                                                <input type="text" class="form-control" id="assigned_employee_display" placeholder="คลิกปุ่มเพื่อค้นหาช่าง" readonly required>

                                                <input type="hidden" name="assigned_employee_id" id="assigned_employee_id" required>

                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#employeeSearchModal" title="ค้นหาช่าง" onclick="setAssignedMode(true)">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                            <div class="invalid-feedback">กรุณาเลือกช่างผู้รับผิดชอบจากรายการ</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h5><i class="fas fa-mobile-alt me-2"></i>ข้อมูลเครื่องที่ซ่อม</h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="serial_no" class="form-label">Serial Number (หรือ IMEI) <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="serial_no" id="serial_no" maxlength="50" required placeholder="กรอก Serial Number เครื่อง">
                                            <div class="serial-check-status" id="serial_status"></div>
                                            <input type="hidden" name="is_new_device" id="is_new_device" value="0">
                                        </div>

                                        <div class="col-md-6" id="new_device_select" style="display:none;">
                                            <label for="new_product_id" class="form-label">รุ่นสินค้า (ถ้าเป็นเครื่องใหม่) <span class="text-danger">*</span></label>
                                            <select class="form-select" name="new_product_id" id="new_product_id">
                                                <option value="">-- เลือกรุ่นสินค้า --</option>
                                                <?php mysqli_data_seek($products_result, 0); ?>
                                                <?php while ($p = mysqli_fetch_assoc($products_result)): ?>
                                                    <option value="<?= $p['prod_id'] ?>">
                                                        <?= htmlspecialchars($p['prod_name']) ?> (<?= htmlspecialchars($p['brand_name_th']) ?>)
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                            <small class="text-muted">ใช้เมื่อ Serial นี้ไม่เคยมีในระบบ</small>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="estimated_cost" class="form-label">ค่าซ่อมประเมิน (บาท) <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">฿</span>
                                                <input type="number" class="form-control" name="estimated_cost" id="estimated_cost" step="0.01" min="0.00" value="0.00" required>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="accessories_list" class="form-label">อุปกรณ์เสริมที่ให้มา</label>
                                            <input type="text" class="form-control" name="accessories_list" id="accessories_list" maxlength="255" placeholder="เช่น: กล่อง, สายชาร์จ, เคส">
                                        </div>

                                        <div class="col-md-12">
                                            <label for="device_description" class="form-label">คำอธิบายสภาพเครื่องภายนอก </label>
                                            <textarea class="form-control" name="device_description" id="device_description" rows="2" maxlength="255" placeholder="เช่น: มีรอยร้าวที่มุมซ้ายบน, สีดำ, เคสยังอยู่"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h5><i class="fas fa-diagnoses me-2"></i>อาการเสียและรายละเอียด</h5>
                                    <label class="form-label">เลือกอาการเสียหลักที่พบ (เลือกได้หลายข้อ) <span class="text-danger">*</span></label>
                                    <div class="symptom-grid border p-3 rounded-3" style="border-color: #dee2e6 !important;">
                                        <?php mysqli_data_seek($symptoms_result, 0); ?>
                                        <?php if (mysqli_num_rows($symptoms_result) > 0): ?>
                                            <?php while ($symp = mysqli_fetch_assoc($symptoms_result)): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input symptom-checkbox" type="checkbox" name="symptoms[]" value="<?= $symp['symptom_id'] ?>" id="symptom_<?= $symp['symptom_id'] ?>">
                                                    <label class="form-check-label" for="symptom_<?= $symp['symptom_id'] ?>">
                                                        <?= htmlspecialchars($symp['symptom_name']) ?>
                                                    </label>
                                                </div>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <p class="text-danger">❌ ไม่พบข้อมูลอาการเสีย กรุณาไปเพิ่มที่หน้าจัดการอาการเสีย</p>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mt-4">
                                        <label for="repair_desc" class="form-label">รายละเอียดอาการเพิ่มเติม (ตามที่ลูกค้าแจ้ง)</label>
                                        <textarea class="form-control" name="repair_desc" id="repair_desc" rows="4" maxlength="500" placeholder="เช่น: ลูกค้าแจ้งว่าทำตกเมื่อวาน, เครื่องเคยซ่อมมาก่อน, ทัชสกรีนรวนเป็นบางครั้ง"></textarea>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end mt-4">
                                    <a href="<?= isset($_GET['return_to']) ? urldecode($_GET['return_to']) : 'repair_list.php' ?>" class="btn btn-secondary me-2">
                                        <i class="fas fa-arrow-left me-1"></i> ย้อนกลับ
                                    </a>
                                    <button type="submit" class="btn btn-success" id="submitBtn">
                                        <i class="fas fa-save me-1"></i> บันทึกใบรับซ่อม
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="customerSearchModal" tabindex="-1">
                    <div class="modal-dialog modal-xl modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-users me-2"></i>ค้นหาและเลือกลูกค้า</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" id="modal_customer_search" placeholder="พิมพ์ชื่อ, นามสกุล, หรือเบอร์โทรศัพท์">
                                    <button class="btn btn-primary" type="button" id="modal_search_btn">
                                        <i class="fas fa-search"></i> ค้นหา
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>ชื่อ-นามสกุล</th>
                                                <th>เบอร์โทร</th>
                                                <th>อีเมล</th>
                                                <th>จัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody id="modal_customer_results">
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">เริ่มพิมพ์เพื่อค้นหาลูกค้า...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="employeeSearchModal" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-user-tie me-2"></i>ค้นหาและเลือกพนักงาน</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="resetAssignedMode()"></button>
                            </div>
                            <div class="modal-body">
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" id="modal_employee_search" placeholder="พิมพ์ชื่อ, นามสกุล, หรือรหัสพนักงาน">
                                    <button class="btn btn-primary" type="button" id="modal_employee_search_btn">
                                        <i class="fas fa-search"></i> ค้นหา
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>รหัสพนักงาน</th>
                                                <th>ชื่อ-นามสกุล</th>
                                                <th>จัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody id="modal_employee_results">
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">เริ่มพิมพ์เพื่อค้นหาพนักงาน...</td>
                                            </tr>
                                        </tbody>
                                    </table>
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
        let isAssignedMode = false;

        function setAssignedMode(state) {
            isAssignedMode = state;
        }

        // --- Customer Search Logic  ---
        const customerSearchModal = new bootstrap.Modal(document.getElementById('customerSearchModal'));
        const customerIdInput = document.getElementById('customer_id');
        const customerDisplayInput = document.getElementById('customer_display');

        function searchCustomerInModal(query) {
            const resultsBody = document.getElementById('modal_customer_results');
            resultsBody.innerHTML = '<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>กำลังค้นหา...</td></tr>';

            if (query.length < 2) {
                resultsBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">พิมพ์อย่างน้อย 2 ตัวอักษรเพื่อค้นหา</td></tr>';
                return;
            }

            const currentShopId = document.getElementById('selected_shop_id').value;

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=search_customer&query=${query}&shop_id=${currentShopId}`
                })
                .then(res => res.json())
                .then(data => {
                    resultsBody.innerHTML = '';
                    if (data.success && data.customers.length > 0) {
                        data.customers.forEach(customer => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                            <td class="text-center">${customer.cs_id}</td>
                            <td>${customer.firstname_th} ${customer.lastname_th}</td>
                            <td>${customer.cs_phone_no}</td>
                            <td>${customer.cs_email || '—'}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-primary" data-bs-dismiss="modal" 
                                        onclick="selectCustomerInForm(${customer.cs_id}, '${customer.firstname_th}', '${customer.lastname_th}', '${customer.cs_phone_no}', '${customer.cs_email || ''}')">
                                    <i class="fas fa-check"></i> เลือก
                                </button>
                            </td>
                        `;
                            resultsBody.appendChild(row);
                        });
                    } else {
                        resultsBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">ไม่พบข้อมูลลูกค้า</td></tr>';
                    }
                });
        }

        // --- Customer Selection in Form ---
        function selectCustomerInForm(cs_id, fname, lname, phone, email) {
            customerIdInput.value = cs_id;
            customerIdInput.classList.remove('is-invalid');
            customerDisplayInput.classList.remove('is-invalid');
            customerDisplayInput.value = `${fname} ${lname} (${phone})`;

            const infoBox = document.getElementById('customer_info_box');
            infoBox.innerHTML = `
                <p class="mb-0"><strong>ลูกค้า:</strong> ${fname} ${lname}</p>
                <p class="mb-0"><strong>โทร:</strong> ${phone}</p>
                <p class="mb-0"><strong>Email:</strong> ${email || 'ไม่มี'}</p>
            `;
        }

        // Attach listeners to modal search
        document.getElementById('customer_display').addEventListener('click', function() {
            // (Open Modal and clear search)
            document.getElementById('modal_customer_search').value = '';
            searchCustomerInModal(''); // Show all active customers initially
            customerSearchModal.show();
        });

        document.getElementById('modal_customer_search').addEventListener('input', function() {
            searchCustomerInModal(this.value.trim());
        });

        document.getElementById('modal_search_btn').addEventListener('click', function() {
            searchCustomerInModal(document.getElementById('modal_customer_search').value.trim());
        });

        // Event listener when Modal opens
        document.getElementById('customerSearchModal').addEventListener('shown.bs.modal', function() {
            document.getElementById('modal_customer_search').value = '';
            document.getElementById('modal_customer_search').focus();
            document.getElementById('modal_customer_results').innerHTML = '<tr><td colspan="5" class="text-center text-muted">เริ่มพิมพ์เพื่อค้นหาลูกค้า...</td></tr>';
        });

        // --- Employee Search Logic (MODAL) ---

        const employeeSearchModal = new bootstrap.Modal(document.getElementById('employeeSearchModal'));
        const employeeIdInput = document.getElementById('employee_id');
        const employeeDisplayInput = document.getElementById('employee_display');

        const assignedEmployeeIdInput = document.getElementById('assigned_employee_id');
        const assignedEmployeeDisplayInput = document.getElementById('assigned_employee_display');

        function searchEmployeeInModal(query) {
            const resultsBody = document.getElementById('modal_employee_results');
            resultsBody.innerHTML = '<tr><td colspan="4" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>กำลังค้นหา...</td></tr>';

            if (query.length < 2) {
                resultsBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">พิมพ์อย่างน้อย 2 ตัวอักษรเพื่อค้นหา</td></tr>';
                return;
            }

            const currentShopId = document.getElementById('selected_shop_id').value;

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=search_employee&query=${query}&shop_id=${currentShopId}`
                })
                .then(res => res.json())
                .then(data => {
                    resultsBody.innerHTML = '';
                    if (data.success && data.employees.length > 0) {
                        data.employees.forEach(employee => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                            <td class="text-center">${employee.emp_id}</td>
                            <td class="text-center">${employee.emp_code || '—'}</td>
                            <td>${employee.firstname_th} ${employee.lastname_th}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-primary" data-bs-dismiss="modal" 
                                        onclick="selectEmployeeInForm(${employee.emp_id}, '${employee.firstname_th}', '${employee.lastname_th}', '${employee.emp_code || ''}')">
                                    <i class="fas fa-check"></i> เลือก
                                </button>
                            </td>
                        `;
                            resultsBody.appendChild(row);
                        });
                    } else {
                        resultsBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">ไม่พบข้อมูลพนักงาน</td></tr>';
                    }
                });
        }

        // --- Employee Selection in Form ---
        function selectEmployeeInForm(emp_id, fname, lname, emp_code) {
            const targetIdInput = isAssignedMode ? assignedEmployeeIdInput : employeeIdInput;
            const targetDisplayInput = isAssignedMode ? assignedEmployeeDisplayInput : employeeDisplayInput;

            targetIdInput.value = emp_id;
            targetIdInput.classList.remove('is-invalid');
            targetDisplayInput.classList.remove('is-invalid');
            targetDisplayInput.value = `${fname} ${lname} (Code: ${emp_code})`;

            // (Reset state after selection)
            isAssignedMode = false;
        }

        // Attach listeners to employee modal search
        document.getElementById('employee_display').addEventListener('click', function() {
            setAssignedMode(false); // Accepted By Mode
            document.getElementById('modal_employee_search').value = '';
            searchEmployeeInModal(''); // Show all active employees initially
            employeeSearchModal.show();
        });

        document.getElementById('assigned_employee_display').addEventListener('click', function() {
            setAssignedMode(true); // Assigned To Mode
            document.getElementById('modal_employee_search').value = '';
            searchEmployeeInModal('');
            employeeSearchModal.show();
        });

        document.getElementById('modal_employee_search').addEventListener('input', function() {
            searchEmployeeInModal(this.value.trim());
        });

        document.getElementById('modal_employee_search_btn').addEventListener('click', function() {
            searchEmployeeInModal(document.getElementById('modal_employee_search').value.trim());
        });

        // --- Serial Number Check Logic ---
        document.getElementById('serial_no').addEventListener('input', function() {
            const serial = this.value.trim();
            const statusDiv = document.getElementById('serial_status');
            const newDeviceSelect = document.getElementById('new_device_select');
            const isNewDeviceInput = document.getElementById('is_new_device');

            if (serial.length < 5) {
                statusDiv.style.display = 'none';
                newDeviceSelect.style.display = 'none';
                isNewDeviceInput.value = 0;
                this.classList.remove('is-invalid');
                return;
            }

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=check_serial&serial_no=${serial}`
                })
                .then(res => res.json())
                .then(data => {
                    statusDiv.classList.remove('valid', 'new', 'error');
                    statusDiv.style.display = 'block';
                    newDeviceSelect.style.display = 'none';
                    isNewDeviceInput.value = 0;

                    if (data.success) {
                        if (data.exists) {
                            // เครื่องนี้เคยอยู่ในระบบแล้ว
                            statusDiv.classList.add('valid');
                            statusDiv.innerHTML = `<i class="fas fa-check-circle me-1"></i> Serial นี้มีในระบบแล้ว (Stock ID: ${data.stock_id}). สถานะปัจจุบัน: <strong>${data.status}</strong>.`;
                            // ไม่ต้องเลือกสินค้าใหม่
                            newDeviceSelect.style.display = 'none';
                            document.getElementById('serial_no').classList.remove('is-invalid');
                        } else {
                            // เครื่องใหม่ ไม่เคยมีในระบบ
                            statusDiv.classList.add('new');
                            statusDiv.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i> Serial นี้เป็นของ <strong>ใหม่</strong>. กรุณาเลือกรุ่นสินค้าด้านล่าง.`;
                            newDeviceSelect.style.display = 'block';
                            isNewDeviceInput.value = 1;
                            document.getElementById('serial_no').classList.remove('is-invalid');
                        }
                    } else {
                        statusDiv.classList.add('error');
                        statusDiv.innerHTML = `<i class="fas fa-times-circle me-1"></i> เกิดข้อผิดพลาดในการตรวจสอบ Serial.`;
                        document.getElementById('serial_no').classList.add('is-invalid');
                    }
                });
        });


        // --- Form Submission Validation ---
        document.getElementById('repairForm').addEventListener('submit', function(e) {
            let isValid = true;
            const customerId = document.getElementById('customer_id');
            const employeeId = document.getElementById('employee_id');
            const assignedEmployeeId = document.getElementById('assigned_employee_id');
            const serialNo = document.getElementById('serial_no');
            const selectedSymptoms = document.querySelectorAll('.symptom-checkbox:checked').length;
            const isNewDevice = document.getElementById('is_new_device').value === '1';
            const newProductId = document.getElementById('new_product_id');
            const estimatedCost = document.getElementById('estimated_cost');

            // Function to check validity and apply class
            const checkField = (field) => {
                if (!field.value.trim() || field.classList.contains('is-invalid')) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            };

            //  ตรวจสอบข้อมูลหลัก
            checkField(customerId);
            checkField(employeeId);
            checkField(assignedEmployeeId);
            checkField(serialNo);

            if (estimatedCost.value.trim() === '' || parseFloat(estimatedCost.value) < 0) {
                estimatedCost.classList.add('is-invalid');
                isValid = false;
            } else {
                estimatedCost.classList.remove('is-invalid');
            }

            if (selectedSymptoms === 0) {
                isValid = false;
                document.querySelector('.symptom-grid').style.border = '1px solid #dc3545';
            } else {
                document.querySelector('.symptom-grid').style.border = 'none';
            }

            // ตรวจสอบเครื่องใหม่
            if (isNewDevice) {
                checkField(newProductId);
            }

            //  ป้องกันการ Submit ถ้าไม่ผ่าน
            if (!isValid) {
                e.preventDefault();
                document.getElementById('submitBtn').disabled = false;
                alert('กรุณากรอกข้อมูลที่จำเป็นทั้งหมดให้ถูกต้อง');
                return;
            }

            // แสดง Confirm Modal
            const customerNameText = document.getElementById('customer_info_box').innerText.split('\n')[0].replace('ลูกค้า:', '').trim();

            const confirmRepair = confirm(
                `ยืนยันการรับเครื่องซ่อม:\n` +
                `ลูกค้า: ${customerNameText}\n` +
                `Serial No: ${serialNo.value}\n` +
                `ค่าซ่อมประเมิน: ฿${parseFloat(estimatedCost.value).toLocaleString()}\n` +
                `สถานะ: รับเครื่อง\n\n` +
                `ระบบจะบันทึกเครื่องเข้าสต็อกในสถานะ 'Repair' และสร้าง Job Order ใหม่\n` +
                `ดำเนินการต่อหรือไม่?`
            );

            if (!confirmRepair) {
                e.preventDefault();
                return;
            }

            // สคริปต์กรองสาขาตามร้านค้า (สำหรับ Admin)
            const shopSelect = document.getElementById('selected_shop_id');
            const branchSelect = document.getElementById('selected_branch_id');

            if (shopSelect && branchSelect && shopSelect.tagName === 'SELECT') { // เช็คว่าเป็น Dropdown ไหม
                shopSelect.addEventListener('change', function() {
                    const selectedShop = this.value;
                    // รีเซ็ตค่าสาขา
                    branchSelect.value = "";

                    // วนลูปตัวเลือกสาขาทั้งหมด
                    Array.from(branchSelect.options).forEach(option => {
                        if (option.value === "") return; // ข้ามตัวเลือกแรก

                        // ถ้า shop id ตรงกัน หรือ ไม่ได้เลือกร้าน ให้แสดง
                        if (option.getAttribute('data-shop') == selectedShop) {
                            option.style.display = 'block';
                        } else {
                            option.style.display = 'none';
                        }
                    });
                });
                // Trigger ครั้งแรกเพื่อให้แสดงถูก
                shopSelect.dispatchEvent(new Event('change'));
            }
            // ปิดปุ่ม
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังบันทึก...';
            document.getElementById('submitBtn').disabled = true;
        });
    </script>
</body>

</html>