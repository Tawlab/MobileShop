<?php
require '_layout.php';
layout_head('จัดการผู้ใช้งาน', 'user-shield', '#ef4444', 'เพิ่ม/แก้ไขบัญชีผู้ใช้งาน รหัสผ่าน และข้อมูลในระบบ');
?>
<div class="section-label"><i class="fas fa-info-circle"></i> ระบบจัดการผู้ใช้งานคืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        ผู้ใช้งาน (User) คือบัญชีที่ใช้เข้าสู่ระบบ แต่ละบัญชีจะมีชื่อผู้ใช้ รหัสผ่าน
        และ Role กำกับ ซึ่ง Role จะกำหนดว่าผู้ใช้คนนั้นมีสิทธิ์เข้าถึงส่วนไหนของระบบได้บ้าง
    </p>
</div>
<div class="info-box">
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-user-plus"></i></div>
        <div class="feature-text"><strong>เพิ่มผู้ใช้งานใหม่</strong><span>สร้างบัญชี กำหนดชื่อผู้ใช้ รหัสผ่าน และ Role</span></div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-edit"></i></div>
        <div class="feature-text"><strong>แก้ไขข้อมูลผู้ใช้</strong><span>อัปเดตชื่อผู้ใช้ รหัสผ่าน หรือเปลี่ยน Role</span></div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-eye"></i></div>
        <div class="feature-text"><strong>ดูรายละเอียดผู้ใช้</strong><span>ดูข้อมูลและสิทธิ์ของผู้ใช้แต่ละคน</span></div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-trash-alt"></i></div>
        <div class="feature-text"><strong>ลบผู้ใช้งาน</strong><span>ลบบัญชีของผู้ใช้ที่ไม่ได้ใช้งานแล้ว</span></div>
    </div>
</div>
<div class="section-label"><i class="fas fa-play-circle"></i> วิธีเพิ่มผู้ใช้งานใหม่</div>
<div class="step-card"><div class="step-num">1</div><div class="step-content"><h5>ไปที่เมนู "จัดการผู้ใช้" → "รายชื่อผู้ใช้"</h5><p>คลิกเมนูด้านซ้ายเพื่อดูรายชื่อผู้ใช้ทั้งหมด</p></div></div>
<div class="step-card"><div class="step-num">2</div><div class="step-content"><h5>กดปุ่ม "เพิ่มผู้ใช้ใหม่"</h5><p>เปิดฟอร์มสร้างบัญชีผู้ใช้</p></div></div>
<div class="step-card"><div class="step-num">3</div><div class="step-content"><h5>กรอกชื่อผู้ใช้และรหัสผ่าน เลือก Role</h5><p>ตั้งค่าข้อมูลการเข้าใช้งานและกำหนดสิทธิ์</p></div></div>
<div class="step-card"><div class="step-num">4</div><div class="step-content"><h5>กดบันทึก</h5><p>ผู้ใช้สามารถเข้าสู่ระบบได้ทันที</p></div></div>
<div class="note-box">
    <i class="fas fa-exclamation-triangle"></i>
    <div><strong>ความปลอดภัย:</strong> ควรตั้งรหัสผ่านที่เดาได้ยาก และไม่แชร์รหัสผ่านกันระหว่างผู้ใช้</div>
</div>
<?php layout_foot(); ?>
