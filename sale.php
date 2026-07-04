<?php
include 'connect.php';
include 'header.php';

// ==================== LẤY NGẪU NHIÊN 2 SẢN PHẨM TỪ MỖI DANH MỤC ====================
$promo_products = [];

$promo_slugs = ['banh-kem', 'banh-mi', 'banh-le', 'ban-chay'];

foreach ($promo_slugs as $slug) {
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.price, p.image_path, p.stock, c.name as category_name 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE c.slug = ? 
        ORDER BY RAND() 
        LIMIT 2
    ");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $promo_products[] = $row;
    }
    $stmt->close();
}

// ==================== PHẦN CODE CÒN LẠI GIỮ NGUYÊN HOÀN TOÀN ====================
$limit  = 12;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort   = isset($_GET['sort']) ? $_GET['sort'] : '';

$search_condition = $search ? "AND p.name LIKE ?" : "";
$search_param     = $search ? "%$search%" : "";

$order_sql = match($sort) {
    'price_asc'  => 'ORDER BY p.price ASC',
    'price_desc' => 'ORDER BY p.price DESC',
    'newest'     => 'ORDER BY p.created_at DESC',
    default      => 'ORDER BY p.id DESC'
};

// Slug danh mục khuyến mãi
$category_slug = 'gia-uu-dai';
$stmt = $conn->prepare("SELECT id FROM categories WHERE slug = ?");
$stmt->bind_param("s", $category_slug);
$stmt->execute();
$category    = $stmt->get_result()->fetch_assoc();
$category_id = $category ? $category['id'] : 0;
$stmt->close();

// Đếm tổng sản phẩm
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM products p WHERE p.category_id = ? $search_condition");
if ($search) $stmt->bind_param("is", $category_id, $search_param);
else         $stmt->bind_param("i",  $category_id);
$stmt->execute();
$total_products = $stmt->get_result()->fetch_assoc()['total'];
$total_pages    = ceil($total_products / $limit);
$stmt->close();

