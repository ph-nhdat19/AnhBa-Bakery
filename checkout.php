<?php
session_start();
include 'connect.php';
include 'header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=checkout.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';
$new_order_id = '';

// Thông tin MB Bank
$mb_account  = '0911792931';
$mb_name     = 'TRAN TRUNG TRUC';
$mb_bank     = 'MB Bank';

// Lấy giỏ hàng
$stmt = $conn->prepare("SELECT c.id, c.quantity, c.size, p.id as product_id, p.name, p.price, p.image_path
                        FROM cart c JOIN products p ON c.product_id = p.id
                        WHERE c.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_result = $stmt->get_result();
$stmt->close();

$subtotal = 0;
$cart_data = [];
while ($item = $cart_result->fetch_assoc()) {
    $subtotal += $item['price'] * $item['quantity'];
    $cart_data[] = $item;
}

// Xử lý đặt hàng (GIỮ NGUYÊN TOÀN BỘ)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    $customer_name    = trim($_POST['customer_name']);
    $customer_phone   = trim($_POST['customer_phone']);
    $customer_address = trim($_POST['customer_address']);
    $note             = trim($_POST['note']);
    $payment_method   = $_POST['payment_method'] ?? 'cod';
    $coupon_code      = strtoupper(trim($_POST['coupon_code'] ?? ''));
    $discount         = 0;

    if ($coupon_code) {
        $stmt = $conn->prepare("SELECT * FROM coupons WHERE code=? AND is_active=1 AND (expires_at IS NULL OR expires_at>=NOW()) AND (used_count<max_uses OR max_uses=0)");
        $stmt->bind_param("s", $coupon_code);
        $stmt->execute();
        $coupon = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($coupon && $subtotal >= $coupon['min_order']) {
            $discount = $coupon['type'] === 'percent'
                ? min($subtotal * $coupon['value'] / 100, $coupon['max_discount'] > 0 ? $coupon['max_discount'] : PHP_INT_MAX)
                : $coupon['value'];
            $discount = min($discount, $subtotal);
        }
    }
    $final_total = $subtotal - $discount;

    if (empty($customer_name) || empty($customer_phone) || empty($customer_address)) {
        $error_message = "Vui lòng điền đầy đủ thông tin giao hàng!";
    } elseif (count($cart_data) == 0) {
        $error_message = "Giỏ hàng của bạn đang trống!";
    } else {
        $conn->begin_transaction();
        try {
            $res = $conn->query("SELECT MAX(CAST(SUBSTRING(order_id, 3) AS UNSIGNED)) AS max_id FROM orders");
            $row = $res->fetch_assoc();
            $new_order_id = 'DH' . str_pad(($row['max_id'] ?? 0) + 1, 3, '0', STR_PAD_LEFT);

            $status = 'Chờ xác nhận';
            $full_note = $note . ($payment_method === 'banking' ? ' [Thanh toán chuyển khoản MB Bank]' : ' [Thanh toán COD]');
            if ($coupon_code && $discount > 0) $full_note .= " [Coupon: $coupon_code -" . number_format($discount,0,',','.') . "₫]";

            $stmt = $conn->prepare("INSERT INTO orders (order_id, user_id, total_amount, customer_name, customer_phone, customer_address, note, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sidsssss", $new_order_id, $user_id, $final_total, $customer_name, $customer_phone, $customer_address, $full_note, $status);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, size, price) VALUES (?, ?, ?, ?, ?)");
            foreach ($cart_data as $item) {
                $stmt->bind_param("siisd", $new_order_id, $item['product_id'], $item['quantity'], $item['size'], $item['price']);
                $stmt->execute();
            }
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            if (!empty($coupon) && $discount > 0) {
                $conn->query("UPDATE coupons SET used_count = used_count + 1 WHERE id = " . (int)$coupon['id']);
            }
            $success_message = "Đặt hàng thành công!";
            $cart_data = [];
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Lỗi khi đặt hàng: " . $e->getMessage();
        }
    }
}

