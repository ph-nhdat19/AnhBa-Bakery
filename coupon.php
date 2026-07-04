<?php
// File này dùng để apply coupon qua AJAX từ checkout.php
session_start();
include 'connect.php';

header('Content-Type: application/json');

if (!isset($_POST['code'])) {
    echo json_encode(['success' => false, 'message' => 'Thiếu mã coupon']);
    exit;
}

$code    = strtoupper(trim($_POST['code']));
$subtotal = (float)($_POST['subtotal'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at >= NOW()) AND (used_count < max_uses OR max_uses = 0)");
$stmt->bind_param("s", $code);
$stmt->execute();
$coupon = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$coupon) {
    echo json_encode(['success' => false, 'message' => 'Mã giảm giá không hợp lệ hoặc đã hết hạn!']);
    exit;
}

if ($subtotal < $coupon['min_order']) {
    echo json_encode(['success' => false, 'message' => 'Đơn hàng tối thiểu ' . number_format($coupon['min_order'], 0, ',', '.') . '₫ để dùng mã này!']);
    exit;
}

$discount = 0;
if ($coupon['type'] === 'percent') {
    $discount = $subtotal * $coupon['value'] / 100;
    if ($coupon['max_discount'] > 0) $discount = min($discount, $coupon['max_discount']);
} else {
    $discount = $coupon['value'];
}
$discount = min($discount, $subtotal);

echo json_encode([
    'success'  => true,
    'message'  => 'Áp dụng mã thành công! Giảm ' . ($coupon['type'] === 'percent' ? $coupon['value'] . '%' : number_format($coupon['value'], 0, ',', '.') . '₫'),
    'discount' => $discount,
    'coupon_id'=> $coupon['id'],
    'code'     => $code,
]);

$conn->close();
?>