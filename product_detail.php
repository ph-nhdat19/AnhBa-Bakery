<?php
include 'connect.php';
include 'header.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug 
                        FROM products p 
                        LEFT JOIN categories c ON p.category_id = c.id 
                        WHERE p.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Anh Ba Bakery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="main.css">
    <style>
        .product-detail {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
        }
        .product-image {
            position: relative;
        }
        .product-image img {
            width: 100%;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .product-info h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 32px;
            color: var(--brown);
            margin-bottom: 12px;
        }
        .price {
            font-size: 28px;
            font-weight: 700;
            color: var(--rose);
            margin: 16px 0;
        }
        .stock-info {
            font-size: 14px;
            margin: 10px 0;
        }
        .stock-info .in-stock { color: #2e7d32; }
        .stock-info .low-stock { color: #e65100; }
        .description {
            line-height: 1.7;
            color: #555;
            margin: 24px 0;
        }
        .size-options {
            margin: 20px 0;
        }
        .size-options label {
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }
        .btn-add {
            width: 100%;
            padding: 16px;
            background: var(--rose);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 20px;
        }
        .btn-add:hover {
            background: var(--rose-deep);
        }
        @media (max-width: 992px) {
            .product-detail {
                grid-template-columns: 1fr;
                gap: 30px;
            }
        }
    </style>
</head>
<body>

<div class="main">
    <nav class="breadcrumb-nav">
        <a href="index.php">Trang chủ</a> →
        <a href="<?php echo $product['category_slug']; ?>.php"><?php echo htmlspecialchars($product['category_name']); ?></a> →
        <span><?php echo htmlspecialchars($product['name']); ?></span>
    </nav>

    <div class="product-detail">
        <!-- Hình ảnh -->
        <div class="product-image">
            <img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
                 alt="<?php echo htmlspecialchars($product['name']); ?>">
        </div>

        <!-- Thông tin -->
        <div class="product-info">
            <h1><?php echo htmlspecialchars($product['name']); ?></h1>
            
            <div class="price"><?php echo number_format($product['price'], 0, ',', '.'); ?> ₫</div>

            <div class="stock-info">
                <?php if ($product['stock'] > 5): ?>
                    <span class="in-stock"><i class="fas fa-check-circle"></i> Còn hàng</span>
                <?php elseif ($product['stock'] > 0): ?>
                    <span class="low-stock"><i class="fas fa-fire"></i> Sắp hết (chỉ còn <?php echo $product['stock']; ?>)</span>
                <?php else: ?>
                    <span style="color:#c62828"><i class="fas fa-times-circle"></i> Hết hàng</span>
                <?php endif; ?>
            </div>

            <div class="description">
                <?php echo nl2br(htmlspecialchars($product['description'] ?? 'Sản phẩm tươi ngon từ Anh Ba Bakery. Được làm thủ công với nguyên liệu cao cấp.')); ?>
            </div>

            <form action="add_to_cart.php" method="POST">
                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                
                <div class="size-options">
                    <label>Chọn kích cỡ bánh</label>
                    <select name="size" class="size-select" style="width:100%;padding:12px;border-radius:8px">
                        <option value="nho">Nhỏ (10-15cm) - Phù hợp 1-2 người</option>
                        <option value="vua" selected>Vừa (16-20cm) - Phù hợp 4-6 người</option>
                        <option value="lon">Lớn (21-26cm) - Phù hợp 8-10 người</option>
                        <option value="party">Party (27cm+) - Phù hợp tiệc lớn</option>
                    </select>
                </div>

                <button type="submit" class="btn-add" <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>
                    <i class="fas fa-cart-plus"></i> Thêm vào giỏ hàng
                </button>
            </form>

            <div style="margin-top:30px;font-size:14px;color:#666">
                <i class="fas fa-truck"></i> Miễn phí giao từ 150.000đ<br>
                <i class="fas fa-clock"></i> Đặt trước 24h cho bánh sinh nhật
            </div>

            <!-- Đánh giá sản phẩm -->
            <div style="margin-top:40px;">
                <h3 style="font-size:20px;color:var(--brown);margin-bottom:10px;">Đánh giá sản phẩm</h3>

                <?php
                // Load reviews
                $reviewAvg = 0;
                $reviewCount = 0;
                $reviews_stmt = $conn->prepare("SELECT rating, comment, created_at, user_id FROM product_reviews WHERE product_id = ? ORDER BY created_at DESC");
                $reviews_stmt->bind_param("i", $id);
                $reviews_stmt->execute();
                $reviews_res = $reviews_stmt->get_result();

                $reviews = [];
                while ($row = $reviews_res->fetch_assoc()) {
                    $reviews[] = $row;
                    $reviewCount++;
                    $reviewAvg += (int)$row['rating'];
                }
                $reviews_stmt->close();
                if ($reviewCount > 0) $reviewAvg = $reviewAvg / $reviewCount;

                $can_review = false;
                $orders_for_review = [];
                if (isset($_SESSION['user_id'])) {
                    $uid = (int)$_SESSION['user_id'];
                    $check_stmt = $conn->prepare("SELECT DISTINCT o.order_id FROM orders o JOIN order_items oi ON oi.order_id = o.order_id WHERE o.user_id = ? AND o.status = 'Hoàn thành' AND oi.product_id = ?");
                    $check_stmt->bind_param("ii", $uid, $id);
                    $check_stmt->execute();
                    $check_res = $check_stmt->get_result();
                    while ($r = $check_res->fetch_assoc()) {
                        $orders_for_review[] = $r['order_id'];
                    }
                    $check_stmt->close();

                    // if already reviewed, no need show form
                    $already_stmt = $conn->prepare("SELECT id FROM product_reviews WHERE user_id = ? AND product_id = ? LIMIT 1");
                    $already_stmt->bind_param("ii", $uid, $id);
                    $already_stmt->execute();
                    $already_res = $already_stmt->get_result();
                    $already = $already_res->num_rows > 0;
                    $already_stmt->close();

                    if (count($orders_for_review) > 0 && !$already) $can_review = true;
                }
                ?>

                <div style="margin-bottom:14px; font-size:14px; color:#666;">
                    <?php if ($reviewCount > 0): ?>
                        <i class="fas fa-star" style="color:#f5a623"></i>
                        <strong><?php echo number_format($reviewAvg, 1); ?></strong>/5
                        <span style="margin-left:8px">(<?php echo $reviewCount; ?> đánh giá)</span>
                    <?php else: ?>
                        <span>Chưa có đánh giá nào.</span>
                    <?php endif; ?>
                </div>

                <?php if ($can_review): ?>
                    <div style="background:#fff; border:1px solid #eee; border-radius:12px; padding:16px;">
                        <div style="font-weight:700; margin-bottom:10px;">Gửi đánh giá của bạn</div>
                        <div style="display:flex; gap:8px; align-items:center; margin-bottom:10px;">
                            <label style="min-width:70px; font-weight:600;">Số sao</label>
                            <select id="ratingSelect" style="padding:10px; border-radius:10px; border:1px solid #ddd;">
                                <option value="5">5 - Tuyệt vời</option>
                                <option value="4">4 - Tốt</option>
                                <option value="3">3 - Trung bình</option>
                                <option value="2">2 - Kém</option>
                                <option value="1">1 - Tệ</option>
                            </select>
                        </div>
                        <div style="margin-bottom:10px;">
                            <textarea id="commentArea" rows="4" placeholder="Nhận xét của bạn..." style="width:100%; padding:12px; border-radius:10px; border:1px solid #ddd; outline:none;"></textarea>
                        </div>
                        <button type="button" onclick="submitReview()" style="width:100%; padding:14px; background:var(--rose); color:#fff; border:none; border-radius:12px; font-weight:800; cursor:pointer;">
                            Gửi đánh giá
                        </button>
                        <input type="hidden" id="reviewProductId" value="<?php echo $id; ?>">
                        <input type="hidden" id="reviewOrderId" value="<?php echo htmlspecialchars($orders_for_review[0]); ?>">
                        <div id="reviewMsg" style="margin-top:10px; color:#c62828; font-size:13px;"></div>
                    </div>
                <?php elseif (isset($_SESSION['user_id'])): ?>
                    <div style="font-size:14px;color:#666; background:#fafafa; border:1px solid #eee; border-radius:12px; padding:14px;">
                        <?php
                        if (!$can_review) {
                            // already reviewed or no eligible order
                            echo 'Bạn đã đánh giá hoặc chưa có đơn hàng hoàn thành để đánh giá.';
                        }
                        ?>
                    </div>
                <?php else: ?>
                    <div style="font-size:14px;color:#666; background:#fafafa; border:1px solid #eee; border-radius:12px; padding:14px;">
                        Vui lòng <a href="login.php" style="color:var(--rose-deep); font-weight:700;">đăng nhập</a> để gửi đánh giá.
                    </div>
                <?php endif; ?>

                <div style="margin-top:18px;">
                    <div style="font-weight:800; margin-bottom:10px;">Danh sách đánh giá</div>
                    <?php if ($reviewCount > 0): ?>
                        <?php foreach ($reviews as $rv): ?>
                            <div style="background:#fff; border:1px solid #eee; border-radius:12px; padding:14px; margin-bottom:12px;">
                                <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; margin-bottom:8px;">
                                    <div>
                                        <?php for ($i=1; $i<=5; $i++): ?>
                                            <i class="fas fa-star" style="color:<?php echo ($i <= (int)$rv['rating']) ? '#f5a623' : '#ddd'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <div style="color:#888; font-size:12px; white-space:nowrap;"><?php echo date('d/m/Y', strtotime($rv['created_at'])); ?></div>
                                </div>
                                <div style="color:#333; line-height:1.6; font-size:14px;">
                                    <?php echo nl2br(htmlspecialchars($rv['comment'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="color:#888; font-size:14px;">Không có dữ liệu.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function submitReview() {
    const productId = document.getElementById('reviewProductId').value;
    const orderId = document.getElementById('reviewOrderId').value;
    const rating = document.getElementById('ratingSelect').value;
    const comment = document.getElementById('commentArea').value.trim();

    if (!comment) {
        document.getElementById('reviewMsg').textContent = 'Vui lòng nhập nội dung đánh giá.';
        return;
    }

    document.getElementById('reviewMsg').textContent = 'Đang gửi...';

    const form = new FormData();
    form.append('product_id', productId);
    form.append('order_id', orderId);
    form.append('rating', rating);
    form.append('comment', comment);

    try {
        const res = await fetch('submit_review.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            document.getElementById('reviewMsg').textContent = data.message;
            setTimeout(() => window.location.reload(), 700);
        } else {
            document.getElementById('reviewMsg').textContent = data.message || 'Gửi đánh giá thất bại.';
        }
    } catch (e) {
        document.getElementById('reviewMsg').textContent = 'Có lỗi xảy ra, vui lòng thử lại.';
    }
}
</script>

<?php include 'footer.php'; ?>
</body>
</html>
<?php $conn->close(); ?>
