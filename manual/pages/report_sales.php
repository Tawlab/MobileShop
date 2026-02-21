<?php
require '_layout.php';
layout_head('รายงานยอดขาย', 'chart-line', '#10b981', 'ดูสรุปยอดขายตามช่วงเวลา พนักงาน ยี่ห้อ และประเภทสินค้า');
?>

<div class="section-label"><i class="fas fa-info-circle"></i> รายงานยอดขายคืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        รายงานยอดขายช่วยให้ผู้บริหารสามารถดูสรุปผลการขายในช่วงเวลาที่ต้องการ
        วิเคราะห์ว่าสินค้าใดขายได้ดี พนักงานคนไหนมียอดสูง และเทรนด์การขายเป็นอย่างไร
        สามารถพิมพ์เป็นรายงานได้ทันที
    </p>
</div>

<div class="section-label"><i class="fas fa-filter"></i> ตัวกรองรายงาน</div>
<div class="info-box">
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-calendar"></i></div>
        <div class="feature-text">
            <strong>กรองตามช่วงวันที่</strong>
            <span>เลือกวันที่เริ่มต้นและสิ้นสุดเพื่อดูยอดในช่วงนั้น</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-user-tie"></i></div>
        <div class="feature-text">
            <strong>กรองตามพนักงาน</strong>
            <span>ดูยอดขายแยกตามพนักงานแต่ละคน</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-copyright"></i></div>
        <div class="feature-text">
            <strong>กรองตามยี่ห้อสินค้า</strong>
            <span>ดูว่ายี่ห้อใดขายดีที่สุดในช่วงเวลาที่เลือก</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-tags"></i></div>
        <div class="feature-text">
            <strong>กรองตามประเภทสินค้า</strong>
            <span>เปรียบเทียบยอดขายระหว่างประเภทสินค้าต่างๆ</span>
        </div>
    </div>
</div>

<div class="section-label"><i class="fas fa-play-circle"></i> วิธีดูรายงานยอดขาย</div>
<div class="step-card">
    <div class="step-num">1</div>
    <div class="step-content">
        <h5>ไปที่เมนู "รายงาน" → "รายงานยอดขาย"</h5>
        <p>คลิกเมนูรายงานและเลือกรายงานยอดขาย</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">2</div>
    <div class="step-content">
        <h5>กำหนดช่วงเวลาที่ต้องการ</h5>
        <p>เลือกวันที่เริ่มต้นและสิ้นสุด จากนั้นเลือกตัวกรองเพิ่มเติมตามต้องการ</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">3</div>
    <div class="step-content">
        <h5>กดปุ่มค้นหา</h5>
        <p>ระบบจะแสดงข้อมูลสรุปและรายละเอียดการขายตามเงื่อนไขที่กำหนด</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">4</div>
    <div class="step-content">
        <h5>พิมพ์หรือส่งออกรายงาน</h5>
        <p>กดปุ่มพิมพ์เพื่อออกรายงานเป็นเอกสาร</p>
    </div>
</div>

<div class="tip-box">
    <i class="fas fa-lightbulb"></i>
    <div>
        <strong>คำแนะนำ:</strong> ควรดูรายงานยอดขายสม่ำเสมอ เช่น ทุกสัปดาห์หรือทุกเดือน
        เพื่อวิเคราะห์แนวโน้มและวางแผนการสั่งซื้อสินค้าล่วงหน้า
    </div>
</div>

<?php layout_foot(); ?>
