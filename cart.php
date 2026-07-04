<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=cart.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Xử lý cập nhật & xóa (giữ nguyên logic)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_cart'])) {
        $cart_id  = (int)$_POST['cart_id'];
        $quantity = (int)$_POST['quantity'];
        $size     = trim($_POST['size'] ?? '');
        if ($quantity > 0) {
            $stmt = $conn->prepare("UPDATE cart SET quantity = ?, size = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("isii", $quantity, $size, $cart_id, $user_id);
            $stmt->execute();
        }
    }
    if (isset($_POST['remove_cart'])) {
        $cart_id = (int)$_POST['cart_id'];
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $cart_id, $user_id);
        $stmt->execute();
    }
    header("Location: cart.php");
    exit;
}

// Lấy dữ liệu giỏ hàng
$stmt = $conn->prepare("
    SELECT c.id, c.quantity, c.size, p.name, p.price, p.image_path
    FROM cart c JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ? ORDER BY c.id DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items = $stmt->get_result();
$stmt->close();

$subtotal = 0;
$items = [];
while ($item = $cart_items->fetch_assoc()) {
    $item['line_total'] = $item['price'] * $item['quantity'];
    $subtotal += $item['line_total'];
    $items[] = $item;
}

include 'header.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ Hàng - Anh Ba Bakery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="main.css">
    <style>
        body { background: #fdf6ef; }
        .cart-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 30px;
        }
        .cart-items {
            background: #fff;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 8px 30px rgba(201,123,132,0.1);
        }
        .cart-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid #f0d4d8;
        }
        .cart-item:last-child { border-bottom: none; }
        .cart-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 12px;
        }
        .qty-control {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8f1eb;
            border-radius: 8px;
            padding: 4px;
        }
        .qty-btn {
            width: 32px; height: 32px;
            border: none;
            background: white;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
        }
        .summary-box {
            background: #fff;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 8px 30px rgba(201,123,132,0.1);
            height: fit-content;
            position: sticky;
            top: 100px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 15.5px;
        }
        .total-row {
            font-size: 21px;
            font-weight: 700;
            color: #c97b84;
            border-top: 2px solid #f0d4d8;
            padding-top: 16px;
            margin-top: 12px;
        }
        .checkout-btn {
            width: 100%;
            padding: 16px;
            background: #c97b84;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 700;
            margin-top: 20px;
            cursor: pointer;
        }
        .checkout-btn:hover {
            background: #a85f68;
        }
        @media (max-width: 992px) {
            .cart-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="cart-container">
    <div class="cart-items">
       
<h2><i class="fas fa-shopping-basket" style="margin-right:12px;color:var(--rose)"></i>Giỏ Hàng Của Bạn</h2>
       

        <?php if (empty($items)): ?>
            <div style="text-align:center;padding:60px 20px;color:#888;">
                <i class="fas fa-shopping-basket" style="font-size:60px;margin-bottom:16px;opacity:0.3"></i>
                <p>Giỏ hàng trống</p>
                <a href="index.php" style="color:#c97b84;font-weight:600;">→ Chọn bánh ngay</a>
            </div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
            <div class="cart-item">
                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="">
                <div style="flex:1">
                    <div style="font-weight:500"><?php echo htmlspecialchars($item['name']); ?></div>
                    <small>Size: <?php echo htmlspecialchars($item['size'] ?: '—'); ?></small>
                </div>
                <div style="text-align:center; min-width:90px;">
                    <div style="font-weight:600"><?php echo number_format($item['price'],0,',','.'); ?>₫</div>
                </div>
                <div class="qty-control">
                    <form action="cart.php" method="POST" style="display:inline">
                        <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                        <input type="hidden" name="size" value="<?php echo $item['size']; ?>">
                        <button type="submit" name="update_cart" class="qty-btn">-</button>
                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" style="width:50px;text-align:center;border:none;background:transparent;">
                        <button type="submit" name="update_cart" class="qty-btn">+</button>
                    </form>
                </div>
                <div style="font-weight:700; min-width:100px;text-align:right;color:#c97b84;">
                    <?php echo number_format($item['line_total'],0,',','.'); ?>₫
                </div>
                <form action="cart.php" method="POST" style="margin-left:12px">
                    <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                    <button type="submit" name="remove_cart" onclick="return confirm('Xóa sản phẩm này?')" style="color:#999;font-size:20px;background:none;border:none;cursor:pointer;">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Tóm tắt đơn hàng -->
    <div class="summary-box">
        <h3 style="margin-bottom:20px;color:#6b3f2a;">Tóm tắt đơn hàng</h3>
        <div class="summary-row">
            <span>Tạm tính (<?php echo count($items); ?> món)</span>
            <span><?php echo number_format($subtotal,0,',','.'); ?> ₫</span>
        </div>
        <div class="summary-row">
            <span>Phí vận chuyển</span>
            <span style="color:#2e7d32;font-weight:600">Miễn phí</span>
        </div>
        <div class="total-row">
            <span>TỔNG CỘNG</span>
            <span><?php echo number_format($subtotal,0,',','.'); ?> ₫</span>
        </div>

        <a href="checkout.php" class="checkout-btn">
            <i class="fas fa-lock"></i> Tiến hành thanh toán
        </a>
        <a href="index.php" style="display:block;text-align:center;margin-top:16px;color:#c97b84;">
            ← Tiếp tục chọn bánh
        </a>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
<?php $conn->close(); ?>