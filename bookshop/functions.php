<?php
// ค่าคงที่สำหรับแสดงสถานะและตัวเลือกในระบบ
$status_th = [
    "pending"   => "รอยืนยันคำสั่งซื้อ",
    "packing"   => "กำลังแพ็กสินค้า",
    "shipped"   => "จัดส่งแล้ว",
    "delivered" => "ได้รับสินค้าแล้ว",
    "cancelled" => "ยกเลิก",
];
$method_th = ["card" => "บัตรเครดิต/เดบิต", "promptpay" => "พร้อมเพย์ (QR)"];
$carriers  = ["Kerry Express", "Flash Express", "J&T Express", "ไปรษณีย์ไทย (EMS)"];

// ลำดับการเปลี่ยนสถานะคำสั่งซื้อที่อนุญาต (สถานะ delivered และ cancelled ถือว่าสิ้นสุด)
$next_status = [
    "pending" => ["packing", "cancelled"],
    "packing" => ["shipped", "cancelled"],
    "shipped" => ["delivered"],
];

// คืนค่าวันเริ่มต้นของรายงาน (1 ม.ค. ของปีที่มีคำสั่งซื้อแรก)
function report_start($pdo) {
    $first = $pdo->query("SELECT YEAR(MIN(order_date)) FROM orders")->fetchColumn();
    return ($first ? $first : date("Y")) . "-01-01";
}

// แปลงตัวเลขทศนิยมให้ตัดเลขศูนย์ท้ายที่ไม่จำเป็นออก (เช่น 600.00 -> 600)
function blank_num($value) {
    return $value === null || $value === "" ? "" : $value + 0;
}

// คืนค่าพาธรูปปกหนังสือ หากไม่มีไฟล์จะใช้รูปปกมาตรฐานแทน
function cover($book_id) {
    return file_exists("img/" . (int)$book_id . ".jpg") ? "img/" . (int)$book_id . ".jpg" : "img/no-cover.jpg";
}

// ---------- การจัดการสิทธิ์ผู้ใช้และ Session ----------
function is_login() {
    return !empty($_SESSION["member_id"]);
}

function is_admin() {
    return isset($_SESSION["role"]) && $_SESSION["role"] == "admin";
}

function this_page() {
    return basename($_SERVER["PHP_SELF"]) . ($_SERVER["QUERY_STRING"] != "" ? "?" . $_SERVER["QUERY_STRING"] : "");
}

// ตรวจสอบ URL ปลายทางให้อยู่ภายในเว็บไซต์เท่านั้น (ป้องกัน Open Redirect)
function safe_back($url) {
    return preg_match('/^[a-z0-9\-]+\.php(\?[^\s]*)?$/i', $url) ? $url : "index.php";
}

function require_login() {
    if (!is_login()) {
        header("location: login.php?back=" . urlencode(this_page()));
        exit;
    }
}

function require_admin() {
    if (!is_admin()) {
        header("location: login.php?msg=admin&back=" . urlencode(this_page()));
        exit;
    }
}

// เก็บข้อความแจ้งเตือนชั่วคราวใน Session เพื่อแสดงผลในหน้าถัดไปเพียงครั้งเดียว
function flash($type, $text) {
    $_SESSION["flash"] = [$type, $text];
}

// ---------- การป้องกัน CSRF (Cross-Site Request Forgery) ----------
function csrf_token() {
    if (empty($_SESSION["token"]))
        $_SESSION["token"] = bin2hex(random_bytes(16));
    return $_SESSION["token"];
}

function csrf_field() {
    return '<input type="hidden" name="token" value="' . csrf_token() . '">';
}

function check_csrf() {
    if (!hash_equals(csrf_token(), $_POST["token"] ?? "")) {
        http_response_code(400);
        exit("คำขอไม่ถูกต้อง (token ไม่ตรง) กรุณากลับไปหน้าเดิม รีเฟรช แล้วลองใหม่");
    }
}