function mbQR($account, $name, $amount, $content) {
    $amount_enc  = urlencode($amount);
    $content_enc = urlencode($content);
    return "https://img.vietqr.io/image/MB-{$account}-compact2.png?amount={$amount_enc}&addInfo={$content_enc}&accountName=" . urlencode($name);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh Toán - Anh Ba Bakery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="main.css">
    <style>
        body { background: #fdf6ef; }
        .checkout-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 30px;
        }
        .order-details, .shipping-info {
            background: #fff;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 8px 30px rgba(201,123,132,0.12);
        }
        h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 26px;
            color: #6b3f2a;
            margin-bottom: 24px;
        }
        .cart-product img {
            width: 65px; height: 65px;
            object-fit: cover;
            border-radius: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #6b3f2a;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e8d5d8;
            border-radius: 10px;
            font-size: 15px;
        }
        .form-group input:focus, .form-group textarea:focus {
            border-color: #c97b84;
        }
        .coupon-row {
            display: flex;
            gap: 10px;
        }
        .coupon-btn {
            padding: 12px 24px;
            background: #6b3f2a;
            color: white;
            border: none;
            border-radius: 10px;
            white-space: nowrap;
        }
        .place-order-btn {
            width: 100%;
            padding: 16px;
            background: #c97b84;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
        }
        .place-order-btn:hover {
            background: #a85f68;
        }
        .qr-box {
            border: 3px solid #c97b84;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            background: #fff;
        }
        @media (max-width: 992px) {
            .checkout-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="checkout-container">

    <!-- Cột trái: Chi tiết đơn hàng -->
    <div class="order-details">
        <h2>📦 Chi tiết đơn hàng</h2>

        <?php if ($error_message): ?>
            <div class="msg msg-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($success_message && $new_order_id): ?>
            <div class="msg msg-success text-center" style="padding:30px 20px;">
                <i class="fas fa-check-circle" style="font-size:48px;color:#2e7d32;"></i><br><br>
                <strong>Đặt hàng thành công!</strong><br>
                Mã đơn: <strong><?php echo $new_order_id; ?></strong>
            </div>
        <?php endif; ?>

        <?php if ($success_message && $new_order_id && isset($_POST['payment_method']) && $_POST['payment_method'] === 'banking'): ?>
            <div class="qr-box">
                <h3><i class="fas fa-university"></i> Thanh toán chuyển khoản MB Bank</h3>
                <img src="<?php echo mbQR($mb_account, $mb_name, $final_total ?? $subtotal, 'ANHBA ' . $new_order_id); ?>" 
                     alt="QR Code" style="width:260px;height:260px;border-radius:12px;">
                <div class="bank-info mt-4 text-left">
                    <p><strong>Ngân hàng:</strong> <?php echo $mb_bank; ?></p>
                    <p><strong>Số tài khoản:</strong> <span id="stk"><?php echo $mb_account; ?></span> 
                       <button class="copy-btn" onclick="copyText('stk')">Copy</button></p>
                    <p><strong>Chủ tài khoản:</strong> <?php echo $mb_name; ?></p>
                    <p><strong>Số tiền:</strong> <span style="color:#c97b84" id="sotien"><?php echo number_format($final_total ?? $subtotal,0,',','.'); ?>₫</span> 
                       <button class="copy-btn" onclick="copyText('sotien')">Copy</button></p>
                    <p><strong>Nội dung:</strong> <span id="noidung">ANHBA <?php echo $new_order_id; ?></span> 
                       <button class="copy-btn" onclick="copyText('noidung')">Copy</button></p>
                </div>
            </div>
        <?php elseif (!empty($cart_data)): ?>
            <table class="cart-table">
                <thead>
                    <tr><th>Sản phẩm</th><th>Đơn giá</th><th>SL</th><th>Size</th><th>Thành tiền</th></tr>
                </thead>
                <tbody>
                <?php foreach ($cart_data as $item): ?>
                    <tr>
                        <td>
                            <div class="cart-product">
                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="">
                                <span><?php echo htmlspecialchars($item['name']); ?></span>
                            </div>
                        </td>
                        <td><?php echo number_format($item['price'],0,',','.'); ?>₫</td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td><?php echo htmlspecialchars($item['size'] ?: '—'); ?></td>
                        <td><strong><?php echo number_format($item['price']*$item['quantity'],0,',','.'); ?>₫</strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="summary mt-4">
                <p><span>Tạm tính</span><span><?php echo number_format($subtotal,0,',','.'); ?>₫</span></p>
                <p><span>Phí vận chuyển</span><span style="color:#2e7d32">Miễn phí</span></p>
                <p class="total-row"><span>TỔNG CỘNG</span><span><?php echo number_format($subtotal,0,',','.'); ?>₫</span></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Cột phải: Form thông tin -->
    <?php if (empty($success_message)): ?>
    <div class="shipping-info">
        <h2>📍 Thông tin giao hàng</h2>
        <form method="POST" action="checkout.php">
            <input type="hidden" name="total_amount" value="<?php echo $subtotal; ?>">

            <div class="form-group">
                <label>Họ và tên <span style="color:#c97b84">*</span></label>
                <input type="text" name="customer_name" required value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Số điện thoại <span style="color:#c97b84">*</span></label>
                <input type="tel" name="customer_phone" required value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Địa chỉ giao hàng <span style="color:#c97b84">*</span></label>
                <input type="text" name="customer_address" placeholder="Số nhà, đường, phường, quận, tỉnh" required value="<?php echo htmlspecialchars($_POST['customer_address'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Ghi chú (tuỳ chọn)</label>
                <textarea name="note" rows="3"><?php echo htmlspecialchars($_POST['note'] ?? ''); ?></textarea>
            </div>

            <!-- Coupon -->
            <div class="form-group">
                <label>Mã giảm giá (tuỳ chọn)</label>
                <div class="coupon-row">
                    <input type="text" id="couponInput" placeholder="Nhập mã coupon...">
                    <button type="button" class="coupon-btn" onclick="applyCoupon()">Áp dụng</button>
                </div>
                <div id="couponMsg" style="display:none" class="coupon-msg"></div>
                <input type="hidden" name="coupon_code" id="couponCodeInput" value="">
            </div>

            <!-- Thanh toán -->
            <label style="font-weight:600;color:#6b3f2a;display:block;margin:20px 0 10px">Phương thức thanh toán <span style="color:#c97b84">*</span></label>
            <div class="payment-methods">
                <label class="pay-option selected" id="opt-cod" onclick="selectPay('cod')">
                    <input type="radio" name="payment_method" value="cod" checked>
                    <div class="pay-icon cod"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="pay-label">
                        <strong>COD - Thanh toán khi nhận hàng</strong>
                        <span>Trả tiền mặt khi shipper giao bánh</span>
                    </div>
                </label>

                <label class="pay-option" id="opt-banking" onclick="selectPay('banking')">
                    <input type="radio" name="payment_method" value="banking">
                    <div class="pay-icon bank"><i class="fas fa-university"></i></div>
                    <div class="pay-label">
                        <strong>Chuyển khoản ngân hàng</strong>
                        <span>Quét QR hoặc chuyển khoản</span>
                    </div>
                </label>
            </div>

            <button type="submit" name="place_order" class="place-order-btn" id="submitBtn">
                <i class="fas fa-lock"></i> Xác nhận đặt bánh
            </button>
        </form>
    </div>
    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>

<script>
// Script cũ giữ nguyên
const subtotalVal = <?php echo $subtotal; ?>;
function selectPay(type) {
    document.querySelectorAll('.pay-option').forEach(el => el.classList.remove('selected'));
    document.getElementById('opt-' + type).classList.add('selected');
}
// Thêm các function applyCoupon, copyText... nếu bạn có
</script>
</body>
</html>
<?php $conn->close(); ?>