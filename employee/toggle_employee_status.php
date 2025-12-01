<?php
// --- toggle_employee_status.php ---
session_start();
// *** ตรวจสอบ Path นี้ให้ถูกต้อง ***
// (Path นี้อ้างอิงจากไฟล์ employee.php ที่คุณส่งมา)
require '../config/config.php';
checkPageAccess($conn, 'toggle_employee_status');

// --- ตั้งค่าให้ตอบกลับเป็น JSON ---
header('Content-Type: application/json');

// --- (ตัวแปรสำหรับเก็บผลลัพธ์) ---
$response = ['success' => false, 'message' => ''];

// --- ตรวจสอบว่ารับข้อมูลมาแบบ POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- รับข้อมูลที่ส่งมา (แบบ JSON) ---
    $data = json_decode(file_get_contents('php://input'), true);

    $emp_id = $data['emp_id'] ?? null;
    $new_status = $data['new_status'] ?? null;

    // --- ตรวจสอบข้อมูล ---
    if ($emp_id && ($new_status == 'Active' || $new_status == 'Resigned')) {
        try {
            // --- เตรียมคำสั่ง UPDATE ---
            $sql = "UPDATE employees SET emp_status = ? WHERE emp_id = ?";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                // --- (Error: 1) ---
                throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error);
            }

            $stmt->bind_param("si", $new_status, $emp_id);

            // --- รันคำสั่ง ---
            if ($stmt->execute()) {
                // --- สำเร็จ ---
                $response['success'] = true;
                $response['message'] = 'เปลี่ยนสถานะสำเร็จ';
            } else {
                // --- (Error: 2) ---
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            // --- (Error: 3) ---
            $response['message'] = "Database Error: " . $e->getMessage();
        }
    } else {
        // --- ข้อมูลไม่ครบถ้วน ---
        $response['message'] = 'ข้อมูลไม่ถูกต้อง (ID หรือ Status ว่าง)';
    }
} else {
    // --- ไม่ใช่ POST ---
    $response['message'] = 'Invalid request method';
}

$conn->close();

// --- ส่งผลลัพธ์กลับเป็น JSON ---
echo json_encode($response);
exit(); // --- (สำคัญ) หยุดการทำงานทันที ป้องกันไม่ให้มี HTML อื่นปน ---
