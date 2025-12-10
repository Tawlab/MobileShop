<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'toggle_employee_status');

// --- ตั้งค่าให้ตอบกลับเป็น JSON ---
header('Content-Type: application/json');
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
            $sql = "UPDATE employees SET emp_status = ? WHERE emp_id = ?";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error);
            }

            $stmt->bind_param("si", $new_status, $emp_id);

            // --- รันคำสั่ง ---
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'เปลี่ยนสถานะสำเร็จ';
            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();
        } catch (Exception $e) {
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
echo json_encode($response);
exit();
