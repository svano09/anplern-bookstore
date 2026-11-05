<?php include "connect.php"; ?>
<?php
// บันทึกข้อมูลหนังสือเล่มใหม่ลงฐานข้อมูลและบันทึกไฟล์ภาพปก
require_admin();
check_csrf();

$errors = book_errors($pdo, $_POST);
if ($errors) {
    $_SESSION["book_old"] = $_POST;
    flash("error", implode(" / ", $errors));
    header("location: add-book.php");
    exit;
}

$sql = "
    INSERT INTO book (
        title,
        author,
        category_id,
        price,
        stock,
        description,
        added_date
    ) VALUES (?, ?, ?, ?, ?, ?, ?)
";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $_POST["title"]);
$stmt->bindParam(2, $_POST["author"]);
$stmt->bindParam(3, $_POST["category_id"]);
$stmt->bindParam(4, $_POST["price"]);
$stmt->bindParam(5, $_POST["stock"]);
$stmt->bindParam(6, $_POST["description"]);
$stmt->bindParam(7, $_POST["added_date"]);
$stmt->execute();
$book_id = $pdo->lastInsertId();

$msg = save_cover($book_id, $_POST["cover_url"] ?? "") ? "added" : "bad_cover";
header("location: list-book.php?msg=" . $msg . "&book_id=" . $book_id);
?>
