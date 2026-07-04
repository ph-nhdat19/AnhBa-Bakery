<?php
// =============================
// A3 Bakery - Admin (Production Ready)

// =============================
session_start();

require_once 'connect.php';

// ---------- Secure helpers ----------
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function require_admin(): void {
    $role = $_SESSION['role'] ?? null;
    $user = $_SESSION['username'] ?? null;
    if (!$user || $role !== 'admin') {
        header('Location: login.php');
        exit;
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('CSRF validation failed');
    }
}

function get_post(string $key, $default = null) {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

function v_str(string $key, int $maxLen = 200, bool $required = false): ?string {
    $v = $_POST[$key] ?? null;
    if ($v === null) return $required ? null : null;
    $v = trim((string)$v);
    if ($required && $v === '') return null;
    if (mb_strlen($v) > $maxLen) $v = mb_substr($v, 0, $maxLen);
    return $v;
}

function v_int(string $key, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX, bool $required = false): ?int {
    $v = $_POST[$key] ?? null;
    if ($v === null) return $required ? null : null;
    if (!is_numeric($v)) return null;
    $n = (int)$v;
    if ($n < $min || $n > $max) return null;
    return $n;
}

function v_float(string $key, float $min = -INF, float $max = INF, bool $required = false): ?float {
    $v = $_POST[$key] ?? null;
    if ($v === null) return $required ? null : null;
    if (!is_numeric($v)) return null;
    $n = (float)$v;
    if ($n < $min || $n > $max) return null;
    return $n;
}

function v_enum(string $key, array $allowed, bool $required = false): ?string {
    $v = $_POST[$key] ?? null;
    if ($v === null) return $required ? null : null;
    $v = (string)$v;
    return in_array($v, $allowed, true) ? $v : null;
}

require_admin();

// ---------- Upload image (production ready) ----------
function handle_product_upload(?string &$error = null): ?string {
    if (empty($_FILES['image']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $error = 'Upload failed';
        return null;
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        $error = 'File too large (max 5MB)';
        return null;
    }

    $allowedExt = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $origName = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!isset($allowedExt[$ext])) {
        $error = 'Unsupported file extension';
        return null;
    }

    $tmp = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmp)) {
        $error = 'Invalid upload';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    $expectedMime = $allowedExt[$ext];

    // Validate both MIME and real image signature
    if ($mime !== $expectedMime) {
        $error = 'Invalid MIME type';
        return null;
    }
    $imgInfo = @getimagesize($tmp);
    if ($imgInfo === false) {
        $error = 'Corrupted/invalid image';
        return null;
    }

    $targetDir = __DIR__ . '/uploads/products/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $randomName = bin2hex(random_bytes(16));
    $newFilename = $randomName . '.' . $ext;
    $destPath = $targetDir . $newFilename;

    if (!move_uploaded_file($tmp, $destPath)) {
        $error = 'Failed to move file';
        return null;
    }

    // return relative path used in HTML
    return 'uploads/products/' . $newFilename;
}

// ---------- CSRF refresh helper for templates ----------
$csrf = csrf_token();



// ── Thêm sản phẩm ──────────────────────────────────────────────
if (isset($_POST['add_product'])) {
    verify_csrf();

    $name = v_str('name', 120, true);
    $price = v_float('price', 0.0, 1000000000.0, true);
    $stock = v_int('stock', 0, 1000000000, true);
    $category_id = v_int('category_id', 1, 1000000000, true);

    $image_error = null;
    $image_path = handle_product_upload($image_error);

    if ($name === null || $price === null || $stock === null || $category_id === null || ($image_path === null && $image_error !== null)) {
        $alert = ['danger', 'Dữ liệu không hợp lệ hoặc ảnh không đúng định dạng!'];
    } else {
        $stmt = $conn->prepare("INSERT INTO products (name, price, stock, category_id, image_path) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sdiss", $name, $price, $stock, $category_id, $image_path);
        $alert = $stmt->execute() ? ['success', 'Sản phẩm đã được thêm thành công!'] : ['danger', 'Lỗi khi thêm sản phẩm!'];
        $stmt->close();
    }
}


// ── Sửa sản phẩm ──────────────────────────────────────────────
if (isset($_POST['update_product'])) {
    verify_csrf();

    $product_id = v_int('product_id', 1, 1000000000, true);
    $name = v_str('name', 120, true);
    $price = v_float('price', 0.0, 1000000000.0, true);
    $stock = v_int('stock', 0, 1000000000, true);
    $category_id = v_int('category_id', 1, 1000000000, true);

    // Get current image
    $stmt = $conn->prepare("SELECT image_path FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $currentImagePath = $row['image_path'] ?? null;
    $newImagePath = $currentImagePath;

    $image_error = null;
    $uploaded = handle_product_upload($image_error);
    if ($uploaded !== null) {
        // Delete old file (best-effort)
        if ($currentImagePath && file_exists($currentImagePath)) {
            @unlink($currentImagePath);
        }
        $newImagePath = $uploaded;
    } elseif (!empty($image_error)) {
        $alert = ['danger', 'Ảnh không hợp lệ!'];
        goto __skip_update_product;
    }

    if ($product_id === null || $name === null || $price === null || $stock === null || $category_id === null) {
        $alert = ['danger', 'Dữ liệu không hợp lệ!'];
        goto __skip_update_product;
    }

    $stmt = $conn->prepare("UPDATE products SET name=?, price=?, stock=?, category_id=?, image_path=? WHERE id=?");
    $stmt->bind_param("sdissi", $name, $price, $stock, $category_id, $newImagePath, $product_id);
    $alert = $stmt->execute() ? ['success', 'Sản phẩm đã được cập nhật!'] : ['danger', 'Lỗi khi cập nhật!'];
    $stmt->close();

    __skip_update_product:
    ;
}


// ── Thêm người dùng ───────────────────────────────────────────
if (isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
    $role     = trim($_POST['role']);
    $stmt = $conn->prepare("SELECT id FROM users WHERE username=? OR email=?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $alert = ['danger', 'Tên đăng nhập hoặc email đã tồn tại!'];
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username,email,phone,password,role) VALUES (?,?,?,?,?)");
        $stmt->bind_param("sssss", $username, $email, $phone, $password, $role);
        $alert = $stmt->execute() ? ['success', 'Người dùng đã được thêm!'] : ['danger', 'Lỗi khi thêm người dùng!'];
    }
    $stmt->close();
}

// ── Sửa người dùng ────────────────────────────────────────────
if (isset($_POST['update_user'])) {
    $user_id  = (int)$_POST['user_id'];
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $role     = trim($_POST['role']);
    $stmt = $conn->prepare("SELECT id FROM users WHERE (username=? OR email=?) AND id!=?");
    $stmt->bind_param("ssi", $username, $email, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $alert = ['danger', 'Tên đăng nhập hoặc email đã tồn tại!'];
    } else {
        $stmt = $conn->prepare("UPDATE users SET username=?,email=?,phone=?,role=? WHERE id=?");
        $stmt->bind_param("ssssi", $username, $email, $phone, $role, $user_id);
        $alert = $stmt->execute() ? ['success', 'Người dùng đã được cập nhật!'] : ['danger', 'Lỗi khi cập nhật!'];
    }
    $stmt->close();
}

