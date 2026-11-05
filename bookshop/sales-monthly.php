<?php include "connect.php"; ?>
<?php
require_admin();
$title = "รายงานยอดขายรายเดือน";
include "header.php";

$date_from = $_GET["date_from"] ?? report_start($pdo);
$date_to   = $_GET["date_to"] ?? date("Y-m-d");

// สรุปยอดขายรายเดือน แยกส่วนลดแต่ละชั้น (ไม่นับคำสั่งซื้อที่ยกเลิก)
//   Subquery i รวมจำนวนเล่มต่อคำสั่งซื้อก่อน JOIN เพื่อไม่ให้ยอดเงินในตาราง orders ถูกนับซ้ำ
$sql = "SELECT
    DATE_FORMAT(o.order_date, '%Y-%m') AS sale_month,
    COUNT(*) AS order_count,
    SUM(i.book_qty) AS book_qty,
    SUM(o.subtotal) AS gross_sales,
    SUM(o.item_discount) AS item_discount,
    SUM(o.tier_discount) AS tier_discount,
    SUM(o.coupon_discount) AS coupon_discount,
    SUM(o.net_total) AS net_sales,
    ROUND(AVG(o.net_total), 2) AS avg_order
FROM orders o
JOIN (
    SELECT
        order_id,
        SUM(quantity) AS book_qty
    FROM order_item
    GROUP BY order_id
) i ON i.order_id = o.order_id
WHERE o.status <> 'cancelled'
  AND DATE(o.order_date) BETWEEN ? AND ?
GROUP BY DATE_FORMAT(o.order_date, '%Y-%m')
ORDER BY sale_month";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $date_from);
$stmt->bindParam(2, $date_to);
$stmt->execute();
$rows = $stmt->fetchAll();

$thai_month = ["", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
$max_net = 0;
$sum = ["order_count" => 0, "book_qty" => 0, "gross_sales" => 0, "item_discount" => 0,
        "tier_discount" => 0, "coupon_discount" => 0, "net_sales" => 0];
foreach ($rows as $row) {
    $max_net = max($max_net, $row["net_sales"]);
    foreach ($sum as $k => $v) $sum[$k] += $row[$k];
}
?>
<p class="lead">ยอดขายสุทธิรายเดือน พร้อมส่วนลดแต่ละชั้น: ส่วนลดระดับสินค้า (เทศกาล/ล้างสต็อก) &rarr; ส่วนลดยอดซื้อขั้นบันได &rarr; คูปอง</p>

<form class="filter">
  <label>ตั้งแต่วันที่ <input type="date" name="date_from" value="<?=htmlspecialchars($date_from)?>" required></label>
  <label>ถึงวันที่ <input type="date" name="date_to" value="<?=htmlspecialchars($date_to)?>" required></label>
  <input type="submit" value="แสดงรายงาน">
</form>

<?php if ($rows) : ?>
<section class="panel">
  <h2>กราฟยอดขายสุทธิและส่วนลดรายเดือน</h2>
  <div class="chart-box"><canvas id="sales-chart"></canvas></div>
</section>
<script src="js/chart.umd.min.js"></script>
<script>
  // วาดกราฟแท่งซ้อน (Stacked Bar): ยอดขายสุทธิ + ส่วนลดแต่ละชั้น = ยอดก่อนลด
  var rows = <?=json_encode($rows)?>;
  var labels = <?=json_encode(array_map(function ($r) use ($thai_month) {
      list($y, $m) = explode("-", $r["sale_month"]);
      return $thai_month[(int)$m] . " " . $y;
  }, $rows), JSON_UNESCAPED_UNICODE)?>;
  function column(name) {
    return rows.map(function (r) { return Number(r[name]); });
  }
  new Chart(document.getElementById("sales-chart"), {
    type: "bar",
    data: {
      labels: labels,
      datasets: [
        { label: "ยอดขายสุทธิ", data: column("net_sales"), backgroundColor: "#22577a" },
        { label: "ลดระดับสินค้า", data: column("item_discount"), backgroundColor: "#ef8354" },
        { label: "ลดขั้นบันได", data: column("tier_discount"), backgroundColor: "#ffd166" },
        { label: "ลดคูปอง", data: column("coupon_discount"), backgroundColor: "#8e7dbe" }
      ]
    },
    options: { responsive: true, maintainAspectRatio: false,
               scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } } }
  });
</script>
<?php endif; ?>

<div class="table-wrap">
<table class="data">
  <thead>
    <tr>
      <th>เดือน</th><th class="num">คำสั่งซื้อ</th><th class="num">จำนวนเล่ม</th><th class="num">ยอดก่อนลด</th>
      <th class="num">ลดระดับสินค้า</th><th class="num">ลดขั้นบันได</th><th class="num">ลดคูปอง</th>
      <th class="num">ยอดขายสุทธิ</th><th class="num">เฉลี่ย/คำสั่งซื้อ</th><th>กราฟยอดขายสุทธิ</th>
    </tr>
  </thead>
  <tbody>
<?php foreach ($rows as $row) : ?>
<?php   list($y, $m) = explode("-", $row["sale_month"]); ?>
    <tr>
      <td><?=$thai_month[(int)$m]?> <?=$y?></td>
      <td class="num"><?=$row["order_count"]?></td>
      <td class="num"><?=$row["book_qty"]?></td>
      <td class="num"><?=number_format($row["gross_sales"], 2)?></td>
      <td class="num"><?=number_format($row["item_discount"], 2)?></td>
      <td class="num"><?=number_format($row["tier_discount"], 2)?></td>
      <td class="num"><?=number_format($row["coupon_discount"], 2)?></td>
      <td class="num"><b><?=number_format($row["net_sales"], 2)?></b></td>
      <td class="num"><?=number_format($row["avg_order"], 2)?></td>
      <td class="bar-cell"><div class="bar" style="width: <?=round($row["net_sales"] / $max_net * 100)?>%"></div></td>
    </tr>
<?php endforeach; ?>
  </tbody>
<?php if ($rows) : ?>
  <tfoot>
    <tr>
      <td>รวม</td>
      <td class="num"><?=$sum["order_count"]?></td>
      <td class="num"><?=$sum["book_qty"]?></td>
      <td class="num"><?=number_format($sum["gross_sales"], 2)?></td>
      <td class="num"><?=number_format($sum["item_discount"], 2)?></td>
      <td class="num"><?=number_format($sum["tier_discount"], 2)?></td>
      <td class="num"><?=number_format($sum["coupon_discount"], 2)?></td>
      <td class="num"><?=number_format($sum["net_sales"], 2)?></td>
      <td class="num"><?=number_format($sum["net_sales"] / $sum["order_count"], 2)?></td>
      <td></td>
    </tr>
  </tfoot>
<?php endif; ?>
</table>
</div>
<?php if (!$rows) : ?>
<p class="msg error">ไม่มียอดขายในช่วงวันที่ที่เลือก</p>
<?php endif; ?>

<details class="sql">
  <summary>ดูคำสั่ง SQL</summary>
  <pre><?=htmlspecialchars($sql)?></pre>
</details>
<?php include "footer.php"; ?>
