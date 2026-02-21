<?php
require '_layout.php';
layout_head('ลืมรหัสผ่าน', 'unlock-alt', '#14b8a6', 'ขั้นตอนการรีเซ็ตรหัสผ่านผ่านอีเมลหรือ OTP');
?>
<div class="section-label"><i class="fas fa-info-circle"></i> ระบบลืมรหัสผ่าน</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        หากลืมรหัสผ่าน สามารถรีเซ็ตได้ด้วยตนเองผ่านอีเมลหรือ OTP
        โดยไม่ต้องติดต่อผู้ดูแลระบบ ขอให้มีอีเมลที่ผูกกับบัญชีไว้ล่วงหน้า
    </p>
</div>
<div class="section-label"><i class="fas fa-play-circle"></i> ขั้นตอนการรีเซ็ตรหัสผ่าน</div>
<div class="step-card"><div class="step-num">1</div><div class="step-content"><h5>คลิก "ลืมรหัสผ่าน" ที่หน้าเข้าสู่ระบบ</h5><p>ลิงก์จะอยู่ด้านล่างของฟอร์มเข้าสู่ระบบ</p></div></div>
<div class="step-card"><div class="step-num">2</div><div class="step-content"><h5>กรอกชื่อผู้ใช้หรืออีเมลของคุณ</h5><p>ระบบจะตรวจสอบว่าบัญชีมีอยู่จริง</p></div></div>
<div class="step-card"><div class="step-num">3</div><div class="step-content"><h5>รับ OTP และกรอกยืนยัน</h5><p>ระบบส่ง OTP ไปยังช่องทางที่ผูกไว้ กรอก OTP เพื่อยืนยันตัวตน</p></div></div>
<div class="step-card"><div class="step-num">4</div><div class="step-content"><h5>ตั้งรหัสผ่านใหม่</h5><p>กรอกรหัสผ่านใหม่ 2 ครั้งเพื่อยืนยัน แล้วกดบันทึก</p></div></div>
<div class="note-box">
    <i class="fas fa-exclamation-triangle"></i>
    <div><strong>ข้อควรทราบ:</strong> หาก OTP หมดอายุหรือไม่ได้รับ ให้ลองใหม่อีกครั้ง
    หากยังไม่ได้ผล ให้ติดต่อผู้ดูแลระบบเพื่อรีเซ็ตรหัสผ่านให้</div>
</div>
<?php layout_foot(); ?>
