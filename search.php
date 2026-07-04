<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'connect.php';

// FIX: xử lý POST trước header.php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    $search_q = trim($_GET['search'] ?? '');
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php?redirect=" . urlencode("search.php?search=" . urlencode($search_q)));
        exit;
    }
    $user_id    = (int)$_SESSION['user_id'];
    $product_id = (int)$_POST['product_id'];
    $quantity   = 1;
    $size       = trim($_POST['size'] ?? '');

    $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND size = ?");
    $stmt->bind_param("iis", $user_id, $product_id, $size);
    $stmt->execute();
    $cart_item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($cart_item) {
        $new_quantity = $cart_item['quantity'] + $quantity;
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_quantity, $cart_item['id']);
    } else {
        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, size) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $user_id, $product_id, $quantity, $size);
    }
    $stmt->execute();
    $stmt->close();
    header("Location: search.php?search=" . urlencode($search_q) . "&success=added");
    exit;
}

$search          = trim($_GET['search'] ?? '');
$limit           = 12;
$page            = max(1, (int)($_GET['page'] ?? 1));
$offset          = ($page - 1) * $limit;
$products        = [];
$total_products  = 0;
$total_pages     = 0;

if ($search !== '') {
    $search_param = "%$search%";

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM products WHERE name LIKE ? OR description LIKE ?");
    $stmt->bind_param("ss", $search_param, $search_param);
    $stmt->execute();
    $total_products = $stmt->get_result()->fetch_assoc()['total'];
    $total_pages    = ceil($total_products / $limit);
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.price, p.image_path, c.name as category_name
        FROM products p LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.name LIKE ? OR p.description LIKE ?
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ssii", $search_param, $search_param, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { $products[] = $row; }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tìm kiếm: <?php echo htmlspecialchars($search); ?> - TWO-AD</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="main.css">
</head>
<body>

<?php include 'header.php'; ?>

<div class="main">
    <?php if ($search === ''): ?>
        <div class="title">Tìm kiếm sản phẩm</div>
        <p style="color:#888;text-align:center;padding:50px 0">Nhập từ khóa vào ô tìm kiếm ở trên để bắt đầu.</p>
    <?php else: ?>
        <div class="title">Kết quả tìm kiếm: "<?php echo htmlspecialchars($search); ?>"</div>
        <p style="color:#888;margin-bottom:20px;font-size:14px">
            Tìm thấy <strong><?php echo $total_products; ?></strong> sản phẩm
        </p>

        <?php if (isset($_GET['success']) && $_GET['success'] === 'added'): ?>
            <div class="success-message"><i class="fas fa-check-circle"></i> Đã thêm vào giỏ hàng!</div>
        <?php endif; ?>

        <?php if (empty($products)): ?>
            <div style="text-align:center;padding:60px 20px;color:#888">
                <i class="fas fa-search" style="font-size:44px;display:block;margin-bottom:16px;opacity:0.25"></i>
                <p>Không tìm thấy sản phẩm nào phù hợp với "<strong><?php echo htmlspecialchars($search); ?></strong>".</p>
                <a href="index.php" style="display:inline-block;margin-top:18px;padding:11px 28px;background:#111;color:#fff;border-radius:8px;font-weight:600">Xem tất cả sản phẩm</a>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product">
                        <a href="product_detail.php?id=<?php echo $product['id']; ?>">
                            <img src="<?php echo htmlspecialchars($product['image_path']); ?>"
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </a>
                        <p><?php echo htmlspecialchars($product['name']); ?></p>
                        <?php if ($product['category_name']): ?>
                            <p style="font-size:12px;color:#aaa;padding:0 10px"><?php echo htmlspecialchars($product['category_name']); ?></p>
                        <?php endif; ?>
                        <p class="price"><?php echo number_format($product['price'], 0, ',', '.'); ?> ₫</p>
                        <form action="search.php?search=<?php echo urlencode($search); ?>" method="POST">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <select name="size" class="size-select">
                                <option value="">Chọn size</option>
                                <?php for ($i = 36; $i <= 44; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                            <div class="button-group">
                                <button type="submit" class="btn btn-outline" name="add_to_cart">Thêm vào giỏ</button>
                                <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="btn btn-fill">Chi tiết</a>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>"
                       class="<?php echo $page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
<?php $conn->close(); ?>