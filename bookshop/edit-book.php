<?php include "connect.php"; ?>
<?php
require_admin();
$title = "แก้ไขข้อมูลหนังสือ";
include "header.php";

$stmt = $pdo->prepare("SELECT * FROM book WHERE book_id = ?");
$stmt->bindParam(1, $_GET["book_id"]);
$stmt->execute();
$row = $stmt->fetch();

$cats = $pdo->prepare("SELECT category_id, category_name FROM category ORDER BY category_id");
$cats->execute();

if (!$row) {
    echo '<p class="msg error">ไม่พบหนังสือรหัสนี้</p>';
    include "footer.php";
    exit;
}
$cover = cover($row["book_id"]);
?>
<p class="lead">Update: แก้ไขข้อมูลหนังสือรหัส <?=$row["book_id"]?> แล้วกดปุ่ม "บันทึกการแก้ไข"</p>

<form class="edit" action="update-book.php" method="post" enctype="multipart/form-data">
  <?=csrf_field()?>
  <input type="hidden" name="book_id" value="<?=$row["book_id"]?>">

  <label for="title">ชื่อหนังสือ</label>
  <input type="text" id="title" name="title" required maxlength="200" value="<?=htmlspecialchars($row["title"])?>">

  <label for="author">ผู้แต่ง</label>
  <input type="text" id="author" name="author" required maxlength="150" value="<?=htmlspecialchars($row["author"])?>">

  <label for="category_id">หมวดหมู่</label>
  <select id="category_id" name="category_id" required>
<?php while ($c = $cats->fetch()) : ?>
    <option value="<?=$c["category_id"]?>"<?=$c["category_id"] == $row["category_id"] ? " selected" : ""?>><?=$c["category_name"]?></option>
<?php endwhile; ?>
  </select>

  <label for="price">ราคา (บาท)</label>
  <input type="number" id="price" name="price" required min="1" max="99999" step="0.01" value="<?=$row["price"]?>">

  <label for="stock">จำนวนคงเหลือ (เล่ม)</label>
  <input type="number" id="stock" name="stock" required min="0" max="99999" step="1" value="<?=$row["stock"]?>">

  <label for="added_date">วันที่นำเข้าระบบ</label>
  <input type="date" id="added_date" name="added_date" required value="<?=$row["added_date"]?>">

  <label for="description">รายละเอียด</label>
  <textarea id="description" name="description" rows="3"><?=htmlspecialchars($row["description"])?></textarea>

  <label for="cover">ภาพปก (เลือกไฟล์ .jpg ใหม่ถ้าต้องการเปลี่ยน)</label>
  <img src="<?=$cover?>" width="90" alt="ภาพปกเดิม">
  <input type="file" id="cover" name="cover" accept="image/jpeg">

  <p>
    <input type="submit" value="บันทึกการแก้ไข">
    <a class="btn secondary" href="list-book.php">ยกเลิก</a>
  </p>
</form>
<?php include "footer.php"; ?>
