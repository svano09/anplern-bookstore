<?php include "connect.php"; ?>
<?php
require_admin();
$title = "สินค้าค้างสต็อก / ล้างสต็อก";
include "header.php";

// ค้นหาหนังสือที่อยู่ในคลังนานหรือเหลือน้อย พร้อมคำนวณส่วนลดล้างสต็อกอัตโนมัติ
//   r = เกณฑ์ขั้นต่ำของกฎล้างสต็อกที่เปิดใช้อยู่ (ใช้คัดหนังสือที่เข้าข่ายมาแสดง)
//   s = ยอดขายย้อนหลัง 90 วันของแต่ละเล่ม
//   p = กฎล้างสต็อกขั้นที่ลดมากที่สุดที่หนังสือเล่มนั้นเข้าเงื่อนไขครบทั้ง 2 ข้อ
$sql = "SELECT
    b.book_id,
    b.title,
    c.category_name,
    b.added_date,
    DATEDIFF(CURDATE(), b.added_date) AS days_in_stock,
    b.stock,
    IFNULL(s.sold_90d, 0) AS sold_90d,
    b.price,
    p.promo_name,
    IFNULL(p.discount_percent, 0) AS discount_percent,
    ROUND(b.price * (100 - IFNULL(p.discount_percent, 0)) / 100, 2) AS clearance_price,
    CASE
        WHEN p.promo_id IS NOT NULL THEN 'ลดราคาล้างสต็อก'
        WHEN DATEDIFF(CURDATE(), b.added_date) < r.need_days THEN 'เหลือน้อย แต่เพิ่งเข้าระบบ'
        ELSE 'อยู่นาน แต่สต็อกยังเหลือมาก'
    END AS clearance_status
FROM book b
JOIN category c ON c.category_id = b.category_id
CROSS JOIN (
    SELECT
        MIN(min_days_in_stock) AS need_days,
        MAX(max_stock) AS need_stock
    FROM promotion
    WHERE promo_type = 'clearance'
      AND is_active = 1
      AND CURDATE() BETWEEN start_date AND end_date
) r
LEFT JOIN (
    SELECT
        oi.book_id,
        SUM(oi.quantity) AS sold_90d
    FROM order_item oi
    JOIN orders o ON o.order_id = oi.order_id
    WHERE o.status <> 'cancelled'
      AND o.order_date >= CURDATE() - INTERVAL 90 DAY
    GROUP BY oi.book_id
) s ON s.book_id = b.book_id
LEFT JOIN (
    SELECT
        b2.book_id,
        p2.promo_id,
        p2.promo_name,
        p2.discount_percent,
        ROW_NUMBER() OVER (
            PARTITION BY b2.book_id
            ORDER BY p2.discount_percent DESC, p2.promo_id ASC
        ) AS rn
    FROM book b2
    JOIN promotion p2
        ON p2.promo_type = 'clearance'
       AND p2.is_active = 1
       AND CURDATE() BETWEEN p2.start_date AND p2.end_date
       AND DATEDIFF(CURDATE(), b2.added_date) >= p2.min_days_in_stock
       AND b2.stock <= p2.max_stock
) p ON p.book_id = b.book_id AND p.rn = 1
WHERE DATEDIFF(CURDATE(), b.added_date) >= r.need_days
   OR b.stock <= r.need_stock
ORDER BY discount_percent DESC, days_in_stock DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();

$rulesSql = "
    SELECT promo_name, discount_percent, min_days_in_stock, max_stock, start_date, end_date
    FROM promotion
    WHERE promo_type = 'clearance'
    ORDER BY discount_percent
";
$rules = $pdo->prepare($rulesSql);
$rules->execute();
?>
<p class="lead">ระบบลดราคาอัตโนมัติให้หนังสือที่ <b>อยู่ในระบบนาน</b> และ <b>เหลือน้อย</b> (ต้องเข้าทั้ง 2 เงื่อนไข) ตามกฎล้างสต็อกที่ผู้ดูแลกำหนด</p>

<h2>กฎล้างสต็อก</h2>
<div class="table-wrap">
<table class="data">
  <thead><tr><th>ชื่อกฎ</th><th class="num">ส่วนลด</th><th class="num">อยู่ในคลังอย่างน้อย</th><th class="num">เหลือไม่เกิน</th><th>ช่วงเวลาใช้งาน</th></tr></thead>
  <tbody>
<?php while ($row = $rules->fetch()) : ?>
    <tr>
      <td><?=$row["promo_name"]?></td>
      <td class="num"><?=$row["discount_percent"] + 0?>%</td>
      <td class="num"><?=$row["min_days_in_stock"]?> วัน</td>
      <td class="num"><?=$row["max_stock"]?> เล่ม</td>
      <td><?=$row["start_date"]?> ถึง <?=$row["end_date"]?></td>
    </tr>
<?php endwhile; ?>
  </tbody>
</table>
</div>

<h2>หนังสือที่อยู่นานหรือเหลือน้อย</h2>
<div class="table-wrap">
<table class="data">
  <thead>
    <tr>
      <th>รหัส</th><th>ชื่อหนังสือ</th><th>หมวดหมู่</th><th>วันที่เข้าระบบ</th>
      <th class="num">อยู่ในคลัง (วัน)</th><th class="num">คงเหลือ</th><th class="num">ขายได้ 90 วัน</th>
      <th class="num">ราคาเดิม</th><th class="num">ส่วนลด</th><th class="num">ราคาล้างสต็อก</th><th>สถานะ</th>
    </tr>
  </thead>
  <tbody>
<?php while ($row = $stmt->fetch()) : ?>
<?php   $is_sale = $row["discount_percent"] > 0; ?>
    <tr>
      <td><?=$row["book_id"]?></td>
      <td><?=htmlspecialchars($row["title"])?></td>
      <td><?=$row["category_name"]?></td>
      <td><?=$row["added_date"]?></td>
      <td class="num"><?=$row["days_in_stock"]?></td>
      <td class="num"><?=$row["stock"]?></td>
      <td class="num"><?=$row["sold_90d"]?></td>
      <td class="num"><?=$is_sale ? "<del>" . number_format($row["price"], 2) . "</del>" : number_format($row["price"], 2)?></td>
      <td class="num"><?=$is_sale ? ($row["discount_percent"] + 0) . "%" : "-"?></td>
      <td class="num"><?=$is_sale ? '<span class="price-sale">' . number_format($row["clearance_price"], 2) . "</span>" : "-"?></td>
      <td><span class="badge <?=$is_sale ? "sale" : "off"?>"><?=$row["clearance_status"]?></span></td>
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