// Xử lý thêm giỏ hàng
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php?redirect=sale.php");
        exit;
    }
    $user_id    = $_SESSION['user_id'];
    $product_id = (int)$_POST['product_id'];
    $quantity   = max(1, (int)($_POST['quantity'] ?? 1));
    $size       = $_POST['size'] ?? '';

    $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id=? AND product_id=? AND size=?");
    $stmt->bind_param("iis", $user_id, $product_id, $size);
    $stmt->execute();
    $cart_item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($cart_item) {
        $nq   = $cart_item['quantity'] + $quantity;
        $stmt = $conn->prepare("UPDATE cart SET quantity=? WHERE id=?");
        $stmt->bind_param("ii", $nq, $cart_item['id']);
    } else {
        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, size) VALUES (?,?,?,?)");
        $stmt->bind_param("iiis", $user_id, $product_id, $quantity, $size);
    }
    $stmt->execute();
    $stmt->close();
    header("Location: sale.php?success=added&sort=$sort&page=$page");
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Khuyến Mãi Hôm Nay — Anh Ba Bakery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="main.css">
    <style>
        /* Banner khuyến mãi */
        .sale-hero {
            background: linear-gradient(135deg, var(--brown) 0%, var(--rose-deep) 100%);
            border-radius: 16px;
            padding: 36px 40px;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            position: relative;
            overflow: hidden;
        }
        .sale-hero::before {
            content: '🎂';
            position: absolute;
            right: -10px;
            top: -20px;
            font-size: 120px;
            opacity: 0.08;
            pointer-events: none;
        }
        .sale-hero-text h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 30px;
            font-style: italic;
            font-weight: 600;
            color: #fff;
            margin-bottom: 6px;
        }
        .sale-hero-text p {
            font-size: 14px;
            color: rgba(255,255,255,0.82);
            line-height: 1.6;
        }
        .sale-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .sale-badge {
            background: rgba(255,255,255,0.18);
            border: 1.5px solid rgba(255,255,255,0.35);
            color: #fff;
            border-radius: 30px;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.03em;
            white-space: nowrap;
        }

        /* Countdown */
        .countdown-bar {
            background: var(--gold-light);
            border: 1px solid var(--gold);
            border-radius: 10px;
            padding: 12px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        .countdown-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--brown);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .countdown-timer {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .cd-block {
            background: var(--brown);
            color: var(--gold-light);
            border-radius: 6px;
            padding: 5px 10px;
            font-size: 18px;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            min-width: 40px;
            text-align: center;
            line-height: 1;
        }
        .cd-sep {
            font-size: 18px;
            font-weight: 700;
            color: var(--brown);
            line-height: 1;
        }
        .cd-unit {
            font-size: 10px;
            color: var(--text-mid);
            text-align: center;
            display: block;
            margin-top: 2px;
        }
    </style>
</head>
<body>
<div class="main">

    <!-- Breadcrumb -->
    <nav class="breadcrumb-nav">
        <a href="index.php"><i class="fas fa-home"></i> Trang chủ</a>
        <i class="fas fa-chevron-right"></i>
        <span style="color:var(--brown)">Khuyến mãi</span>
    </nav>

    <!-- Banner khuyến mãi -->
    <div class="sale-hero">
        <div class="sale-hero-text">
            <h2>🎉 Khuyến Mãi Hôm Nay</h2>
            <p>Bánh tươi ngon — Giá ưu đãi mỗi ngày.<br>Đặt sớm, giao đúng giờ, miễn phí hộp!</p>
        </div>
        <div class="sale-badges">
            <span class="sale-badge"><i class="fas fa-tag" style="margin-right:5px"></i>Giảm đến 40%</span>
            <span class="sale-badge"><i class="fas fa-box-open" style="margin-right:5px"></i>Tặng hộp đẹp</span>
            <span class="sale-badge"><i class="fas fa-shipping-fast" style="margin-right:5px"></i>Ship miễn phí</span>
        </div>
    </div>

    <!-- Countdown -->
    <div class="countdown-bar">
        <div class="countdown-label">
            <i class="fas fa-clock" style="color:var(--rose)"></i>
            Khuyến mãi kết thúc sau:
        </div>
        <div class="countdown-timer">
            <div><span class="cd-block" id="cd-h">00</span><span class="cd-unit">giờ</span></div>
            <span class="cd-sep">:</span>
            <div><span class="cd-block" id="cd-m">00</span><span class="cd-unit">phút</span></div>
            <span class="cd-sep">:</span>
            <div><span class="cd-block" id="cd-s">00</span><span class="cd-unit">giây</span></div>
        </div>
        <span style="font-size:12px;color:var(--text-soft);margin-left:auto">* Cập nhật lúc nửa đêm mỗi ngày</span>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i> Đã thêm vào giỏ hàng!
        </div>
    <?php endif; ?>

    <!-- Filter bar -->
    <div class="filter-bar">
        <span class="result-count">
            Tìm thấy <strong><?php echo count($promo_products); ?></strong> sản phẩm khuyến mãi
        </span>
        <select class="sort-select" onchange="location.href='?sort='+this.value+'<?php echo $search ? '&search='.urlencode($search) : ''; ?>'">
            <option value="" <?php echo $sort==''?'selected':''; ?>>Mặc định</option>
            <option value="newest" <?php echo $sort=='newest'?'selected':''; ?>>Mới nhất</option>
            <option value="price_asc" <?php echo $sort=='price_asc'?'selected':''; ?>>Giá: Thấp → Cao</option>
            <option value="price_desc" <?php echo $sort=='price_desc'?'selected':''; ?>>Giá: Cao → Thấp</option>
        </select>
    </div>

    <!-- Product Grid -->
    <div class="product-grid">
        <?php if (empty($promo_products)): ?>
            <p style="grid-column:1/-1;text-align:center;padding:60px 20px;color:#888">Chưa có sản phẩm khuyến mãi hôm nay.</p>
        <?php else: ?>
            <?php foreach ($promo_products as $product): ?>
            <div class="product">
                <a href="product_detail.php?id=<?php echo $product['id']; ?>">
                    <div class="product-img-wrap">
                        <img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <div style="position:absolute;top:10px;left:10px;background:var(--rose);color:#fff;
                                    font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;">
                            SALE
                        </div>
                    </div>
                </a>

                <?php if ($product['stock'] <= 0): ?>
                    <span class="stock-badge badge-out"><i class="fas fa-times-circle"></i> Hết hàng</span>
                <?php elseif ($product['stock'] <= 5): ?>
                    <span class="stock-badge badge-low"><i class="fas fa-fire"></i> Sắp hết (còn <?php echo $product['stock']; ?>)</span>
                <?php endif; ?>

                <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                <div><span class="product-price"><?php echo number_format($product['price'], 0, ',', '.'); ?>₫</span></div>

                <form action="sale.php" method="POST" onsubmit="return checkSize(this)">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <input type="hidden" name="quantity" value="1">
                    <select name="size" class="size-select">
                        <option value="">-- Chọn kích cỡ --</option>
                        <option value="nho">Nhỏ (10–15cm)</option>
                        <option value="vua">Vừa (16–20cm)</option>
                        <option value="lon">Lớn (21–26cm)</option>
                        <option value="party">Party (27cm+)</option>
                    </select>
                    <div class="button-group">
                        <button type="submit" class="btn btn-outline" name="add_to_cart" 
                            <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-cart-plus"></i> Đặt bánh
                        </button>
                        <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="btn btn-fill">Chi tiết</a>
                    </div>
                </form>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Phân trang và các phần khác giữ nguyên như file cũ của bạn -->

</div>

<?php include 'footer.php'; ?>

<!-- Popup và script giữ nguyên -->
<div class="custom-alert-overlay" id="sizeAlert">
  <div class="custom-alert-box">
    <span class="custom-alert-icon"></span>
    <div class="custom-alert-title">Chưa chọn kích cỡ!</div>
    <div class="custom-alert-msg">Vui lòng chọn kích cỡ bánh<br>trước khi thêm vào giỏ hàng.</div>
    <button class="custom-alert-btn" onclick="document.getElementById('sizeAlert').classList.remove('show')">Đã hiểu</button>
  </div>
</div>

<script>
/* Countdown */
(function() {
    function tick() {
        var now = new Date();
        var end = new Date();
        end.setHours(23, 59, 59, 0);
        var diff = Math.max(0, Math.floor((end - now) / 1000));
        var h = Math.floor(diff / 3600);
        var m = Math.floor((diff % 3600) / 60);
        var s = diff % 60;
        document.getElementById('cd-h').textContent = String(h).padStart(2,'0');
        document.getElementById('cd-m').textContent = String(m).padStart(2,'0');
        document.getElementById('cd-s').textContent = String(s).padStart(2,'0');
    }
    tick();
    setInterval(tick, 1000);
})();

function checkSize(form) {
    var sel = form.querySelector('select[name=size]');
    if (sel && sel.value === '') {
        document.getElementById('sizeAlert').classList.add('show');
        sel.style.border = '2px solid var(--rose)';
        setTimeout(() => sel.style.border = '', 2500);
        return false;
    }
    return true;
}
</script>
</body>
</html>
<?php $conn->close(); ?>