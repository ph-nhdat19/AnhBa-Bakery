<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=orders.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT order_id, customer_name, customer_phone, customer_address, total_amount, status, note, created_at
    FROM orders
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng của tôi | T-SHOP</title>
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { background: #f5f5f5; }
        .orders-container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        .orders-container h2 { font-size: 24px; font-weight: 700; margin-bottom: 24px; }
        .order-card {
            background: #fff; border-radius: 12px; padding: 20px 24px;
            margin-bottom: 20px; box-shadow: 0 1px 6px rgba(0,0,0,0.07);
        }
        .order-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid #f0f0f0;
            flex-wrap: wrap; gap: 8px;
        }
        .order-id   { font-weight: 700; font-size: 16px; }
        .order-date { font-size: 13px; color: #888; margin-left: 12px; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-wait   { background: #fff3cd; color: #856404; }
        .status-proc   { background: #d1ecf1; color: #0c5460; }
        .status-ship   { background: #cce5ff; color: #004085; }
        .status-done   { background: #d4edda; color: #155724; }
        .status-cancel { background: #f8d7da; color: #721c24; }
        .order-info { font-size: 14px; color: #555; margin-bottom: 14px; line-height: 2; }
        .order-items-toggle {
            background: none; border: 1px solid #ddd; border-radius: 6px;
            padding: 6px 14px; font-size: 13px; cursor: pointer; color: #555;
            transition: background 0.15s;
        }
        .order-items-toggle:hover { background: #f5f5f5; }
        .order-items-table {
            width: 100%; border-collapse: collapse; margin-top: 14px;
            font-size: 13px; display: none;
        }
        .order-items-table th {
            background: #f8f8f8; padding: 8px 12px; text-align: left;
            font-weight: 600; color: #555;
        }
        .order-items-table td { padding: 10px 12px; border-bottom: 1px solid #f0f0f0; }
        .order-items-table tr:last-child td { border-bottom: none; }
        .order-items-table img { width: 44px; height: 44px; object-fit: cover; border-radius: 6px; }
        .order-total { font-size: 16px; font-weight: 700; color: #e44; text-align: right; margin-top: 14px; }
        .empty-orders {
            text-align: center; padding: 70px 20px;
            background: #fff; border-radius: 12px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.06);
        }
        .empty-orders i { font-size: 52px; color: #ccc; display: block; margin-bottom: 16px; }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="orders-container">
    <h2><i class="fas fa-box" style="margin-right:10px;color:#888"></i>Đơn hàng của tôi</h2>

    <?php if ($orders->num_rows == 0): ?>
        <div class="empty-orders">
            <i class="fas fa-clipboard-list"></i>
            <p style="color:#888;font-size:15px;margin-bottom:8px">Bạn chưa có đơn hàng nào.</p>
            <a href="index.php" style="display:inline-block;margin-top:14px;padding:11px 28px;background:#111;color:#fff;border-radius:8px;font-size:14px;font-weight:600">Bắt đầu mua sắm</a>
        </div>
    <?php else: ?>
        <?php while ($order = $orders->fetch_assoc()):
            $status_class = match($order['status']) {
                'Chờ xác nhận' => 'status-wait',
                'Đang xử lý'   => 'status-proc',
                'Đang giao'    => 'status-ship',
                'Hoàn thành'   => 'status-done',
                'Huỷ'          => 'status-cancel',
                default        => 'status-wait'
            };
            $oid = $order['order_id'];
            $items_stmt = $conn->prepare("
                SELECT p.name, p.image_path, oi.quantity, oi.size, oi.price
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $items_stmt->bind_param("s", $oid);
            $items_stmt->execute();
            $order_items = $items_stmt->get_result();
            $items_stmt->close();
        ?>
        <div class="order-card">
            <div class="order-header">
                <div>
                    <span class="order-id">Đơn hàng #<?php echo htmlspecialchars($order['order_id']); ?></span>
                    <span class="order-date"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></span>
                </div>
                <span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($order['status']); ?></span>
            </div>
            <div class="order-info">
                <i class="fas fa-user" style="width:18px;color:#aaa"></i> <?php echo htmlspecialchars($order['customer_name']); ?> &nbsp;|&nbsp;
                <i class="fas fa-phone" style="width:18px;color:#aaa"></i> <?php echo htmlspecialchars($order['customer_phone']); ?><br>
                <i class="fas fa-map-marker-alt" style="width:18px;color:#aaa"></i> <?php echo htmlspecialchars($order['customer_address']); ?>
                <?php if ($order['note']): ?>
                    <br><i class="fas fa-sticky-note" style="width:18px;color:#aaa"></i> <?php echo htmlspecialchars($order['note']); ?>
                <?php endif; ?>
            </div>

            <button class="order-items-toggle" onclick="toggleItems(this)">
                <i class="fas fa-list" style="margin-right:4px"></i>
                Xem sản phẩm (<?php echo $order_items->num_rows; ?>)
            </button>

            <table class="order-items-table">
                <thead>
                    <tr><th>Sản phẩm</th><th>Đơn giá</th><th>SL</th><th>Size</th><th>Thành tiền</th></tr>
                </thead>
                <tbody>
                <?php while ($it = $order_items->fetch_assoc()): ?>
                <tr>
                    <td style="display:flex;align-items:center;gap:10px">
                        <img src="<?php echo htmlspecialchars($it['image_path']); ?>" alt="">
                        <span><?php echo htmlspecialchars($it['name']); ?></span>
                    </td>
                    <td><?php echo number_format($it['price'], 0, ',', '.'); ?>₫</td>
                    <td><?php echo $it['quantity']; ?></td>
                    <td><?php echo htmlspecialchars($it['size'] ?: '—'); ?></td>
                    <td><strong><?php echo number_format($it['price'] * $it['quantity'], 0, ',', '.'); ?>₫</strong></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>

            <div class="order-total">
                Tổng cộng: <?php echo number_format($order['total_amount'], 0, ',', '.'); ?> ₫
            </div>
        </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>

<script>
function toggleItems(btn) {
    const table = btn.nextElementSibling;
    const isVisible = table.style.display === 'table';
    table.style.display = isVisible ? 'none' : 'table';
    btn.innerHTML = isVisible
        ? '<i class="fas fa-list" style="margin-right:4px"></i> Xem sản phẩm'
        : '<i class="fas fa-chevron-up" style="margin-right:4px"></i> Ẩn sản phẩm';
}
</script>
</body>
</html>
<?php $conn->close(); ?>