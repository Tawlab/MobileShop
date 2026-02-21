<?php
require '_layout.php';
layout_head('บทบาทผู้ใช้งาน (Role)', 'user-tag', '#ef4444', 'กำหนด Role เช่น ผู้ดูแลระบบ พนักงานขาย ช่างซ่อม ฯลฯ');
?>
<div class="section-label"><i class="fas fa-info-circle"></i> บทบาท (Role) คืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        Role คือกลุ่มสิทธิ์การใช้งาน เช่น "ผู้ดูแลระบบ" จะเข้าถึงได้ทุกส่วน ในขณะที่ "พนักงานขาย"
        อาจเข้าถึงได้เฉพาะระบบขายและสต็อค การแยก Role ออกจากกันช่วยให้ระบบปลอดภัยและจัดการง่าย
    </p>
</div>

<div class="section-label"><i class="fas fa-list-check"></i> ตัวอย่าง Role ที่นิยมใช้</div>
<div class="info-box">
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-crown"></i></div>
        <div class="feature-text"><strong>ผู้ดูแลระบบ (Admin)</strong><span>เข้าถึงได้ทุกฟังก์ชัน รวมถึงการตั้งค่าระบบและจัดการผู้ใช้</span></div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-cash-register"></i></div>
        <div class="feature-text"><strong>พนักงานขาย</strong><span>เข้าถึงระบบขาย ดูสต็อค และข้อมูลลูกค้า</span></div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-tools"></i></div>
        <div class="feature-text"><strong>ช่างซ่อม</strong><span>เข้าถึงระบบซ่อม ดูรายการซ่อมและอัปเดตสถานะ</span></div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-chart-bar"></i></div>
        <div class="feature-text"><strong>ผู้จัดการ</strong><span>ดูรายงาน แดชบอร์ด และข้อมูลภาพรวม</span></div>
    </div>
</div>

<div class="section-label"><i class="fas fa-play-circle"></i> วิธีสร้าง Role ใหม่</div>
<div class="step-card"><div class="step-num">1</div><div class="step-content"><h5>ไปที่เมนู "จัดการผู้ใช้" → "บทบาทผู้ใช้"</h5><p>คลิกเมนูเพื่อดูรายการ Role ทั้งหมด</p></div></div>
<div class="step-card"><div class="step-num">2</div><div class="step-content"><h5>กดปุ่ม "เพิ่ม Role ใหม่"</h5><p>ตั้งชื่อ Role และคำอธิบาย</p></div></div>
<div class="step-card"><div class="step-num">3</div><div class="step-content"><h5>กำหนดสิทธิ์การใช้งาน</h5><p>เลือกว่า Role นี้สามารถเข้าถึงเมนูและฟังก์ชันใดได้บ้างในหน้า <a href="permission.php">สิทธิ์การใช้งาน</a></p></div></div>
<div class="step-card"><div class="step-num">4</div><div class="step-content"><h5>กดบันทึก แล้วกำหนด Role ให้ผู้ใช้</h5><p>ไปที่ <a href="users.php">จัดการผู้ใช้งาน</a> เพื่อกำหนด Role ให้แต่ละบัญชี</p></div></div>
<?php layout_foot(); ?>
