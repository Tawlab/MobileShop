<?php
require '_layout.php';
layout_head('แดชบอร์ด', 'tachometer-alt', '#10b981', 'ภาพรวมยอดขาย ยอดซ่อม และสถิติสำคัญของร้านค้า');
?>

<div class="section-label"><i class="fas fa-info-circle"></i> แดชบอร์ดคืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        แดชบอร์ด คือ หน้าแรกที่แสดงข้อมูลสรุปภาพรวมของร้านค้าทั้งหมดในรูปแบบกราฟและตัวเลขสถิติ
        ช่วยให้ผู้บริหารและพนักงานทราบสถานการณ์ของธุรกิจได้อย่างรวดเร็ว
        โดยไม่ต้องเปิดดูทีละหน้า
    </p>
</div>

<div class="section-label"><i class="fas fa-list-check"></i> ข้อมูลที่แสดงบนแดชบอร์ด</div>

<div class="info-box">
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-dollar-sign"></i></div>
        <div class="feature-text">
            <strong>ยอดขายรวมวันนี้</strong>
            <span>รวมมูลค่าการขายสินค้าทั้งหมดที่เกิดขึ้นในวันปัจจุบัน</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-tools"></i></div>
        <div class="feature-text">
            <strong>จำนวนงานซ่อม</strong>
            <span>แสดงจำนวนงานซ่อมที่รอดำเนินการ กำลังซ่อม และซ่อมเสร็จแล้ว</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-boxes"></i></div>
        <div class="feature-text">
            <strong>สต็อคสินค้าคงเหลือ</strong>
            <span>ข้อมูลจำนวนสินค้าในคลัง แจ้งเตือนเมื่อสินค้าใกล้หมด</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
        <div class="feature-text">
            <strong>กราฟยอดขายรายวัน / รายเดือน</strong>
            <span>แสดงแนวโน้มยอดขายในรูปแบบกราฟที่เข้าใจง่าย</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-users"></i></div>
        <div class="feature-text">
            <strong>จำนวนลูกค้าและพนักงาน</strong>
            <span>แสดงตัวเลขรวมลูกค้าและพนักงานที่ลงทะเบียนในระบบ</span>
        </div>
    </div>
</div>

<div class="tip-box">
    <i class="fas fa-lightbulb"></i>
    <div>
        <strong>คำแนะนำ:</strong> แดชบอร์ดจะอัปเดตข้อมูลอัตโนมัติทุกครั้งที่โหลดหน้า
        ควรเข้ามาตรวจสอบทุกเช้าก่อนเริ่มงาน เพื่อวางแผนการทำงานในแต่ละวัน
    </div>
</div>

<div class="section-label"><i class="fas fa-mouse-pointer"></i> วิธีเข้าถึงแดชบอร์ด</div>

<div class="step-card">
    <div class="step-num">1</div>
    <div class="step-content">
        <h5>คลิกที่เมนู "แดชบอร์ด" บนแถบเมนูด้านซ้าย</h5>
        <p>หลังจากเข้าสู่ระบบแล้ว ให้คลิกที่ไอคอน 📊 แดชบอร์ด ในแถบเมนูด้านซ้ายมือ</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">2</div>
    <div class="step-content">
        <h5>ระบบจะแสดงข้อมูลสรุปทันที</h5>
        <p>ข้อมูลทั้งหมดจะโหลดขึ้นมาโดยอัตโนมัติ ไม่ต้องกดปุ่มเพิ่มเติม</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">3</div>
    <div class="step-content">
        <h5>เลือกดูรายละเอียดเพิ่มเติม</h5>
        <p>สามารถคลิกที่การ์ดข้อมูลแต่ละอัน เพื่อไปยังหน้าที่เกี่ยวข้องโดยตรง</p>
    </div>
</div>

<?php layout_foot(); ?>
