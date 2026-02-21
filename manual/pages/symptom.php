<?php
require '_layout.php';
layout_head('อาการเสีย', 'stethoscope', '#f97316', 'รายการอาการเสียสำหรับใช้ในระบบงานซ่อม เพิ่ม/แก้ไขอาการ');
?>
<div class="section-label"><i class="fas fa-info-circle"></i> อาการเสียคืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        อาการเสียคือรายการปัญหาที่โทรศัพท์มักพบบ่อย เช่น "หน้าจอแตก" "แบตเตอรี่เสื่อม" "กล้องไม่ทำงาน"
        เมื่อรับงานซ่อม พนักงานจะเลือกอาการจากรายการนี้ เพื่อให้ข้อมูลครบถ้วนและสม่ำเสมอ
    </p>
</div>
<div class="section-label"><i class="fas fa-play-circle"></i> วิธีเพิ่มอาการเสีย</div>
<div class="step-card"><div class="step-num">1</div><div class="step-content"><h5>ไปที่เมนู "การซ่อม" → "อาการเสีย"</h5><p>คลิกเมนูเพื่อดูรายการอาการทั้งหมด</p></div></div>
<div class="step-card"><div class="step-num">2</div><div class="step-content"><h5>กดปุ่ม "เพิ่มอาการใหม่"</h5><p>กรอกชื่ออาการเสียที่ต้องการเพิ่ม</p></div></div>
<div class="step-card"><div class="step-num">3</div><div class="step-content"><h5>กดบันทึก</h5><p>อาการจะปรากฏในแบบฟอร์มรับングานซ่อม</p></div></div>
<div class="tip-box">
    <i class="fas fa-lightbulb"></i>
    <div><strong>คำแนะนำ:</strong> ควรเพิ่มอาการเสียที่พบบ่อยในร้านให้ครบ เพื่อให้พนักงานเลือกได้ง่ายและรวดเร็ว</div>
</div>
<?php layout_foot(); ?>
