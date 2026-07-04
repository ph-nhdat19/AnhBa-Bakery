<?php
include 'connect.php';
include 'header.php';

function getProducts($conn, $slug, $limit = 8) {
    $stmt = $conn->prepare("SELECT p.id, p.name, p.price, p.image_path FROM products p JOIN categories c ON p.category_id = c.id WHERE c.slug = ? LIMIT ?");
    $stmt->bind_param("si", $slug, $limit);
    $stmt->execute();
    $r = $stmt->get_result();
    $stmt->close();
    return $r;
}

// Lấy sản phẩm cho các section khác
$banchay   = getProducts($conn, 'ban-chay');
$banh_le   = getProducts($conn, 'banh-le');
$banh_kem  = getProducts($conn, 'banh-kem');
$banh_mi   = getProducts($conn, 'banh-mi');

// ==================== LẤY SẢN PHẨM KHUYẾN MÃI NGẪU NHIÊN ====================
$promo_products = [];
$promo_slugs = ['banh-kem', 'banh-mi', 'banh-le', 'ban-chay'];

foreach ($promo_slugs as $slug) {
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.price, p.image_path, p.stock 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE c.slug = ? 
        ORDER BY RAND() LIMIT 2
    ");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $promo_products[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Anh Ba Bakery — Chuỗi Bánh Kem & Bánh Ngọt</title>
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<!-- Video Banner (giữ nguyên) -->
<div style="position:relative;width:100%;height:480px;overflow:hidden">
    <video autoplay muted loop playsinline style="width:100%;height:100%;object-fit:cover">
        <source src="img/video-banner.mp4" type="video/mp4">
    </video>
    <div style="position:absolute;inset:0;background:linear-gradient(to bottom,rgba(58,35,24,0.35) 0%,rgba(58,35,24,0.55) 100%)"></div>
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;color:#fff;">
        <p style="font-family:'Cormorant Garamond',serif;font-size:15px;letter-spacing:0.2em;text-transform:uppercase;opacity:0.9;margin-bottom:10px">Tiệm bánh ngọt</p>
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:56px;font-weight:600;font-style:italic;margin-bottom:10px;text-shadow:0 2px 12px rgba(0,0,0,0.3)">Anh Ba Bakery</h1>
        <p style="font-size:16px;margin-bottom:28px;opacity:0.9;letter-spacing:0.05em">Tươi ngon mỗi ngày — Làm bằng cả tấm lòng</p>
        <a href="banchay.php" style="background:var(--rose);color:#fff;padding:14px 36px;border-radius:30px;text-decoration:none;font-size:15px;font-weight:600;letter-spacing:0.05em;transition:background 0.2s;font-family:'DM Sans',sans-serif">Khám phá ngay →</a>
    </div>
</div>

<?php
function renderGrid($result) {
    if (!$result || (is_array($result) && count($result) == 0) || (!is_array($result) && $result->num_rows == 0)) {
        echo '<p style="grid-column:1/-1;text-align:center;color:#b08878;padding:40px 0;font-style:italic">Chưa có sản phẩm</p>';
        return;
    }
    
    if (is_array($result)) {
        // Dùng cho mảng promo_products
        foreach ($result as $p):
?>
    <div class="product">
        <a href="product_detail.php?id=<?php echo $p['id']; ?>">
            <div class="product-img-wrap">
                <img src="<?php echo htmlspecialchars($p['image_path']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>">
            </div>
        </a>
        <div class="product-name"><?php echo htmlspecialchars($p['name']); ?></div>
        <div><span class="product-price"><?php echo number_format($p['price'], 0, ',', '.'); ?>₫</span></div>
        <form action="add_to_cart.php" method="POST" onsubmit="return checkSize(this)">
            <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
            <input type="hidden" name="quantity" value="1">
            <select name="size" class="size-select">
                <option value="">-- Chọn kích cỡ --</option>
                <option value="nho">Nhỏ (10–15cm)</option>
                <option value="vua">Vừa (16–20cm)</option>
                <option value="lon">Lớn (21–26cm)</option>
                <option value="party">Party (27cm+)</option>
            </select>
            <div class="button-group">
                <button type="submit" class="btn btn-outline" name="add_to_cart">
                    <i class="fas fa-cart-plus"></i> Đặt bánh
                </button>
                <a href="product_detail.php?id=<?php echo $p['id']; ?>" class="btn btn-fill">Chi tiết</a>
            </div>
        </form>
    </div>
<?php endforeach;
    } else {
        // Dùng cho MySQLi Result (các section khác)
        while ($p = $result->fetch_assoc()):
?>
    <div class="product">
        <a href="product_detail.php?id=<?php echo $p['id']; ?>">
            <div class="product-img-wrap">
                <img src="<?php echo htmlspecialchars($p['image_path']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>">
            </div>
        </a>
        <div class="product-name"><?php echo htmlspecialchars($p['name']); ?></div>
        <div><span class="product-price"><?php echo number_format($p['price'], 0, ',', '.'); ?>₫</span></div>
        <form action="add_to_cart.php" method="POST" onsubmit="return checkSize(this)">
            <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
            <input type="hidden" name="quantity" value="1">
            <select name="size" class="size-select">
                <option value="">-- Chọn kích cỡ --</option>
                <option value="nho">Nhỏ (10–15cm)</option>
                <option value="vua">Vừa (16–20cm)</option>
                <option value="lon">Lớn (21–26cm)</option>
                <option value="party">Party (27cm+)</option>
            </select>
            <div class="button-group">
                <button type="submit" class="btn btn-outline" name="add_to_cart">
                    <i class="fas fa-cart-plus"></i> Đặt bánh
                </button>
                <a href="product_detail.php?id=<?php echo $p['id']; ?>" class="btn btn-fill">Chi tiết</a>
            </div>
        </form>
    </div>
<?php endwhile;
    }
}
?>

<div class="section-title"><a href="banchay.php">Bán chạy nhất</a></div>
<div class="main"><div class="product-grid"><?php renderGrid($banchay); ?></div></div>

<!-- === KHUYẾN MÃI HÔM NAY (ĐÃ SỬA) === -->
<div class="section-title"><a href="sale.php">Khuyến mãi hôm nay</a></div>
<div class="main"><div class="product-grid"><?php renderGrid($promo_products); ?></div></div>

<div class="section-title"><a href="men.php">Bánh kem</a></div>
<div class="main"><div class="product-grid"><?php renderGrid($banh_kem); ?></div></div>

<div class="section-title"><a href="women.php">Bánh mì</a></div>
<div class="main"><div class="product-grid"><?php renderGrid($banh_mi); ?></div></div>

<div class="section-title"><a href="bag.php">Bánh lẻ</a></div>
<div class="main"><div class="product-grid"><?php renderGrid($banh_le); ?></div></div>

<?php include 'footer.php'; ?>

<!-- Popup chọn kích cỡ -->
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
        setTimeout(function(){ sel.style.border = ''; }, 2500);
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