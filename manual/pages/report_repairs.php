<?php
require '_layout.php';
layout_head('รายงานยอดซ่อม', 'chart-pie', '#f97316', 'ดูสรุปงานซ่อมตามช่วงเวลา พนักงาน ยี่ห้อ และประเภทสินค้า');
?>

<div class="section-label"><i class="fas fa-info-circle"></i> รายงานยอดซ่อมคืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        รายงานยอดซ่อมช่วยสรุปผลงานซ่อมในช่วงเวลาที่ต้องการ วิเคราะห์ว่าช่างคนไหนซ่อมได้มากที่สุด
        ยี่ห้อไหนมีการซ่อมบ่อย และอาการเสียที่พบบ่อยคืออะไร ข้อมูลนี้ช่วยให้วางแผนบริการได้ดียิ่งขึ้น
    </p>
</div>

<div class="section-label"><i class="fas fa-filter"></i> ตัวกรองรายงาน</div>
<div class="info-box">
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-calendar"></i></div>
        <div class="feature-text">
            <strong>กรองตามช่วงวันที่</strong>
            <span>เลือกช่วงเวลาที่ต้องการดูสรุปงานซ่อม</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-user-tie"></i></div>
        <div class="feature-text">
            <strong>กรองตามช่างซ่อม</strong>
            <span>ดูจำนวนงานและมูลค่าซ่อมแยกตามพนักงานแต่ละคน</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-copyright"></i></div>
        <div class="feature-text">
            <strong>กรองตามยี่ห้อสินค้า</strong>
            <span>ดูว่ายี่ห้อโทรศัพท์ใดมีการนำมาซ่อมมากที่สุด</span>
        </div>
    </div>
</div>

<div class="section-label"><i class="fas fa-play-circle"></i> วิธีดูรายงานยอดซ่อม</div>
<div class="step-card">
    <div class="step-num">1</div>
    <div class="step-content">
        <h5>ไปที่เมนู "รายงาน" → "รายงานยอดซ่อม"</h5>
        <p>คลิกเมนูรายงาน แล้วเลือกรายงานยอดซ่อม</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">2</div>
    <div class="step-content">
        <h5>เลือกช่วงเวลาและตัวกรอง</h5>
        <p>กำหนดวันที่และเลือกเงื่อนไขตามที่ต้องการ</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">3</div>
    <div class="step-content">
        <h5>กดค้นหาและดูผลลัพธ์</h5>
        <p>ระบบแสดงสรุปจำนวนงานซ่อม มูลค่ารวม และรายละเอียดแต่ละรายการ</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">4</div>
    <div class="step-content">
        <h5>พิมพ์รายงาน</h5>
        <p>กดพิมพ์เพื่อออกเอกสารรายงานได้ทันที</p>
    </div>
</div>

<?php layout_foot(); ?>
