<?php include "connect.php"; ?>
<?php
// คืนค่าสถานะล่าสุดและไทม์ไลน์ของคำสั่งซื้อในรูปแบบ JSON
header("Content-Type: application/json; charset=utf-8");
$order_id  = (int)($_GET["order_id"] ?? 0);
$is_admin  = is_admin() ? 1 : 0;
$member_id = $_SESSION["member_id"] ?? 0;

$sql_order = "
    SELECT status, carrier, tracking_no
    FROM orders
    WHERE order_id = ?
      AND (member_id = ? OR ? = 1)
";
$stmt = $pdo->prepare($sql_order);
$stmt->bindParam(1, $order_id);
$stmt->bindParam(2, $member_id);
$stmt->bindParam(3, $is_admin);
$stmt->execute();
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo json_encode(["error" => "ไม่พบคำสั่งซื้อ"]);
    exit;
}

$sql_logs = "
    SELECT status, note, changed_at
    FROM order_status_log
    WHERE order_id = ?
    ORDER BY changed_at, log_id
";
$logs = $pdo->prepare($sql_logs);
$logs->bindParam(1, $order_id);
$logs->execute();

$list = [];
while ($row = $logs->fetch()) {
    $row["status_th"] = $status_th[$row["status"]];
    $list[] = $row;
}

echo json_encode([
    "status"      => $order["status"],
    "status_th"   => $status_th[$order["status"]],
    "carrier"     => $order["carrier"],
    "tracking_no" => $order["tracking_no"],
    "logs"        => $list,
], JSON_UNESCAPED_UNICODE);
?>
