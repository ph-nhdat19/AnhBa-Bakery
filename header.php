<?php
// header.php - ANH BA BAKERY
// Chỉ output phần nội dung (promo-bar, header, mobile-menu)
// KHÔNG khai báo <!DOCTYPE>, <html>, <head>, <body> ở đây

if (session_status() === PHP_SESSION_NONE) session_start();
include_once 'connect.php';

$search = isset($_GET['search']) ? $_GET['search'] : '';

$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $cart_count = $r['total'] ?? 0;
    $stmt->close();
}
?>
<!-- Promo bar -->
<div class="promo-bar">
    🚚 Miễn phí giao hàng từ 150.000đ &nbsp;|&nbsp; 🎂 Đặt bánh sinh nhật trước 24h &nbsp;|&nbsp; 🎁 Tặng hộp VIP khi mua từ 2 bánh
</div>

<!-- Header -->
<header class="header">
    <div class="logo"><a href="index.php">Anh Ba Bakery</a></div>

    <nav>
        <ul class="nav">
            <li><a href="sale.php">Khuyến mãi</a></li>
            <li><a href="men.php">Bánh kem</a></li>
            <li><a href="women.php">Bánh mì</a></li>
            <li><a href="bag.php">Bánh lẻ</a></li>
            <li><a href="banchay.php" style="color:var(--rose-deep)">🔥 Bán chạy</a></li>
        </ul>
    </nav>

    <div class="header-right">
        <div class="search-wrap">
            <form action="search.php" method="GET">
                <input type="text" name="search" placeholder="Tìm bánh..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <?php if (isset($_SESSION['user_id'])): ?>
        <div class="user-menu">
            <a href="#" class="icon-btn"><i class="fas fa-user"></i></a>
            <div class="user-dropdown">
              <div class="user-dropdown-inner">
                <div class="u-name">Xin chào, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></div>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="admin.php"><i class="fas fa-cog" style="margin-right:6px;width:16px"></i>Quản trị</a>
                <?php endif; ?>
                <a href="orders.php"><i class="fas fa-box" style="margin-right:6px;width:16px"></i>Đơn hàng của tôi</a>
                <form method="POST" action="logout.php">
                    <button type="submit"><i class="fas fa-sign-out-alt" style="margin-right:6px;width:16px"></i>Đăng xuất</button>
                </form>
              </div>
            </div>
        </div>
        <?php else: ?>
        <a href="login.php" class="icon-btn"><i class="fas fa-user"></i></a>
        <?php endif; ?>

        <a href="cart.php" class="icon-btn" style="position:relative">
            <i class="fas fa-shopping-basket"></i>
            <?php if ($cart_count > 0): ?>
                <span class="cart-badge"><?php echo $cart_count > 99 ? '99+' : $cart_count; ?></span>
            <?php endif; ?>
        </a>

        <button class="hamburger" onclick="toggleMobileMenu()"><i class="fas fa-bars"></i></button>
    </div>
</header>

<!-- Mobile menu -->
<div id="mobileMenu" class="mobile-menu" style="display:none">
    <a href="sale.php">Khuyến mãi</a>
    <a href="men.php">Bánh kem</a>
    <a href="women.php">Bánh mì</a>
    <a href="bag.php">Bánh lẻ</a>
    <a href="banchay.php" style="color:var(--rose-deep)">🔥 Bán chạy</a>
</div>

<script>
function toggleMobileMenu() {
    const m = document.getElementById('mobileMenu');
    m.style.display = m.style.display === 'none' ? 'block' : 'none';
}
</script>