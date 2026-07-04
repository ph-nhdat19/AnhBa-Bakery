<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

function verify_csrf(): void {
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF validation failed');
    }
}

if (isset($_POST['delete_product'])) {
    verify_csrf();
    $product_id = (int)$_POST['product_id'];

    // Lấy đường dẫn hình ảnh
    $stmt = $conn->prepare("SELECT image_path FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $row        = $stmt->get_result()->fetch_assoc();
    $image_path = $row ? $row['image_path'] : null;
    $stmt->close();

    // Xóa sản phẩm
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $ok = $stmt->execute();
    $stmt->close(); // FIX: đóng trước khi redirect

    if ($ok) {
        // Xóa file ảnh nếu tồn tại
        if ($image_path && file_exists($image_path)) {
            unlink($image_path);
        }
        $conn->close();
        header("Location: admin.php?msg=product_deleted#product-list");
        exit;
    } else {
        $conn->close();
        header("Location: admin.php?err=delete_failed#product-list");
        exit;
    }
}

$conn->close();
header("Location: admin.php");
exit;
?>