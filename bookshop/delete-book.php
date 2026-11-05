<?php include "connect.php"; ?>
<?php
// ลบหนังสือออกจากฐานข้อมูลและลบไฟล์ภาพปก (ไม่สามารถลบได้หากมีประวัติการสั่งซื้ออ้างอิงอยู่)
require_admin();
check_csrf();
$book_id = (int)($_POST["book_id"] ?? 0);

try {
    $stmt = $pdo->prepare("DELETE FROM book WHERE book_id = ?");
    $stmt->bindParam(1, $book_id);
    $stmt->execute();
    if (file_exists("img/" . $book_id . ".jpg"))
        unlink("img/" . $book_id . ".jpg");
    header("location: list-book.php?msg=deleted&book_id=" . $book_id);
} catch (PDOException $e) {
    header("location: list-book.php?msg=delete_failed&book_id=" . $book_id);
}
?>