// ── Xác nhận đơn hàng nhanh ───────────────────────────────────
if (isset($_POST['confirm_order'])) {
    $order_id = trim($_POST['order_id']);
    $stmt = $conn->prepare("UPDATE orders SET status='Đang xử lý' WHERE order_id=? AND status='Chờ xác nhận'");
    $stmt->bind_param("s", $order_id);
    $alert = $stmt->execute() ? ['success', "Đã xác nhận đơn hàng $order_id!"] : ['danger', 'Lỗi khi xác nhận!'];
    $stmt->close();
    // Ở lại panel đơn hàng
    header("Location: admin.php?panel=order-list");
    exit;
}

// ── Xóa đơn hàng ──────────────────────────────────────────────
if (isset($_POST['delete_order'])) {
    $order_id = trim($_POST['order_id']);
    // Chỉ cho phép xóa đơn Huỷ, KHÔNG xóa đơn Hoàn thành để giữ doanh thu
    $check = $conn->prepare("SELECT status FROM orders WHERE order_id=?");
    $check->bind_param("s", $order_id);
    $check->execute();
    $order_status = $check->get_result()->fetch_assoc()['status'] ?? '';
    $check->close();

    if ($order_status !== 'Huỷ') {
        $alert = ['danger', "Không thể xóa đơn hàng $order_id! Chỉ được xóa đơn có trạng thái 'Huỷ'."];
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id=?");
            $stmt->bind_param("s", $order_id); $stmt->execute(); $stmt->close();
            $stmt = $conn->prepare("DELETE FROM orders WHERE order_id=?");
            $stmt->bind_param("s", $order_id); $stmt->execute(); $stmt->close();
            $conn->commit();
            $alert = ['success', "Đã xóa đơn hàng $order_id!"];
        } catch(Exception $e) {
            $conn->rollback();
            $alert = ['danger', 'Lỗi khi xóa đơn hàng!'];
        }
    }
    header("Location: admin.php?panel=order-list");
    exit;
}

// ── Thêm coupon ────────────────────────────────────────────────
if (isset($_POST['add_coupon'])) {
    $code        = strtoupper(trim($_POST['coupon_code']));
    $type        = $_POST['coupon_type'];
    $value       = (float)$_POST['coupon_value'];
    $min_order   = (float)($_POST['min_order'] ?? 0);
    $max_discount= (float)($_POST['max_discount'] ?? 0);
    $max_uses    = (int)($_POST['max_uses'] ?? 0);
    $expires_at  = !empty($_POST['expires_at']) ? $_POST['expires_at'] . ' 23:59:59' : null;

    if (empty($code) || $value <= 0) {
        $alert = ['danger', 'Vui lòng nhập mã và giá trị giảm!'];
    } else {
        $stmt = $conn->prepare("INSERT INTO coupons (code,type,value,min_order,max_discount,max_uses,is_active,expires_at) VALUES (?,?,?,?,?,?,1,?)");
        $stmt->bind_param("ssdddis", $code, $type, $value, $min_order, $max_discount, $max_uses, $expires_at);
        $alert = $stmt->execute() ? ['success', "Đã tạo mã giảm giá <strong>$code</strong>!"] : ['danger', 'Mã này đã tồn tại hoặc có lỗi!'];
        $stmt->close();
    }
    header("Location: admin.php?panel=coupon-list"); exit;
}

// ── Bật/tắt coupon ─────────────────────────────────────────────
if (isset($_POST['toggle_coupon'])) {
    $cid = (int)$_POST['coupon_id'];
    $conn->query("UPDATE coupons SET is_active = IF(is_active=1,0,1) WHERE id=$cid");
    header("Location: admin.php?panel=coupon-list"); exit;
}

// ── Xóa coupon ─────────────────────────────────────────────────
if (isset($_POST['delete_coupon'])) {
    $cid = (int)$_POST['coupon_id'];
    $conn->query("DELETE FROM coupons WHERE id=$cid");
    $alert = ['success', 'Đã xóa mã giảm giá!'];
    header("Location: admin.php?panel=coupon-list"); exit;
}

// ── Xuất báo cáo CSV ───────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'revenue') {
    $from = $_GET['from'] ?? date('Y-m-01');
    $to   = $_GET['to']   ?? date('Y-m-d');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bao_cao_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Mã đơn','Khách hàng','SĐT','Địa chỉ','Tổng tiền','Trạng thái','Ngày đặt']);
    $stmt = $conn->prepare("SELECT order_id, customer_name, customer_phone, customer_address, total_amount, status, created_at FROM orders WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC");
    $stmt->bind_param("ss", $from, $to); $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        fputcsv($out, [$r['order_id'], $r['customer_name'], $r['customer_phone'], $r['customer_address'], number_format($r['total_amount'],0,'.',','), $r['status'], $r['created_at']]);
    }
    $stmt->close(); fclose($out); exit;
}

// ── Cập nhật trạng thái đơn hàng ──────────────────────────────
if (isset($_POST['update_order_status'])) {
    $order_id = trim($_POST['order_id']);
    $status   = trim($_POST['status']);
    $allowed  = ['Chờ thanh toán','Chờ xác nhận','Đang xử lý','Đang giao','Hoàn thành','Huỷ'];
    if (in_array($status, $allowed)) {
        $stmt = $conn->prepare("UPDATE orders SET status=? WHERE order_id=?");
        $stmt->bind_param("ss", $status, $order_id);
        $alert = $stmt->execute() ? ['success', 'Đã cập nhật trạng thái đơn hàng!'] : ['danger', 'Lỗi khi cập nhật!'];
        $stmt->close();
    }
}

// ── Thêm coupon ────────────────────────────────────────────────
if (isset($_POST['add_coupon'])) {
    $code       = strtoupper(trim($_POST['coupon_code']));
    $type       = $_POST['coupon_type'];
    $value      = (float)$_POST['coupon_value'];
    $min_order  = (float)($_POST['min_order'] ?: 0);
    $max_disc   = (float)($_POST['max_discount'] ?: 0);
    $max_uses   = (int)($_POST['max_uses'] ?: 0);
    $expires    = !empty($_POST['expires_at']) ? $_POST['expires_at'] : NULL;
    if ($code && $value > 0) {
        $stmt = $conn->prepare("INSERT INTO coupons (code,type,value,min_order,max_discount,max_uses,is_active,expires_at) VALUES (?,?,?,?,?,?,1,?)");
        $stmt->bind_param("ssdddis", $code, $type, $value, $min_order, $max_disc, $max_uses, $expires);
        $alert = $stmt->execute() ? ['success',"Đã tạo mã <strong>$code</strong>!"] : ['danger','Mã đã tồn tại hoặc có lỗi!'];
        $stmt->close();
    } else { $alert = ['danger','Vui lòng nhập đầy đủ thông tin!']; }
    header("Location: admin.php?panel=coupon-list"); exit;
}