// ---------- ระบบตะกร้าสินค้า ----------
// กรณีไม่ได้เข้าสู่ระบบจะเก็บใน $_SESSION["cart"] เมื่อเข้าสู่ระบบแล้วจะเก็บในตาราง cart_item
function cart_get($pdo) {
    if (!is_login())
        return $_SESSION["cart"] ?? [];

    $sql = "
        SELECT book_id, quantity
        FROM cart_item
        WHERE member_id = ?
        ORDER BY added_at
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(1, $_SESSION["member_id"]);
    $stmt->execute();

    $cart = [];
    while ($row = $stmt->fetch())
        $cart[$row["book_id"]] = $row["quantity"];
    return $cart;
}

function cart_set($pdo, $book_id, $qty) {
    if (!is_login()) {
        if ($qty > 0)
            $_SESSION["cart"][$book_id] = $qty;
        else
            unset($_SESSION["cart"][$book_id]);
        return;
    }
    if ($qty > 0) {
        $sql = "
            INSERT INTO cart_item (member_id, book_id, quantity)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION["member_id"], $book_id, $qty]);
    } else {
        $sql = "
            DELETE FROM cart_item
            WHERE member_id = ? AND book_id = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION["member_id"], $book_id]);
    }
}

function cart_count($pdo) {
    return array_sum(cart_get($pdo));
}

function cart_clear($pdo) {
    unset($_SESSION["cart"], $_SESSION["coupon"]);
    if (is_login()) {
        $stmt = $pdo->prepare("DELETE FROM cart_item WHERE member_id = ?");
        $stmt->bindParam(1, $_SESSION["member_id"]);
        $stmt->execute();
    }
}

// โอนย้ายตะกร้าจาก Session มารวมกับฐานข้อมูลหลังเข้าสู่ระบบ โดยจำกัดไม่ให้เกินสต็อกคงเหลือ
function cart_merge_session($pdo) {
    if (empty($_SESSION["cart"]))
        return;

    $sql = "
        INSERT INTO cart_item (member_id, book_id, quantity)
        SELECT ?, book_id, LEAST(?, stock)
        FROM book
        WHERE book_id = ? AND stock > 0
        ON DUPLICATE KEY UPDATE
            quantity = LEAST(
                cart_item.quantity + VALUES(quantity),
                (SELECT stock FROM book WHERE book_id = ?)
            )
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($_SESSION["cart"] as $book_id => $qty)
        $stmt->execute([$_SESSION["member_id"], $qty, $book_id, $book_id]);
    unset($_SESSION["cart"]);
}

// ---------- การตรวจสอบข้อมูลและอัปโหลดปกหนังสือ ----------
function book_errors($pdo, $data) {
    $errors = [];
    if (trim($data["title"] ?? "") == "" || mb_strlen($data["title"]) > 200)
        $errors[] = "กรุณากรอกชื่อหนังสือ (ไม่เกิน 200 ตัวอักษร)";
    if (trim($data["author"] ?? "") == "" || mb_strlen($data["author"]) > 150)
        $errors[] = "กรุณากรอกชื่อผู้แต่ง (ไม่เกิน 150 ตัวอักษร)";
    if (!is_numeric($data["price"] ?? "") || $data["price"] < 1 || $data["price"] > 99999)
        $errors[] = "ราคาต้องอยู่ระหว่าง 1 - 99,999 บาท";
    if (!preg_match('/^[0-9]{1,5}$/', $data["stock"] ?? ""))
        $errors[] = "จำนวนคงเหลือต้องเป็นจำนวนเต็มตั้งแต่ 0 ขึ้นไป";

    $d = DateTime::createFromFormat("Y-m-d", $data["added_date"] ?? "");
    if (!$d || $d->format("Y-m-d") != $data["added_date"])
        $errors[] = "วันที่นำเข้าระบบไม่ถูกต้อง";

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM category WHERE category_id = ?");
    $stmt->bindParam(1, $data["category_id"]);
    $stmt->execute();
    if ($stmt->fetchColumn() == 0)
        $errors[] = "กรุณาเลือกหมวดหมู่ที่มีอยู่ในระบบ";
    return $errors;
}

