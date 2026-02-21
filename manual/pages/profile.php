<?php
require '_layout.php';
layout_head('ข้อมูลส่วนตัวและรหัสผ่าน', 'user-circle', '#6b7280', 'แก้ไขข้อมูลส่วนตัว รูปโปรไฟล์ และเปลี่ยนรหัสผ่านของตนเอง');
?>
<div class="section-label"><i class="fas fa-info-circle"></i> หน้าข้อมูลส่วนตัวคืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        ผู้ใช้งานทุกคนสามารถแก้ไขข้อมูลส่วนตัวของตนเองได้ เช่น ชื่อ-นามสกุล เบอร์โทร
        และอัปโหลดรูปโปรไฟล์ รวมถึงเปลี่ยนรหัสผ่านได้ด้วยตนเอง
    </p>
</div>
<div class="info-box">
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-user-edit"></i></div>
        <div class="feature-text"><strong>แก้ไขข้อมูลส่วนตัว</strong><span>เปลี่ยนชื่อ นามสกุล เบอร์โทร และอีเมลของตนเอง</span></div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-camera"></i></div>
        <div class="feature-text"><strong>อัปโหลดรูปโปรไฟล์</strong><span>เลือกรูปภาพเพื่อใช้เป็นรูปโปรไฟล์ในระบบ</span></div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-lock"></i></div>
        <div class="feature-text"><strong>เปลี่ยนรหัสผ่าน</strong><span>ใส่รหัสผ่านเดิมและตั้งรหัสผ่านใหม่ได้ทันที</span></div>
    </div>
</div>
<div class="section-label"><i class="fas fa-play-circle"></i> วิธีเปลี่ยนรหัสผ่าน</div>
<div class="step-card"><div class="step-num">1</div><div class="step-content"><h5>คลิกที่ชื่อผู้ใช้มุมล่างซ้าย</h5><p>จะปรากฏเมนู Dropdown</p></div></div>
<div class="step-card"><div class="step-num">2</div><div class="step-content"><h5>เลือก "เปลี่ยนรหัสผ่าน"</h5><p>เปิดหน้าเปลี่ยนรหัสผ่าน</p></div></div>
<div class="step-card"><div class="step-num">3</div><div class="step-content"><h5>กรอกรหัสผ่านเดิมและรหัสใหม่</h5><p>ใส่รหัสผ่านเดิม รหัสใหม่ และยืนยันรหัสใหม่</p></div></div>
<div class="step-card"><div class="step-num">4</div><div class="step-content"><h5>กดบันทึก</h5><p>รหัสผ่านจะเปลี่ยนทันที ควรจำรหัสใหม่ให้ดี</p></div></div>
<div class="tip-box">
    <i class="fas fa-lightbulb"></i>
    <div><strong>คำแนะนำ:</strong> ควรตั้งรหัสผ่านที่มีความยาวอย่างน้อย 8 ตัวอักษร ผสมตัวเลข ตัวอักษรพิมพ์ใหญ่-เล็ก เพื่อความปลอดภัย</div>
</div>
<?php layout_foot(); ?>
