<?php include "connect.php"; ?>
<?php
require_admin();
$title = "หนังสือขายดี 3 อันดับแรกของแต่ละหมวด";
include "header.php";

// จัดอันดับหนังสือขายดีภายในแต่ละหมวดด้วย Window Function:
//   ROW_NUMBER() OVER (PARTITION BY category_id ...) -> เริ่มนับอันดับ 1 ใหม่ทุกหมวด
//   SUM(SUM(...)) OVER (PARTITION BY category_id)    -> ยอดขายรวมทั้งหมวดเพื่อคิดสัดส่วน %
$sql = "SELECT
    category_name,
    rank_no,
    book_id,
    title,
    qty_sold,
    revenue,
    pct_of_category
FROM (
    SELECT
        c.category_id,
        c.category_name,
        b.book_id,
        b.title,
        SUM(oi.quantity) AS qty_sold,
        SUM(oi.quantity * oi.unit_price - oi.discount_amount) AS revenue,
        ROUND(
            100 * SUM(oi.quantity * oi.unit_price - oi.discount_amount)
            / SUM(SUM(oi.quantity * oi.unit_price - oi.discount_amount)) OVER (PARTITION BY c.category_id),
            1
        ) AS pct_of_category,
        ROW_NUMBER() OVER (
            PARTITION BY c.category_id
            ORDER BY
                SUM(oi.quantity) DESC,
                SUM(oi.quantity * oi.unit_price - oi.discount_amount) DESC
        ) AS rank_no
    FROM order_item oi
    JOIN orders o ON o.order_id = oi.order_id
    JOIN book b ON b.book_id = oi.book_id
    JOIN category c ON c.category_id = b.category_id
    WHERE o.status <> 'cancelled'
    GROUP BY c.category_id, c.category_name, b.book_id, b.title
) AS ranked
WHERE rank_no <= 3
ORDER BY category_id, rank_no";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$last_category = "";
?>
<p class="lead">อันดับหนังสือขายดีภายในแต่ละหมวด (นับเฉพาะคำสั่งซื้อที่ไม่ถูกยกเลิก) ยอดขายคิดหลังหักส่วนลดระดับสินค้า</p>

<div class="table-wrap">
<table class="data">
  <thead>
    <tr><th>หมวดหมู่</th><th class="num">อันดับ</th><th>ชื่อหนังสือ</th><th class="num">ขายได้ (เล่ม)</th><th class="num">ยอดขาย (บาท)</th><th class="num">สัดส่วนในหมวด</th><th>กราฟ</th></tr>
  </thead>
  <tbody>
<?php while ($row = $stmt->fetch()) : ?>
    <tr>
      <td><?=$row["category_name"] != $last_category ? "<b>" . $row["category_name"] . "</b>" : ""?></td>
      <td class="num"><?=$row["rank_no"]?></td>
      <td><?=htmlspecialchars($row["title"])?></td>
      <td class="num"><?=$row["qty_sold"]?></td>
      <td class="num"><?=number_format($row["revenue"], 2)?></td>
      <td class="num"><?=$row["pct_of_category"]?>%</td>
      <td class="bar-cell"><div class="bar" style="width: <?=round($row["pct_of_category"])?>%"></div></td>
    </tr>
<?php   $last_category = $row["category_name"]; ?>
<?php endwhile; ?>
  </tbody>
</table>
</div>

<details class="sql">
  <summary>ดูคำสั่ง SQL</summary>
  <pre><?=htmlspecialchars($sql)?></pre>
</details>
<?php include "footer.php"; ?>
