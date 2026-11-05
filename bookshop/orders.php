<?php include "connect.php"; ?>
<?php
require_login();
$title = is_admin() ? "จัดการคำสั่งซื้อและสถานะพัสดุ" : "คำสั่งซื้อของฉัน";
include "header.php";

$status   = $_GET["status"] ?? "";
$page     = max(1, (int)($_GET["page"] ?? 1));
$per_page = 10;
$offset   = ($page - 1) * $per_page;

// กำหนดการเข้าถึงข้อมูล: ลูกค้าดูได้เฉพาะของตนเอง ผู้ดูแลดูได้ทั้งหมดหรือกรองตามรายชื่อสมาชิก
$member_id = is_admin() ? (int)($_GET["member_id"] ?? 0) : $_SESSION["member_id"];

// ดึงรายการคำสั่งซื้อ พร้อมจำนวนเล่ม สถานะล่าสุดจาก order_status_log และระยะเวลาดำเนินการ
$sql = "SELECT
    o.order_id,
    o.order_date,
    CONCAT(m.first_name, ' ', m.last_name) AS customer,
    i.book_qty,
    o.net_total,
    o.status,
    o.carrier,
    o.tracking_no,
    l.note AS last_note,
    l.changed_at AS last_update,
    CASE
        WHEN o.status = 'delivered' THEN CONCAT('ส่งถึงภายใน ', DATEDIFF(l.changed_at, o.order_date), ' วัน')
        WHEN o.status = 'cancelled' THEN '-'
        ELSE CONCAT('ผ่านมาแล้ว ', DATEDIFF(CURDATE(), o.order_date), ' วัน')
    END AS lead_time
FROM orders o
JOIN member m ON m.member_id = o.member_id
JOIN (
    SELECT order_id, SUM(quantity) AS book_qty
    FROM order_item
    GROUP BY order_id
) i ON i.order_id = o.order_id
JOIN order_status_log l ON l.log_id = (
    SELECT l2.log_id
    FROM order_status_log l2
    WHERE l2.order_id = o.order_id
    ORDER BY l2.changed_at DESC, l2.log_id DESC
    LIMIT 1
)
WHERE (o.status = ? OR ? = '')
  AND (o.member_id = ? OR ? = 0)
ORDER BY o.order_date DESC
LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $status);
$stmt->bindParam(2, $status);
$stmt->bindParam(3, $member_id);
$stmt->bindParam(4, $member_id);
$stmt->bindValue(5, $per_page, PDO::PARAM_INT);
$stmt->bindValue(6, $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();

// นับจำนวนคำสั่งซื้อแยกตามสถานะเพื่อทำแถบตัวกรองและคำนวณหน้าทั้งหมด
$sql_count = "
    SELECT status, COUNT(*) AS n
    FROM orders
    WHERE (member_id = ? OR ? = 0)
    GROUP BY status
";
$count = $pdo->prepare($sql_count);
$count->bindParam(1, $member_id);
$count->bindParam(2, $member_id);
$count->execute();

$status_count = ["" => 0];
while ($row = $count->fetch()) {
    $status_count[$row["status"]] = $row["n"];
    $status_count[""] += $row["n"];
}
$total_pages = max(1, ceil(($status_count[$status] ?? 0) / $per_page));

$filter_name = "";
if (is_admin() && $member_id) {
    $m = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM member WHERE member_id = ?");
    $m->bindParam(1, $member_id);
    $m->execute();
    $filter_name = $m->fetchColumn();
}
$query = "member_id=" . (is_admin() ? $member_id : 0);
?>
<p class="lead">
<?php if (is_admin()) : ?>
  คลิกเลขที่คำสั่งซื้อเพื่อดูรายละเอียด อัปเดตสถานะ และออกหมายเลขพัสดุ
<?php   if ($filter_name) : ?>
  &middot; แสดงเฉพาะของ <b><?=htmlspecialchars($filter_name)?></b> (<a href="orders.php">ดูทั้งหมด</a>)
<?php   endif; ?>
<?php else : ?>
  ติดตามสถานะคำสั่งซื้อ ผู้ให้บริการขนส่ง และเลขพัสดุ &mdash; คลิกเลขที่คำสั่งซื้อเพื่อดูไทม์ไลน์การจัดส่งแบบเรียลไทม์
<?php endif; ?>
</p>

<div class="pager">
  <?php foreach (["" => "ทั้งหมด"] + $status_th as $key => $label) : ?>
  <?php   if ($key == $status) : ?>
  <span class="current"><?=$label?> (<?=$status_count[$key] ?? 0?>)</span>
  <?php   else : ?>
  <a href="orders.php?<?=$query?>&amp;status=<?=$key?>"><?=$label?> (<?=$status_count[$key] ?? 0?>)</a>
  <?php   endif; ?>
  <?php endforeach; ?>
</div>

<?php if (!$orders) : ?>
<p class="msg info">ยังไม่มีคำสั่งซื้อ<?=is_admin() ? "" : ' &mdash; <a href="search.php">เลือกซื้อหนังสือ</a>'?></p>
<?php else : ?>
<div class="table-wrap">
<table class="data">
  <thead>
    <tr>
      <th>เลขที่</th><th>วันที่สั่ง</th><?=is_admin() ? "<th>ลูกค้า</th>" : ""?><th class="num">จำนวนเล่ม</th><th class="num">ยอดสุทธิ</th>
      <th>สถานะ</th><th>ขนส่ง / เลขพัสดุ</th><th>อัปเดตล่าสุด</th><th>ระยะเวลา</th>
    </tr>
  </thead>
  <tbody>
<?php foreach ($orders as $row) : ?>
    <tr>
      <td><a href="order-detail.php?order_id=<?=$row["order_id"]?>">#<?=$row["order_id"]?></a></td>
      <td><?=substr($row["order_date"], 0, 16)?></td>
<?php   if (is_admin()) : ?>
      <td><?=htmlspecialchars($row["customer"])?></td>
<?php   endif; ?>
      <td class="num"><?=$row["book_qty"]?></td>
      <td class="num"><?=number_format($row["net_total"], 2)?></td>
      <td><span class="badge <?=$row["status"]?>"><?=$status_th[$row["status"]]?></span></td>
      <td><?=$row["tracking_no"] ? htmlspecialchars($row["carrier"]) . "<br><code>" . htmlspecialchars($row["tracking_no"]) . "</code>" : "-"?></td>
      <td><?=substr($row["last_update"], 0, 16)?><br><small><?=htmlspecialchars($row["last_note"])?></small></td>
      <td><?=$row["lead_time"]?></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<div class="pager">
<?php for ($p = 1; $p <= $total_pages; $p++) : ?>
<?php   if ($p == $page) : ?>
  <span class="current"><?=$p?></span>
<?php   else : ?>
  <a href="orders.php?<?=$query?>&amp;status=<?=urlencode($status)?>&amp;page=<?=$p?>"><?=$p?></a>
<?php   endif; ?>
<?php endfor; ?>
</div>

<?php if (is_admin()) : ?>
<details class="sql">
  <summary>ดูคำสั่ง SQL</summary>
  <pre><?=htmlspecialchars($sql)?></pre>
</details>
<?php endif; ?>
<?php include "footer.php"; ?>
