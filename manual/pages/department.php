<?php
require '_layout.php';
layout_head('แผนก', 'sitemap', '#ec4899', 'กำหนดโครงสร้างแผนกในองค์กร รองรับการจัดสรรพนักงาน');
?>
<div class="section-label"><i class="fas fa-info-circle"></i> แผนกคืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        แผนกใช้แบ่งโครงสร้างองค์กร เช่น "แผนกขาย" "แผนกช่าง" "แผนกบัญชี"
        พนักงานแต่ละคนจะสังกัดแผนกใดแผนกหนึ่ง ช่วยให้การบริหารงานและออกรายงานมีความชัดเจนยิ่งขึ้น
    </p>
</div>
<div class="section-label"><i class="fas fa-play-circle"></i> วิธีเพิ่มแผนกใหม่</div>
<div class="step-card"><div class="step-num">1</div><div class="step-content"><h5>ไปที่เมนู "ข้อมูลพนักงาน" → "แผนก"</h5><p>คลิกเมนูเพื่อดูรายการแผนกทั้งหมด</p></div></div>
<div class="step-card"><div class="step-num">2</div><div class="step-content"><h5>กดปุ่ม "เพิ่มแผนกใหม่"</h5><p>กรอกชื่อแผนกที่ต้องการ</p></div></div>
<div class="step-card"><div class="step-num">3</div><div class="step-content"><h5>กดบันทึก</h5><p>แผนกพร้อมใช้งานในการเพิ่มพนักงาน</p></div></div>
<?php layout_foot(); ?>
