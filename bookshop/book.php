<?php include "connect.php"; ?>
<?php
$book_id = (int)($_GET["book_id"] ?? 0);

$sql = "
    SELECT b.*, c.category_name
    FROM book b
    JOIN category c ON c.category_id = b.category_id
    WHERE b.book_id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $book_id);
$stmt->execute();
$book = $stmt->fetch();

$title = $book ? $book["title"] : "ไม่พบหนังสือ";
include "header.php";

if (!$book) {
    echo '<p class="msg error">ไม่พบหนังสือรหัสนี้ <a href="search.php">กลับไปค้นหา</a></p>';
    include "footer.php";
    exit;
}

// คำนวณราคาหลังหักโปรโมชั่นระดับสินค้า
$price = price_cart($pdo, [$book_id => 1], "");
$line  = $price["lines"][0];

// สุ่มหนังสือในหมวดหมู่เดียวกันมาแสดง 4 เล่ม
$sql_related = "
    SELECT book_id, title, author, price
    FROM book
    WHERE category_id = ? AND book_id <> ?
    ORDER BY RAND()
    LIMIT 4
";
$related = $pdo->prepare($sql_related);
$related->bindParam(1, $book["category_id"]);
$related->bindParam(2, $book_id);
$related->execute();
?>
<section class="book-detail">
  <figure class="cover">
    <img src="<?=cover($book_id)?>" alt="ปกหนังสือ <?=htmlspecialchars($book["title"])?>">
<?php if ($line["discount_percent"] > 0) : ?>
    <span class="off">-<?=$line["discount_percent"] + 0?>%</span>
<?php endif; ?>
  </figure>
  <div class="info">
    <p class="author">โดย <b><?=htmlspecialchars($book["author"])?></b> &middot; หมวด <a href="search.php?category=<?=$book["category_id"]?>"><?=$book["category_name"]?></a></p>
    <div class="price big">
<?php if ($line["discount_percent"] > 0) : ?>
      <del><?=number_format($book["price"], 2)?></del>
      <span class="price-sale"><?=number_format($line["line_total"], 2)?> ฿</span>
      <span class="badge sale"><?=htmlspecialchars($line["promo_name"])?></span>
<?php else : ?>
      <b><?=number_format($book["price"], 2)?> ฿</b>
<?php endif; ?>
    </div>
    <p class="stock<?=$book["stock"] <= 5 ? " low" : ""?>"><?=$book["stock"] > 0 ? "มีสินค้า " . $book["stock"] . " เล่ม" : "สินค้าหมด"?></p>

<?php if ($book["stock"] > 0) : ?>
    <form method="post" action="cart-action.php" class="inline-form"
          onsubmit="return addToCart(<?=$book_id?>, document.getElementById('qty').value)">
      <?=csrf_field()?>
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="book_id" value="<?=$book_id?>">
      <input type="hidden" name="back" value="book.php?book_id=<?=$book_id?>">
      <label for="qty">จำนวน</label>
      <input type="number" id="qty" name="qty" value="1" min="1" max="<?=$book["stock"]?>" required class="short">
      <input type="submit" value="ใส่ตะกร้า">
    </form>
<?php endif; ?>

    <h2>รายละเอียด</h2>
    <p><?=nl2br(htmlspecialchars($book["description"] ?? "-"))?></p>
    <p class="hint">รหัสสินค้า <?=$book_id?> &middot; เข้าระบบเมื่อ <?=$book["added_date"]?></p>
  </div>
</section>

<h2>หนังสือในหมวดเดียวกัน</h2>
<div class="book-grid">
<?php while ($row = $related->fetch()) : ?>
  <div class="book-card">
    <a class="cover" href="book.php?book_id=<?=$row["book_id"]?>"><img src="<?=cover($row["book_id"])?>" alt="" loading="lazy"></a>
    <div class="body">
      <h3><a href="book.php?book_id=<?=$row["book_id"]?>"><?=htmlspecialchars($row["title"])?></a></h3>
      <p class="author"><?=htmlspecialchars($row["author"])?></p>
      <b><?=number_format($row["price"], 2)?> ฿</b>
    </div>
  </div>
<?php endwhile; ?>
</div>
<?php include "footer.php"; ?>