// ── Bật/tắt coupon ─────────────────────────────────────────────
if (isset($_POST['toggle_coupon'])) {
    $id = (int)$_POST['coupon_id'];
    $conn->query("UPDATE coupons SET is_active = 1 - is_active WHERE id = $id");
    header("Location: admin.php?panel=coupon-list"); exit;
}

// ── Xóa coupon ─────────────────────────────────────────────────
if (isset($_POST['delete_coupon'])) {
    $id = (int)$_POST['coupon_id'];
    $conn->query("DELETE FROM coupons WHERE id = $id");
    header("Location: admin.php?panel=coupon-list"); exit;
}

// ── Đăng xuất ──────────────────────────────────────────────────
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// ── Thống kê dashboard ─────────────────────────────────────────
$stats = [];
$r = $conn->query("SELECT COUNT(*) as c FROM products"); $stats['products'] = $r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='user' OR role='customer'"); $stats['users'] = $r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM orders"); $stats['orders'] = $r->fetch_assoc()['c'];
$r = $conn->query("SELECT COALESCE(SUM(total_amount),0) as s FROM orders WHERE status='Hoàn thành'"); $stats['revenue'] = $r->fetch_assoc()['s'];
$r = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status='Chờ xác nhận' OR status='Chờ thanh toán'"); $stats['pending'] = $r->fetch_assoc()['c'];
$r = $conn->query("SELECT COALESCE(SUM(total_amount),0) as s FROM orders WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) AND status='Hoàn thành'"); $stats['revenue_month'] = $r->fetch_assoc()['s'];

