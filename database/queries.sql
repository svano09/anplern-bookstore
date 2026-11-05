-- =====================================================================
--  รวมคำสั่ง SQL เชิงวิเคราะห์และประมวลผลหลักของระบบ อ่านเพลิน Bookstore
--  พารามิเตอร์ (?) ในโค้ด PHP ถูกแทนด้วยค่าตัวอย่างเพื่อให้ทดสอบรันได้ทันที
--  รองรับ MySQL 8.0+ / MariaDB 10.4+ / TiDB Cloud (ใช้ Window Function)
-- =====================================================================

-- 1. ค้นหาหนังสือ พร้อมคำนวณราคาหลังหักโปรโมชั่นระดับสินค้าที่ดีที่สุดของวันนี้ (search.php)
SELECT
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
WHERE (b.category_id = 0 OR 0 = 0)
  AND (b.title LIKE '%%' OR b.author LIKE '%%')
  AND ROUND(b.price * (100 - IFNULL(p.discount_percent, 0)) / 100, 2) BETWEEN 0 AND 999999
ORDER BY discount_percent DESC, b.title;


-- 2. ตรวจสอบสินค้าค้างสต็อก (อยู่ในระบบนาน และเหลือน้อย) พร้อมคำนวณราคาล้างสต็อกอัตโนมัติ (clearance.php)
SELECT
    b.book_id,
    b.title,
    c.category_name,
    b.added_date,
    DATEDIFF(CURDATE(), b.added_date) AS days_in_stock,
    b.stock,
    IFNULL(s.sold_90d, 0) AS sold_90d,
    b.price,
    p.promo_name,
    IFNULL(p.discount_percent, 0) AS discount_percent,
    ROUND(b.price * (100 - IFNULL(p.discount_percent, 0)) / 100, 2) AS clearance_price,
    CASE
        WHEN p.promo_id IS NOT NULL THEN 'ลดราคาล้างสต็อก'
        WHEN DATEDIFF(CURDATE(), b.added_date) < r.need_days THEN 'เหลือน้อย แต่เพิ่งเข้าระบบ'
        ELSE 'อยู่นาน แต่สต็อกยังเหลือมาก'
    END AS clearance_status
FROM book b
JOIN category c ON c.category_id = b.category_id
CROSS JOIN (
    SELECT
        MIN(min_days_in_stock) AS need_days,
        MAX(max_stock) AS need_stock
    FROM promotion
    WHERE promo_type = 'clearance'
      AND is_active = 1
      AND CURDATE() BETWEEN start_date AND end_date
) r
LEFT JOIN (
    SELECT
        oi.book_id,
        SUM(oi.quantity) AS sold_90d
    FROM order_item oi
    JOIN orders o ON o.order_id = oi.order_id
    WHERE o.status <> 'cancelled'
      AND o.order_date >= CURDATE() - INTERVAL 90 DAY
    GROUP BY oi.book_id
) s ON s.book_id = b.book_id
LEFT JOIN (
    SELECT
        b2.book_id,
        p2.promo_id,
        p2.promo_name,
        p2.discount_percent,
        ROW_NUMBER() OVER (
            PARTITION BY b2.book_id
            ORDER BY p2.discount_percent DESC, p2.promo_id ASC
        ) AS rn
    FROM book b2
    JOIN promotion p2
        ON p2.promo_type = 'clearance'
       AND p2.is_active = 1
       AND CURDATE() BETWEEN p2.start_date AND p2.end_date
       AND DATEDIFF(CURDATE(), b2.added_date) >= p2.min_days_in_stock
       AND b2.stock <= p2.max_stock
) p ON p.book_id = b.book_id AND p.rn = 1
WHERE DATEDIFF(CURDATE(), b.added_date) >= r.need_days
   OR b.stock <= r.need_stock
ORDER BY discount_percent DESC, days_in_stock DESC;


-- 3. รายงานยอดขายรายเดือน แยกตามส่วนลดแต่ละชั้น (sales-monthly.php)
SELECT
    DATE_FORMAT(o.order_date, '%Y-%m') AS sale_month,
    COUNT(*) AS order_count,
    SUM(i.book_qty) AS book_qty,
    SUM(o.subtotal) AS gross_sales,
    SUM(o.item_discount) AS item_discount,
    SUM(o.tier_discount) AS tier_discount,
    SUM(o.coupon_discount) AS coupon_discount,
    SUM(o.net_total) AS net_sales,
    ROUND(AVG(o.net_total), 2) AS avg_order
FROM orders o
JOIN (
    SELECT
        order_id,
        SUM(quantity) AS book_qty
    FROM order_item
    GROUP BY order_id
) i ON i.order_id = o.order_id
WHERE o.status <> 'cancelled'
  AND DATE(o.order_date) BETWEEN '2026-01-01' AND '2026-12-31'
GROUP BY DATE_FORMAT(o.order_date, '%Y-%m')
ORDER BY sale_month;


-- 4. จัดอันดับหนังสือขายดี 3 อันดับแรกของแต่ละหมวดหมู่ด้วย Window Function (bestseller.php)
SELECT
    category_name,
    rank_no,
    book_id,
    title,
    qty_sold,
    revenue,
    pct_of_category