// บันทึกไฟล์ภาพปกจากฟอร์มอัปโหลด หรือดึงภาพจาก Open Library (อนุญาตเฉพาะไฟล์ JPG)
function save_cover($book_id, $cover_url) {
    if (!empty($_FILES["cover"]["tmp_name"])) {
        $info = getimagesize($_FILES["cover"]["tmp_name"]);
        if (!$info || $info[2] != IMAGETYPE_JPEG)
            return false;
        return move_uploaded_file($_FILES["cover"]["tmp_name"], "img/" . (int)$book_id . ".jpg");
    }
    if (preg_match('#^https://covers\.openlibrary\.org/b/(id|isbn|olid)/[A-Za-z0-9\-]+\.jpg$#', $cover_url)) {
        $context = stream_context_create(["http" => ["timeout" => 10]]);
        $image = @file_get_contents($cover_url, false, $context);
        $info = $image ? @getimagesizefromstring($image) : false;
        if (!$info || $info[2] != IMAGETYPE_JPEG || $info[0] < 20)
            return false;
        return file_put_contents("img/" . (int)$book_id . ".jpg", $image) !== false;
    }
    return true;
}

// ---------- การคำนวณราคา โปรโมชั่น และคูปอง ----------
// ตรวจสอบเงื่อนไขคูปอง (วันใช้งาน, สิทธิ์คงเหลือ, ยอดขั้นต่ำ) และคำนวณมูลค่าส่วนลดที่ได้รับ
define("COUPON_CHECK_SQL", "SELECT
    c.coupon_code,
    c.description,
    c.min_spend,
    c.start_date,
    c.end_date,
    c.usage_limit - COUNT(o.order_id) AS remaining,
    CASE
        WHEN CURDATE() < c.start_date           THEN 'คูปองยังไม่เริ่มใช้งาน'
        WHEN CURDATE() > c.end_date             THEN 'คูปองหมดอายุแล้ว'
        WHEN COUNT(o.order_id) >= c.usage_limit THEN 'สิทธิ์การใช้คูปองเต็มแล้ว'
        WHEN a.amount < c.min_spend             THEN 'ยอดซื้อยังไม่ถึงขั้นต่ำของคูปอง'
        ELSE 'ใช้คูปองได้'
    END AS check_result,
    CASE
        WHEN c.discount_type = 'fixed' THEN LEAST(c.discount_value, a.amount)
        ELSE LEAST(ROUND(a.amount * c.discount_value / 100, 2), IFNULL(c.max_discount, a.amount))
    END AS discount
FROM coupon c
CROSS JOIN (
    SELECT CAST(? AS DECIMAL(10,2)) AS amount
) a
LEFT JOIN orders o
    ON o.coupon_code = c.coupon_code
   AND o.status <> 'cancelled'
WHERE c.coupon_code = ?
GROUP BY
    c.coupon_code,
    c.description,
    c.discount_type,
    c.discount_value,
    c.max_discount,
    c.min_spend,
    c.usage_limit,
    c.start_date,
    c.end_date,
    a.amount");

function check_coupon($pdo, $code, $amount) {
    $stmt = $pdo->prepare(COUPON_CHECK_SQL);
    $stmt->bindParam(1, $amount);
    $stmt->bindParam(2, $code);
    $stmt->execute();
    return $stmt->fetch();
}

// คำนวณราคาสุทธิของตะกร้าตามลำดับส่วนลด 3 ชั้น:
//   (1) ส่วนลดระดับสินค้า: เลือกโปรฯ เทศกาลหรือล้างสต็อกที่ให้ส่วนลดสูงสุดของแต่ละเล่ม
//   (2) ส่วนลดยอดซื้อขั้นบันได (Tier): คำนวณจากยอดหลังหักข้อ (1)
//   (3) ส่วนลดคูปอง: คำนวณจากยอดหลังหักข้อ (2)
function price_cart($pdo, $cart, $coupon_code) {
    $r = [
        "lines"           => [],
        "subtotal"        => 0,
        "item_discount"   => 0,
        "tier"            => null,
        "next_tier"       => null,
        "tier_discount"   => 0,
        "coupon"          => null,
        "coupon_error"    => "",
        "coupon_discount" => 0,
        "net_total"       => 0,
    ];
    if (!$cart)
        return $r;

    $ids = array_keys($cart);
    $marks = implode(", ", array_fill(0, count($ids), "?"));
    $sql_items = "
        SELECT
            b.book_id,
            b.title,
            b.author,
            b.price,
            b.stock,
            p.promo_id,
            p.promo_name,
            IFNULL(p.discount_percent, 0) AS discount_percent
        FROM book b
        LEFT JOIN promotion p ON p.promo_id = (
            SELECT p2.promo_id
            FROM promotion p2
            WHERE p2.is_active = 1
              AND CURDATE() BETWEEN p2.start_date AND p2.end_date
              AND (
                  (p2.promo_type = 'seasonal'
                      AND (p2.category_id IS NULL OR p2.category_id = b.category_id))
                  OR
                  (p2.promo_type = 'clearance'
                      AND DATEDIFF(CURDATE(), b.added_date) >= p2.min_days_in_stock
                      AND b.stock <= p2.max_stock)
              )
            ORDER BY p2.discount_percent DESC
            LIMIT 1
        )
        WHERE b.book_id IN ($marks)
        ORDER BY b.title
    ";
    $stmt = $pdo->prepare($sql_items);
    $stmt->execute($ids);

    while ($row = $stmt->fetch()) {
        $row["qty"]             = $cart[$row["book_id"]];
        $row["line_price"]      = $row["qty"] * $row["price"];
        $row["discount_amount"] = round($row["line_price"] * $row["discount_percent"] / 100, 2);
        $row["line_total"]      = $row["line_price"] - $row["discount_amount"];
        $r["lines"][]           = $row;
        $r["subtotal"]         += $row["line_price"];
        $r["item_discount"]    += $row["discount_amount"];
    }
    $after_item = round($r["subtotal"] - $r["item_discount"], 2);

    $sql_tier = "
        SELECT promo_id, promo_name, discount_percent, min_spend
        FROM promotion
        WHERE promo_type = 'tier'
          AND is_active = 1
          AND CURDATE() BETWEEN start_date AND end_date
        ORDER BY min_spend
    ";
    $stmt = $pdo->prepare($sql_tier);
    $stmt->execute();

    while ($row = $stmt->fetch()) {
        if ($after_item >= $row["min_spend"])
            $r["tier"] = $row;
        else if (!$r["next_tier"])
            $r["next_tier"] = $row;
    }
    if ($r["tier"])
        $r["tier_discount"] = round($after_item * $r["tier"]["discount_percent"] / 100, 2);
    $after_tier = round($after_item - $r["tier_discount"], 2);

    if ($coupon_code != "") {
        $check = check_coupon($pdo, $coupon_code, $after_tier);
        if (!$check)
            $r["coupon_error"] = "ไม่พบคูปองรหัส " . $coupon_code;
        else if ($check["check_result"] != "ใช้คูปองได้")
            $r["coupon_error"] = $check["check_result"] . " (ยอดขั้นต่ำ " . number_format($check["min_spend"], 2) . " บาท)";
        else {
            $r["coupon"] = $check;
            $r["coupon_discount"] = $check["discount"];
        }
    }
    $r["net_total"] = round($after_tier - $r["coupon_discount"], 2);
    return $r;
}
?>
