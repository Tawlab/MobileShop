<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'delete_symptom');

// -----------------------------------------------------------------------------
//POST HANDLER: จัดการการลบ
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $symptom_id = isset($_POST['symptom_id']) ? (int)$_POST['symptom_id'] : 0;

    // ตรวจสอบ ID
    if ($symptom_id <= 0) {
        $_SESSION['error'] = 'ไม่พบรหัสอาการเสียที่ต้องการลบ';
        header('Location: symptoms.php');
        exit;
    }

    mysqli_autocommit($conn, false);

    try {
        // CRITICAL CHECK ***: ตรวจสอบว่ามีงานซ่อมใดๆ ใช้อาการนี้อยู่หรือไม่
        $check_sql = "SELECT COUNT(*) FROM repair_symptoms WHERE symptoms_symptom_id = ?";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("i", $symptom_id);
        $stmt_check->execute();
        $count = (int)$stmt_check->get_result()->fetch_assoc()['COUNT(*)'];
        $stmt_check->close();

        if ($count > 0) {
            throw new Exception("ไม่สามารถลบได้: มีงานซ่อม ($count) ที่ใช้อาการนี้อยู่");
        }

        // DELETE 
        $delete_sql = "DELETE FROM symptoms WHERE symptom_id = ?";
        $stmt_delete = $conn->prepare($delete_sql);
        $stmt_delete->bind_param("i", $symptom_id);

        if (!$stmt_delete->execute()) {
            throw new Exception('ลบไม่สำเร็จ: ' . $stmt_delete->error);
        }
        $stmt_delete->close();

        // Commit Transaction
        mysqli_commit($conn);
        $_SESSION['success'] = '✅ ลบอาการเสียรหัส ' . $symptom_id . ' สำเร็จ';
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }

    mysqli_autocommit($conn, true);
} else {
    $_SESSION['error'] = 'Invalid request method.';
}
header('Location: symptoms.php');
exit;
