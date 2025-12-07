<?php
// --- ไฟล์: employee/add_employee.php ---

session_start(); // --- เริ่ม Session ---
require '../config/config.php';
checkPageAccess($conn, 'add_employee');

// --- ฟังก์ชันสำหรับ Hash รหัสผ่าน ---
function hashPassword($password)
{
    // --- ใช้ password_hash() ซึ่งเป็นวิธีที่ปลอดภัยที่สุด ---
    return password_hash($password, PASSWORD_DEFAULT);
}

// --- ดึงข้อมูลสำหรับ Dropdowns ---

// --- 1. คำนำหน้า (Prefixes) ---
$prefix_sql = "SELECT prefix_id, prefix_th FROM prefixs WHERE is_active = 1 ORDER BY prefix_th";
$prefix_result = mysqli_query($conn, $prefix_sql);
if (!$prefix_result) {
    // --- จัดการ Error ถ้า Query ล้มเหลว ---
    error_log("Error fetching prefixes: " . mysqli_error($conn));
    die("Error fetching prefixes: " . mysqli_error($conn)); // --- หยุดทำงานถ้าข้อมูลพื้นฐานดึงไม่ได้ ---
}

// --- 2. ศาสนา (Religions) ---
// --- (แก้ไข: เพิ่ม is_active = 1 ตามฐานข้อมูลล่าสุด) ---
$religion_sql = "SELECT religion_id, religion_name_th FROM religions WHERE is_active = 1 ORDER BY religion_id";
$religion_result = mysqli_query($conn, $religion_sql);
if (!$religion_result) {
    error_log("Error fetching religions: " . mysqli_error($conn));
    die("Error fetching religions: " . mysqli_error($conn));
}

// --- 3. แผนก (Departments) ---
$department_sql = "SELECT dept_id, dept_name FROM departments ORDER BY dept_name";
$department_result = mysqli_query($conn, $department_sql);
if (!$department_result) {
    error_log("Error fetching departments: " . mysqli_error($conn));
    die("Error fetching departments: " . mysqli_error($conn));
}

// --- 4. สาขา (Branches) ---
$branch_sql = "SELECT branch_id, branch_name FROM branches ORDER BY branch_name";
$branch_result = mysqli_query($conn, $branch_sql);
if (!$branch_result) {
    error_log("Error fetching branches: " . mysqli_error($conn));
    die("Error fetching branches: " . mysqli_error($conn));
}

// --- 5. บทบาท (Roles) ---
$role_sql = "SELECT role_id, role_name FROM roles ORDER BY role_name";
$role_result = mysqli_query($conn, $role_sql);
if (!$role_result) {
    error_log("Error fetching roles: " . mysqli_error($conn));
    die("Error fetching roles: " . mysqli_error($conn));
}

// --- 6. ที่อยู่ (Provinces, Districts, Subdistricts) ---
$provinces_result = mysqli_query($conn, "SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th");
if (!$provinces_result) {
    error_log("Error fetching provinces: " . mysqli_error($conn));
    die("Error fetching provinces: " . mysqli_error($conn));
}

$districts_result = mysqli_query($conn, "SELECT district_id, district_name_th, provinces_province_id FROM districts ORDER BY district_name_th");
if (!$districts_result) {
    error_log("Error fetching districts: " . mysqli_error($conn));
    die("Error fetching districts: " . mysqli_error($conn));
}

$subdistricts_result = mysqli_query($conn, "SELECT subdistrict_id, subdistrict_name_th, zip_code, districts_district_id FROM subdistricts ORDER BY subdistrict_name_th");
if (!$subdistricts_result) {
    error_log("Error fetching subdistricts: " . mysqli_error($conn));
    die("Error fetching subdistricts: " . mysqli_error($conn));
}


// --- ตัวแปรสำหรับเก็บข้อมูลพนักงานที่เพิ่มสำเร็จ (เพื่อแสดงผล) ---
$added_employee_data = null;
// --- ตัวแปรสำหรับเก็บข้อมูลที่กรอกค้าง (ถ้า Error) ---
$form_data = [];
$errors_to_display = [];


