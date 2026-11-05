<?php include "connect.php"; ?>
<?php
$title = "ค้นหาหนังสือ";
include "header.php";

$category  = (int)($_GET["category"] ?? 0);
$keyword   = $_GET["keyword"] ?? "";
$min_price = ($_GET["min_price"] ?? "") === "" ? 0 : (float)$_GET["min_price"];
$max_price = ($_GET["max_price"] ?? "") === "" ? 999999 : (float)$_GET["max_price"];
$like      = "%" . $keyword . "%";
$page      = max(1, (int)($_GET["page"] ?? 1));
$per_page  = 12;

// ค้นหาหนังสือพร้อมคำนวณราคาหลังหักโปรโมชั่นระดับสินค้า (เทศกาล / ล้างสต็อก) ที่ให้ส่วนลดสูงสุด
$base_sql = "SELECT
    b.book_id,
    b.title,
    b.author,
    c.category_name,
    b.price,
    b.stock,
    p.promo_name,
    p.promo_type,
    IFNULL(p.discount_percent, 0) AS discount_percent,
    ROUND(b.price * (100 - IFNULL(p.discount_percent, 0)) / 100, 2) AS sale_price
FROM book b
JOIN category c ON c.category_id = b.category_id
LEFT JOIN (
    SELECT
        b2.book_id,
        p2.promo_id,
        p2.promo_name,
        p2.promo_type,
        p2.discount_percent,
        ROW_NUMBER() OVER (
            PARTITION BY b2.book_id
            ORDER BY p2.discount_percent DESC, p2.promo_id ASC
        ) AS rn
    FROM book b2
    JOIN promotion p2
        ON p2.is_active = 1
       AND CURDATE() BETWEEN p2.start_date AND p2.end_date
       AND (
           (p2.promo_type = 'seasonal'
            AND (p2.category_id IS NULL OR p2.category_id = b2.category_id))
           OR
           (p2.promo_type = 'clearance'
            AND DATEDIFF(CURDATE(), b2.added_date) >= p2.min_days_in_stock
            AND b2.stock <= p2.max_stock)
       )
) p ON p.book_id = b.book_id AND p.rn = 1
WHERE (b.category_id = ? OR ? = 0)
  AND (b.title LIKE ? OR b.author LIKE ?)
  AND ROUND(b.price * (100 - IFNULL(p.discount_percent, 0)) / 100, 2) BETWEEN ? AND ?";

$sql = $base_sql . "
ORDER BY discount_percent DESC, b.title
LIMIT ? OFFSET ?";

$count = $pdo->prepare("SELECT COUNT(*) FROM ($base_sql) AS result");
$count->execute([$category, $category, $like, $like, $min_price, $max_price]);
$total = $count->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));
$page = min($page, $total_pages);

$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $category);
$stmt->bindParam(2, $category);
$stmt->bindParam(3, $like);
$stmt->bindParam(4, $like);
$stmt->bindParam(5, $min_price);
$stmt->bindParam(6, $max_price);
$stmt->bindValue(7, $per_page, PDO::PARAM_INT);
$stmt->bindValue(8, ($page - 1) * $per_page, PDO::PARAM_INT);
$stmt->execute();
$books = $stmt->fetchAll();

$cats = $pdo->prepare("SELECT category_id, category_name FROM category ORDER BY category_id");
$cats->execute();

$params = $_GET;
unset($params["page"]);
?>
<script>
  // ค้นหาหนังสืออัตโนมัติขณะพิมพ์ด้วย Ajax และแสดงรายการแนะนำใต้ช่องค้นหา
  var request;
  function suggest() {
    var keyword = document.getElementById("keyword").value;
    if (keyword.length < 2) {
      document.getElementById("suggest").innerHTML = "";
      return;
    }
    request = new XMLHttpRequest();
    request.onreadystatechange = showSuggest;
    request.open("GET", "suggest.php?keyword=" + encodeURIComponent(keyword), true);
    request.send(null);
  }
  function showSuggest() {
    if (request.readyState == 4 && request.status == 200)
      document.getElementById("suggest").innerHTML = request.responseText;
  }
</script>

