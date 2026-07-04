<?php
session_start();
include 'connect.php';
include 'header.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . (isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php'));
    exit;
}

$login_error = ''; 
$register_error = ''; 
$register_success = '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username); 
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            header("Location: " . ($user['role'] === 'admin' ? 'admin.php' : $redirect));
            exit;
        } else { 
            $login_error = "Sai mật khẩu!"; 
        }
    } else { 
        $login_error = "Tài khoản không tồn tại!"; 
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['reg_username']); 
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['reg_password'];
    $confirm = $_POST['confirm_password'];

    // Chuẩn hoá input
    $username = trim($username);
    $email = trim($email);
    $phone = preg_replace('/\s+/', '', $phone);

    // Ràng buộc số điện thoại: đủ số và đúng format (ưu tiên VN: 10 số bắt đầu 0 hoặc 84)
    // Bạn có thể chỉnh regex nếu muốn chuẩn hoá theo format khác.
    $phone_digits = preg_replace('/\D/', '', $phone);

    if (empty($username) || empty($password) || empty($confirm) || empty($email) || empty($phone)) {
        $register_error = "Vui lòng điền đầy đủ thông tin!";
    } elseif ($password !== $confirm) {
        $register_error = "Mật khẩu xác nhận không khớp!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_error = "Email không hợp lệ!";
    } elseif (strlen($password) < 6) {
        $register_error = "Mật khẩu phải có ít nhất 6 ký tự!";
    } elseif (!preg_match('/^(0\d{9,10}|84\d{9,10})$/', $phone_digits)) {
        $register_error = "Số điện thoại không hợp lệ. Vui lòng nhập đúng số!";
    } else {
        // Kiểm tra trùng username / email / phone
        $stmt = $conn->prepare("SELECT id FROM users WHERE username=? OR email=? OR phone=?");
        $stmt->bind_param("sss", $username, $email, $phone_digits);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $register_error = "Tên đăng nhập, email hoặc số điện thoại đã tồn tại!";
        } else {
            // (Tuỳ chọn) kiểm tra email có tồn tại trên Gmail là không khả thi chuẩn bằng PHP thuần.
            // Vì vậy hệ thống kiểm tra theo chuẩn cú pháp email hợp lệ như trên.
            // Nếu bạn muốn kiểm tra có tồn tại thật (accepting mailbox), cần tích hợp dịch vụ SMTP/validation bên thứ ba hoặc gửi verification link.

            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $role = 'user';
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, email, phone) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $hashed, $role, $email, $phone_digits);
            $register_success = $stmt->execute() ? "Đăng ký thành công! Vui lòng đăng nhập." : "Lỗi khi đăng ký!";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - Anh Ba Bakery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="main.css">
    <style>
        body { 
            background: linear-gradient(135deg, #fdf6ef 0%, #f0d4d8 100%); 
        }
        .auth-wrapper { 
            max-width: 960px; 
            margin: 60px auto; 
            padding: 0 20px; 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 30px; 
        }
        .auth-card { 
            background: #fff; 
            padding: 40px 36px; 
            border-radius: 20px; 
            box-shadow: 0 10px 30px rgba(201, 123, 132, 0.15); 
            border: 1px solid var(--rose-light);
        }
        .auth-card h2 { 
            font-family: 'Cormorant Garamond', serif;
            font-size: 28px; 
            font-weight: 500; 
            font-style: italic;
            color: var(--brown); 
            margin-bottom: 24px; 
            text-align: center;
        }
        .form-group { 
            margin-bottom: 18px; 
        }
        .form-group label { 
            display: block; 
            font-size: 14px; 
            font-weight: 600; 
            margin-bottom: 6px; 
            color: var(--brown); 
        }
        .form-group input { 
            width: 100%; 
            padding: 12px 16px; 
            border: 1.5px solid var(--rose-light);
            border-radius: 10px; 
            font-size: 15px; 
            outline: none; 
            transition: all 0.2s; 
        }
        .form-group input:focus { 
            border-color: var(--rose); 
            box-shadow: 0 0 0 3px rgba(201,123,132,0.1);
        }
        .submit-btn { 
            width: 100%; 
            padding: 14px; 
            background: var(--rose); 
            color: #fff; 
            border: none; 
            border-radius: 10px; 
            font-size: 16px; 
            font-weight: 700; 
            cursor: pointer; 
            transition: background 0.2s; 
            margin-top: 8px;
        }
        .submit-btn:hover { 
            background: var(--rose-deep); 
        }
        .forgot-link { 
            text-align: right; 
            margin-top: -8px; 
            margin-bottom: 16px; 
        }
        .forgot-link a { 
            font-size: 13.5px; 
            color: var(--rose); 
        }
        .msg { 
            padding: 12px 16px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
            font-size: 14px; 
        }
        .msg-error { 
            background: #f8d7da; 
            color: #721c24; 
        }
        .msg-success { 
            background: #d4edda; 
            color: #155724; 
        }
        .auth-icon {
            text-align: center;
            font-size: 42px;
            margin-bottom: 16px;
            color: var(--rose);
        }
        @media (max-width: 768px) { 
            .auth-wrapper { 
                grid-template-columns: 1fr; 
            } 
        }
    </style>
</head>
<body>

<div class="auth-wrapper">
    
    <!-- Đăng nhập -->
    <div class="auth-card">
        <div class="auth-icon">🍰</div>
        <h2>Đăng Nhập</h2>
        <?php if ($login_error): ?>
            <div class="msg msg-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($login_error); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php?redirect=<?php echo urlencode($redirect); ?>">
            <input type="hidden" name="login" value="1">
            <div class="form-group">
                <label>Tên đăng nhập</label>
                <input type="text" name="username" placeholder="Nhập tên đăng nhập" required 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" name="password" placeholder="Nhập mật khẩu" required>
            </div>
            <div class="forgot-link">
                <a href="forgot_password.php">Quên mật khẩu?</a>
            </div>
            <button type="submit" class="submit-btn">
                <i class="fas fa-sign-in-alt"></i> Đăng nhập
            </button>
        </form>
    </div>

    <!-- Đăng ký -->
    <div class="auth-card">
        <div class="auth-icon">🎂</div>
        <h2>Tạo Tài Khoản Mới</h2>
        <?php if ($register_success): ?>
            <div class="msg msg-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($register_success); ?></div>
        <?php endif; ?>
        <?php if ($register_error): ?>
            <div class="msg msg-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($register_error); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="hidden" name="register" value="1">
            <div class="form-group">
                <label>Tên đăng nhập</label>
                <input type="text" name="reg_username" placeholder="Chọn tên đăng nhập" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="email@example.com" required>
            </div>
            <div class="form-group">
                <label>Số điện thoại</label>
                <input type="tel" name="phone" placeholder="0900 000 000" required>
            </div>
            <div class="form-group">
                <label>Mật khẩu <small>(ít nhất 6 ký tự)</small></label>
                <input type="password" name="reg_password" placeholder="Tạo mật khẩu" required>
            </div>
            <div class="form-group">
                <label>Xác nhận mật khẩu</label>
                <input type="password" name="confirm_password" placeholder="Nhập lại mật khẩu" required>
            </div>
            <button type="submit" class="submit-btn">
                <i class="fas fa-user-plus"></i> Đăng ký
            </button>
        </form>
    </div>

</div>

<?php include 'footer.php'; ?>
</body>
</html>
<?php $conn->close(); ?>