// --- ประมวลผลเมื่อมีการส่งฟอร์ม (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    // --- รับข้อมูลพนักงาน (employees) ---
    $emp_code = trim($_POST['emp_code']);
    $emp_national_id = trim($_POST['emp_national_id']);
    $prefixs_prefix_id = (int)$_POST['prefixs_prefix_id'];
    $firstname_th = trim($_POST['firstname_th']);
    $lastname_th = trim($_POST['lastname_th']);
    $firstname_en = !empty($_POST['firstname_en']) ? trim($_POST['firstname_en']) : NULL;
    $lastname_en = !empty($_POST['lastname_en']) ? trim($_POST['lastname_en']) : NULL;
    $emp_phone_no = trim($_POST['emp_phone_no']);
    $emp_email = !empty($_POST['emp_email']) ? trim($_POST['emp_email']) : NULL;
    $emp_line_id = !empty($_POST['emp_line_id']) ? trim($_POST['emp_line_id']) : NULL;
    $emp_birthday = !empty($_POST['emp_birthday']) ? trim($_POST['emp_birthday']) : NULL;
    $emp_gender = $_POST['emp_gender'] ?? ''; // --- enum('Male','Female') ---
    $emp_status = $_POST['emp_status'] ?? ''; // --- enum('Active','Resigned') ---
    $religions_religion_id = (int)$_POST['religions_religion_id'];
    $departments_dept_id = (int)$_POST['departments_dept_id'];
    $branches_branch_id = (int)$_POST['branches_branch_id'];

    // --- รับข้อมูลที่อยู่ (addresses) ---
    $home_no = !empty($_POST['home_no']) ? trim($_POST['home_no']) : NULL;
    $moo = !empty($_POST['moo']) ? trim($_POST['moo']) : NULL;
    $soi = !empty($_POST['soi']) ? trim($_POST['soi']) : NULL;
    $road = !empty($_POST['road']) ? trim($_POST['road']) : NULL;
    $village = !empty($_POST['village']) ? trim($_POST['village']) : NULL;
    $subdistricts_subdistrict_id = !empty($_POST['subdistricts_subdistrict_id']) ? (int)$_POST['subdistricts_subdistrict_id'] : NULL;

    // --- รับข้อมูลผู้ใช้งาน (users & user_roles) ---
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_status = $_POST['user_status'] ?? ''; // --- enum('Active','Inactive') ---
    $role_id = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;

    // --- ตรวจสอบข้อมูลเบื้องต้น ---
    $errors = [];
    // --- Employee ---
    if (empty($emp_code)) {
        $errors[] = "กรุณากรอกรหัสพนักงาน";
    }
    if (empty($emp_national_id)) {
        $errors[] = "กรุณากรอกเลขบัตรประชาชน";
    } elseif (!ctype_digit($emp_national_id) || strlen($emp_national_id) != 13) {
        $errors[] = "เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก";
    }
    if (empty($prefixs_prefix_id)) {
        $errors[] = "กรุณาเลือกคำนำหน้า";
    }
    if (empty($firstname_th)) {
        $errors[] = "กรุณากรอกชื่อจริง (ไทย)";
    }
    if (empty($lastname_th)) {
        $errors[] = "กรุณากรอกนามสกุล (ไทย)";
    }
    if (empty($emp_phone_no)) {
        $errors[] = "กรุณากรอกเบอร์โทรศัพท์";
    } elseif (!preg_match('/^[0-9-]+$/', $emp_phone_no)) {
        $errors[] = "รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง (ตัวเลขและขีด)";
    }
    if ($emp_email !== NULL && !filter_var($emp_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "รูปแบบอีเมลไม่ถูกต้อง";
    }
    if (empty($emp_gender)) {
        $errors[] = "กรุณาเลือกเพศ";
    }
    if (empty($emp_status)) {
        $errors[] = "กรุณาเลือกสถานะพนักงาน";
    }
    if (empty($religions_religion_id)) {
        $errors[] = "กรุณาเลือกศาสนา";
    }
    if (empty($departments_dept_id)) {
        $errors[] = "กรุณาเลือกแผนก";
    }
    if (empty($branches_branch_id)) {
        $errors[] = "กรุณาเลือกสาขา";
    }
    // --- Address ---
    if (empty($subdistricts_subdistrict_id)) {
        $errors[] = "กรุณาเลือกจังหวัด/อำเภอ/ตำบล";
    }
    // --- User ---
    if (empty($username)) {
        $errors[] = "กรุณากรอก Username";
    }
    if (empty($password)) {
        $errors[] = "กรุณากรอกรหัสผ่าน";
    }
    if ($password !== $confirm_password) {
        $errors[] = "รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน";
    }
    if (empty($user_status)) {
        $errors[] = "กรุณาเลือกสถานะผู้ใช้งาน";
    }
    if (empty($role_id)) {
        $errors[] = "กรุณาเลือกบทบาทผู้ใช้งาน";
    }

    // --- (ย้ายมา) ส่วนจัดการอัปโหลดรูปโปรไฟล์ ---
    $emp_image_filename = NULL; // --- ตั้งค่าเริ่มต้นเป็น NULL (ว่าง) ---

    // --- ตรวจสอบว่ามีไฟล์ส่งมาหรือไม่ และไม่มี Error ---
    if (isset($_FILES['emp_image']) && $_FILES['emp_image']['error'] == 0) {
        $upload_dir = '../uploads/employees/'; // --- โฟลเดอร์ที่เก็บรูป ---
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = mime_content_type($_FILES['emp_image']['tmp_name']);

        // --- ตรวจสอบชนิดไฟล์ ---
        if (in_array($file_type, $allowed_types)) {
            // --- ตั้งชื่อไฟล์ใหม่ (กันซ้ำ) เช่น emp_รหัสพนักงาน_timestamp.jpg ---
            // --- (เราจะใช้ $emp_code ที่รับมาก่อนหน้า) ---
            $file_extension = pathinfo($_FILES['emp_image']['name'], PATHINFO_EXTENSION);
            $emp_image_filename = "emp_" . $emp_code . "_" . time() . "." . $file_extension;
            $target_path = $upload_dir . $emp_image_filename;

            // --- ตรวจสอบว่ามีโฟลเดอร์หรือไม่ ถ้าไม่มีให้สร้าง ---
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // --- ย้ายไฟล์ไปเก็บ ---
            if (move_uploaded_file($_FILES['emp_image']['tmp_name'], $target_path)) {
                // --- ถ้าย้ายสำเร็จ $emp_image_filename จะมีค่าชื่อไฟล์ ---
            } else {
                // --- ถ้าย้ายไม่สำเร็จ ให้ล้มเหลว และแจ้ง Error ---
                $errors[] = "เกิดข้อผิดพลาดในการย้ายไฟล์รูปภาพ";
                $emp_image_filename = NULL; // --- ตั้งค่ากลับเป็น NULL ---
            }
        } else {
            // --- ถ้าไฟล์ไม่ใช่รูปภาพ ---
            $errors[] = "ไฟล์ที่อัปโหลดต้องเป็นรูปภาพ (JPG, PNG, GIF, WEBP) เท่านั้น";
        }
    }
    // --- จบส่วนจัดการอัปโหลดรูปโปรไฟล์ ---

    // --- ตรวจสอบข้อมูลซ้ำใน Database (emp_code, emp_national_id, username) ---
    if (empty($errors)) {
        // --- Check emp_code ---
        $stmt_check = $conn->prepare("SELECT emp_id FROM employees WHERE emp_code = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("s", $emp_code);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $errors[] = "รหัสพนักงาน '$emp_code' นี้มีอยู่แล้ว";
            }
            $stmt_check->close();
        } else {
            $errors[] = "DB Error (check emp_code): " . $conn->error;
        }

        // --- Check emp_national_id ---
        $stmt_check = $conn->prepare("SELECT emp_id FROM employees WHERE emp_national_id = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("s", $emp_national_id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $errors[] = "เลขบัตรประชาชน '$emp_national_id' นี้มีอยู่แล้ว";
            }
            $stmt_check->close();
        } else {
            $errors[] = "DB Error (check national_id): " . $conn->error;
        }

        // --- Check username ---
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("s", $username);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $errors[] = "Username '$username' นี้มีผู้ใช้งานแล้ว";
            }
            $stmt_check->close();
        } else {
            $errors[] = "DB Error (check username): " . $conn->error;
        }
    }

    // --- หากไม่มีข้อผิดพลาด ---
    if (empty($errors)) {
        // --- สร้าง ID อัตโนมัติ (Max + 1) ---
        // --- Address ID ---
        $sql_max_addr = "SELECT MAX(address_id) AS max_id FROM addresses";
        $max_addr_result = $conn->query($sql_max_addr);
        $max_addr_id = $max_addr_result ? ($max_addr_result->fetch_assoc()['max_id'] ?? 0) : 0;
        $next_address_id = $max_addr_id + 1;

        // --- User ID ---
        $sql_max_user = "SELECT MAX(user_id) AS max_id FROM users";
        $max_user_result = $conn->query($sql_max_user);
        $max_user_id = $max_user_result ? ($max_user_result->fetch_assoc()['max_id'] ?? 0) : 0;
        $next_user_id = $max_user_id + 1;

        // --- Employee ID ---
        $sql_max_emp = "SELECT MAX(emp_id) AS max_id FROM employees";
        $max_emp_result = $conn->query($sql_max_emp);
        $max_emp_id = $max_emp_result ? ($max_emp_result->fetch_assoc()['max_id'] ?? 0) : 0;
        $next_emp_id = $max_emp_id + 1;

        // --- Hash Password ---
        $hashed_password = hashPassword($password);

        // --- เริ่ม Transaction ---
        $conn->begin_transaction();
        try {
            // --- 1. บันทึกที่อยู่ (addresses) ---
            $stmt_addr = $conn->prepare("INSERT INTO addresses (address_id, home_no, moo, soi, road, village, subdistricts_subdistrict_id)
                                         VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt_addr) {
                throw new Exception("Prepare failed (addresses): " . $conn->error);
            }
            $stmt_addr->bind_param("isssssi", $next_address_id, $home_no, $moo, $soi, $road, $village, $subdistricts_subdistrict_id);
            if (!$stmt_addr->execute()) {
                throw new Exception("บันทึกที่อยู่ล้มเหลว: " . $stmt_addr->error);
            }
            $stmt_addr->close();

            // --- 2. บันทึกผู้ใช้งาน (users) ---
            $stmt_user = $conn->prepare("INSERT INTO users (user_id, username, password, user_status) VALUES (?, ?, ?, ?)");
            if (!$stmt_user) {
                throw new Exception("Prepare failed (users): " . $conn->error);
            }
            $stmt_user->bind_param("isss", $next_user_id, $username, $hashed_password, $user_status);
            if (!$stmt_user->execute()) {
                throw new Exception("บันทึกข้อมูลผู้ใช้ล้มเหลว: " . $stmt_user->error);
            }
            $stmt_user->close();

            // --- 3. บันทึกพนักงาน (employees) ---
            // --- (แก้ไข: เพิ่ม emp_image) ---
            $stmt_emp = $conn->prepare("INSERT INTO employees (emp_id, emp_code, emp_national_id, firstname_th, lastname_th, firstname_en, lastname_en,
                                        emp_phone_no, emp_email, emp_line_id, emp_birthday, emp_gender, emp_status,
                                        prefixs_prefix_id, Addresses_address_id, religions_religion_id, departments_dept_id, branches_branch_id, users_user_id, emp_image)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"); // --- (แก้ไข: เพิ่ม ?) ---
            if (!$stmt_emp) {
                throw new Exception("Prepare failed (employees): " . $conn->error);
            }
            // --- (แก้ไข: เพิ่ม "s" และ $emp_image_filename) ---
            $stmt_emp->bind_param(
                "issssssssssssiiiiiis", // --- (แก้ไข: เพิ่ม s ตัวสุดท้าย) ---
                $next_emp_id,
                $emp_code,
                $emp_national_id,
                $firstname_th,
                $lastname_th,
                $firstname_en,
                $lastname_en,
                $emp_phone_no,
                $emp_email,
                $emp_line_id,
                $emp_birthday,
                $emp_gender,
                $emp_status,
                $prefixs_prefix_id,
                $next_address_id,
                $religions_religion_id,
                $departments_dept_id,
                $branches_branch_id,
                $next_user_id,
                $emp_image_filename // --- (แก้ไข: เพิ่มตัวแปร) ---
            );
            if (!$stmt_emp->execute()) {
                throw new Exception("บันทึกข้อมูลพนักงานล้มเหลว: " . $stmt_emp->error);
            }
            $stmt_emp->close();

            // --- 4. บันทึกบทบาทผู้ใช้ (user_roles) ---
            $stmt_ur = $conn->prepare("INSERT INTO user_roles (roles_role_id, users_user_id) VALUES (?, ?)");
            if (!$stmt_ur) {
                throw new Exception("Prepare failed (user_roles): " . $conn->error);
            }
            $stmt_ur->bind_param("ii", $role_id, $next_user_id);
            if (!$stmt_ur->execute()) {
                throw new Exception("บันทึกบทบาทผู้ใช้ล้มเหลว: " . $stmt_ur->error);
            }
            $stmt_ur->close();

            // --- ถ้าทุกอย่างสำเร็จ ---
            $conn->commit();
            $_SESSION['message'] = "เพิ่มข้อมูลพนักงาน '$firstname_th $lastname_th' และสร้างบัญชีผู้ใช้ '$username' สำเร็จ";
            $_SESSION['message_type'] = "success";

            // [แก้ไข] ให้ Redirect กลับไปหน้าเดิมทันที เพื่อเคลียร์ค่าและแสดงฟอร์มเปล่า
            header("Location: add_employee.php");
            exit();
            $sql_get_added = "
                SELECT
                    e.*, 
                    p.prefix_th, d.dept_name, b.branch_name, r.religion_name_th,
                    a.home_no, a.moo, a.soi, a.road, a.village,
                    sd.subdistrict_name_th, sd.zip_code,
                    dist.district_name_th,
                    prov.province_name_th
                FROM employees e
                LEFT JOIN prefixs p ON e.prefixs_prefix_id = p.prefix_id
                LEFT JOIN departments d ON e.departments_dept_id = d.dept_id
                LEFT JOIN branches b ON e.branches_branch_id = b.branch_id
                LEFT JOIN religions r ON e.religions_religion_id = r.religion_id
                LEFT JOIN addresses a ON e.Addresses_address_id = a.address_id
                LEFT JOIN subdistricts sd ON a.subdistricts_subdistrict_id = sd.subdistrict_id
                LEFT JOIN districts dist ON sd.districts_district_id = dist.district_id
                LEFT JOIN provinces prov ON dist.provinces_province_id = prov.province_id
                WHERE e.emp_id = ?";
            $stmt_get_added = $conn->prepare($sql_get_added);
            if ($stmt_get_added) { // --- ตรวจสอบ Prepare ---
                $stmt_get_added->bind_param("i", $next_emp_id);
                $stmt_get_added->execute();
                $added_employee_data = $stmt_get_added->get_result()->fetch_assoc();
                $stmt_get_added->close();
            } else {
                // --- ถ้าดึงข้อมูลมาแสดงผลไม่ได้ ---
                error_log("Failed to fetch added employee data: " . $conn->error);
                // --- ตั้งค่า $added_employee_data เป็นข้อมูลจาก POST (อาจไม่สมบูรณ์) ---
                $added_employee_data = $_POST;
                $added_employee_data['emp_id'] = $next_emp_id; // --- ใส่ ID ที่เพิ่งสร้าง ---
                // --- (แก้ไข: เพิ่ม emp_image) ---
                $added_employee_data['emp_image'] = $emp_image_filename;
                // --- ดึงข้อมูลที่ขาดมาเพิ่ม (เช่น ชื่อแผนก) ---
                $added_employee_data['prefix_th'] = $conn->query("SELECT prefix_th FROM prefixs WHERE prefix_id = $prefixs_prefix_id")->fetch_assoc()['prefix_th'] ?? '';
                $added_employee_data['dept_name'] = $conn->query("SELECT dept_name FROM departments WHERE dept_id = $departments_dept_id")->fetch_assoc()['dept_name'] ?? '';
                $added_employee_data['branch_name'] = $conn->query("SELECT branch_name FROM branches WHERE branch_id = $branches_branch_id")->fetch_assoc()['branch_name'] ?? '';
                $added_employee_data['religion_name_th'] = $conn->query("SELECT religion_name_th FROM religions WHERE religion_id = $religions_religion_id")->fetch_assoc()['religion_name_th'] ?? '';
                // --- ข้อมูลที่อยู่จะดึงยากถ้าไม่ Join ---
            }

            // --- ไม่ต้อง Redirect ---

        } catch (Exception $e) {
            // --- ถ้ามีข้อผิดพลาด ---
            $conn->rollback();
            $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
            error_log("Employee Add Error (Attempted EmpID: $next_emp_id): " . $e->getMessage());
            $_SESSION['form_data'] = $_POST; // --- เก็บข้อมูลที่กรอกไว้ ---
            header("Location: add_employee.php"); // --- กลับไปหน้า Add พร้อม Error ---
            exit();
        }
    } else {
        // --- ถ้ามี Error จาก Validation ---
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        // --- (เพิ่ม) เก็บ ID จังหวัด/อำเภอ ที่เลือกไว้ (ถ้ามี) ---
        // --- (จำเป็นสำหรับ JavaScript ตอนโหลดหน้าใหม่) ---
        if ($subdistricts_subdistrict_id) {
            $sql_get_ids = "SELECT d.provinces_province_id, sd.districts_district_id 
                             FROM subdistricts sd 
                             JOIN districts d ON sd.districts_district_id = d.district_id 
                             WHERE sd.subdistrict_id = $subdistricts_subdistrict_id";
            $ids_result = $conn->query($sql_get_ids);
            if ($ids_result && $ids_row = $ids_result->fetch_assoc()) {
                $_SESSION['form_data']['province_id'] = $ids_row['provinces_province_id'];
                $_SESSION['form_data']['district_id'] = $ids_row['districts_district_id'];
            }
        }
        header("Location: add_employee.php");
        exit();
    }
} else {
    // --- ถ้าเป็นการเปิดหน้าครั้งแรก หรือมี Error กลับมา ---
    $form_data = $_SESSION['form_data'] ?? [];
    $errors_to_display = $_SESSION['errors'] ?? [];
    unset($_SESSION['form_data'], $_SESSION['errors']); // --- ล้าง Session ---
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มพนักงานและผู้ใช้งาน - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        /* --- สไตล์ทั่วไป & ธีมเขียว --- */
        body {
            background-color: #f0fdf4;
            font-size: 0.95rem;
        }

        .form-container {
            max-width: 960px;
            margin: 40px auto;
        }

        /* ขยาย container */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            background: linear-gradient(135deg, #2dd4bf 0%, #15803d 100%);
            color: white;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
            padding: 1.5rem;
            border-bottom: none;
        }

        .card-header h4 {
            font-weight: 600;
            margin-bottom: 0;
        }

        .card-body {
            padding: 2rem;
        }

        .section-title {
            font-weight: 600;
            color: #15803d;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #a7f3d0;
            font-size: 1.1rem;
        }

        .btn-success {
            background: linear-gradient(135deg, #2dd4bf 0%, #15803d 100%);
            border: none;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #15803d 0%, #0d9488 100%);
            transform: translateY(-2px);
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 1px solid #ced4da;
            padding: 0.6rem 1rem;
            font-size: 0.9rem;
            background-color: #f8f9fa;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #15803d;
            box-shadow: 0 0 0 0.2rem rgba(21, 128, 61, 0.15);
            background-color: #fff;
        }

        .form-label {
            font-weight: 500;
            color: #495057;
            display: flex;
            align-items: center;
            margin-bottom: 0.3rem;
            /* ลดระยะห่าง */
        }

        .form-label i {
            margin-right: 8px;
            color: #15803d;
            width: 16px;
            text-align: center;
        }

        .required {
            color: #dc3545;
            margin-left: 4px;
        }

        .alert-danger ul {
            margin-bottom: 0;
            padding-left: 1.5rem;
        }

        .error-feedback {
            font-size: 0.8em;
            color: #dc3545;
            display: none;
            margin-top: 0.2rem;
        }

        /* ปรับขนาดและระยะห่าง Error */
        .is-invalid {
            border-color: #dc3545 !important;
        }

        .is-invalid+.error-feedback,
        .is-invalid~.error-feedback {
            display: block;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.8rem 1rem;
        }

        /* ปรับ Grid */
        .form-grid-full {
            grid-column: 1 / -1;
        }

        .password-toggle {
            cursor: pointer;
        }

        /* ไอคอนเปิด/ปิดรหัสผ่าน */
        /* --- Alert styles --- */
        .custom-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 300px;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.3s ease forwards;
            z-index: 1050;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .alert-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }

        .alert-error {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            color: white;
        }

        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* --- Print Styles (Default, ซ่อนก่อน) --- */
        /* .print-section { */
        /* ไม่ต้องทำอะไรพิเศษตอนแสดงผลปกติ */
        /* } */
        .print-header {
            display: none;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body * {
                visibility: hidden;
            }

            .print-section,
            .print-section * {
                visibility: visible;
            }

            .print-section {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">

        <?php include '../global/sidebar.php'; ?>

        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <?php if (!empty($_SESSION['message'])): ?>
                    <div class="custom-alert alert-success" role="alert">
                        <i class="fas fa-check-circle fa-lg"></i>
                        <div><strong>สำเร็จ!</strong><br><?= htmlspecialchars($_SESSION['message']) ?></div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close" style="filter: invert(1) grayscale(100%) brightness(200%);"></button>
                    </div>
                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                <?php endif; ?>

                <?php if (!empty($_SESSION['error_message'])): ?>
                    <div class="custom-alert alert-error" role="alert">
                        <i class="fas fa-exclamation-circle fa-lg"></i>
                        <div> <strong>ผิดพลาด!</strong><br> <?= htmlspecialchars($_SESSION['error_message']); ?> </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close" style="filter: invert(1) grayscale(100%) brightness(200%);"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>


                <div class="form-container">
                    <?php if ($added_employee_data === null): ?>
                        <div class="card fade-in">
                            <div class="card-header">
                                <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i>เพิ่มข้อมูลพนักงานและบัญชีผู้ใช้งาน</h4>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($errors_to_display)): ?>
                                    <div class="alert alert-danger mb-4">
                                        <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>ข้อมูลไม่ถูกต้อง</h5>
                                        <ul>
                                            <?php foreach ($errors_to_display as $error): ?>
                                                <li><?= htmlspecialchars($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="add_employee.php" id="addEmployeeForm" enctype="multipart/form-data" novalidate>

                                    <h5 class="section-title"><i class="fas fa-id-card-alt"></i>ข้อมูลพนักงาน</h5>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label for="emp_code" class="form-label"><i class="fas fa-hashtag"></i>รหัสพนักงาน<span class="required">*</span></label>
                                            <input type="text" class="form-control" id="emp_code" name="emp_code" required maxlength="20" value="<?= htmlspecialchars($form_data['emp_code'] ?? '') ?>">
                                            <div class="error-feedback">กรุณากรอกรหัสพนักงาน</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="emp_national_id" class="form-label"><i class="fas fa-id-card"></i>เลขบัตรประชาชน<span class="required">*</span></label>
                                            <input type="text" class="form-control" id="emp_national_id" name="emp_national_id" required maxlength="13" pattern="\d{13}" value="<?= htmlspecialchars($form_data['emp_national_id'] ?? '') ?>">
                                            <div class="error-feedback">กรุณากรอกเลขบัตร ปชช. 13 หลัก</div>
                                        </div>

                                        <div class="col-md-3 col-lg-2"> <label for="prefixs_prefix_id" class="form-label"><i class="fas fa-user-tag"></i>คำนำหน้า<span class="required">*</span></label>
                                            <select class="form-select" id="prefixs_prefix_id" name="prefixs_prefix_id" required>
                                                <option value="">-- เลือก --</option>
                                                <?php
                                                // --- วนลูปสร้าง options ---
                                                mysqli_data_seek($prefix_result, 0); // --- รีเซ็ต pointer ---
                                                while ($p = mysqli_fetch_assoc($prefix_result)): ?>
                                                    <option value="<?= $p['prefix_id']; ?>" <?= (isset($form_data['prefixs_prefix_id']) && $form_data['prefixs_prefix_id'] == $p['prefix_id']) ? 'selected' : ''; ?>>
                                                        <?= htmlspecialchars($p['prefix_th']); // --- แสดงคำนำหน้าไทย --- 
                                                        ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                            <div class="error-feedback">กรุณาเลือก</div>
                                        </div>
                                        <div class="col-md-5 col-lg-5"> <label for="firstname_th" class="form-label"><i class="fas fa-user"></i>ชื่อ (ไทย)<span class="required">*</span></label>
                                            <input type="text" class="form-control" id="firstname_th" name="firstname_th" required maxlength="30" value="<?= htmlspecialchars($form_data['firstname_th'] ?? '') ?>">
                                            <div class="error-feedback">กรุณากรอกชื่อ</div>
                                        </div>
                                        <div class="col-md-4 col-lg-5"> <label for="lastname_th" class="form-label"><i class="fas fa-user"></i>นามสกุล (ไทย)<span class="required">*</span></label>
                                            <input type="text" class="form-control" id="lastname_th" name="lastname_th" required maxlength="30" value="<?= htmlspecialchars($form_data['lastname_th'] ?? '') ?>">
                                            <div class="error-feedback">กรุณากรอกนามสกุล</div>
                                        </div>

                                        <div class="col-md-3 col-lg-2">
                                            <label for="prefix_en_display" class="form-label"><i class="fas fa-user-tag"></i>คำนำหน้า (Eng)</label>
                                            <input type="text" class="form-control" id="prefix_en_display" name="prefix_en_display_placeholder" disabled placeholder="(ไม่มีข้อมูล)">
                                        </div>
                                        <div class="col-md-5 col-lg-5">
                                            <label for="firstname_en" class="form-label"><i class="fas fa-user"></i>ชื่อ (Eng)</label>
                                            <input type="text" class="form-control" id="firstname_en" name="firstname_en" maxlength="30" value="<?= htmlspecialchars($form_data['firstname_en'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4 col-lg-5">
                                            <label for="lastname_en" class="form-label"><i class="fas fa-user"></i>นามสกุล (Eng)</label>
                                            <input type="text" class="form-control" id="lastname_en" name="lastname_en" maxlength="30" value="<?= htmlspecialchars($form_data['lastname_en'] ?? '') ?>">
                                        </div>

                                        <div class="col-12"> <label class="form-label d-block"><i class="fas fa-venus-mars"></i>เพศ<span class="required">*</span></label>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="emp_gender" id="gender_male" value="Male" <?= (isset($form_data['emp_gender']) && $form_data['emp_gender'] == 'Male') ? 'checked' : ''; ?> required>
                                                <label class="form-check-label" for="gender_male">ชาย</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="emp_gender" id="gender_female" value="Female" <?= (isset($form_data['emp_gender']) && $form_data['emp_gender'] == 'Female') ? 'checked' : ''; ?> required>
                                                <label class="form-check-label" for="gender_female">หญิง</label>
                                            </div>
                                            <div class="error-feedback d-block">กรุณาเลือกเพศ</div>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="emp_birthday" class="form-label"><i class="fas fa-calendar-alt"></i>วันเกิด</label>
                                            <input type="date" class="form-control" id="emp_birthday" name="emp_birthday" value="<?= htmlspecialchars($form_data['emp_birthday'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="religions_religion_id" class="form-label"><i class="fas fa-pray"></i>ศาสนา<span class="required">*</span></label>
                                            <select class="form-select" id="religions_religion_id" name="religions_religion_id" required>
                                                <option value="">-- เลือก --</option>
                                                <?php mysqli_data_seek($religion_result, 0);
                                                while ($r = mysqli_fetch_assoc($religion_result)): ?>
                                                    <option value="<?= $r['religion_id']; ?>" <?= (isset($form_data['religions_religion_id']) && $form_data['religions_religion_id'] == $r['religion_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($r['religion_name_th']); ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                            <div class="error-feedback">กรุณาเลือกศาสนา</div>
                                        </div>

                                    </div>

                                    <h5 class="section-title"><i class="fas fa-address-book"></i>ข้อมูลติดต่อ</h5>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label for="emp_phone_no" class="form-label"><i class="fas fa-phone"></i>เบอร์โทรศัพท์<span class="required">*</span></label>
                                            <input type="text" class="form-control" id="emp_phone_no" name="emp_phone_no" required maxlength="20" value="<?= htmlspecialchars($form_data['emp_phone_no'] ?? '') ?>">
                                            <div class="error-feedback">กรุณากรอกเบอร์โทร</div>
                                            <div id="phone_error" class="error-feedback">รูปแบบไม่ถูกต้อง</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="emp_line_id" class="form-label"><i class="fab fa-line"></i>Line ID</label>
                                            <input type="text" class="form-control" id="emp_line_id" name="emp_line_id" maxlength="30" value="<?= htmlspecialchars($form_data['emp_line_id'] ?? '') ?>">
                                        </div>

                                        <div class="col-12"> <label for="emp_email" class="form-label"><i class="fas fa-envelope"></i>อีเมล</label>
                                            <input type="email" class="form-control" id="emp_email" name="emp_email" maxlength="75" value="<?= htmlspecialchars($form_data['emp_email'] ?? '') ?>">
                                            <div id="email_error" class="error-feedback">รูปแบบอีเมลไม่ถูกต้อง</div>
                                        </div>

                                    </div>

                                    <h5 class="section-title"><i class="fas fa-map-marker-alt"></i>ที่อยู่ปัจจุบัน</h5>
                                    <div class="form-grid mb-3">
                                        <div> <label for="home_no" class="form-label"><i class="fas fa-home"></i>บ้านเลขที่</label> <input type="text" name="home_no" id="home_no" class="form-control" maxlength="20" value="<?= htmlspecialchars($form_data['home_no'] ?? '') ?>"> </div>
                                        <div> <label for="moo" class="form-label">หมู่</label> <input type="text" name="moo" id="moo" class="form-control" maxlength="20" value="<?= htmlspecialchars($form_data['moo'] ?? '') ?>"> </div>
                                        <div> <label for="village" class="form-label">หมู่บ้าน/อาคาร</label> <input type="text" name="village" id="village" class="form-control" maxlength="50" value="<?= htmlspecialchars($form_data['village'] ?? '') ?>"> </div>
                                        <div> <label for="soi" class="form-label">ซอย</label> <input type="text" name="soi" id="soi" class="form-control" maxlength="50" value="<?= htmlspecialchars($form_data['soi'] ?? '') ?>"> </div>
                                        <div class="form-grid-full"> <label for="road" class="form-label">ถนน</label> <input type="text" name="road" id="road" class="form-control" maxlength="50" value="<?= htmlspecialchars($form_data['road'] ?? '') ?>"> </div>
                                        <div> <label for="provinceSelect" class="form-label">จังหวัด<span class="required">*</span></label> <select id="provinceSelect" class="form-select" required>
                                                <option value="">-- เลือก --</option> <?php mysqli_data_seek($provinces_result, 0);
                                                                                        while ($p = mysqli_fetch_assoc($provinces_result)) {
                                                                                            echo "<option value='{$p['province_id']}'>" . htmlspecialchars($p['province_name_th']) . "</option>";
                                                                                        } ?>
                                            </select>
                                            <div class="error-feedback">กรุณาเลือกจังหวัด</div>
                                        </div>
                                        <div> <label for="districtSelect" class="form-label">อำเภอ<span class="required">*</span></label> <select id="districtSelect" class="form-select" required>
                                                <option value="">-- เลือก --</option>
                                            </select>
                                            <div class="error-feedback">กรุณาเลือกอำเภอ</div>
                                        </div>
                                        <div> <label for="subdistrictSelect" class="form-label">ตำบล<span class="required">*</span></label> <select name="subdistricts_subdistrict_id" id="subdistrictSelect" class="form-select" required>
                                                <option value="">-- เลือก --</option>
                                            </select>
                                            <div class="error-feedback">กรุณาเลือกตำบล</div>
                                        </div>
                                        <div> <label for="zip_code" class="form-label">รหัสไปรษณีย์</label> <input type="text" name="zip_code" id="zip_code" class="form-control" maxlength="5" placeholder="(อัตโนมัติ)" readonly value="<?= htmlspecialchars($form_data['zip_code'] ?? '') ?>"></div>
                                    </div>

                                    <h5 class="section-title"><i class="fas fa-briefcase"></i>ข้อมูลการทำงาน</h5>
                                    <div class="form-grid mb-3">
                                        <div>
                                            <label for="departments_dept_id" class="form-label"><i class="fas fa-sitemap"></i>แผนก<span class="required">*</span></label>
                                            <select class="form-select" id="departments_dept_id" name="departments_dept_id" required>
                                                <option value="">-- เลือก --</option>
                                                <?php mysqli_data_seek($department_result, 0);
                                                while ($d = mysqli_fetch_assoc($department_result)): ?>
                                                    <option value="<?= $d['dept_id']; ?>" <?= (isset($form_data['departments_dept_id']) && $form_data['departments_dept_id'] == $d['dept_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($d['dept_name']); ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                            <div class="error-feedback">กรุณาเลือกแผนก</div>
                                        </div>
                                        <div>
                                            <label for="branches_branch_id" class="form-label"><i class="fas fa-store"></i>สาขา<span class="required">*</span></label>
                                            <select class="form-select" id="branches_branch_id" name="branches_branch_id" required>
                                                <option value="">-- เลือก --</option>
                                                <?php mysqli_data_seek($branch_result, 0);
                                                while ($b = mysqli_fetch_assoc($branch_result)): ?>
                                                    <option value="<?= $b['branch_id']; ?>" <?= (isset($form_data['branches_branch_id']) && $form_data['branches_branch_id'] == $b['branch_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($b['branch_name']); ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                            <div class="error-feedback">กรุณาเลือกสาขา</div>
                                        </div>
                                        <div>
                                            <label class="form-label"><i class="fas fa-toggle-on"></i>สถานะพนักงาน<span class="required">*</span></label>
                                            <div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="emp_status" id="status_active_emp" value="Active" <?= (!isset($form_data['emp_status']) || $form_data['emp_status'] == 'Active') ? 'checked' : ''; ?> required>
                                                    <label class="form-check-label" for="status_active_emp">ทำงานอยู่</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="emp_status" id="status_resigned_emp" value="Resigned" <?= (isset($form_data['emp_status']) && $form_data['emp_status'] == 'Resigned') ? 'checked' : ''; ?> required>
                                                    <label class="form-check-label" for="status_resigned_emp">ลาออก</label>
                                                </div>
                                            </div>
                                            <div class="error-feedback d-block">กรุณาเลือกสถานะ</div>
                                        </div>
                                    </div>

                                    <h5 class="section-title"><i class="fas fa-user-lock"></i>ข้อมูลบัญชีผู้ใช้งาน</h5>
                                    <div class="form-grid mb-3">
                                        <div>
                                            <label for="username" class="form-label"><i class="fas fa-user-circle"></i>Username<span class="required">*</span></label>
                                            <input type="text" class="form-control" id="username" name="username" required maxlength="50" value="<?= htmlspecialchars($form_data['username'] ?? '') ?>">
                                            <div class="error-feedback">กรุณากรอก Username</div>
                                        </div>
                                        <div>
                                            <label for="password" class="form-label"><i class="fas fa-key"></i>Password<span class="required">*</span></label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="password" name="password" required>
                                                <button class="btn btn-outline-secondary password-toggle" type="button" id="togglePassword"><i class="fas fa-eye"></i></button>
                                            </div>
                                            <div class="error-feedback">กรุณากรอกรหัสผ่าน</div>
                                        </div>
                                        <div>
                                            <label for="confirm_password" class="form-label"><i class="fas fa-key"></i>Confirm Password<span class="required">*</span></label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                                <button class="btn btn-outline-secondary password-toggle" type="button" id="toggleConfirmPassword"><i class="fas fa-eye"></i></button>
                                            </div>
                                            <div class="error-feedback">กรุณายืนยันรหัสผ่าน</div>
                                            <div id="password_match_error" class="error-feedback">รหัสผ่านไม่ตรงกัน</div>
                                        </div>
                                        <div>
                                            <label for="role_id" class="form-label"><i class="fas fa-user-tag"></i>บทบาท<span class="required">*</span></label>
                                            <select class="form-select" id="role_id" name="role_id" required>
                                                <option value="">-- เลือก --</option>
                                                <?php mysqli_data_seek($role_result, 0);
                                                while ($r = mysqli_fetch_assoc($role_result)): ?>
                                                    <option value="<?= $r['role_id']; ?>" <?= (isset($form_data['role_id']) && $form_data['role_id'] == $r['role_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($r['role_name']); ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                            <div class="error-feedback">กรุณาเลือกบทบาท</div>
                                        </div>
                                        <div class="form-grid-full">
                                            <label class="form-label"><i class="fas fa-toggle-on"></i>สถานะบัญชี<span class="required">*</span></label>
                                            <div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="user_status" id="status_active_user" value="Active" <?= (!isset($form_data['user_status']) || $form_data['user_status'] == 'Active') ? 'checked' : ''; ?> required>
                                                    <label class="form-check-label" for="status_active_user">เปิดใช้งาน</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="user_status" id="status_inactive_user" value="Inactive" <?= (isset($form_data['user_status']) && $form_data['user_status'] == 'Inactive') ? 'checked' : ''; ?> required>
                                                    <label class="form-check-label" for="status_inactive_user">ปิดใช้งาน</label>
                                                </div>
                                            </div>
                                            <div class="error-feedback d-block">กรุณาเลือกสถานะบัญชี</div>
                                        </div>
                                        <div class="col-md-12"> <label for="emp_image" class="form-label"><i class="fas fa-camera"></i>รูปโปรไฟล์ <span class="text-muted small">(ไม่บังคับ)</span></label>
                                            <input type="file" class="form-control" id="emp_image" name="emp_image" accept="image/jpeg, image/png, image/gif, image/webp">
                                            <div class="error-feedback">กรุณาเลือกไฟล์รูปภาพ (JPG, PNG, GIF, WEBP)</div>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2 mt-4 justify-content-center">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save me-2"></i>บันทึกข้อมูล
                                        </button>
                                        <a href="employee.php" class="btn btn-secondary">
                                            <i class="fas fa-times me-2"></i>ยกเลิก
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php elseif ($added_employee_data !== null): ?>
                        <?php
                        // --- PHP Helper: จัดรูปแบบที่อยู่ ---
                        $address_print_parts = [];
                        if (!empty($added_employee_data['home_no'])) $address_print_parts['เลขที่'] = htmlspecialchars($added_employee_data['home_no']);
                        if (!empty($added_employee_data['moo'])) $address_print_parts['หมู่'] = htmlspecialchars($added_employee_data['moo']);
                        if (!empty($added_employee_data['village'])) $address_print_parts['หมู่บ้าน/อาคาร'] = htmlspecialchars($added_employee_data['village']);
                        if (!empty($added_employee_data['soi'])) $address_print_parts['ซอย'] = htmlspecialchars($added_employee_data['soi']);
                        if (!empty($added_employee_data['road'])) $address_print_parts['ถนน'] = htmlspecialchars($added_employee_data['road']);
                        if (!empty($added_employee_data['subdistrict_name_th'])) $address_print_parts['ตำบล/แขวง'] = htmlspecialchars($added_employee_data['subdistrict_name_th']);
                        if (!empty($added_employee_data['district_name_th'])) $address_print_parts['อำเภอ/เขต'] = htmlspecialchars($added_employee_data['district_name_th']);
                        if (!empty($added_employee_data['province_name_th'])) $address_print_parts['จังหวัด'] = htmlspecialchars($added_employee_data['province_name_th']);
                        if (!empty($added_employee_data['zip_code'])) $address_print_parts['รหัสไปรษณีย์'] = htmlspecialchars($added_employee_data['zip_code']);
                        ?>

                        <div class="card fade-in" id="employeeDetailsCard">
                            <div class="card-header d-flex justify-content-between align-items-center no-print">
                                <h4 class="mb-0"><i class="fas fa-check-circle me-2 text-white"></i>เพิ่มข้อมูลพนักงานสำเร็จ</h4>
                                <button class="btn btn-sm btn-light" onclick="window.print();"><i class="fas fa-print me-1"></i> พิมพ์หน้านี้</button>
                            </div>

                            <div class="card-body print-section">

                                <?php if (!empty($added_employee_data['emp_image'])): ?>
                                    <div class="text-center mb-3">
                                        <img src="../uploads/employees/<?= htmlspecialchars($added_employee_data['emp_image']) ?>"
                                            alt="รูปพนักงาน"
                                            class="img-thumbnail"
                                            style="width: 150px; height: 150px; object-fit: cover; border-radius: 8px;">
                                    </div>
                                <?php endif; ?>

                                <h4 class="mb-3 text-center print-header">ข้อมูลพนักงาน #<?= sprintf("%06d", $added_employee_data['emp_id']) ?></h4>
                                <hr class="mb-4">

                                <h5 class="section-title mb-3"><i class="fas fa-id-card-alt me-2"></i>ข้อมูลส่วนตัว</h5>

                                <div class="row g-2 print-row">
                                    <div class="col-6">
                                        <strong class="info-label">รหัสพนักงาน:</strong>
                                        <span class="info-value"><?= htmlspecialchars($added_employee_data['emp_code']) ?></span>
                                    </div>
                                    <div class="col-6">
                                        <strong class="info-label">เลขบัตร ปชช.:</strong>
                                        <span class="info-value"><?= htmlspecialchars($added_employee_data['emp_national_id']) ?></span>
                                    </div>
                                </div>

                                <div class="row g-2 print-row">
                                    <div class="col-6">
                                        <strong class="info-label">ชื่อ-สกุล (ไทย):</strong>
                                        <span class="info-value"><?= htmlspecialchars($added_employee_data['prefix_th'] . $added_employee_data['firstname_th'] . ' ' . $added_employee_data['lastname_th']) ?></span>
                                    </div>
                                    <div class="col-6">
                                        <strong class="info-label">ชื่อ-สกุล (Eng):</strong>
                                        <span class="info-value"><?= htmlspecialchars(($added_employee_data['firstname_en'] ?? '') . ' ' . ($added_employee_data['lastname_en'] ?? '')) ?: '-' ?></span>
                                    </div>
                                </div>

                                <div class="row g-2 mb-4 print-row">
                                    <div class="col-4">
                                        <strong class="info-label">เพศ:</strong>
                                        <span class="info-value"><?= $added_employee_data['emp_gender'] == 'Male' ? 'ชาย' : 'หญิง' ?></span>
                                    </div>
                                    <div class="col-4">
                                        <strong class="info-label">วันเกิด:</strong>
                                        <span class="info-value"><?= $added_employee_data['emp_birthday'] ? date('d/m/Y', strtotime($added_employee_data['emp_birthday'])) : '-' ?></span>
                                    </div>
                                    <div class="col-4">
                                        <strong class="info-label">ศาสนา:</strong>
                                        <span class="info-value"><?= htmlspecialchars($added_employee_data['religion_name_th'] ?? '-') ?></span>
                                    </div>
                                </div>

                                <h5 class="section-title mb-3"><i class="fas fa-address-book me-2"></i>ข้อมูลติดต่อ</h5>

                                <div class="row g-2 print-row">
                                    <div class="col-6">
                                        <strong class="info-label">เบอร์โทรศัพท์:</strong>
                                        <span class="info-value"><?= htmlspecialchars($added_employee_data['emp_phone_no']) ?></span>
                                    </div>
                                    <div class="col-6">
                                        <strong class="info-label">Line ID:</strong>
                                        <span class="info-value"><?= htmlspecialchars($added_employee_data['emp_line_id'] ?: '-') ?></span>
                                    </div>
                                </div>

                                <div class="row g-2 mb-4 print-row">
                                    <div class="col-12">
                                        <strong class="info-label">อีเมล:</strong>
                                        <span class="info-value"><?= htmlspecialchars($added_employee_data['emp_email'] ?: '-') ?></span>
                                    </div>
                                </div>

                                <h5 class="section-title mb-3"><i class="fas fa-map-marker-alt me-2"></i>ที่อยู่ปัจจุบัน</h5>

                                <div class="row g-2 print-row">
                                    <div class="col-4">
                                        <strong class="info-label">บ้านเลขที่:</strong>
                                        <span class="info-value"><?= $address_print_parts['เลขที่'] ?? '-' ?></span>
                                    </div>
                                    <div class="col-4">
                                        <strong class="info-label">ซอย:</strong>
                                        <span class="info-value"><?= $address_print_parts['ซอย'] ?? '-' ?></span>
                                    </div>
                                    <div class="col-4">
                                        <strong class="info-label">หมู่ที่:</strong>
                                        <span class="info-value"><?= $address_print_parts['หมู่'] ?? '-' ?></span>
                                    </div>
                                </div>

                                <div class="row g-2 print-row">
                                    <div class="col-6">
                                        <strong class="info-label">หมู่บ้าน/อาคาร:</strong>
                                        <span class="info-value"><?= $address_print_parts['หมู่บ้าน/อาคาร'] ?? '-' ?></span>
                                    </div>
                                    <div class="col-6">
                                        <strong class="info-label">ถนน:</strong>
                                        <span class="info-value"><?= $address_print_parts['ถนน'] ?? '-' ?></span>
                                    </div>
                                </div>

                                <div class="row g-2 mb-4 print-row">
                                    <div class="col-3">
                                        <strong class="info-label">ตำบล/แขวง:</strong>
                                        <span class="info-value"><?= $address_print_parts['ตำบล/แขวง'] ?? '-' ?></span>
                                    </div>
                                    <div class="col-3">
                                        <strong class="info-label">อำเภอ/เขต:</strong>
                                        <span class="info-value"><?= $address_print_parts['อำเภอ/เขต'] ?? '-' ?></span>
                                    </div>
                                    <div class="col-3">
                                        <strong class="info-label">จังหวัด:</strong>
                                        <span class="info-value"><?= $address_print_parts['จังหวัด'] ?? '-' ?></span>
                                    </div>
                                    <div class="col-3">
                                        <strong class="info-label">รหัสไปรษณีย์:</strong>
                                        <span class="info-value"><?= $address_print_parts['รหัสไปรษณีย์'] ?? '-' ?></span>
                                    </div>
                                </div>


                                <h5 class="section-title mb-3"><i class="fas fa-briefcase me-2"></i>ข้อมูลการทำงาน</h5>
                                <div class="row g-2 mb-4 print-row">
                                    <div class="col-6">
                                        <strong class="info-label">แผนก:</strong>
                                        <span class="info-value"><?= htmlspecialchars($added_employee_data['dept_name'] ?? '-') ?></span>
                                    </div>
                                    <div class="col-6">
                                        <strong class="info-label">สาขา:</strong>
                                        <span class="info-value"><?= htmlspecialchars($added_employee_data['branch_name'] ?? '-') ?></span>
                                    </div>
                                </div>

                                <div class="text-center mt-4 no-print">
                                    <a href="employee.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>กลับไปหน้ารายการ</a>
                                    <button class="btn btn-success" onclick="window.print();"><i class="fas fa-print me-2"></i> พิมพ์ข้อมูลนี้</button>
                                    <a href="add_employee.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i> เพิ่มพนักงานอีก</a>
                                </div>
                            </div>
                        </div>
                </div>
            </div>
            <style>
                /* --- Style ปกติ (นอก Print) --- */
                .print-header {
                    display: none;
                }

                .info-label {
                    display: block;
                    font-size: 0.8rem;
                    color: #6c757d;
                    font-weight: normal;
                    /* ปกติ Label ไม่ต้องหนา */
                }

                .info-value {
                    display: block;
                    margin-bottom: 0.5rem;
                    color: #212529;
                    /* สีข้อความปกติ */
                }


                /* --- Style สำหรับ Print (ปรับให้กระชับ) --- */
                @media print {
                    @page {
                        margin: 0.7cm;
                        /* ลดขอบกระดาษ */
                    }

                    body {
                        background-color: #fff;
                        font-size: 9pt;
                        /* ลดขนาดอักษร */
                        margin: 0;
                    }

                    .view-container,
                    .form-container {
                        margin: 0;
                        max-width: 100%;
                        width: 100%;
                    }

                    .card {
                        box-shadow: none;
                        border: none;
                        border-radius: 0;
                    }

                    .card-body {
                        padding: 0;
                    }

                    .print-header {
                        display: block;
                        font-weight: bold;
                        font-size: 11pt;
                        /* หัวข้อใหญ่ */
                        margin-bottom: 0.3rem;
                    }

                    .text-center.mb-3 {
                        margin-bottom: 0.5rem !important;
                        /* ลดช่องว่างรูป */
                    }

                    .section-title {
                        font-size: 10pt;
                        /* หัวข้อรอง */
                        border: none;
                        padding-bottom: 0;
                        margin-bottom: 0.3rem;
                        margin-top: 0.5rem;
                        color: #000;
                        font-weight: bold;
                        /* ทำให้หัวข้อรองหนา */
                    }

                    .print-row {
                        margin-bottom: 0.5rem !important;
                        /* ระยะห่างระหว่างกลุ่มแถว */
                        row-gap: 0.2rem !important;
                    }

                    /* กลับมาใช้แบบ Label อยู่บน (ประหยัดแนวนอน) */
                    .info-label {
                        min-width: auto;
                        margin-bottom: 0;
                        font-size: 8pt;
                        /* Label เล็ก */
                        color: #555;
                        /* สีเทาเข้ม */
                        display: block;
                        font-weight: normal;
                    }

                    .info-value {
                        margin-bottom: 0.2rem;
                        /* ลดช่องว่างใต้ Value */
                        font-weight: normal;
                        display: block;
                        color: #000;
                        font-size: 9pt;
                    }

                    hr {
                        margin: 0.3rem 0 !important;
                    }

                    .no-print {
                        display: none !important;
                    }
                }
            </style>
        <?php endif; ?>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // --- Data from PHP for Address Dropdowns ---
            const provinces = <?php mysqli_data_seek($provinces_result, 0);
                                $p_arr = [];
                                while ($p = mysqli_fetch_assoc($provinces_result)) $p_arr[] = $p;
                                echo json_encode($p_arr); ?>;
            const districts = <?php mysqli_data_seek($districts_result, 0);
                                $d_arr = [];
                                while ($d = mysqli_fetch_assoc($districts_result)) $d_arr[] = $d;
                                echo json_encode($d_arr); ?>;
            const subdistricts = <?php mysqli_data_seek($subdistricts_result, 0);
                                    $s_arr = [];
                                    while ($s = mysqli_fetch_assoc($subdistricts_result)) $s_arr[] = $s;
                                    echo json_encode($s_arr); ?>;

            // --- Dropdown Elements ---
            const provinceSelect = document.getElementById('provinceSelect');
            const districtSelect = document.getElementById('districtSelect');
            const subdistrictSelect = document.getElementById('subdistrictSelect');
            const zipcodeInput = document.getElementById('zip_code');

            // --- Event Listeners for Address Dropdowns ---
            provinceSelect?.addEventListener('change', function() {
                onProvinceChange();
            });
            districtSelect?.addEventListener('change', function() {
                onDistrictChange();
            });
            subdistrictSelect?.addEventListener('change', function() {
                onSubdistrictChange();
            });

            // --- Toggle Password Visibility ---
            document.querySelectorAll('.password-toggle').forEach(button => {
                button.addEventListener('click', function() {
                    const input = this.previousElementSibling; // Input element before the button
                    const icon = this.querySelector('i');
                    if (input.type === "password") {
                        input.type = "text";
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        input.type = "password";
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });

            // --- Validation Functions & Elements ---
            const form = document.getElementById('addEmployeeForm');
            const passwordInput = document.getElementById("password");
            const confirmPasswordInput = document.getElementById("confirm_password");

            /**
             * ตรวจสอบความถูกต้องและแสดง Error
             * @param {HTMLElement} input
             * @returns {boolean}
             */
            function validateField(input) {
                if (!input) return true; // ถ้าไม่มี input นี้
                let isValid = true;
                const value = input.value.trim();
                // --- Required ---
                if (input.required && !value) {
                    showError(input, 'กรุณากรอกข้อมูล');
                    isValid = false;
                }
                // --- National ID ---
                else if (input.id === 'emp_national_id' && value && (!/^\d{13}$/.test(value))) {
                    showError(input, 'เลข ปชช. ต้องเป็น 13 หลัก');
                    isValid = false;
                }
                // --- Email ---
                else if (input.id === 'emp_email' && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    showError(input, 'รูปแบบอีเมลไม่ถูกต้อง', 'email_error');
                    isValid = false;
                }
                // --- Phone ---
                else if (input.id === 'emp_phone_no' && value && !/^[0-9-]+$/.test(value)) {
                    showError(input, 'รูปแบบเบอร์โทรไม่ถูกต้อง', 'phone_error');
                    isValid = false;
                }
                // --- Password Match ---
                else if (input.id === 'confirm_password' && passwordInput.value !== value) {
                    showError(input, 'รหัสผ่านไม่ตรงกัน', 'password_match_error');
                    isValid = false;
                }
                // --- เพิ่มการเช็คชื่อภาษาไทย ---
                else if ((input.id === 'firstname_th' || input.id === 'lastname_th') && value && !/^[ก-๙เแโใไฤฦๅ\s]+$/.test(value)) {
                    showError(input, 'กรุณากรอกภาษาไทยเท่านั้น');
                    isValid = false;
                }
                // --- เพิ่มการเช็คชื่อภาษาอังกฤษ ---
                else if ((input.id === 'firstname_en' || input.id === 'lastname_en') && value && !/^[a-zA-Z\s]+$/.test(value)) {
                    showError(input, 'กรุณากรอกภาษาอังกฤษเท่านั้น');
                    isValid = false;
                }
                // --- Clear error if valid ---
                else {
                    hideError(input);
                    if (input.id === 'emp_email') hideError(input, 'email_error');
                    if (input.id === 'emp_phone_no') hideError(input, 'phone_error');
                    if (input.id === 'confirm_password') hideError(input, 'password_match_error');
                    // --- Clear match error on password input if confirm matches ---
                    if (input.id === 'password' && confirmPasswordInput && value === confirmPasswordInput.value) {
                        hideError(confirmPasswordInput, 'password_match_error');
                    }
                }
                return isValid;
            }

            function showError(input, message, errorDivId = null) {
                if (!input) return;
                input.classList.add('is-invalid');
                let errorDiv;
                if (errorDivId) {
                    errorDiv = document.getElementById(errorDivId);
                } else {
                    errorDiv = input.parentNode.querySelector('.error-feedback');
                    if (!errorDiv && input.type === 'radio') {
                        errorDiv = input.closest('.form-check')?.parentNode?.querySelector('.error-feedback');
                    }
                }
                if (errorDiv) {
                    errorDiv.textContent = message;
                    errorDiv.style.display = 'block';
                }
            }

            function hideError(input, errorDivId = null) {
                if (!input) return;
                input.classList.remove('is-invalid');
                let errorDiv;
                if (errorDivId) {
                    errorDiv = document.getElementById(errorDivId);
                } else {
                    errorDiv = input.parentNode.querySelector('.error-feedback');
                    if (!errorDiv && input.type === 'radio') {
                        errorDiv = input.closest('.form-check')?.parentNode?.querySelector('.error-feedback');
                    }
                }
                if (errorDiv && errorDiv.style.display === 'block') {
                    if (!(input.required && !input.value.trim() && errorDiv.textContent.startsWith('กรุณา'))) {
                        errorDiv.style.display = 'none';
                    }
                }
                if (input.tagName === 'SELECT' && input.classList.contains('is-invalid') && input.value) {
                    const selectErrorDiv = input.parentNode.querySelector('.error-feedback');
                    if (selectErrorDiv) selectErrorDiv.style.display = 'none';
                    input.classList.remove('is-invalid');
                }
            }

            // --- ซ่อน Alert (ป๊อบอัพแจ้งเตือน) ที่มาจาก PHP หลังจากผ่านไป 7 วินาที ---
            setTimeout(() => {
                document.querySelectorAll('.custom-alert').forEach(alert => {
                    const bsAlert = bootstrap.Alert.getInstance(alert);
                    if (bsAlert) {
                        bsAlert.close();
                    } else {
                        alert.style.transition = 'opacity 0.5s ease';
                        alert.style.opacity = '0';
                        setTimeout(() => alert.remove(), 500);
                    }
                });
            }, 7000); // 7 วินาที

            // --- Event Listeners for Validation ---
            form?.querySelectorAll('input, select').forEach(element => {
                element.addEventListener('blur', function() {
                    validateField(this);
                });
                element.addEventListener('input', function() {
                    if (this.classList.contains('is-invalid')) {
                        validateField(this);
                    }
                });
                element.addEventListener('change', function() {
                    if (this.classList.contains('is-invalid')) {
                        validateField(this);
                    }
                });
            });
            // --- Re-check confirm password when password changes ---
            passwordInput?.addEventListener('input', () => validateField(confirmPasswordInput));


            // --- Final Check on Submit ---
            form?.addEventListener('submit', function(e) {
                let formIsValid = true;
                form.querySelectorAll('input[required], select[required], input[type="email"], input[type="tel"], input[type="password"]').forEach(field => {
                    if (!validateField(field)) {
                        formIsValid = false;
                    }
                });
                // ตรวจสอบ Radio ที่จำเป็น
                form.querySelectorAll('input[type="radio"][required]').forEach(radio => {
                    const name = radio.name;
                    if (!form.querySelector(`input[name="${name}"]:checked`)) {
                        showError(radio, 'กรุณาเลือก');
                        formIsValid = false;
                    }
                });


                if (!formIsValid) {
                    e.preventDefault();
                    const firstError = form.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.focus();
                    }
                    // --- สร้าง Alert แบบ Bootstrap (ถ้าฟังก์ชัน showAlert ไม่มี) ---
                    // (ถ้ามีฟังก์ชัน showAlert อยู่แล้ว ก็ใช้ได้เลย)
                    console.error("ข้อมูลในฟอร์มไม่ถูกต้อง");
                } else {
                    const submitButton = form.querySelector('button[type="submit"]');
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> กำลังบันทึก...';
                }
            });

            // --- Trigger Address Dropdowns on Load if Data Exists (for error return) ---
            document.addEventListener('DOMContentLoaded', () => {
                const existingProvince = "<?= $form_data['province_id'] ?? '' ?>";
                const existingDistrict = "<?= $form_data['district_id'] ?? '' ?>";
                const existingSubdistrict = "<?= $form_data['subdistricts_subdistrict_id'] ?? '' ?>";

                if (existingProvince && provinceSelect) {
                    provinceSelect.value = existingProvince;
                    onProvinceChange(existingDistrict, existingSubdistrict);
                }
            });

            // --- ปรับปรุงฟังก์ชัน Address Dropdown ให้รับค่า Selected ID เริ่มต้น ---
            function onProvinceChange(selectedDistrictId = null, selectedSubdistrictId = null) {
                if (!provinceSelect) return;
                const provinceId = provinceSelect.value;
                districtSelect.innerHTML = '<option value="">-- เลือก --</option>';
                subdistrictSelect.innerHTML = '<option value="">-- เลือก --</option>';
                zipcodeInput.value = '';
                if (provinceId) {
                    districts.filter(d => d.provinces_province_id == provinceId)
                        .forEach(d => {
                            const opt = new Option(d.district_name_th, d.district_id);
                            if (selectedDistrictId && d.district_id == selectedDistrictId) opt.selected = true; // --- เลือกค่าเดิม ---
                            districtSelect.add(opt);
                        });
                }
                validateField(districtSelect);
                onDistrictChange(selectedSubdistrictId); // --- ส่งค่าต่อไป ---
            }

            function onDistrictChange(selectedSubdistrictId = null) {
                if (!districtSelect) return;
                const districtId = districtSelect.value;
                subdistrictSelect.innerHTML = '<option value="">-- เลือก --</option>';
                zipcodeInput.value = '';
                if (districtId) {
                    subdistricts.filter(s => s.districts_district_id == districtId)
                        .forEach(s => {
                            const opt = new Option(s.subdistrict_name_th, s.subdistrict_id);
                            opt.dataset.zip = s.zip_code;
                            if (selectedSubdistrictId && s.subdistrict_id == selectedSubdistrictId) opt.selected = true; // --- เลือกค่าเดิม ---
                            subdistrictSelect.add(opt);
                        });
                }
                validateField(subdistrictSelect);
                onSubdistrictChange(); // --- เรียกเพื่อให้ zip code แสดง ---
            }

            function onSubdistrictChange() {
                if (!subdistrictSelect) return;
                const selectedOpt = subdistrictSelect.options[subdistrictSelect.selectedIndex];
                zipcodeInput.value = selectedOpt?.dataset?.zip || '';
                validateField(subdistrictSelect);
            }
            // ฟังก์ชันสำหรับจำกัดภาษาใน Input
            function restrictInput(elementId, pattern) {
                const input = document.getElementById(elementId);
                if (input) {
                    input.addEventListener('input', function() {
                        // ถ้ามีตัวอักษรที่ไม่ตรงกับ Pattern ให้ลบทิ้งทันที
                        this.value = this.value.replace(pattern, '');
                    });
                }
            }

            // เรียกใช้งาน (Regex: ^ หมายถึง "ไม่เอา")
            // 1. ช่องภาษาไทย (อนุญาต: ก-๙, สระ, วรรณยุกต์ไทย และช่องว่าง) -> ลบตัวอื่นทิ้ง
            const regexNotThai = /[^ก-๙เแโใไฤฦๅ\s]/g;
            restrictInput('firstname_th', regexNotThai);
            restrictInput('lastname_th', regexNotThai);

            // 2. ช่องภาษาอังกฤษ (อนุญาต: a-z, A-Z และช่องว่าง) -> ลบตัวอื่นทิ้ง
            const regexNotEng = /[^a-zA-Z\s]/g;
            restrictInput('firstname_en', regexNotEng);
            restrictInput('lastname_en', regexNotEng);
        </script>
</body>

</html>