<p class="lead">ค้นหาตามหมวดหมู่ คำค้นหา และช่วงราคา &mdash; หนังสือที่ร่วมโปรโมชั่นจะแสดงป้ายส่วนลดและราคาเดิมที่ถูกขีดฆ่าบนปก</p>

<form class="filter">
  <label>หมวดหมู่
    <select name="category">
      <option value="0">ทุกหมวด</option>
<?php while ($c = $cats->fetch()) : ?>
      <option value="<?=$c["category_id"]?>"<?=$c["category_id"] == $category ? " selected" : ""?>><?=$c["category_name"]?></option>
<?php endwhile; ?>
    </select>
  </label>
  <div class="suggest-wrap">
    <label for="keyword">คำค้นหา (ชื่อเรื่อง/ผู้แต่ง)</label>
    <input type="text" id="keyword" name="keyword" value="<?=htmlspecialchars($keyword)?>" placeholder="เช่น Harry, ศรีบูรพา"
           onkeyup="suggest()" autocomplete="off">
    <div id="suggest"></div>
  </div>
  <label>ราคาตั้งแต่
    <input type="number" name="min_price" min="0" step="1" value="<?=htmlspecialchars($_GET["min_price"] ?? "")?>" class="short">
  </label>
  <label>ถึง
    <input type="number" name="max_price" min="0" step="1" value="<?=htmlspecialchars($_GET["max_price"] ?? "")?>" class="short">
  </label>
  <input type="submit" value="ค้นหา">
  <a class="btn secondary" href="search.php">ล้างเงื่อนไข</a>
</form>

<p>พบหนังสือ <b><?=$total?></b> รายการ<?=$total_pages > 1 ? " (หน้า " . $page . "/" . $total_pages . ")" : ""?></p>

<div class="book-grid">
<?php foreach ($books as $row) : ?>
  <div class="book-card">
    <a class="cover" href="book.php?book_id=<?=$row["book_id"]?>">
      <img src="<?=cover($row["book_id"])?>" alt="<?=htmlspecialchars($row["title"])?>" loading="lazy">
<?php   if ($row["discount_percent"] > 0) : ?>
      <span class="off">-<?=$row["discount_percent"] + 0?>%</span>
      <span class="promo-tag"><?=$row["promo_type"] == "clearance" ? "ล้างสต็อก" : htmlspecialchars($row["promo_name"])?></span>
<?php   endif; ?>
    </a>
    <div class="body">
      <h3><a href="book.php?book_id=<?=$row["book_id"]?>"><?=htmlspecialchars($row["title"])?></a></h3>
      <p class="author"><?=htmlspecialchars($row["author"])?> &middot; <?=$row["category_name"]?></p>
      <div class="price">
<?php   if ($row["discount_percent"] > 0) : ?>
        <del><?=number_format($row["price"], 2)?></del>
        <span class="price-sale"><?=number_format($row["sale_price"], 2)?> ฿</span>
<?php   else : ?>
        <b><?=number_format($row["price"], 2)?> ฿</b>
<?php   endif; ?>
      </div>
      <div class="stock<?=$row["stock"] <= 5 ? " low" : ""?>"><?=$row["stock"] > 0 ? "เหลือ " . $row["stock"] . " เล่ม" : "สินค้าหมด"?></div>
      <button class="btn small add-cart" onclick="addToCart(<?=$row["book_id"]?>, 1)"<?=$row["stock"] > 0 ? "" : " disabled"?>>ใส่ตะกร้า</button>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php if ($total_pages > 1) : ?>
<div class="pager">
<?php   for ($p = 1; $p <= $total_pages; $p++) : ?>
<?php     if ($p == $page) : ?>
  <span class="current"><?=$p?></span>
<?php     else : ?>
  <a href="search.php?<?=htmlspecialchars(http_build_query($params + ["page" => $p]))?>"><?=$p?></a>
<?php     endif; ?>
<?php   endfor; ?>
</div>
<?php endif; ?>

<?php if (is_admin()) : ?>
<details class="sql">
  <summary>ดูคำสั่ง SQL</summary>
  <pre><?=htmlspecialchars($sql)?></pre>
</details>
<?php endif; ?>
<?php include "footer.php"; ?>