// ── Biểu đồ 6 tháng ───────────────────────────────────────────
$chart_labels = []; $chart_data = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $chart_labels[] = date('m/Y', strtotime("-$i months"));
    $r = $conn->query("SELECT COALESCE(SUM(total_amount),0) as s FROM orders WHERE DATE_FORMAT(created_at,'%Y-%m')='$m' AND status='Hoàn thành'");
    $chart_data[] = (float)$r->fetch_assoc()['s'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Anh Ba Bakery </title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /*
          ==============================
          UI/UX Luxury Bakery Theme
          - Tone: Chocolate (#6b3f2a), Pink (#c97b84), Cream (#fdf6ef), Accent Gold (#f5d9b0)
          - Fonts: Titles (Cormorant Garamond), Content (DM Sans)
          - Note: chỉ chỉnh giao diện (HTML/CSS/style), KHÔNG đụng logic PHP/JS/database.
          ==============================
        */

        @import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@400;500;600&display=swap');

        :root{
            --chocolate:#6b3f2a;
            --choco-2:#7a4a34;
            --pink:#c97b84;
            --cream:#fdf6ef;
            --gold:#f5d9b0;
            --ink:#2b1b17;
            --muted:#6f5a54;
            --card:#ffffff;
            --line:rgba(107,63,42,0.12);
            --shadow: 0 10px 30px rgba(43,27,23,0.10);
            --shadow-sm: 0 6px 18px rgba(43,27,23,0.08);
            --radius: 16px;
        }

        body {
            background: radial-gradient(1000px 600px at 10% 0%, rgba(201,123,132,0.12), transparent 55%),
                        radial-gradient(900px 500px at 90% 0%, rgba(245,217,176,0.18), transparent 55%),
                        linear-gradient(180deg, var(--cream), #faf0e2);
            color: var(--ink);
            font-family: 'DM Sans', system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
        }

        /* Sidebar */
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, rgba(107,63,42,0.98), rgba(107,63,42,0.92));
            border-right: 1px solid rgba(245,217,176,0.18);
            position: sticky;
            top: 0;
        }

        .sidebar .nav-link {
            color: rgba(253,246,239,0.78);
            padding: 10px 14px;
            margin: 6px 8px;
            border-radius: 12px;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .sidebar .nav-link i {
            width: 22px;
            text-align: center;
            color: var(--gold);
        }

        .sidebar .nav-link:hover {
            color: #fff;
            background: rgba(245,217,176,0.14);
            transform: translateY(-1px);
            box-shadow: 0 10px 26px rgba(0,0,0,0.12);
        }

        .sidebar .nav-link.active {
            color: #fff;
            background: rgba(245,217,176,0.18);
            box-shadow: inset 0 0 0 1px rgba(245,217,176,0.22);
        }

        .sidebar-brand {
            color: #fff;
            font-family: 'Cormorant Garamond', serif;
            letter-spacing: 0.4px;
            font-size: 22px;
            font-weight: 700;
            padding: 20px 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            border-bottom: 1px solid rgba(245,217,176,0.18);
        }

        .sidebar-brand::before{
            content: '🧁';
            font-size: 18px;
            filter: drop-shadow(0 6px 10px rgba(0,0,0,0.15));
        }

        /* Stats cards */
        .stat-card {
            border-radius: 18px;
            padding: 18px 18px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(245,217,176,0.18);
            transform: translateY(0);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .stat-card:hover{
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .stat-card::after{
            content:'';
            position:absolute;
            inset:-2px;
            background: radial-gradient(500px 200px at 20% 0%, rgba(245,217,176,0.35), transparent 60%);
            pointer-events:none;
        }

        /* Images & badges */
        .product-img { max-width: 56px; max-height: 56px; object-fit: cover; border-radius: 14px; border:1px solid rgba(107,63,42,0.10); }
        .badge-status { padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; letter-spacing: .2px; }

        /* Cards */
        .section-card {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 22px 22px;
            margin-bottom: 28px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(107,63,42,0.10);
            backdrop-filter: blur(8px);
        }
        .section-card h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 18px;
            letter-spacing: .2px;
        }


        /* PANEL POPUP */
        .panel-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.55);
            z-index: 2000;
        }
        .panel-overlay.active { display: block; }
        .panel-box {
            position: absolute;
            top: 0; right: 0;
            background: #fff;
            width: 78vw;
            max-width: 1100px;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: -4px 0 30px rgba(0,0,0,0.25);
            animation: slideIn 0.25s ease;
        }
        @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
        .panel-header {
            position: sticky; top: 0;
            background: #1e2a3a;
            color: #fff;
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 10;
        }
        .panel-header h4 { margin: 0; font-size: 16px; font-weight: 700; color: #fff; }
        .panel-close {
            background: rgba(255,255,255,0.15);
            border: none; color: #fff;
            width: 32px; height: 32px;
            border-radius: 50%; font-size: 16px;
            cursor: pointer; display: flex;
            align-items: center; justify-content: center;
            transition: background 0.2s; flex-shrink: 0;
        }
        .panel-close:hover { background: rgba(255,255,255,0.3); }
        .panel-body { padding: 20px; }
        .panel-body .table-responsive { overflow-x: auto; width: 100%; }
        .panel-body .table { font-size: 13px; width: 100%; }
        .panel-body .table td, .panel-body .table th { white-space: nowrap; }
    </style>
</head>
<body>
<div class="container-fluid p-0">
<div class="row g-0">

    <!-- Sidebar -->
    <div class="col-md-2 sidebar p-2">
ANH BA BAKERY MANAGER
        <ul class="nav flex-column mt-2">
            <li class="nav-item"><a class="nav-link" href="#dashboard"><i class="fas fa-chart-bar me-2"></i>Tổng quan</a></li>
            <li class="nav-item"><a class="nav-link" href="#" onclick="openPanel('product-list')"><i class="fas fa-box me-2"></i>Sản phẩm</a></li>
            <li class="nav-item"><a class="nav-link" href="#" onclick="openPanel('order-list')"><i class="fas fa-clipboard-list me-2"></i>Đơn hàng</a></li>
            <li class="nav-item"><a class="nav-link" href="#" onclick="openPanel('cart-list')"><i class="fas fa-shopping-cart me-2"></i>Giỏ hàng</a></li>
            <li class="nav-item"><a class="nav-link" href="#" onclick="openPanel('user-list')"><i class="fas fa-users me-2"></i>Người dùng</a></li>
            <li class="nav-item"><a class="nav-link" href="#" onclick="openPanel('reviews-list')"><i class="fas fa-star me-2"></i>Đánh giá</a></li>
            <li class="nav-item"><a class="nav-link" href="#" onclick="openPanel('add-product')"><i class="fas fa-plus me-2"></i>Thêm sản phẩm</a></li>
            <li class="nav-item"><a class="nav-link" href="#" onclick="openPanel('add-user')"><i class="fas fa-user-plus me-2"></i>Thêm user</a></li>
            <li class="nav-item"><a class="nav-link" href="#" onclick="openPanel('coupon-list')"><i class="fas fa-tag me-2"></i>Mã khuyến mãi</a></li>

            <li class="nav-item mt-3">
                <a class="nav-link" href="index.php"><i class="fas fa-home me-2"></i>Trang chính</a>
            </li>
            <li class="nav-item">
                <form method="POST">
                    <button type="submit" name="logout" class="nav-link btn btn-link text-start w-100" style="color:#adb5bd">
                        <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                    </button>
                </form>
            </li>
        </ul>
    </div>

    <!-- Main content -->
    <div class="col-md-10 p-4">

        <?php if (isset($alert)): ?>
            <div class="alert alert-<?php echo $alert[0]; ?> alert-dismissible fade show" role="alert">
                <?php echo $alert[1]; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Dashboard stats -->
        <div id="dashboard" class="row g-3 mb-4">
            <div class="col-md-2">
                <div class="stat-card" style="background:linear-gradient(135deg,#667eea,#764ba2)">
                    <div style="font-size:26px;font-weight:700"><?php echo number_format($stats['products']); ?></div>
                    <div style="font-size:12px;opacity:.85"><i class="fas fa-box me-1"></i>Sản phẩm</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" style="background:linear-gradient(135deg,#f093fb,#f5576c)">
                    <div style="font-size:26px;font-weight:700"><?php echo number_format($stats['orders']); ?></div>
                    <div style="font-size:12px;opacity:.85"><i class="fas fa-clipboard-list me-1"></i>Đơn hàng</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" style="background:linear-gradient(135deg,#fa8231,#f7b731)">
                    <div style="font-size:26px;font-weight:700"><?php echo number_format($stats['pending']); ?></div>
                    <div style="font-size:12px;opacity:.85"><i class="fas fa-clock me-1"></i>Chờ xác nhận</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" style="background:linear-gradient(135deg,#4facfe,#00f2fe)">
                    <div style="font-size:26px;font-weight:700"><?php echo number_format($stats['users']); ?></div>
                    <div style="font-size:12px;opacity:.85"><i class="fas fa-users me-1"></i>Khách hàng</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" style="background:linear-gradient(135deg,#43e97b,#38f9d7)">
                    <div style="font-size:16px;font-weight:700"><?php echo number_format($stats['revenue_month'],0,',','.'); ?>₫</div>
                    <div style="font-size:12px;opacity:.85"><i class="fas fa-calendar me-1"></i>Tháng này</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" style="background:linear-gradient(135deg,#11998e,#38ef7d)">
                    <div style="font-size:16px;font-weight:700"><?php echo number_format($stats['revenue'],0,',','.'); ?>₫</div>
                    <div style="font-size:12px;opacity:.85"><i class="fas fa-chart-line me-1"></i>Tổng DT</div>
                </div>
            </div>
        </div>

        <!-- Biểu đồ + xuất CSV -->
        <div class="section-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h3 class="mb-0">📈 Doanh thu 6 tháng gần nhất</h3>
                <form method="GET" action="admin.php" class="d-flex gap-2 align-items-center flex-wrap">
                    <input type="hidden" name="export" value="revenue">
                    <input type="date" name="from" class="form-control form-control-sm" value="<?php echo date('Y-m-01'); ?>" style="width:140px">
                    <span>đến</span>
                    <input type="date" name="to" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" style="width:140px">
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-file-csv me-1"></i>Xuất CSV</button>
                </form>
            </div>
            <div style="position:relative;height:260px">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Product List PANEL -->
        <div class="panel-overlay" id="panel-product-list" onclick="closePanelOutside(event, 'product-list')">
        <div class="panel-box">
            <div class="panel-header">
                <h4>📦 Danh sách sản phẩm</h4>
                <button class="panel-close" onclick="closePanel('product-list')">✕</button>
            </div>
            <div class="panel-body">
            <div id="product-list" class="section-card" style="margin:0;box-shadow:none">
            <h3>📦 Danh sách sản phẩm</h3>
            <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr><th>ID</th><th>Ảnh</th><th>Tên</th><th>Giá</th><th>Tồn kho</th><th>Danh mục</th><th>Thao tác</th></tr>
                </thead>
                <tbody>
                <?php
                $result = $conn->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");
                while ($row = $result->fetch_assoc()):
                ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><img src="<?php echo htmlspecialchars($row['image_path'] ?: 'uploads/products/default.jpg'); ?>" class="product-img" alt=""></td>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo number_format($row['price'], 0, ',', '.'); ?>₫</td>
                    <td><?php echo $row['stock']; ?></td>
                    <td><?php echo htmlspecialchars($row['category_name'] ?? '—'); ?></td>
                    <td>
                        <button class="btn btn-sm btn-primary me-1" data-bs-toggle="modal" data-bs-target="#editProductModal<?php echo $row['id']; ?>"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteProductModal<?php echo $row['id']; ?>"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <!-- Modal sửa sản phẩm -->
                <div class="modal fade" id="editProductModal<?php echo $row['id']; ?>" tabindex="-1">
                    <div class="modal-dialog"><div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Chỉnh sửa sản phẩm</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
<form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                                <div class="mb-3"><label class="form-label">Tên sản phẩm</label><input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($row['name']); ?>" required></div>
                                <div class="mb-3"><label class="form-label">Giá (₫)</label><input type="number" name="price" class="form-control" value="<?php echo $row['price']; ?>" required></div>
                                <div class="mb-3"><label class="form-label">Tồn kho</label><input type="number" name="stock" class="form-control" value="<?php echo $row['stock']; ?>" required></div>
                                <div class="mb-3"><label class="form-label">Danh mục</label>
                                    <select name="category_id" class="form-control" required>
                                        <?php $cats = $conn->query("SELECT id,name FROM categories"); while($cat=$cats->fetch_assoc()): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo $row['category_id']==$cat['id']?'selected':''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="mb-3"><img src="<?php echo htmlspecialchars($row['image_path'] ?: ''); ?>" style="max-width:80px;border-radius:6px" alt=""></div>
                                <div class="mb-3"><label class="form-label">Thay ảnh mới</label><input type="file" name="image" class="form-control" accept="image/*"></div>
                                <button type="submit" name="update_product" class="btn btn-success w-100">Lưu thay đổi</button>
                            </form>
                        </div>
                    </div></div>
                </div>
                <!-- Modal xóa sản phẩm -->
                <div class="modal fade" id="deleteProductModal<?php echo $row['id']; ?>" tabindex="-1">
                    <div class="modal-dialog"><div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Xác nhận xóa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body text-center">
                            <p>Bạn có chắc muốn xóa sản phẩm <strong><?php echo htmlspecialchars($row['name']); ?></strong>?</p>
                            <form method="POST" action="delete_product.php">
                                <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                <button type="submit" name="delete_product" class="btn btn-danger me-2">Xóa</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                            </form>
                        </div>
                    </div></div>
                </div>
                <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        </div>
        </div></div></div><!-- /panel product-list -->

        <!-- Order List PANEL -->
        <div class="panel-overlay" id="panel-order-list" onclick="closePanelOutside(event, 'order-list')">
        <div class="panel-box">
            <div class="panel-header">
                <h4>📋 Quản lý đơn hàng</h4>
                <button class="panel-close" onclick="closePanel('order-list')">✕</button>
            </div>
            <div class="panel-body">
            <div id="order-list" class="section-card" style="margin:0;box-shadow:none">
            <h3>📋 Quản lý đơn hàng
                <?php if ($stats['pending'] > 0): ?>
                <span class="badge bg-warning text-dark ms-2" style="font-size:13px"><?php echo $stats['pending']; ?> chờ xác nhận</span>
                <?php endif; ?>
            </h3>
            <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr><th>Mã ĐH</th><th>Khách hàng</th><th>SĐT</th><th>Tổng tiền</th><th>Trạng thái</th><th>Ngày đặt</th><th>Thao tác</th></tr>
                </thead>
                <tbody>
                <?php
                $result = $conn->query("SELECT o.order_id, o.customer_name, o.customer_phone, o.total_amount, o.status, o.created_at, o.note, u.username
                                        FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC");
                $status_colors = ['Chờ thanh toán'=>'secondary','Chờ xác nhận'=>'warning','Đang xử lý'=>'info','Đang giao'=>'primary','Hoàn thành'=>'success','Huỷ'=>'danger'];
                $orders_data = [];
                while ($row = $result->fetch_assoc()) $orders_data[] = $row;
                foreach ($orders_data as $row):
                    $sc = $status_colors[$row['status']] ?? 'secondary';
                    $is_pending = in_array($row['status'], ['Chờ xác nhận', 'Chờ thanh toán']);
                    $note_raw = $row['note'] ?? '';
                    $is_banking = $row['status'] === 'Chờ thanh toán'
                        || mb_strpos($note_raw, 'chuyển khoản') !== false
                        || mb_strpos($note_raw, 'Chuyển khoản') !== false
                        || mb_strpos($note_raw, 'MB Bank') !== false
                        || mb_strpos($note_raw, 'mb bank') !== false
                        || mb_strpos($note_raw, 'banking') !== false
                        || mb_strpos($note_raw, '[CK') !== false;
                    $pay_badge = $is_banking
                        ? '<span style="display:inline-block;margin-top:3px;padding:2px 7px;border-radius:8px;font-size:10px;font-weight:600;background:#dbeafe;color:#1d4ed8;white-space:nowrap">🏦 Chuyển khoản</span>'
                        : '<span style="display:inline-block;margin-top:3px;padding:2px 7px;border-radius:8px;font-size:10px;font-weight:600;background:#dcfce7;color:#166534;white-space:nowrap">💵 Tiền mặt</span>';
                ?>
                <tr style="<?php echo $is_pending ? 'background:#fff8e1' : ''; ?>">
                    <td><strong><?php echo htmlspecialchars($row['order_id']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['customer_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($row['username'] ?? '—'); ?></small></td>
                    <td><?php echo htmlspecialchars($row['customer_phone']); ?></td>
                    <td><strong><?php echo number_format($row['total_amount'], 0, ',', '.'); ?>₫</strong></td>
                    <td><?php echo $pay_badge; ?></td>
                    <td><span class="badge bg-<?php echo $sc; ?> badge-status"><?php echo htmlspecialchars($row['status']); ?></span></td>
                    <td style="font-size:12px"><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                    <td style="white-space:nowrap">
                        <?php if ($is_pending): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($row['order_id']); ?>">
                            <button type="submit" name="confirm_order" class="btn btn-sm btn-success me-1" onclick="return confirm('Xác nhận đơn <?php echo $row['order_id']; ?>?')" title="Xác nhận"><i class="fas fa-check"></i></button>
                        </form>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#orderDetailModal<?php echo htmlspecialchars($row['order_id']); ?>" title="Xem chi tiết"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-sm btn-outline-warning me-1" data-bs-toggle="modal" data-bs-target="#updateStatusModal<?php echo htmlspecialchars($row['order_id']); ?>" title="Cập nhật trạng thái"><i class="fas fa-edit"></i></button>
                        <?php if ($row['status'] === 'Huỷ'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($row['order_id']); ?>">
                            <button type="submit" name="delete_order" class="btn btn-sm btn-danger" title="Xóa đơn đã huỷ" onclick="return confirm('Xóa đơn hàng <?php echo $row['order_id']; ?>? Không thể khôi phục!')"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            </div><!-- /section-card -->

            <?php
            /* Vẽ modal RA NGOÀI bảng - tránh vỡ HTML */
            foreach ($orders_data as $row):
                $sc = $status_colors[$row['status']] ?? 'secondary';
                $is_pending = in_array($row['status'], ['Chờ xác nhận', 'Chờ thanh toán']);
            ?>
            <!-- Modal chi tiết đơn hàng -->
            <div class="modal fade" id="orderDetailModal<?php echo htmlspecialchars($row['order_id']); ?>" tabindex="-1">
                <div class="modal-dialog modal-lg"><div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Chi tiết đơn hàng <?php echo htmlspecialchars($row['order_id']); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p><strong>Khách hàng:</strong> <?php echo htmlspecialchars($row['customer_name']); ?> — <?php echo htmlspecialchars($row['customer_phone']); ?></p>
                        <p><strong>Trạng thái:</strong> <span class="badge bg-<?php echo $sc; ?>"><?php echo htmlspecialchars($row['status']); ?></span></p>
                        <table class="table table-sm mt-3">
                            <thead><tr><th>Sản phẩm</th><th>Số lượng</th><th>Size</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead>
                            <tbody>
                            <?php
                            $oid = $row['order_id'];
                            $items = $conn->query("SELECT p.name, oi.quantity, oi.size, oi.price FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id='$oid'");
                            while ($it = $items->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($it['name']); ?></td>
                                <td><?php echo $it['quantity']; ?></td>
                                <td><?php echo $it['size'] ?: '—'; ?></td>
                                <td><?php echo number_format($it['price'], 0, ',', '.'); ?>₫</td>
                                <td><?php echo number_format($it['price']*$it['quantity'], 0, ',', '.'); ?>₫</td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                            <tfoot><tr><td colspan="4" class="text-end fw-bold">Tổng cộng:</td><td class="fw-bold text-danger"><?php echo number_format($row['total_amount'],0,',','.'); ?>₫</td></tr></tfoot>
                        </table>
                    </div>
                </div></div>
            </div>
            <!-- Modal cập nhật trạng thái -->
            <div class="modal fade" id="updateStatusModal<?php echo htmlspecialchars($row['order_id']); ?>" tabindex="-1">
                <div class="modal-dialog"><div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Cập nhật trạng thái</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($row['order_id']); ?>">
                            <div class="mb-3"><label class="form-label">Trạng thái đơn hàng</label>
                                <select name="status" class="form-control">
                                    <?php foreach(array_keys($status_colors) as $s): ?>
                                        <option value="<?php echo $s; ?>" <?php echo $row['status']==$s?'selected':''; ?>><?php echo $s; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="update_order_status" class="btn btn-warning w-100">Cập nhật</button>
                        </form>
                    </div>
                </div></div>
            </div>
            <?php endforeach; ?>
            </div></div></div><!-- /panel order-list -->

        <!-- Cart List PANEL -->
        <div class="panel-overlay" id="panel-cart-list" onclick="closePanelOutside(event, 'cart-list')">
        <div class="panel-box">
            <div class="panel-header">
                <h4>🛒 Giỏ hàng hiện tại của khách</h4>
                <button class="panel-close" onclick="closePanel('cart-list')">✕</button>
            </div>
            <div class="panel-body">
            <div id="cart-list" class="section-card" style="margin:0;box-shadow:none">
            <h3>🛒 Giỏ hàng hiện tại của khách</h3>
            <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr><th>ID</th><th>Người dùng</th><th>Sản phẩm</th><th>Số lượng</th><th>Size</th><th>Thao tác</th></tr>
                </thead>
                <tbody>
                <?php
                $result = $conn->query("SELECT c.id, u.username, p.name as product_name, c.quantity, c.size
                                        FROM cart c JOIN users u ON c.user_id=u.id JOIN products p ON c.product_id=p.id
                                        ORDER BY c.id DESC");
                while ($row = $result->fetch_assoc()):
                ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                    <td><?php echo $row['quantity']; ?></td>
                    <td><?php echo $row['size'] ?: '—'; ?></td>
                    <td>
                        <form method="POST" action="delete_cart_item.php" style="display:inline">
                            <input type="hidden" name="cart_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" name="delete_cart_item" class="btn btn-sm btn-danger" onclick="return confirm('Xóa mục này?')"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        </div>
        </div></div></div><!-- /panel cart-list -->

        <!-- User List PANEL -->
        <div class="panel-overlay" id="panel-user-list" onclick="closePanelOutside(event, 'user-list')">
        <div class="panel-box">
            <div class="panel-header">
                <h4>👤 Quản lý người dùng</h4>
                <button class="panel-close" onclick="closePanel('user-list')">✕</button>
            </div>
            <div class="panel-body">
            <div id="user-list" class="section-card" style="margin:0;box-shadow:none">
            <h3>👤 Quản lý người dùng</h3>
            <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr><th>ID</th><th>Tên đăng nhập</th><th>Email</th><th>SĐT</th><th>Vai trò</th><th>Thao tác</th></tr>
                </thead>
                <tbody>
                <?php
                $result = $conn->query("SELECT id, username, email, phone, role FROM users ORDER BY id");
                while ($row = $result->fetch_assoc()):
                ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                    <td><span class="badge <?php echo $row['role']==='admin'?'bg-danger':'bg-secondary'; ?>"><?php echo $row['role']==='admin'?'Admin':'Khách hàng'; ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-primary me-1" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $row['id']; ?>"><i class="fas fa-edit"></i></button>
                        <?php if ($row['id'] != $_SESSION['user_id']): ?>
                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal<?php echo $row['id']; ?>"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <!-- Modal sửa user -->
                <div class="modal fade" id="editUserModal<?php echo $row['id']; ?>" tabindex="-1">
                    <div class="modal-dialog"><div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Chỉnh sửa người dùng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <form method="POST">
                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                <div class="mb-3"><label class="form-label">Tên đăng nhập</label><input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($row['username']); ?>" required></div>
                                <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($row['email']); ?>" required></div>
                                <div class="mb-3"><label class="form-label">Số điện thoại</label><input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($row['phone']); ?>"></div>
                                <div class="mb-3"><label class="form-label">Vai trò</label>
                                    <select name="role" class="form-control">
                                        <option value="admin" <?php echo $row['role']==='admin'?'selected':''; ?>>Admin</option>
                                        <option value="user" <?php echo $row['role']==='user'?'selected':''; ?>>Khách hàng</option>
                                    </select>
                                </div>
                                <button type="submit" name="update_user" class="btn btn-success w-100">Lưu</button>
                            </form>
                        </div>
                    </div></div>
                </div>
                <!-- Modal xóa user -->
                <div class="modal fade" id="deleteUserModal<?php echo $row['id']; ?>" tabindex="-1">
                    <div class="modal-dialog"><div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Xác nhận xóa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body text-center">
                            <p>Xóa người dùng <strong><?php echo htmlspecialchars($row['username']); ?></strong>?</p>
                            <form method="POST" action="delete_user.php">
                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                <button type="submit" name="delete_user" class="btn btn-danger me-2">Xóa</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                            </form>
                        </div>
                    </div></div>
                </div>
                <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        </div>
        </div></div></div><!-- /panel user-list -->

        <!-- Add Product PANEL -->
        <div class="panel-overlay" id="panel-add-product" onclick="closePanelOutside(event, 'add-product')">
        <div class="panel-box" style="max-width:600px">
            <div class="panel-header">
                <h4>➕ Thêm sản phẩm mới</h4>
                <button class="panel-close" onclick="closePanel('add-product')">✕</button>
            </div>
            <div class="panel-body">
            <div id="add-product" class="section-card" style="margin:0;box-shadow:none">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3"><label class="form-label">Tên sản phẩm</label><input type="text" name="name" class="form-control" placeholder="Tên sản phẩm" required></div>
                <div class="mb-3"><label class="form-label">Giá (₫)</label><input type="number" name="price" class="form-control" placeholder="Giá" required></div>
                <div class="mb-3"><label class="form-label">Tồn kho</label><input type="number" name="stock" class="form-control" placeholder="Số lượng" required></div>
                <div class="mb-3"><label class="form-label">Danh mục</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">-- Chọn danh mục --</option>
                        <?php $cats = $conn->query("SELECT id,name FROM categories"); while($cat=$cats->fetch_assoc()): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Hình ảnh</label><input type="file" name="image" class="form-control" accept="image/*"></div>
                <button type="submit" name="add_product" class="btn btn-success w-100"><i class="fas fa-plus me-2"></i>Thêm sản phẩm</button>
            </form>
            </div>
            </div></div></div><!-- /panel add-product -->

        <!-- Add User PANEL -->
        <div class="panel-overlay" id="panel-add-user" onclick="closePanelOutside(event, 'add-user')">
        <div class="panel-box" style="max-width:600px">
            <div class="panel-header">
                <h4>➕ Thêm người dùng mới</h4>
                <button class="panel-close" onclick="closePanel('add-user')">✕</button>
            </div>
            <div class="panel-body">
            <div id="add-user" class="section-card" style="margin:0;box-shadow:none">
            <form method="POST">
                <div class="mb-3"><label class="form-label">Tên đăng nhập</label><input type="text" name="username" class="form-control" placeholder="Tên đăng nhập" required></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" placeholder="Email" required></div>
                <div class="mb-3"><label class="form-label">Số điện thoại</label><input type="tel" name="phone" class="form-control" placeholder="Số điện thoại"></div>
                <div class="mb-3"><label class="form-label">Mật khẩu</label><input type="password" name="password" class="form-control" placeholder="Mật khẩu" required></div>
                <div class="mb-3"><label class="form-label">Vai trò</label>
                    <select name="role" class="form-control" required>
                        <option value="user">Khách hàng</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <button type="submit" name="add_user" class="btn btn-success w-100"><i class="fas fa-user-plus me-2"></i>Thêm người dùng</button>
            </form>
            </div>
            </div></div></div><!-- /panel add-user -->

        <!-- Reviews PANEL -->
        <div class="panel-overlay" id="panel-reviews-list" onclick="closePanelOutside(event, 'reviews-list')">
        <div class="panel-box" style="max-width: 980px;">
            <div class="panel-header">
                <h4>⭐ Quản lý đánh giá sản phẩm</h4>
                <button class="panel-close" onclick="closePanel('reviews-list')">✕</button>
            </div>
            <div class="panel-body">
                <div class="section-card" style="margin:0;box-shadow:none;border:1px solid rgba(107,63,42,0.10);">
                    <h3 style="margin-bottom:16px;">Danh sách đánh giá</h3>
                    <div class="table-responsive">
                        <div class="row g-2 align-items-center mb-2">
                            <div class="col-12 col-md-5">
                                <input id="reviewSearch" type="text" class="form-control" placeholder="Tìm theo sản phẩm / người dùng / comment...">
                            </div>
                            <div class="col-6 col-md-3">
                                <select id="reviewRatingFilter" class="form-select">
                                    <option value="all">Tất cả sao</option>
                                    <option value="5">5 sao</option>
                                    <option value="4">4 sao</option>
                                    <option value="3">3 sao</option>
                                    <option value="2">2 sao</option>
                                    <option value="1">1 sao</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-4 text-end">
                                <small id="reviewCountLabel" class="text-muted"></small>
                            </div>
                        </div>

                        <table class="table table-hover align-middle" style="font-size:13px;">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Sản phẩm</th>
                                    <th>Người dùng</th>
                                    <th>Sao</th>
                                    <th>Comment</th>
                                    <th>Ngày</th>
                                </tr>
                            </thead>
                            <tbody id="reviewsTableBody">
                                <?php
                                // Load reviews with product + user info
                                $reviews_stmt = $conn->prepare("SELECT r.id, r.product_id, r.user_id, r.rating, r.comment, r.created_at, p.name AS product_name, u.username AS user_name
                                    FROM product_reviews r
                                    JOIN products p ON r.product_id = p.id
                                    LEFT JOIN users u ON r.user_id = u.id
                                    ORDER BY r.created_at DESC");
                                $reviews_stmt->execute();
                                $reviews_res = $reviews_stmt->get_result();
                                while ($rv = $reviews_res->fetch_assoc()):
                                    $comment = $rv['comment'];
                                ?>
                                    <tr data-rating="<?php echo (int)$rv['rating']; ?>">
                                        <td><?php echo (int)$rv['id']; ?></td>
                                        <td><?php echo htmlspecialchars($rv['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($rv['user_name'] ?? '—'); ?></td>
                                        <td style="white-space:nowrap">⭐ <?php echo (int)$rv['rating']; ?>/5</td>
                                        <td style="max-width:420px;">
                                            <div style="white-space:normal;word-break:break-word;">
                                                <?php echo htmlspecialchars($comment); ?>
                                            </div>
                                        </td>

                                        <td style="white-space:nowrap"><?php echo date('d/m/Y H:i', strtotime($rv['created_at'])); ?></td>
                                    </tr>
                                <?php endwhile; $reviews_stmt->close(); ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <!-- Coupon PANEL -->
        <div class="panel-overlay" id="panel-coupon-list" onclick="closePanelOutside(event, 'coupon-list')">

        <div class="panel-box">
            <div class="panel-header">
                <h4>🏷️ Quản lý mã khuyến mãi</h4>
                <button class="panel-close" onclick="closePanel('coupon-list')">✕</button>
            </div>
            <div class="panel-body">

            <!-- Form tạo mã mới -->
            <div class="section-card" style="margin-bottom:20px;box-shadow:none;border:1px solid #eee">
                <h3 style="margin-bottom:16px">➕ Tạo mã mới</h3>
                <form method="POST">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                        <div>
                            <label class="form-label">Mã coupon <span style="color:#e44">*</span></label>
                            <input type="text" name="coupon_code" class="form-control" placeholder="VD: SALE20" required style="text-transform:uppercase">
                        </div>
                        <div>
                            <label class="form-label">Loại giảm <span style="color:#e44">*</span></label>
                            <select name="coupon_type" class="form-control" id="couponType" onchange="toggleMaxDisc()">
                                <option value="percent">% Phần trăm</option>
                                <option value="fixed">Số tiền cố định (₫)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Giá trị giảm <span style="color:#e44">*</span></label>
                            <input type="number" name="coupon_value" class="form-control" placeholder="VD: 10 (%) hoặc 50000 (₫)" required min="1">
                        </div>
                        <div>
                            <label class="form-label">Đơn tối thiểu (₫)</label>
                            <input type="number" name="min_order" class="form-control" placeholder="0 = không giới hạn" min="0">
                        </div>
                        <div id="maxDiscWrap">
                            <label class="form-label">Giảm tối đa (₫)</label>
                            <input type="number" name="max_discount" class="form-control" placeholder="0 = không giới hạn" min="0">
                        </div>
                        <div>
                            <label class="form-label">Số lần dùng tối đa</label>
                            <input type="number" name="max_uses" class="form-control" placeholder="0 = không giới hạn" min="0">
                        </div>
                        <div>
                            <label class="form-label">Ngày hết hạn</label>
                            <input type="datetime-local" name="expires_at" class="form-control">
                        </div>
                    </div>
                    <button type="submit" name="add_coupon" class="btn btn-success">
                        <i class="fas fa-plus me-1"></i> Tạo mã khuyến mãi
                    </button>
                </form>
            </div>

            <!-- Danh sách coupon -->
            <div class="section-card" style="margin:0;box-shadow:none;border:1px solid #eee">
                <h3 style="margin-bottom:16px">📋 Danh sách mã hiện có</h3>
                <div class="table-responsive">
                <table class="table table-hover align-middle" style="font-size:13px">
                    <thead class="table-dark">
                        <tr><th>Mã</th><th>Loại</th><th>Giá trị</th><th>Đơn tối thiểu</th><th>Đã dùng</th><th>Hết hạn</th><th>Trạng thái</th><th>Thao tác</th></tr>
                    </thead>
                    <tbody>
                    <?php
                    $coupons = $conn->query("SELECT * FROM coupons ORDER BY id DESC");
                    while ($c = $coupons->fetch_assoc()):
                        $is_expired = $c['expires_at'] && strtotime($c['expires_at']) < time();
                        $used_ratio = $c['max_uses'] > 0 ? $c['used_count'].'/'.$c['max_uses'] : $c['used_count'].'/ ∞';
                    ?>
                    <tr style="<?php echo !$c['is_active'] || $is_expired ? 'opacity:0.6' : ''; ?>">
                        <td><strong style="font-size:14px;letter-spacing:1px"><?php echo htmlspecialchars($c['code']); ?></strong></td>
                        <td><?php echo $c['type'] === 'percent' ? '% Phần trăm' : 'Cố định'; ?></td>
                        <td style="color:#e44;font-weight:700">
                            <?php echo $c['type'] === 'percent'
                                ? $c['value'].'%'
                                : number_format($c['value'],0,',','.').'₫'; ?>
                        </td>
                        <td><?php echo $c['min_order'] > 0 ? number_format($c['min_order'],0,',','.').'₫' : '—'; ?></td>
                        <td><?php echo $used_ratio; ?></td>
                        <td style="font-size:12px">
                            <?php if ($c['expires_at']): ?>
                                <span style="color:<?php echo $is_expired ? '#e44' : '#333'; ?>">
                                    <?php echo $is_expired ? '⚠️ ' : ''; ?>
                                    <?php echo date('d/m/Y H:i', strtotime($c['expires_at'])); ?>
                                </span>
                            <?php else: echo '—'; endif; ?>
                        </td>
                        <td>
                            <?php if ($is_expired): ?>
                                <span class="badge bg-danger">Hết hạn</span>
                            <?php elseif ($c['is_active']): ?>
                                <span class="badge bg-success">Đang bật</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Đã tắt</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap">
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="coupon_id" value="<?php echo $c['id']; ?>">
                                <button type="submit" name="toggle_coupon" class="btn btn-sm <?php echo $c['is_active'] ? 'btn-warning' : 'btn-success'; ?>" title="<?php echo $c['is_active'] ? 'Tắt' : 'Bật'; ?>">
                                    <i class="fas fa-<?php echo $c['is_active'] ? 'pause' : 'play'; ?>"></i>
                                </button>
                            </form>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="coupon_id" value="<?php echo $c['id']; ?>">
                                <button type="submit" name="delete_coupon" class="btn btn-sm btn-danger ms-1" onclick="return confirm('Xóa mã <?php echo $c['code']; ?>?')" title="Xóa">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
            </div>

            </div>
        </div></div><!-- /panel coupon-list -->

    </div><!-- /col -->
</div><!-- /row -->
</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Biểu đồ doanh thu
const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{
            label: 'Doanh thu (₫)',
            data: <?php echo json_encode($chart_data); ?>,
            backgroundColor: 'rgba(228,68,68,0.7)',
            borderColor: '#e44',
            borderWidth: 2,
            borderRadius: 6,
        },{
            label: 'Xu hướng',
            data: <?php echo json_encode($chart_data); ?>,
            type: 'line',
            borderColor: '#667eea',
            borderWidth: 2,
            pointBackgroundColor: '#667eea',
            fill: false,
            tension: 0.4,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'top' }, tooltip: { callbacks: { label: c => c.dataset.label + ': ' + c.raw.toLocaleString('vi-VN') + '₫' } } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => (v/1000000).toFixed(1)+'tr' } } }
    }
});

// Toggle max discount field
// Toggle max discount field
function toggleMaxDisc() {
    const type = document.getElementById('couponType');
    const wrap = document.getElementById('maxDiscWrap');
    if (type && wrap) wrap.style.display = type.value === 'percent' ? 'block' : 'none';
}

// Panel functions
function openPanel(id) {
    document.querySelectorAll('.panel-overlay').forEach(p => p.classList.remove('active'));
    document.getElementById('panel-' + id).classList.add('active');
    document.body.style.overflow = 'hidden';
    return false;
}
function closePanel(id) {
    document.getElementById('panel-' + id).classList.remove('active');
    document.body.style.overflow = '';
    // Xóa param khỏi URL
    history.replaceState(null, '', 'admin.php');
}
function closePanelOutside(event, id) {
    if (event.target === document.getElementById('panel-' + id)) closePanel(id);
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.panel-overlay').forEach(p => p.classList.remove('active'));
        document.body.style.overflow = '';
        history.replaceState(null, '', 'admin.php');
    }
});
// Ẩn/hiện ô giảm tối đa khi chọn loại coupon (đã khai báo ở trên)
// (giữ lại khối này để tránh lỗi nếu file cũ có cấu trúc khác)

document.addEventListener('DOMContentLoaded', toggleMaxDisc);

const urlParams = new URLSearchParams(window.location.search);
const panelParam = urlParams.get('panel');
if (panelParam && document.getElementById('panel-' + panelParam)) {
    openPanel(panelParam);
}
</script>
</body>
</html>
<?php $conn->close(); ?>