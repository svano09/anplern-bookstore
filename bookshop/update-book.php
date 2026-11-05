<?php include "connect.php"; ?>
<?php
// อัปเดตข้อมูลหนังสือและเปลี่ยนไฟล์ภาพปกหากมีการอัปโหลดใหม่
require_admin();
check_csrf();
$book_id = (int)($_POST["book_id"] ?? 0);

$errors = book_errors($pdo, $_POST);
if ($errors) {
    flash("error", implode(" / ", $errors));
    header("location: edit-book.php?book_id=" . $book_id);
    exit;
}

$sql = "
    UPDATE book
    SET
        title       = ?,
        author      = ?,
        category_id = ?,
        price       = ?,
        stock       = ?,
        description = ?,
        added_date  = ?
    WHERE book_id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $_POST["title"]);
$stmt->bindParam(2, $_POST["author"]);
$stmt->bindParam(3, $_POST["category_id"]);
$stmt->bindParam(4, $_POST["price"]);
$stmt->bindParam(5, $_POST["stock"]);
$stmt->bindParam(6, $_POST["description"]);
$stmt->bindParam(7, $_POST["added_date"]);
$stmt->bindParam(8, $book_id);
$stmt->execute();

$msg = save_cover($book_id, "") ? "updated" : "bad_cover";
header("location: list-book.php?msg=" . $msg . "&book_id=" . $book_id);
?>
