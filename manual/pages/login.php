<?php
require '_layout.php';
layout_head('เข้าสู่ระบบ', 'sign-in-alt', '#14b8a6', 'วิธีเข้าสู่ระบบด้วยชื่อผู้ใช้และรหัสผ่าน');
?>

<div class="section-label"><i class="fas fa-info-circle"></i> หน้าเข้าสู่ระบบคืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        หน้าเข้าสู่ระบบเป็นจุดเริ่มต้นการใช้งาน ผู้ใช้ทุกคนต้องกรอกชื่อผู้ใช้และรหัสผ่าน
        ที่ได้รับจากผู้ดูแลระบบก่อนเข้าใช้งาน ระบบจะแสดงเมนูตามสิทธิ์ของผู้ใช้แต่ละคน
    </p>
</div>

<div class="section-label"><i class="fas fa-play-circle"></i> วิธีเข้าสู่ระบบ</div>
<div class="step-card"><div class="step-num">1</div><div class="step-content"><h5>เปิดเบราว์เซอร์และไปที่ URL ของระบบ</h5><p>พิมพ์ที่อยู่เว็บไซต์ของระบบในแถบ URL</p></div></div>
<div class="step-card"><div class="step-num">2</div><div class="step-content"><h5>กรอกชื่อผู้ใช้ (Username)</h5><p>ใส่ชื่อผู้ใช้ที่ได้รับจากผู้ดูแลระบบ</p></div></div>
<div class="step-card"><div class="step-num">3</div><div class="step-content"><h5>กรอกรหัสผ่าน (Password)</h5><p>ใส่รหัสผ่านที่ตั้งไว้ คลิกไอคอนตาเพื่อดูรหัสที่พิมพ์</p></div></div>
<div class="step-card"><div class="step-num">4</div><div class="step-content"><h5>กดปุ่ม "เข้าสู่ระบบ"</h5><p>หากข้อมูลถูกต้อง ระบบจะพาไปยังหน้าหลักโดยอัตโนมัติ</p></div></div>

<div class="note-box">
    <i class="fas fa-exclamation-triangle"></i>
    <div><strong>หมายเหตุ:</strong> หากเข้าสู่ระบบไม่ได้ ให้ตรวจสอบว่าพิมพ์ชื่อผู้ใช้และรหัสผ่านถูกต้อง
    หากยังไม่ได้ ให้ติดต่อผู้ดูแลระบบหรือใช้ฟังก์ชัน <a href="forgot_password.php">ลืมรหัสผ่าน</a></div>
</div>

<div class="tip-box">
    <i class="fas fa-lightbulb"></i>
    <div><strong>ความปลอดภัย:</strong> ไม่ควรบันทึกรหัสผ่านในเบราว์เซอร์บนคอมพิวเตอร์สาธารณะ
    และควรออกจากระบบทุกครั้งหลังเสร็จงาน</div>
</div>

<?php layout_foot(); ?>
