<?php
require '_layout.php';
layout_head('พนักงาน', 'user-tie', '#6366f1', 'บันทึกข้อมูลพนักงาน เพิ่ม/แก้ไข/ปิดใช้งานบัญชีพนักงาน');
?>

<div class="section-label"><i class="fas fa-info-circle"></i> ระบบข้อมูลพนักงานคืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        ระบบพนักงานเก็บข้อมูลส่วนตัวของบุคลากรทุกคนในร้าน ตั้งแต่ชื่อ เลขบัตรประชาชน
        แผนกที่สังกัด ตำแหน่ง ไปจนถึงวันที่เริ่มงาน ช่วยให้ฝ่ายจัดการดูแลบุคลากรได้อย่างเป็นระบบ
    </p>
</div>

<div class="section-label"><i class="fas fa-list-check"></i> ฟังก์ชันหลัก</div>
<div class="info-box">
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-user-plus"></i></div>
        <div class="feature-text">
            <strong>เพิ่มพนักงานใหม่</strong>
            <span>บันทึกข้อมูลส่วนตัว ตำแหน่ง แผนก และเอกสารสำคัญ</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-edit"></i></div>
        <div class="feature-text">
            <strong>แก้ไขข้อมูลพนักงาน</strong>
            <span>อัปเดตข้อมูลส่วนตัว ตำแหน่ง หรือแผนกเมื่อมีการเปลี่ยนแปลง</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-toggle-off"></i></div>
        <div class="feature-text">
            <strong>ปิดใช้งานบัญชี</strong>
            <span>ปิดการใช้งานพนักงานที่ลาออกโดยไม่ต้องลบข้อมูลออก</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-print"></i></div>
        <div class="feature-text">
            <strong>พิมพ์ข้อมูลพนักงาน</strong>
            <span>พิมพ์รายงานข้อมูลส่วนตัวและประวัติของพนักงาน</span>
        </div>
    </div>
</div>

<div class="section-label"><i class="fas fa-play-circle"></i> วิธีเพิ่มพนักงานใหม่</div>
<div class="step-card">
    <div class="step-num">1</div>
    <div class="step-content">
        <h5>ไปที่เมนู "ข้อมูลพนักงาน" → "พนักงาน"</h5>
        <p>คลิกเมนูด้านซ้ายเพื่อดูรายชื่อพนักงานทั้งหมด</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">2</div>
    <div class="step-content">
        <h5>กดปุ่ม "เพิ่มพนักงานใหม่"</h5>
        <p>เปิดฟอร์มสำหรับกรอกข้อมูลพนักงาน</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">3</div>
    <div class="step-content">
        <h5>กรอกข้อมูลส่วนตัวให้ครบถ้วน</h5>
        <p>ระบุชื่อ-นามสกุล เลขบัตรประชาชน เบอร์โทร แผนก ตำแหน่ง และวันที่เริ่มงาน</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">4</div>
    <div class="step-content">
        <h5>กดบันทึก</h5>
        <p>ข้อมูลพนักงานจะถูกบันทึกในระบบเรียบร้อย</p>
    </div>
</div>

<div class="note-box">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong>ข้อควรทราบ:</strong> "พนักงาน" ในระบบนี้แตกต่างจาก "บัญชีผู้ใช้งาน"
        หากต้องการให้พนักงานเข้าสู่ระบบได้ ต้องสร้างบัญชีใน <a href="users.php">จัดการผู้ใช้งาน</a> ด้วย
    </div>
</div>

<?php layout_foot(); ?>
