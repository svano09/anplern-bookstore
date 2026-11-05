<?php include "connect.php"; ?>
<?php
require_admin();
$title = "สรุปผลโปรโมชั่น";
include "header.php";

// สรุปผลการสั่งซื้อและยอดส่วนลดของโปรโมชั่นทุกแคมเปญ
// รวมสถิติของโปรฯ ระดับสินค้า (จาก order_item) และโปรฯ ขั้นบันได (จาก orders) ด้วย UNION ALL
$sql = "SELECT
    p.promo_id,
    p.promo_name,
    p.promo_type,
    p.discount_percent,
    CASE p.promo_type
        WHEN 'tier'     THEN CONCAT('ยอดซื้อตั้งแต่ ', FORMAT(p.min_spend, 0), ' บาท')
        WHEN 'seasonal' THEN IFNULL(c.category_name, 'ทุกหมวด')
        ELSE CONCAT('อยู่ในคลัง >= ', p.min_days_in_stock, ' วัน และเหลือ <= ', p.max_stock, ' เล่ม')
    END AS conditions,
    p.start_date,
    p.end_date,
    CASE
        WHEN p.is_active = 0          THEN 'ปิดใช้งาน'
        WHEN CURDATE() < p.start_date THEN 'ยังไม่เริ่ม'
        WHEN CURDATE() > p.end_date   THEN 'สิ้นสุดแล้ว'
        ELSE 'กำลังใช้งาน'
    END AS promo_status,
    IFNULL(u.order_count, 0) AS order_count,
    IFNULL(u.book_count, 0) AS book_count,
    IFNULL(u.discount_total, 0) AS discount_total
FROM promotion p
LEFT JOIN category c ON c.category_id = p.category_id
LEFT JOIN (
    SELECT
        oi.promo_id,
        COUNT(DISTINCT oi.order_id) AS order_count,
        SUM(oi.quantity) AS book_count,
        SUM(oi.discount_amount) AS discount_total
    FROM order_item oi
    JOIN orders o ON o.order_id = oi.order_id
    WHERE o.status <> 'cancelled'
      AND oi.promo_id IS NOT NULL
    GROUP BY oi.promo_id

    UNION ALL

    SELECT
        o.promo_id,
        COUNT(*) AS order_count,
        SUM(i.book_qty) AS book_count,
        SUM(o.tier_discount) AS discount_total
    FROM orders o
    JOIN (
        SELECT order_id, SUM(quantity) AS book_qty
        FROM order_item
        GROUP BY order_id
    ) i ON i.order_id = o.order_id
    WHERE o.status <> 'cancelled'
      AND o.promo_id IS NOT NULL
    GROUP BY o.promo_id
) u ON u.promo_id = p.promo_id
ORDER BY
    FIELD(p.promo_type, 'tier', 'seasonal', 'clearance'),
    p.start_date,
    p.promo_id";

$stmt = $pdo->prepare($sql);
$stmt->execute();

$type_th = ["tier" => "ยอดซื้อขั้นบันได", "seasonal" => "เทศกาล", "clearance" => "ล้างสต็อก"];
$status_class = ["กำลังใช้งาน" => "ok", "ยังไม่เริ่ม" => "info", "สิ้นสุดแล้ว" => "off", "ปิดใช้งาน" => "warn"];
$total = 0;
?>
<p class="lead">โปรโมชั่นอัตโนมัติ 3 ประเภท คิดตามลำดับ: (1) ระดับสินค้า เลือกส่วนลดที่มากที่สุดระหว่างเทศกาลกับล้างสต็อก &rarr; (2) ยอดซื้อขั้นบันได &rarr; (3) คูปอง</p>

<div class="table-wrap">
<table class="data">
  <thead>
    <tr>
      <th>ประเภท</th><th>ชื่อโปรโมชั่น</th><th>เงื่อนไข</th><th class="num">ส่วนลด</th><th>ช่วงเวลา</th><th>สถานะ</th>
      <th class="num">ใช้ใน (คำสั่งซื้อ)</th><th class="num">จำนวนเล่ม</th><th class="num">ส่วนลดที่ให้ไป (บาท)</th>
    </tr>
  </thead>
  <tbody>
<?php while ($row = $stmt->fetch()) : ?>
<?php   $total += $row["discount_total"]; ?>
    <tr>
      <td><span class="badge info"><?=$type_th[$row["promo_type"]]?></span></td>
      <td><?=htmlspecialchars($row["promo_name"])?></td>
      <td><?=htmlspecialchars($row["conditions"])?></td>
      <td class="num"><?=$row["discount_percent"] + 0?>%</td>
      <td><?=$row["start_date"]?><br><?=$row["end_date"]?></td>
      <td><span class="badge <?=$status_class[$row["promo_status"]]?>"><?=$row["promo_status"]?></span></td>
      <td class="num"><?=$row["order_count"]?></td>
      <td class="num"><?=$row["book_count"]?></td>
      <td class="num"><?=number_format($row["discount_total"], 2)?></td>
    </tr>
<?php endwhile; ?>
  </tbody>
  <tfoot>
    <tr><td colspan="8">รวมส่วนลดจากโปรโมชั่นอัตโนมัติ</td><td class="num"><?=number_format($total, 2)?></td></tr>
  </tfoot>
</table>
</div>

<details class="sql">
  <summary>ดูคำสั่ง SQL</summary>
  <pre><?=htmlspecialchars($sql)?></pre>
</details>
<?php include "footer.php"; ?>
