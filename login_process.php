<?php
/**
 * login_process.php
 * FIX: File cũ dùng SQL nối chuỗi trực tiếp (SQL Injection) + không hash password.
 * File này đã được thay bằng xử lý login an toàn, redirect sang login.php.
 * Toàn bộ logic đăng nhập đã được tích hợp vào login.php (prepared statement + password_verify).
 */
session_start();
header("Location: login.php");
exit;
?>