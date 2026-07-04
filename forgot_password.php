<?php
session_start();
include 'connect.php';
include 'header.php';

$success = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $username = trim($_POST['username']);
    $phone    = trim($_POST['phone']);
    $new_pass = trim($_POST['new_password']);
    $confirm  = trim($_POST['confirm_password']);

    if (empty($username) || empty($phone) || empty($new_pass) || empty($confirm)) {
        $error = "Vui lòng điền đầy đủ thông tin!";
    } elseif ($new_pass !== $confirm) {
        $error = "Mật khẩu xác nhận không khớp!";
    } elseif (strlen($new_pass) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự!";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND phone = ?");
        $stmt->bind_param("ss", $username, $phone);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = "Tên đăng nhập hoặc số điện thoại không đúng!";
        } else {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $user['id']);
            $stmt->execute(); $stmt->close();
            $success = "Đặt lại mật khẩu thành công! Bạn có thể đăng nhập ngay.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu | T-SHOP</title>
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { background: #f5f5f5; }
        .fp-wrap { max-width: 460px; margin: 60px auto; padding: 0 20px; }
        .fp-card { background: #fff; padding: 36px; border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); }
        .fp-card h2 { font-size: 22px; font-weight: 700; margin-bottom: 8px; }
        .fp-card p { font-size: 14px; color: #888; margin-bottom: 24px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #444; }
        .form-group input { width: 100%; padding: 11px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; outline: none; transition: border-color 0.2s; }
        .form-group input:focus { border-color: #999; }
        .submit-btn { width: 100%; padding: 13px; background: #e44; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer; transition: background 0.2s; }
        .submit-btn:hover { background: #c33; }
        .msg { padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; }
        .msg-success { background: #d4edda; color: #155724; }
        .msg-error { background: #f8d7da; color: #721c24; }
        .back-link { text-align: center; margin-top: 16px; font-size: 13px; }
        .back-link a { color: #e44; font-weight: 600; }
    </style>
</head>
<body>
<div class="fp-wrap">
    <div class="fp-card">
        <h2><i class="fas fa-lock" style="color:#e44;margin-right:8px"></i>Quên mật khẩu</h2>
        <p>Nhập tên đăng nhập và số điện thoại đã đăng ký để đặt lại mật khẩu.</p>

        <?php if ($success): ?>
            <div class="msg msg-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <a href="login.php" style="display:block;text-align:center;padding:12px;background:#111;color:#fff;border-radius:8px;font-weight:600;text-decoration:none">Đăng nhập ngay</a>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="msg msg-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Tên đăng nhập</label>
                    <input type="text" name="username" placeholder="Nhập tên đăng nhập" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Số điện thoại đã đăng ký</label>
                    <input type="tel" name="phone" placeholder="0900 000 000" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Mật khẩu mới</label>
                    <input type="password" name="new_password" placeholder="Tối thiểu 6 ký tự" required>
                </div>
                <div class="form-group">
                    <label>Xác nhận mật khẩu mới</label>
                    <input type="password" name="confirm_password" placeholder="Nhập lại mật khẩu mới" required>
                </div>
                <button type="submit" name="reset_password" class="submit-btn">
                    <i class="fas fa-key" style="margin-right:6px"></i>Đặt lại mật khẩu
                </button>
            </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="login.php"><i class="fas fa-arrow-left" style="margin-right:4px"></i>Quay lại đăng nhập</a>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
<?php $conn->close(); ?>