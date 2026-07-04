<?php
include 'connect.php';
include 'header.php';

$limit = 12;
$page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort   = isset($_GET['sort']) ? $_GET['sort'] : '';

$search_condition = $search ? "AND p.name LIKE ?" : "";
$search_param = $search ? "%$search%" : "";

$order_sql = match($sort) {
    'price_asc'  => 'ORDER BY p.price ASC',
    'price_desc' => 'ORDER BY p.price DESC',
    'newest'     => 'ORDER BY p.created_at DESC',
    default      => 'ORDER BY p.id DESC'
};

// Danh mục Bán Chạy
$category_slug = 'ban-chay';
$stmt = $conn->prepare("SELECT id FROM categories WHERE slug = ?");
$stmt->bind_param("s", $category_slug);
$stmt->execute();
$category = $stmt->get_result()->fetch_assoc();
$category_id = $category ? $category['id'] : 0;
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM products p WHERE p.category_id = ? $search_condition");
if ($search) $stmt->bind_param("is", $category_id, $search_param);
else $stmt->bind_param("i", $category_id);
$stmt->execute();
$total_products = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_products / $limit);
$stmt->close();

$stmt = $conn->prepare("SELECT p.id, p.name, p.price, p.image_path, p.stock FROM products p WHERE p.category_id = ? $search_condition $order_sql LIMIT ? OFFSET ?");
if ($search) $stmt->bind_param("isii", $category_id, $search_param, $limit, $offset);
else $stmt->bind_param("iii", $category_id, $limit, $offset);
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bán Chạy Nhất - Anh Ba Bakery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="main.css">
    <style>
        .category-banner {
            background: linear-gradient(rgba(107,63,42,0.85), rgba(201,123,132,0.85)), 
                        url('https://source.unsplash.com/random/1200x400/?best-seller,cake') center/cover;
            color: white;
            padding: 70px 20px;
            text-align: center;
            border-radius: 20px;
            margin-bottom: 30px;
            position: relative;
        }
        .category-banner h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 42px;
            font-style: italic;
            margin-bottom: 10px;
        }
        .category-banner::after {
            content: '🔥';
            position: absolute;
            top: 20px;
            right: 30px;
            font-size: 60px;
            opacity: 0.3;
        }
        .stock-badge { 
            font-size: 11px; 
            font-weight: 600; 
            padding: 3px 10px; 
            border-radius: 20px; 
            display: inline-block; 
            margin-bottom: 6px; 
        }
        .badge-low { background: #fff3e0; color: #e65100; }
        .badge-out { background: #ffebee; color: #c62828; }
    </style>
</head>
<body>
<div class="main">

    <nav class="breadcrumb-nav">
        <a href="index.php"><i class="fas fa-home"></i> Trang chủ</a>
        <i class="fas fa-chevron-right"></i>
        <span style="color:#333">Bán Chạy Nhất</span>
    </nav>

    <!-- Banner Bán Chạy -->
    <div class="category-banner">
        <h1>🔥 BÁN CHẠY NHẤT</h1>
        <p style="font-size:18px;opacity:0.95">Những chiếc bánh được khách yêu thích nhất • Luôn tươi mới mỗi ngày</p>
    </div>

    <div class="title">🔥 SẢN PHẨM BÁN CHẠY</div>

    <?php if (isset($_GET['success'])): ?>
        <div class="success-message"><i class="fas fa-check-circle"></i> Đã thêm vào giỏ hàng!</div>
    <?php endif; ?>

    <div class="filter-bar">
        <span class="result-count">Tìm thấy <strong><?php echo $total_products; ?></strong> sản phẩm bán chạy</span>
        <select class="sort-select" onchange="location.href='?sort='+this.value+'<?php echo $search ? '&search='.urlencode($search) : ''; ?>'">
            <option value="" <?php echo $sort==''?'selected':''; ?>>Mặc định</option>
            <option value="newest" <?php echo $sort=='newest'?'selected':''; ?>>Mới nhất</option>
            <option value="price_asc" <?php echo $sort=='price_asc'?'selected':''; ?>>Giá: Thấp → Cao</option>
            <option value="price_desc" <?php echo $sort=='price_desc'?'selected':''; ?>>Giá: Cao → Thấp</option>
        </select>
    </div>

    <div class="product-grid">
        <?php while ($product = $products->fetch_assoc()): ?>
        <div class="product">
            <a href="product_detail.php?id=<?php echo $product['id']; ?>">
                <div class="product-img-wrap">
                    <img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <div style="position:absolute;top:12px;right:12px;background:#c97b84;color:white;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;">
                        🔥 Bán chạy
                    </div>
                </div>
            </a>

            <?php if ($product['stock'] <= 0): ?>
                <span class="stock-badge badge-out"><i class="fas fa-times-circle"></i> Hết hàng</span>
            <?php elseif ($product['stock'] <= 5): ?>
                <span class="stock-badge badge-low"><i class="fas fa-fire"></i> Sắp hết (<?php echo $product['stock']; ?>)</span>
            <?php endif; ?>

            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
            <div><span class="product-price"><?php echo number_format($product['price'], 0, ',', '.'); ?>₫</span></div>

            <form action="banchay.php" method="POST" onsubmit="return checkSize(this)">
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
        <?php endwhile; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
               class="<?php echo $page==$i?'active':''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>

<!-- Popup chọn size -->
<div class="custom-alert-overlay" id="sizeAlert">
  <div class="custom-alert-box">
    <span class="custom-alert-icon"></span>
    <div class="custom-alert-title">Chưa chọn kích cỡ!</div>
    <div class="custom-alert-msg">Vui lòng chọn kích cỡ bánh<br>trước khi thêm vào giỏ hàng.</div>
    <button class="custom-alert-btn" onclick="document.getElementById('sizeAlert').classList.remove('show')">Đã hiểu</button>
  </div>
</div>

<script>
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
document.addEventListener('DOMContentLoaded', function(){
    var overlay = document.getElementById('sizeAlert');
    if(overlay) overlay.addEventListener('click', function(e){
        if(e.target === this) this.classList.remove('show');
    });
});
</script>
</body>
</html>
<?php $conn->close(); ?>