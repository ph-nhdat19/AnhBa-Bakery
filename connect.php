<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "t-shop";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// FIX: thêm charset utf8mb4 tránh lỗi tiếng Việt
$conn->set_charset("utf8mb4");
?>