FROM (
    SELECT
        c.category_id,
        c.category_name,
        b.book_id,
        b.title,
        SUM(oi.quantity) AS qty_sold,
        SUM(oi.quantity * oi.unit_price - oi.discount_amount) AS revenue,
        ROUND(
            100 * SUM(oi.quantity * oi.unit_price - oi.discount_amount)
            / SUM(SUM(oi.quantity * oi.unit_price - oi.discount_amount)) OVER (PARTITION BY c.category_id),
            1
        ) AS pct_of_category,
        ROW_NUMBER() OVER (
            PARTITION BY c.category_id
            ORDER BY
                SUM(oi.quantity) DESC,
                SUM(oi.quantity * oi.unit_price - oi.discount_amount) DESC
        ) AS rank_no
    FROM order_item oi
    JOIN orders o ON o.order_id = oi.order_id
    JOIN book b ON b.book_id = oi.book_id
    JOIN category c ON c.category_id = b.category_id
    WHERE o.status <> 'cancelled'
    GROUP BY c.category_id, c.category_name, b.book_id, b.title
) AS ranked
WHERE rank_no <= 3
ORDER BY category_id, rank_no;


-- 5. รายงานสรุปผลการใช้งานโปรโมชั่นทุกแคมเปญ (promotion-report.php)
SELECT
    p.promo_id,
    p.promo_name,
    p.promo_type,
    p.discount_percent,
    CASE p.promo_type
        WHEN 'tier'     THEN CONCAT('ยอดซื้อตั้งแต่ ', FORMAT(p.min_spend, 0), ' บาท')
        WHEN 'seasonal' THEN IFNULL(c.category_name, 'ทุกหมวด')
        ELSE CONCAT('อยู่ในคลัง >= ', p.min_days_in_stock, ' วัน และเหลือ <= ', p.max_stock, ' เล่ม')
    END AS conditions,
    p.start_date,
    p.end_date,
    CASE
        WHEN p.is_active = 0          THEN 'ปิดใช้งาน'
        WHEN CURDATE() < p.start_date THEN 'ยังไม่เริ่ม'
        WHEN CURDATE() > p.end_date   THEN 'สิ้นสุดแล้ว'
        ELSE 'กำลังใช้งาน'
    END AS promo_status,
    IFNULL(u.order_count, 0) AS order_count,
    IFNULL(u.book_count, 0) AS book_count,
    IFNULL(u.discount_total, 0) AS discount_total
FROM promotion p
LEFT JOIN category c ON c.category_id = p.category_id
LEFT JOIN (
    SELECT
        oi.promo_id,
        COUNT(DISTINCT oi.order_id) AS order_count,
        SUM(oi.quantity) AS book_count,
        SUM(oi.discount_amount) AS discount_total
    FROM order_item oi
    JOIN orders o ON o.order_id = oi.order_id
    WHERE o.status <> 'cancelled'
      AND oi.promo_id IS NOT NULL
    GROUP BY oi.promo_id

    UNION ALL

    SELECT
        o.promo_id,
        COUNT(*) AS order_count,
        SUM(i.book_qty) AS book_count,
        SUM(o.tier_discount) AS discount_total
    FROM orders o
    JOIN (
        SELECT
            order_id,
            SUM(quantity) AS book_qty
        FROM order_item
        GROUP BY order_id
    ) i ON i.order_id = o.order_id
    WHERE o.status <> 'cancelled'
      AND o.promo_id IS NOT NULL
    GROUP BY o.promo_id
) u ON u.promo_id = p.promo_id
ORDER BY FIELD(p.promo_type, 'tier', 'seasonal', 'clearance'), p.start_date, p.promo_id;


-- 6.1 ตรวจสอบความถูกต้องและคำนวณส่วนลดของรหัสคูปอง (coupon.php / functions.php)
SELECT
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
    SELECT CAST(850 AS DECIMAL(10, 2)) AS amount
) a
LEFT JOIN orders o
    ON o.coupon_code = c.coupon_code
   AND o.status <> 'cancelled'
WHERE c.coupon_code = 'BOOK15'
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
    a.amount;


-- 6.2 แสดงรายการคูปองทั้งหมด พร้อมจำนวนครั้งที่ใช้ไปและสิทธิ์คงเหลือ (coupon.php / coupons.php)
SELECT
    c.coupon_code,
    c.description,
    c.discount_type,
    c.discount_value,
    c.max_discount,
    c.min_spend,
    c.start_date,
    c.end_date,
    c.usage_limit,
    COUNT(o.order_id) AS used_count,
    c.usage_limit - COUNT(o.order_id) AS remaining,
    IFNULL(SUM(o.coupon_discount), 0) AS discount_given,
    CASE
        WHEN CURDATE() < c.start_date           THEN 'ยังไม่เริ่ม'
        WHEN CURDATE() > c.end_date             THEN 'หมดอายุ'
        WHEN COUNT(o.order_id) >= c.usage_limit THEN 'สิทธิ์เต็ม'
        ELSE 'ใช้งานได้'
    END AS coupon_status
FROM coupon c
LEFT JOIN orders o
    ON o.coupon_code = c.coupon_code
   AND o.status <> 'cancelled'
GROUP BY
    c.coupon_code,
    c.description,
    c.discount_type,
    c.discount_value,
    c.max_discount,
    c.min_spend,
    c.start_date,
    c.end_date,
    c.usage_limit
ORDER BY c.end_date DESC, c.coupon_code;


-- 7. ติดตามคำสั่งซื้อและสถานะพัสดุล่าสุด พร้อมแบ่งหน้า (orders.php)
SELECT
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
    SELECT
        order_id,
        SUM(quantity) AS book_qty
    FROM order_item
    GROUP BY order_id
) i ON i.order_id = o.order_id
JOIN (
    SELECT
        order_id,
        note,
        changed_at,
        ROW_NUMBER() OVER (
            PARTITION BY order_id
            ORDER BY changed_at DESC, log_id DESC
        ) AS rn
    FROM order_status_log
) l ON l.order_id = o.order_id AND l.rn = 1
WHERE (o.status = '' OR '' = '')
ORDER BY o.order_date DESC
LIMIT 10 OFFSET 0;
