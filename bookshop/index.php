<?php include "connect.php"; ?>
<?php
$title = "หน้าแรก";
include "header.php";

// ดึงโปรโมชั่นที่เปิดใช้งานและอยู่ในช่วงเวลาปัจจุบัน
$sql_promo = "
    SELECT
        p.promo_name,
        p.promo_type,
        p.discount_percent,
        p.end_date,
        c.category_name
    FROM promotion p
    LEFT JOIN category c ON c.category_id = p.category_id
    WHERE p.is_active = 1
      AND CURDATE() BETWEEN p.start_date AND p.end_date
    ORDER BY
        FIELD(p.promo_type, 'seasonal', 'clearance', 'tier'),
        p.discount_percent DESC
";
$promo = $pdo->prepare($sql_promo);
$promo->execute();

// ดึงคูปองที่ยังไม่หมดอายุและยังมีสิทธิ์คงเหลือ
$sql_coupon = "
    SELECT
        c.coupon_code,
        c.description,
        c.end_date,
        c.usage_limit - COUNT(o.order_id) AS remaining
    FROM coupon c
    LEFT JOIN orders o
        ON o.coupon_code = c.coupon_code
       AND o.status <> 'cancelled'
    WHERE CURDATE() BETWEEN c.start_date AND c.end_date
    GROUP BY
        c.coupon_code,
        c.description,
        c.end_date,
        c.usage_limit
    HAVING remaining > 0
    ORDER BY c.end_date
";
$coupon = $pdo->prepare($sql_coupon);
$coupon->execute();
$coupons = $coupon->fetchAll();

// ดึงหนังสือเข้าใหม่ 8 เล่มล่าสุดที่มีสินค้าพร้อมขาย
$sql_latest = "
    SELECT book_id, title, author, price, stock
    FROM book
    WHERE stock > 0
    ORDER BY added_date DESC, book_id DESC
    LIMIT 8
";
$latest = $pdo->prepare($sql_latest);
$latest->execute();

$type_th = ["tier" => "ยอดซื้อขั้นบันได", "seasonal" => "เทศกาล", "clearance" => "ล้างสต็อก"];
?>
<script>
  // เมื่อโหลดหน้าเสร็จ: สร้างข้อความทักทายตามช่วงเวลา และแสดงจำนวนวันที่เหลือของแต่ละคูปอง
  var coupons = <?=json_encode(array_column($coupons, "end_date", "coupon_code"))?>;

  window.addEventListener("load", function () {
    var hour = new Date().getHours();
    var text = hour < 12 ? "สวัสดีตอนเช้า" : (hour < 17 ? "สวัสดีตอนบ่าย" : "สวัสดีตอนเย็น");
    var greeting = document.createElement("p");
    greeting.textContent = text + <?=json_encode(is_login() ? " คุณ" . $_SESSION["first_name"] : "")?> + " วันนี้อยากอ่านอะไรดี?";
    document.getElementById("greeting").appendChild(greeting);

    var today = new Date();
    today.setHours(0, 0, 0, 0);
    for (var code in coupons) {
      var days = Math.round((new Date(coupons[code] + "T00:00:00") - today) / 86400000);
      var badge = document.createElement("span");
      badge.className = days <= 7 ? "badge sale" : "badge info";
      badge.textContent = days == 0 ? "วันสุดท้าย!" : "เหลืออีก " + days + " วัน";
      document.getElementById("left-" + code).appendChild(badge);
    }
  });
</script>

<section class="hero">
  <div id="greeting"></div>
  <p class="lead">ร้านหนังสือออนไลน์ ค้นหาหนังสือ รับโปรโมชั่นอัตโนมัติ ใช้คูปอง ชำระเงินออนไลน์ และติดตามพัสดุได้ในที่เดียว</p>
  <p><a class="btn" href="search.php">เลือกซื้อหนังสือ &rarr;</a></p>
</section>

<section>
  <h2>หนังสือเข้าใหม่</h2>
  <div class="book-grid">
<?php while ($row = $latest->fetch()) : ?>
    <div class="book-card">
      <a class="cover" href="book.php?book_id=<?=$row["book_id"]?>"><img src="<?=cover($row["book_id"])?>" alt="<?=htmlspecialchars($row["title"])?>" loading="lazy"></a>
      <div class="body">
        <h3><a href="book.php?book_id=<?=$row["book_id"]?>"><?=htmlspecialchars($row["title"])?></a></h3>
        <p class="author"><?=htmlspecialchars($row["author"])?></p>
        <b><?=number_format($row["price"], 2)?> ฿</b>
        <button class="btn small add-cart" onclick="addToCart(<?=$row["book_id"]?>, 1)">ใส่ตะกร้า</button>
      </div>
    </div>
<?php endwhile; ?>
  </div>
</section>

<section>
  <h2>โปรโมชั่นวันนี้</h2>
  <div class="table-wrap">
  <table class="data">
    <thead><tr><th>ประเภท</th><th>โปรโมชั่น</th><th>ใช้กับ</th><th class="num">ส่วนลด</th><th>ถึงวันที่</th></tr></thead>
    <tbody>
<?php while ($row = $promo->fetch()) : ?>
      <tr>
        <td><span class="badge info"><?=$type_th[$row["promo_type"]]?></span></td>
        <td><?=htmlspecialchars($row["promo_name"])?></td>
        <td><?=$row["promo_type"] == "seasonal" ? ($row["category_name"] ?? "ทุกหมวด") : ($row["promo_type"] == "tier" ? "ทั้งตะกร้า" : "หนังสือที่เข้าเงื่อนไข")?></td>
        <td class="num"><?=$row["discount_percent"] + 0?>%</td>
        <td><?=$row["end_date"]?></td>
      </tr>
<?php endwhile; ?>
    </tbody>
  </table>
  </div>
</section>

<section>
  <h2>คูปองที่ใช้ได้</h2>
  <div class="table-wrap">
  <table class="data">
    <thead><tr><th>รหัสคูปอง</th><th>รายละเอียด</th><th class="num">สิทธิ์คงเหลือ</th><th>ใช้ได้ถึง</th></tr></thead>
    <tbody>
<?php foreach ($coupons as $row) : ?>
      <tr>
        <td><b><?=$row["coupon_code"]?></b></td>
        <td><?=htmlspecialchars($row["description"])?></td>
        <td class="num"><?=$row["remaining"]?></td>
        <td id="left-<?=$row["coupon_code"]?>"><?=$row["end_date"]?> </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<?php include "footer.php"; ?>
