<?php include "connect.php"; ?>
<?php
// คืนข้อมูลสถิติสำหรับแดชบอร์ดในรูปแบบ JSON ตามตัวกรองช่วงวันที่และหมวดหมู่ (0 = ทุกหมวด)
header("Content-Type: application/json; charset=utf-8");
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(["error" => "เฉพาะผู้ดูแลระบบ"]);
    exit;
}
$from     = $_GET["from"] ?? report_start($pdo);
$to       = $_GET["to"] ?? date("Y-m-d");
$category = (int)($_GET["category"] ?? 0);
$params   = [$from, $to, $category, $category];

// เงื่อนไขฐานสำหรับคำนวณยอดขายตามรายการหนังสือ (ราคา x จำนวน - ส่วนลดระดับสินค้า)
$where = "
    FROM orders o
    JOIN order_item oi ON oi.order_id = o.order_id
    JOIN book b ON b.book_id = oi.book_id
    JOIN category c ON c.category_id = b.category_id
    WHERE o.status <> 'cancelled'
      AND DATE(o.order_date) BETWEEN ? AND ?
      AND (b.category_id = ? OR ? = 0)
";
$revenue = "SUM(oi.quantity * oi.unit_price - oi.discount_amount)";

function rows($pdo, $sql, $params) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

$summary = rows($pdo, "
    SELECT
        COUNT(DISTINCT o.order_id) AS orders,
        IFNULL(SUM(oi.quantity), 0) AS books,
        IFNULL($revenue, 0) AS revenue,
        COUNT(DISTINCT o.member_id) AS customers
    $where
", $params)[0];

$monthly = rows($pdo, "
    SELECT
        DATE_FORMAT(o.order_date, '%Y-%m') AS month,
        COUNT(DISTINCT o.order_id) AS orders,
        $revenue AS revenue
    $where
    GROUP BY month
    ORDER BY month
", $params);

$categories = rows($pdo, "
    SELECT
        c.category_name,
        SUM(oi.quantity) AS books,
        $revenue AS revenue
    $where
    GROUP BY c.category_id, c.category_name
    ORDER BY revenue DESC
", $params);

$top_books = rows($pdo, "
    SELECT
        b.book_id,
        b.title,
        SUM(oi.quantity) AS books,
        $revenue AS revenue
    $where
    GROUP BY b.book_id, b.title
    ORDER BY books DESC, revenue DESC
    LIMIT 5
", $params);

// นับจำนวนคำสั่งซื้อแยกตามสถานะในช่วงวันที่เลือก (รวมสถานะยกเลิก)
$status = rows($pdo, "
    SELECT
        o.status,
        COUNT(*) AS orders
    FROM orders o
    WHERE DATE(o.order_date) BETWEEN ? AND ?
      AND (
          ? = 0
          OR EXISTS (
              SELECT 1
              FROM order_item oi
              JOIN book b ON b.book_id = oi.book_id
              WHERE oi.order_id = o.order_id
                AND b.category_id = ?
          )
      )
    GROUP BY o.status
    ORDER BY FIELD(o.status, 'pending', 'packing', 'shipped', 'delivered', 'cancelled')
", [$from, $to, $category, $category]);

foreach ($status as $i => $row)
    $status[$i]["status_th"] = $status_th[$row["status"]];

echo json_encode([
    "from" => $from, "to" => $to, "category" => $category,
    "summary" => $summary, "monthly" => $monthly, "categories" => $categories,
    "top_books" => $top_books, "status" => $status,
], JSON_UNESCAPED_UNICODE);
?>
