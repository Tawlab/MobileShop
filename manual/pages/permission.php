<?php
require '_layout.php';
layout_head('สิทธิ์การใช้งาน (Permission)', 'key', '#ef4444', 'กำหนดว่า Role ใดเข้าถึงเมนูและฟังก์ชันใดได้บ้าง');
?>
<div class="section-label"><i class="fas fa-info-circle"></i> สิทธิ์การใช้งานคืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        สิทธิ์การใช้งาน (Permission) คือการกำหนดว่า Role แต่ละประเภทสามารถเข้าถึงส่วนใดในระบบได้บ้าง
        เช่น Role "พนักงานขาย" อาจเข้าถึงเมนูขายได้ แต่ไม่สามารถเข้าถึงเมนูตั้งค่าระบบ
    </p>
</div>

<div class="section-label"><i class="fas fa-list-check"></i> สิทธิ์ที่สามารถกำหนดได้</div>
<div class="info-box">
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-tachometer-alt"></i></div>
        <div class="feature-text"><strong>เมนูแดชบอร์ด</strong><span>อนุญาตให้เข้าดูหน้าแดชบอร์ดและสถิติภาพรวม</span></div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-cash-register"></i></div>
        <div class="feature-text"><strong>เมนูการขาย</strong><span>อนุญาตให้สร้างบิลขายและรับชำระเงิน</span></div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-tools"></i></div>
        <div class="feature-text"><strong>เมนูการซ่อม</strong><span>อนุญาตให้รับงานซ่อมและอัปเดตสถานะ</span></div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-user-shield"></i></div>
        <div class="feature-text"><strong>เมนูจัดการผู้ใช้</strong><span>อนุญาตให้จัดการบัญชี Role และสิทธิ์ (เฉพาะ Admin)</span></div>
    </div>
</div>

<div class="section-label"><i class="fas fa-play-circle"></i> วิธีกำหนดสิทธิ์ให้ Role</div>
<div class="step-card"><div class="step-num">1</div><div class="step-content"><h5>ไปที่เมนู "จัดการผู้ใช้" → "สิทธิ์การใช้งาน"</h5><p>คลิกเมนูสิทธิ์การใช้งาน</p></div></div>
<div class="step-card"><div class="step-num">2</div><div class="step-content"><h5>เลือก Role ที่ต้องการกำหนดสิทธิ์</h5><p>เลือกจากรายการ Role ที่มีในระบบ</p></div></div>
<div class="step-card"><div class="step-num">3</div><div class="step-content"><h5>ติ๊กเลือกสิทธิ์ที่ต้องการ</h5><p>เลือกเมนูและฟังก์ชันที่ต้องการให้ Role นั้นเข้าถึงได้</p></div></div>
<div class="step-card"><div class="step-num">4</div><div class="step-content"><h5>กดบันทึก</h5><p>สิทธิ์จะมีผลทันทีสำหรับผู้ใช้ที่ใช้ Role นั้น</p></div></div>
<div class="note-box">
    <i class="fas fa-exclamation-triangle"></i>
    <div><strong>ข้อควรระวัง:</strong> การเปลี่ยนสิทธิ์จะมีผลกับผู้ใช้ทุกคนที่ใช้ Role เดียวกัน ควรตรวจสอบให้ดีก่อนบันทึก</div>
</div>
<?php layout_foot(); ?>
