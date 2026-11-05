<?php
$shop_name = "อ่านเพลิน Bookstore";
$current = basename($_SERVER["PHP_SELF"]);

// กำหนดรายการเมนูนำทางตามสิทธิ์ของผู้ใช้ใน Session
$menu = [
    "ร้านหนังสือ" => [
        "index.php"  => "หน้าแรก",
        "search.php" => "ค้นหาหนังสือ",
        "cart.php"   => "ตะกร้าสินค้า",
        "coupon.php" => "คูปองส่วนลด",
    ],
];
if (is_login()) {
    $menu["บัญชีของฉัน"] = [
        "orders.php"  => is_admin() ? "คำสั่งซื้อทั้งหมด" : "คำสั่งซื้อของฉัน",
        "account.php" => "ข้อมูลส่วนตัว",
    ];
}
if (is_admin()) {
    $menu["ผู้ดูแลระบบ"] = [
        "admin.php"            => "แดชบอร์ด / กราฟ",
        "list-book.php"        => "จัดการหนังสือ",
        "promotions.php"       => "โปรโมชั่น",
        "coupons.php"          => "คูปอง",
        "members.php"          => "สมาชิก",
        "clearance.php"        => "สินค้าค้างสต็อก",
        "sales-monthly.php"    => "รายงานยอดขายรายเดือน",
        "bestseller.php"       => "หนังสือขายดีแต่ละหมวด",
        "promotion-report.php" => "สรุปผลโปรโมชั่น",
    ];
}

// จับคู่หน้าย่อยให้ไฮไลต์เมนูหลักที่เกี่ยวข้อง
$parent = [
    "add-book.php"       => "list-book.php",
    "edit-book.php"      => "list-book.php",
    "promotion-form.php" => "promotions.php",
    "coupon-form.php"    => "coupons.php",
    "order-detail.php"   => "orders.php",
    "book.php"           => "search.php",
    "checkout.php"       => "cart.php",
    "payment.php"        => "cart.php",
];
$active = $parent[$current] ?? $current;

$flash = $_SESSION["flash"] ?? null;
unset($_SESSION["flash"]);
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?=htmlspecialchars($title)?> | <?=$shop_name?></title>
  <link rel="icon" href="data:,">
  <link href="style.css" rel="stylesheet" type="text/css">
  <script>var TOKEN = "<?=csrf_token()?>";</script>
  <script src="js/menu.js"></script>
  <script src="js/shop.js"></script>
</head>
<body>
  <header id="top">
    <div class="logo">
      <a href="index.php"><span class="logo-mark">อ</span><?=$shop_name?></a>
    </div>
    <div class="search">
      <form action="search.php" role="search">
        <input type="search" name="keyword" placeholder="ค้นหาชื่อหนังสือหรือผู้แต่ง..."
               value="<?=htmlspecialchars($_GET["keyword"] ?? "")?>" aria-label="ค้นหาหนังสือ">
        <button>ค้นหา</button>
      </form>
    </div>
    <div class="userbar">
      <a class="cart-link" href="cart.php">ตะกร้า <span id="cart-count" class="count"><?=cart_count($pdo)?></span></a>
<?php if (is_login()) : ?>
      <a href="account.php" class="user-name"><?=htmlspecialchars($_SESSION["first_name"])?><?=is_admin() ? ' <small class="role">ผู้ดูแล</small>' : ""?></a>
      <a href="logout.php">ออกจากระบบ</a>
<?php else : ?>
      <a href="login.php">เข้าสู่ระบบ</a>
      <a href="register.php" class="btn small">สมัครสมาชิก</a>
<?php endif; ?>
    </div>
  </header>

  <div class="mobile_bar">
    <a href="index.php">หน้าแรก</a>
    <a href="#" onclick="toggle_visibility('menu'); return false;">☰ เมนู</a>
  </div>

  <main>
    <nav id="menu" aria-label="เมนูหลัก">
<?php foreach ($menu as $group => $items) : ?>
      <h2><?=$group?></h2>
      <ul class="menu">
<?php   foreach ($items as $file => $label) : ?>
        <li<?=$file == $active ? ' class="current"' : ''?>><a href="<?=$file?>"><?=$label?></a></li>
<?php   endforeach; ?>
      </ul>
<?php endforeach; ?>
    </nav>
    <article>
      <h1><?=htmlspecialchars($title)?></h1>
<?php if ($flash) : ?>
      <p class="msg <?=$flash[0]?>"><?=htmlspecialchars($flash[1])?></p>
<?php endif; ?>
