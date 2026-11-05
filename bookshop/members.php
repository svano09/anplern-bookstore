<?php include "connect.php"; ?>
<?php
require_admin();
$title = "สมาชิก";
include "header.php";

$keyword  = $_GET["q"] ?? "";
$like     = "%" . $keyword . "%";
$page     = max(1, (int)($_GET["page"] ?? 1));
$per_page = 10;

$sql_count = "
    SELECT COUNT(*)
    FROM member
    WHERE CONCAT(first_name, ' ', last_name) LIKE ?
       OR email LIKE ?
";
$count = $pdo->prepare($sql_count);
$count->execute([$like, $like]);
$total = $count->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));
$page = min($page, $total_pages);

// ดึงรายชื่อสมาชิกพร้อมจำนวนคำสั่งซื้อและยอดซื้อสะสมสุทธิ (ไม่นับรายการที่ยกเลิก)
$sql = "
    SELECT
        m.member_id,
        CONCAT(m.first_name, ' ', m.last_name) AS name,
        m.email,
        m.phone,
        m.role,
        m.created_at,
        COUNT(o.order_id) AS order_count,
        IFNULL(SUM(CASE WHEN o.status <> 'cancelled' THEN o.net_total END), 0) AS total_spent,
        MAX(o.order_date) AS last_order
    FROM member m
    LEFT JOIN orders o ON o.member_id = m.member_id
    WHERE CONCAT(m.first_name, ' ', m.last_name) LIKE ?
       OR m.email LIKE ?
    GROUP BY m.member_id
    ORDER BY m.role, total_spent DESC
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $like);
$stmt->bindParam(2, $like);
$stmt->bindValue(3, $per_page, PDO::PARAM_INT);
$stmt->bindValue(4, ($page - 1) * $per_page, PDO::PARAM_INT);
$stmt->execute();
?>
<p class="lead">สมาชิกทั้งหมด <?=$total?> คน &mdash; คลิกจำนวนคำสั่งซื้อเพื่อดูคำสั่งซื้อทั้งหมดของลูกค้าคนนั้น</p>

<form class="filter">
  <label>ค้นหาชื่อหรืออีเมล <input type="text" name="q" value="<?=htmlspecialchars($keyword)?>"></label>
  <input type="submit" value="ค้นหา">
</form>

<div class="table-wrap">
<table class="data">
  <thead>
    <tr><th>ชื่อ</th><th>อีเมล</th><th>โทร</th><th>สิทธิ์</th><th>สมัครเมื่อ</th><th class="num">คำสั่งซื้อ</th><th class="num">ยอดซื้อสะสม</th><th>สั่งล่าสุด</th></tr>
  </thead>
  <tbody>
<?php while ($row = $stmt->fetch()) : ?>
    <tr>
      <td><?=htmlspecialchars($row["name"])?></td>
      <td><?=htmlspecialchars($row["email"])?></td>
      <td><?=htmlspecialchars($row["phone"])?></td>
      <td><span class="badge <?=$row["role"] == "admin" ? "warn" : "info"?>"><?=$row["role"] == "admin" ? "ผู้ดูแล" : "ลูกค้า"?></span></td>
      <td><?=substr($row["created_at"], 0, 10)?></td>
      <td class="num"><?=$row["order_count"] > 0 ? '<a href="orders.php?member_id=' . $row["member_id"] . '">' . $row["order_count"] . "</a>" : "0"?></td>
      <td class="num"><?=number_format($row["total_spent"], 2)?></td>
      <td><?=$row["last_order"] ? substr($row["last_order"], 0, 10) : "-"?></td>
    </tr>
<?php endwhile; ?>
  </tbody>
</table>
</div>

<div class="pager">
<?php for ($p = 1; $p <= $total_pages; $p++) : ?>
<?php   if ($p == $page) : ?>
  <span class="current"><?=$p?></span>
<?php   else : ?>
  <a href="members.php?<?=htmlspecialchars(http_build_query(["q" => $keyword, "page" => $p]))?>"><?=$p?></a>
<?php   endif; ?>
<?php endfor; ?>
</div>
<?php include "footer.php"; ?>
