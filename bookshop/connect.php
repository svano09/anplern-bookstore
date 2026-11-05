<?php
// เชื่อมต่อฐานข้อมูลด้วย PDO (รูปแบบเดียวกับ Lecture 7-8) และเริ่มใช้ Session (Lecture 9)
// รองรับทั้ง XAMPP ในเครื่อง, Docker, Host 193, และ Cloud (Koyeb / Render / TiDB Cloud)
$db_host = getenv("DB_HOST") ?: "localhost";
$db_port = getenv("DB_PORT") ?: "3306";
$db_name = getenv("DB_NAME") ?: "bookshop";
$db_user = getenv("DB_USER") ?: "root";
$db_pass = getenv("DB_PASS") !== false ? getenv("DB_PASS") : "";

$dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

// ถ้าเชื่อมต่อกับ TiDB Cloud หรือ Aiven ที่ต้องใช้ SSL
$is_cloud_db = (getenv("DB_SSL") === "1")
    || (strpos($db_host, "tidbcloud.com") !== false)
    || (strpos($db_host, "aivencloud.com") !== false);

if ($is_cloud_db) {
    if (file_exists("/etc/ssl/certs/ca-certificates.crt")) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = "/etc/ssl/certs/ca-certificates.crt";
    }
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
}

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // กรณีที่ต่อ SSL ไม่ผ่าน ลองต่อแบบธรรมดา
    if (!empty($options[PDO::MYSQL_ATTR_SSL_CA])) {
        unset($options[PDO::MYSQL_ATTR_SSL_CA], $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]);
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    } else {
        throw $e;
    }
}

// Initialize a session object: ทุกหน้าเรียก session_start() ผ่านไฟล์นี้
// (cookie ของ session อ่านด้วย JavaScript ไม่ได้ และส่งเฉพาะเว็บเดียวกัน)
ini_set("session.cookie_httponly", 1);
ini_set("session.cookie_samesite", "Lax");
session_start();

include "functions.php";
?>