<?php
session_start();
include 'connect.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

function verify_csrf(): void {
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF validation failed');
    }
}

if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];

    // Ngăn xóa tài khoản admin hiện tại
    if ($user_id == $_SESSION['user_id']) {
        echo "<div class='alert alert-danger'>Không thể xóa tài khoản đang đăng nhập!</div>";
    } else {
        // Xóa người dùng khỏi cơ sở dữ liệu
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            header("Location: admin.php#user-list");
            exit;
        } else {
            echo "<div class='alert alert-danger'>Lỗi khi xóa người dùng!</div>";
        }
        $stmt->close();
    }
}

$conn->close();
?>