<?php
require '_layout.php';
layout_head('สมัครใช้งานระบบ', 'user-plus', '#14b8a6', 'ขั้นตอนการสมัครบัญชีใหม่สำหรับเจ้าของร้าน');
?>
<div class="section-label"><i class="fas fa-info-circle"></i> การสมัครใช้งาน</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        การสมัครใช้งานระบบสำหรับร้านใหม่ที่ยังไม่มีบัญชี เจ้าของร้านจะต้องกรอกข้อมูลร้านค้า
        และสร้างบัญชีผู้ดูแลระบบ (Admin) คนแรก จากนั้นจึงเพิ่มผู้ใช้คนอื่นๆ ได้จากเมนูจัดการผู้ใช้
    </p>
</div>
<div class="section-label"><i class="fas fa-play-circle"></i> ขั้นตอนการสมัคร</div>
<div class="step-card"><div class="step-num">1</div><div class="step-content"><h5>ไปที่หน้าสมัครใช้งาน</h5><p>คลิกลิงก์ "สมัครใช้งาน" ที่หน้าเข้าสู่ระบบ</p></div></div>
<div class="step-card"><div class="step-num">2</div><div class="step-content"><h5>กรอกข้อมูลร้านค้า</h5><p>ระบุชื่อร้าน ที่อยู่ เบอร์โทร และข้อมูลทั่วไปของร้าน</p></div></div>
<div class="step-card"><div class="step-num">3</div><div class="step-content"><h5>ตั้งชื่อผู้ใช้และรหัสผ่านสำหรับเจ้าของร้าน</h5><p>สร้างบัญชีแรกที่มีสิทธิ์ผู้ดูแลระบบ (Admin)</p></div></div>
<div class="step-card"><div class="step-num">4</div><div class="step-content"><h5>กดสมัคร และเข้าสู่ระบบ</h5><p>หลังสมัครสำเร็จ สามารถเข้าสู่ระบบได้ทันที</p></div></div>
<div class="tip-box">
    <i class="fas fa-lightbulb"></i>
    <div><strong>หมายเหตุ:</strong> หลังจากสมัครแล้ว ควรตั้งค่าข้อมูลพื้นฐานให้ครบ เช่น เพิ่มสาขา เพิ่มประเภทสินค้า ยี่ห้อสินค้า และสร้างบัญชีพนักงาน ก่อนเริ่มใช้งานจริง</div>
</div>
<?php layout_foot(); ?>
