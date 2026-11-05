<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูลด้วย PDO (รองรับทั้ง Local, Docker และ Cloud DB)
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

// เปิดใช้งาน SSL อัตโนมัติเมื่อเชื่อมต่อกับ Cloud Database (เช่น TiDB Cloud / Aiven)
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
    // กรณีฐานข้อมูลบน Cloud ยังไม่ถูกสร้าง (Error 1049) ให้สร้างฐานข้อมูลอัตโนมัติ
    if (strpos($e->getMessage(), "1049") !== false) {
        $dsn_no_db = "mysql:host=$db_host;port=$db_port;charset=utf8mb4";
        $pdo = new PDO($dsn_no_db, $db_user, $db_pass, $options);
        $safe_db = preg_replace('/[^a-zA-Z0-9_]/', '', $db_name);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$safe_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$safe_db`");
    } elseif (!empty($options[PDO::MYSQL_ATTR_SSL_CA])) {
        unset($options[PDO::MYSQL_ATTR_SSL_CA], $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]);
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    } else {
        throw $e;
    }
}

// กรณีเชื่อมต่อ Cloud Database ครั้งแรกแล้วยังไม่มีตาราง ให้รันไฟล์ bookshop.sql อัตโนมัติ
if ($is_cloud_db && !$pdo->query("SHOW TABLES LIKE 'member'")->fetchColumn()) {
    $sql_path = file_exists("/var/www/bookshop.sql") ? "/var/www/bookshop.sql" : __DIR__ . "/../database/bookshop.sql";
    if (file_exists($sql_path)) {
        $lines = explode("\n", str_replace("\r\n", "\n", file_get_contents($sql_path)));
        $clean_sql = "";
        foreach ($lines as $line) {
            if (strpos(ltrim($line), "--") === 0) continue;
            $clean_sql .= $line . "\n";
        }
        foreach (explode(";\n", $clean_sql) as $stmt_sql) {
            $stmt_sql = trim($stmt_sql);
            if ($stmt_sql !== "") {
                $pdo->exec($stmt_sql);
            }
        }
    }
}

// เริ่มต้นใช้งาน Session พร้อมป้องกันการเข้าถึง Cookie จากฝั่ง JavaScript (HttpOnly)
ini_set("session.cookie_httponly", 1);
ini_set("session.cookie_samesite", "Lax");
session_start();

include "functions.php";
?>
