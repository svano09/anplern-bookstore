<?php include "connect.php"; ?>
<?php
require_admin();
$title = "จัดการข้อมูลหนังสือ (CRUD)";
include "header.php";

$keyword  = $_GET["q"] ?? "";
$low      = !empty($_GET["low"]) ? 1 : 0;
$like     = "%" . $keyword . "%";
$page     = max(1, (int)($_GET["page"] ?? 1));
$per_page = 10;

$where = "
    WHERE (b.title LIKE ? OR b.author LIKE ?)
      AND (b.stock <= 5 OR ? = 0)
";

$count = $pdo->prepare("SELECT COUNT(*) FROM book b $where");
$count->execute([$like, $like, $low]);
$total = $count->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));
$page = min($page, $total_pages);

$sql = "
    SELECT
        b.book_id,
        b.title,
        b.author,
        c.category_name,
        b.price,
        b.stock,
        b.added_date
    FROM book b
    JOIN category c ON c.category_id = b.category_id
    $where
    ORDER BY b.book_id
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $like);
$stmt->bindParam(2, $like);
$stmt->bindParam(3, $low);
$stmt->bindValue(4, $per_page, PDO::PARAM_INT);
$stmt->bindValue(5, ($page - 1) * $per_page, PDO::PARAM_INT);
$stmt->execute();

$messages = [
    "added"         => ["ok", "เพิ่มหนังสือใหม่เรียบร้อย"],
    "updated"       => ["ok", "แก้ไขข้อมูลหนังสือเรียบร้อย"],
    "deleted"       => ["ok", "ลบหนังสือเรียบร้อย"],
    "delete_failed" => ["error", "ลบไม่ได้ เพราะหนังสือเล่มนี้มีประวัติการสั่งซื้อแล้ว (แนะนำให้แก้จำนวนคงเหลือเป็น 0 แทน)"],
    "bad_cover"     => ["error", "บันทึกข้อมูลแล้ว แต่ไม่ได้เปลี่ยนภาพปก เพราะไฟล์ไม่ใช่ภาพ JPG หรือดึงภาพปกไม่สำเร็จ"],
];
$msg = $messages[$_GET["msg"] ?? ""] ?? null;
$params = ["q" => $keyword, "low" => $low];
?>
<script>
function confirmDelete(form, book_id, title) {
  return confirm("ต้องการลบหนังสือรหัส " + book_id + " : " + title + " ?");
}
</script>

<p class="lead">เพิ่ม ลบ แก้ไข ข้อมูลหนังสือ ราคา และจำนวนสต็อก</p>

<?php if ($msg) : ?>
<p class="msg <?=$msg[0]?>"><?=$msg[1]?><?=isset($_GET["book_id"]) ? " (รหัส " . (int)$_GET["book_id"] . ")" : ""?></p>
<?php endif; ?>

<form class="filter">
  <label>ค้นหาชื่อเรื่อง/ผู้แต่ง <input type="text" name="q" value="<?=htmlspecialchars($keyword)?>"></label>
  <label class="check"><input type="checkbox" name="low" value="1"<?=$low ? " checked" : ""?>> เฉพาะที่เหลือ ≤ 5 เล่ม</label>
  <input type="submit" value="ค้นหา">
  <a class="btn" href="add-book.php">+ เพิ่มหนังสือใหม่</a>
</form>

<p>ทั้งหมด <b><?=$total?></b> รายการ</p>
<div class="table-wrap">
<table class="data">
  <thead>
    <tr><th>ปก</th><th>รหัส</th><th>ชื่อหนังสือ</th><th>ผู้แต่ง</th><th>หมวดหมู่</th><th class="num">ราคา</th><th class="num">คงเหลือ</th><th>วันที่เข้าระบบ</th><th>จัดการ</th></tr>
  </thead>
  <tbody>
<?php while ($row = $stmt->fetch()) : ?>
    <tr>
      <td><img src="<?=cover($row["book_id"])?>" width="44" alt=""></td>
      <td><?=$row["book_id"]?></td>
      <td><a href="book.php?book_id=<?=$row["book_id"]?>"><?=htmlspecialchars($row["title"])?></a></td>
      <td><?=htmlspecialchars($row["author"])?></td>
      <td><?=$row["category_name"]?></td>
      <td class="num"><?=number_format($row["price"], 2)?></td>
      <td class="num<?=$row["stock"] <= 5 ? " warn-text" : ""?>"><?=$row["stock"]?></td>
      <td><?=$row["added_date"]?></td>
      <td class="actions">
        <a href="edit-book.php?book_id=<?=$row["book_id"]?>">แก้ไข</a> |
        <form method="post" action="delete-book.php"
              onsubmit='return confirmDelete(this, <?=$row["book_id"]?>, <?=json_encode($row["title"], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)?>)'>
          <?=csrf_field()?>
          <input type="hidden" name="book_id" value="<?=$row["book_id"]?>">
          <button class="link-btn danger">ลบ</button>
        </form>
      </td>
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
  <a href="list-book.php?<?=htmlspecialchars(http_build_query($params + ["page" => $p]))?>"><?=$p?></a>
<?php   endif; ?>
<?php endfor; ?>
</div>
<?php include "footer.php"; ?>
