# 🎂 AnhBa Bakery – Website Bán Bánh Kem & Bánh Ngọt

![PHP](https://img.shields.io/badge/PHP-8.0-blue)
![MySQL](https://img.shields.io/badge/Database-MySQL-orange)
![Bootstrap](https://img.shields.io/badge/Frontend-Bootstrap-purple)
![XAMPP](https://img.shields.io/badge/Server-XAMPP-red)

## 📋 Giới thiệu
AnhBa Bakery là website thương mại điện tử dành cho cửa hàng bánh kem & bánh ngọt.  
Hệ thống hỗ trợ trải nghiệm mua hàng từ xem sản phẩm, chọn kích thước, thêm vào giỏ, đến đặt hàng và thanh toán. Ngoài ra có trang quản trị giúp vận hành sản phẩm, đơn hàng, người dùng, đánh giá và mã khuyến mãi.

---

## ✨ Tính năng – Người dùng
- 🏠 Trang chủ hiển thị theo danh mục: bánh chạy nhất, khuyến mãi hôm nay
- 🔍 Tìm kiếm & lọc sản phẩm
- 🎂 Trang chi tiết sản phẩm, chọn kích cỡ (cảnh báo nếu chưa chọn)
- 🛒 Giỏ hàng & đặt hàng / checkout
- ⭐ Đánh giá sản phẩm
- 👤 Đăng ký / Đăng nhập
- 🎫 Mã giảm giá (coupon)
- 📱 Giao diện responsive

## 🔧 Tính năng – Admin
- 📊 Dashboard: tổng sản phẩm, đơn hàng, doanh thu tháng, số khách hàng
- 📦 Quản lý sản phẩm: thêm/sửa/xóa, upload ảnh an toàn (validate MIME + giới hạn 5MB)
- 🧾 Quản lý đơn hàng: xem, cập nhật trạng thái, xem chi tiết, xóa đơn đã hủy
- 🛒 Quản lý giỏ hàng: xem theo user, xóa từng item
- 👥 Quản lý người dùng: xem, sửa role, xóa (trừ tài khoản đang đăng nhập)
- ⭐ Quản lý đánh giá: tìm kiếm, lọc theo số sao
- 🎫 Quản lý coupon: tạo/sửa/xóa, hỗ trợ giảm % hoặc số tiền cố định
- 📥 Xuất CSV báo cáo doanh thu theo khoảng ngày

---

## 🔒 Bảo mật
- Phân quyền: chỉ tài khoản `role = admin` mới truy cập trang quản trị
- CSRF Token: sinh token theo session, verify khi submit form
- Validate & sanitize dữ liệu đầu vào trước khi ghi database
- Upload ảnh: kiểm tra MIME thực tế + chữ ký ảnh, đổi tên ngẫu nhiên

---

## 🛠️ Công nghệ sử dụng
| Thành phần | Công nghệ |
|-----------|-----------|
| Ngôn ngữ backend | PHP |
| Database | MySQL / MariaDB |
| Frontend | HTML, CSS, JavaScript |
| UI Framework | Bootstrap |
| Biểu đồ | Chart.js |
| Server local | XAMPP (Apache) |

---

## 🚀 Cài đặt & Chạy

### Yêu cầu
- XAMPP (Apache + MySQL)
- PHP 7.4 trở lên

### Các bước
1. Clone repository:
git clone https://github.com/ph-nhdat19/AnhBa-Bakery.git
2. Copy vào thư mục:
C:\xampp\htdocs\YourName
3. Tạo file `connect.php`:
```php
   <?php
   $conn = mysqli_connect("localhost", "root", "", "tên_database");
   ?>
```
4. Import database: chạy file `reviews.sql` trong phpMyAdmin
5. Khởi động **Apache** và **MySQL** trong XAMPP
6. Mở trình duyệt vào:
http://localhost/YourName/

### Đăng nhập Admin
Truy cập `http://localhost/YourName/admin.php` bằng tài khoản có `role = admin`

---

## 📁 Cấu trúc thư mục
YourName/
├── admin.php           # Trang quản trị
├── index.php           # Trang chủ
├── header.php          # Header dùng chung
├── footer.php          # Footer dùng chung
├── connect.php         # Kết nối database (không đẩy lên GitHub)
├── uploads/products/   # Ảnh sản phẩm
├── img/                # Hình ảnh & video banner
└── ...

---

## 👤 Tác giả
Anh Ba Sóc Trăng  
GitHub: [@ph-nhdat19](https://github.com/ph-nhdat19)
