<?php
session_start();
require '../config/config.php';

// ส่วนประมวลผล ACTIONS
if (isset($_POST['bulk_delete']) && !empty($_POST['selected_ids'])) {
    $ids = $_POST['selected_ids'];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "DELETE FROM subdistricts WHERE subdistrict_id IN ($placeholders)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, str_repeat('i', count($ids)), ...$ids);
    mysqli_stmt_execute($stmt);
    $_SESSION['msg'] = "ลบรายการรหัสที่เลือกเรียบร้อยแล้ว";
    header("Location: manage_duplicates.php");
    exit();
}

// ลบ ID ที่ซ้ำกันอัตโนมัติ (Auto-Clean) 
if (isset($_POST['auto_clean_id'])) {
    // สร้างตารางชั่วคราวเพื่อเก็บแถวที่ไม่ซ้ำไว้ (GroupBy ID)
    $conn->query("CREATE TEMPORARY TABLE temp_subdistricts AS 
                  SELECT * FROM subdistricts GROUP BY subdistrict_id");
    
    // ล้างข้อมูลในตารางหลัก
    $conn->query("DELETE FROM subdistricts");
    
    // ย้ายข้อมูลที่คลีนแล้วกลับลงไป
    $res = $conn->query("INSERT INTO subdistricts SELECT * FROM temp_subdistricts");
    
    if ($res) {
        $_SESSION['msg'] = "จัดการลบรหัสที่ซ้ำกันอัตโนมัติเรียบร้อยแล้ว";
    }
    header("Location: manage_duplicates.php");
    exit();
}

// Query ดึงเฉพาะรายการที่ซ้ำ
$sql = "SELECT s.*, d.district_name_th 
        FROM subdistricts s
        LEFT JOIN districts d ON s.districts_district_id = d.district_id
        WHERE s.subdistrict_id IN (
            SELECT subdistrict_id FROM subdistricts GROUP BY subdistrict_id HAVING COUNT(*) > 1
        )
        OR s.subdistrict_name_th IN (
            SELECT subdistrict_name_th FROM subdistricts GROUP BY subdistrict_name_th HAVING COUNT(*) > 1
        )
        ORDER BY s.subdistrict_name_th ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการข้อมูลซ้ำ - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #fff8f8; font-family: 'Prompt', sans-serif; }
        .main-card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .header-custom { background: #dc3545; color: white; padding: 20px; border-radius: 15px 15px 0 0; }
        .duplicate-row { border-left: 4px solid #dc3545; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="main-card card">
        <div class="header-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-layers me-2"></i> จัดการรายการตำบลที่ซ้ำ</h4>
            <div class="d-flex gap-2">
                <form method="POST" onsubmit="return confirm('ระบบจะลบข้อมูลที่รหัส (ID) ซ้ำกันให้เหลือเพียง 1 รายการ ยืนยันหรือไม่?')">
                    <button type="submit" name="auto_clean_id" class="btn btn-dark btn-sm">
                        <i class="bi bi-magic"></i> ลบ ID ซ้ำอัตโนมัติ
                    </button>
                </form>
                <a href="subdistricts.php" class="btn btn-outline-light btn-sm">กลับหน้าหลัก</a>
            </div>
        </div>
        
        <div class="card-body p-4">
            <?php if(isset($_SESSION['msg'])): ?>
                <div class="alert alert-success border-0 shadow-sm"><?= $_SESSION['msg']; unset($_SESSION['msg']); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="40"><input type="checkbox" id="checkAll" class="form-check-input"></th>
                                <th>รหัสตำบล (ID)</th>
                                <th>ชื่อตำบล (ไทย)</th>
                                <th>อำเภอ</th>
                                <th>รหัสไปรษณีย์</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="duplicate-row">
                                    <td>
                                        <input type="checkbox" name="selected_ids[]" value="<?= $row['subdistrict_id'] ?>" class="form-check-input item-check">
                                    </td>
                                    <td><b class="text-danger">#<?= $row['subdistrict_id'] ?></b></td>
                                    <td class="fw-bold"><?= $row['subdistrict_name_th'] ?></td>
                                    <td><?= $row['district_name_th'] ?? '-' ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= $row['zip_code'] ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-5">ไม่พบข้อมูลซ้ำซ้อน</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($result->num_rows > 0): ?>
                <div class="mt-4 d-flex justify-content-between align-items-center">
                    <span class="text-muted small">พบข้อมูลซ้ำทั้งหมด <?= $result->num_rows ?> รายการ</span>
                    <button type="submit" name="bulk_delete" class="btn btn-danger px-5 shadow" onclick="return confirm('ยืนยันลบรายการที่เลือก?')">
                        <i class="bi bi-trash3-fill me-2"></i> ลบรายการที่เลือก
                    </button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('checkAll').onclick = function() {
        document.querySelectorAll('.item-check').forEach(cb => cb.checked = this.checked);
    };
</script>

</body>
</html>