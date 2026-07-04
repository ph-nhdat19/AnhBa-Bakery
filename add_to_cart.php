<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['product_id'])) {
    $user_id    = (int)$_SESSION['user_id'];
    $product_id = (int)$_POST['product_id']; // FIX: cast int (bản gốc bỏ sót)
    $quantity   = max(1, (int)($_POST['quantity'] ?? 1));
    $size       = trim($_POST['size'] ?? '');

    // Kiểm tra sản phẩm đã có trong giỏ chưa
    $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND size = ?");
    $stmt->bind_param("iis", $user_id, $product_id, $size);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row         = $result->fetch_assoc();
        $new_quantity = $row['quantity'] + $quantity;
        $stmt->close();
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_quantity, $row['id']);
    } else {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, size) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $user_id, $product_id, $quantity, $size);
    }

    $stmt->execute();
    $stmt->close();
    $conn->close();
    header("Location: cart.php");
    exit;
}

$conn->close();
header("Location: index.php");
exit;
?>