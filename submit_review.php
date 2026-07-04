<?php
session_start();
include 'connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để đánh giá.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$order_id = isset($_POST['order_id']) ? $_POST['order_id'] : '';
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$comment = trim($_POST['comment'] ?? '');

if ($product_id <= 0 || $order_id === '' || $rating < 1 || $rating > 5 || $comment === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dữ liệu đánh giá không hợp lệ.']);
    exit;
}

// Kiểm tra user có mua đúng sản phẩm trong đơn status = 'Hoàn thành' hay không
$stmt = $conn->prepare("
    SELECT 1
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.order_id
    WHERE o.user_id = ?
      AND o.order_id = ?
      AND o.status = 'Hoàn thành'
      AND oi.product_id = ?
    LIMIT 1
");
$stmt->bind_param('isi', $user_id, $order_id, $product_id);
$stmt->execute();
$ok = $stmt->get_result()->num_rows > 0;
$stmt->close();

if (!$ok) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Bạn chưa thể đánh giá sản phẩm này.']);
    exit;
}

// Insert review (hoặc cập nhật nếu tồn tại)
$comment_s = mb_substr($comment, 0, 1000);

$stmt = $conn->prepare("
    SELECT id FROM product_reviews WHERE user_id = ? AND product_id = ?
");
$stmt->bind_param('ii', $user_id, $product_id);
$stmt->execute();
$res = $stmt->get_result();
$existing = $res->fetch_assoc();
$stmt->close();

if ($existing) {
    $stmt = $conn->prepare("
        UPDATE product_reviews
        SET rating = ?, comment = ?, created_at = NOW()
        WHERE id = ?
    ");
    $id = (int)$existing['id'];
    $stmt->bind_param('isi', $rating, $comment_s, $id);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("
        INSERT INTO product_reviews (user_id, product_id, order_id, rating, comment, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param('iisds', $user_id, $product_id, $order_id, $rating, $comment_s);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['success' => true, 'message' => 'Đánh giá đã được gửi thành công!']);


