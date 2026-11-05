<?php include "connect.php"; ?>
<?php
require_admin();
$title = "จัดการโปรโมชั่น";
include "header.php";

$sql = "
    SELECT
        p.*,
        c.category_name,
        CASE
            WHEN p.is_active = 0          THEN 'ปิดใช้งาน'
            WHEN CURDATE() < p.start_date THEN 'ยังไม่เริ่ม'
            WHEN CURDATE() > p.end_date   THEN 'สิ้นสุดแล้ว'
            ELSE 'กำลังใช้งาน'
        END AS promo_status
    FROM promotion p
    LEFT JOIN category c ON c.category_id = p.category_id
    ORDER BY
        FIELD(p.promo_type, 'tier', 'seasonal', 'clearance'),
        p.start_date DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute();

$type_th = ["tier" => "ซื้อครบยอด (ขั้นบันได)", "seasonal" => "เทศกาล", "clearance" => "ล้างสต็อก"];
$status_class = ["กำลังใช้งาน" => "ok", "ยังไม่เริ่ม" => "info", "สิ้นสุดแล้ว" => "off", "ปิดใช้งาน" => "warn"];
?>
<p class="lead">สร้างแคมเปญโปรโมชั่น 3 ประเภท: ซื้อครบยอดลดทั้งตะกร้า, ลดตามเทศกาล (เลือกหมวดได้), ลดล้างสต็อก (อยู่นาน + เหลือน้อย)
  &middot; <a href="promotion-report.php">ดูผลของแต่ละโปรโมชั่น</a></p>
<p><a class="btn" href="promotion-form.php">+ สร้างโปรโมชั่นใหม่</a></p>

<div class="table-wrap">
<table class="data">
  <thead>
    <tr><th>ประเภท</th><th>ชื่อโปรโมชั่น</th><th>เงื่อนไข</th><th class="num">ส่วนลด</th><th>ช่วงเวลา</th><th>สถานะ</th><th>จัดการ</th></tr>
  </thead>
  <tbody>
<?php while ($row = $stmt->fetch()) : ?>
    <tr>
      <td><span class="badge info"><?=$type_th[$row["promo_type"]]?></span></td>
      <td><?=htmlspecialchars($row["promo_name"])?></td>
      <td>
<?php   if ($row["promo_type"] == "tier") : ?>
        ยอดซื้อตั้งแต่ <?=number_format($row["min_spend"])?> บาท
<?php   elseif ($row["promo_type"] == "seasonal") : ?>
        <?=$row["category_name"] ?? "ทุกหมวด"?>
<?php   else : ?>
        อยู่ในคลัง ≥ <?=$row["min_days_in_stock"]?> วัน และเหลือ ≤ <?=$row["max_stock"]?> เล่ม
<?php   endif; ?>
      </td>
      <td class="num"><?=$row["discount_percent"] + 0?>%</td>
      <td><?=$row["start_date"]?> ถึง <?=$row["end_date"]?></td>
      <td><span class="badge <?=$status_class[$row["promo_status"]]?>"><?=$row["promo_status"]?></span></td>
      <td class="actions">
        <a href="promotion-form.php?promo_id=<?=$row["promo_id"]?>">แก้ไข</a> |
        <form method="post" action="promotion-save.php">
          <?=csrf_field()?>
          <input type="hidden" name="promo_id" value="<?=$row["promo_id"]?>">
          <input type="hidden" name="action" value="toggle">
          <button class="link-btn"><?=$row["is_active"] ? "ปิด" : "เปิด"?>ใช้งาน</button>
        </form>
      </td>
    </tr>
<?php endwhile; ?>
  </tbody>
</table>
</div>
<?php include "footer.php"; ?>
