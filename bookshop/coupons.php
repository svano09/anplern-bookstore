<?php include "connect.php"; ?>
<?php
require_admin();
$title = "จัดการคูปองส่วนลด";
include "header.php";

// ดึงข้อมูลคูปองทั้งหมด พร้อมจำนวนครั้งที่ถูกใช้งาน สิทธิ์คงเหลือ และสถานะปัจจุบัน
$sql = "SELECT
    c.coupon_code,
    c.description,
    c.discount_type,
    c.discount_value,
    c.max_discount,
    c.min_spend,
    c.start_date,
    c.end_date,
    c.usage_limit,
    COUNT(o.order_id) AS used_count,
    c.usage_limit - COUNT(o.order_id) AS remaining,
    IFNULL(SUM(o.coupon_discount), 0) AS discount_given,
    CASE
        WHEN CURDATE() < c.start_date           THEN 'ยังไม่เริ่ม'
        WHEN CURDATE() > c.end_date             THEN 'หมดอายุ'
        WHEN COUNT(o.order_id) >= c.usage_limit THEN 'สิทธิ์เต็ม'
        ELSE 'ใช้งานได้'
    END AS coupon_status
FROM coupon c
LEFT JOIN orders o
    ON o.coupon_code = c.coupon_code
   AND o.status <> 'cancelled'
GROUP BY
    c.coupon_code,
    c.description,
    c.discount_type,
    c.discount_value,
    c.max_discount,
    c.min_spend,
    c.start_date,
    c.end_date,
    c.usage_limit
ORDER BY c.end_date DESC, c.coupon_code";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$status_class = ["ใช้งานได้" => "ok", "ยังไม่เริ่ม" => "info", "หมดอายุ" => "off", "สิทธิ์เต็ม" => "warn"];
?>
<p class="lead">กำหนดเงื่อนไขรหัสคูปอง: ส่วนลดเงินสดหรือเปอร์เซ็นต์ (มีเพดานได้) ยอดซื้อขั้นต่ำ จำนวนสิทธิ์ และช่วงวันที่ใช้ได้</p>
<p><a class="btn" href="coupon-form.php">+ สร้างคูปองใหม่</a></p>

<div class="table-wrap">
<table class="data">
  <thead>
    <tr>
      <th>รหัสคูปอง</th><th>รายละเอียด</th><th class="num">ส่วนลด</th><th class="num">ยอดขั้นต่ำ</th><th>ช่วงเวลาใช้งาน</th>
      <th class="num">ใช้ไป / สิทธิ์ทั้งหมด</th><th class="num">ส่วนลดที่ให้ไป</th><th>สถานะ</th><th>จัดการ</th>
    </tr>
  </thead>
  <tbody>
<?php while ($row = $stmt->fetch()) : ?>
    <tr>
      <td><b><?=$row["coupon_code"]?></b></td>
      <td><?=htmlspecialchars($row["description"])?></td>
      <td class="num"><?=$row["discount_type"] == "percent" ? ($row["discount_value"] + 0) . "%" . ($row["max_discount"] ? " (สูงสุด " . number_format($row["max_discount"]) . ")" : "") : number_format($row["discount_value"]) . " บาท"?></td>
      <td class="num"><?=number_format($row["min_spend"], 2)?></td>
      <td class="nowrap"><?=$row["start_date"]?><br>ถึง <?=$row["end_date"]?></td>
      <td class="num"><?=$row["used_count"]?> / <?=$row["usage_limit"]?><small>เหลือ <?=max(0, $row["remaining"])?> สิทธิ์</small></td>
      <td class="num"><?=number_format($row["discount_given"], 2)?></td>
      <td><span class="badge <?=$status_class[$row["coupon_status"]]?>"><?=$row["coupon_status"]?></span></td>
      <td class="actions">
        <a href="coupon-form.php?code=<?=urlencode($row["coupon_code"])?>">แก้ไข</a>
<?php   if ($row["used_count"] == 0) : ?>
        |
        <form method="post" action="coupon-save.php" onsubmit="return confirm('ลบคูปอง <?=$row["coupon_code"]?> ?')">
          <?=csrf_field()?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="coupon_code" value="<?=$row["coupon_code"]?>">
          <button class="link-btn danger">ลบ</button>
        </form>
<?php   endif; ?>
      </td>
    </tr>
<?php endwhile; ?>
  </tbody>
</table>
</div>

<details class="sql">
  <summary>ดูคำสั่ง SQL</summary>
  <pre><?=htmlspecialchars($sql)?></pre>
</details>
<?php include "footer.php"; ?>
