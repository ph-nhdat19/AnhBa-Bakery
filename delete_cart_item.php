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

if (isset($_POST['delete_cart_item'])) {
    verify_csrf();
    $cart_id = (int)$_POST['cart_id'];
    $stmt = $conn->prepare("DELETE FROM cart WHERE id = ?");
    $stmt->bind_param("i", $cart_id);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
header("Location: admin.php#cart-list");
exit;
?>