# TODO - Admin.php Production Hardening

## Bước 1: Chuẩn hóa hạ tầng bảo mật (CSRF/Session/Input)
- [x] Thêm hàm CSRF token (token theo session + hidden field + verify khi POST)
- [x] Thêm require_admin() kiểm tra chặt session/role
- [x] Thêm helper sanitize/validate (int/float/string/enum)






## Bước 2: Chuẩn hóa Prepared Statements
- [ ] Đổi tất cả các truy vấn trong admin.php sang prepared statements
- [ ] Đổi các select dùng cho modal (categories, products, users, orders, order_items, cart)
- [ ] Đổi query thống kê/dashboard/chart (nơi có tham số)

## Bước 3: Upload ảnh production ready
- [ ] Validate upload: MIME bằng finfo + getimagesize
- [ ] Chỉ accept jpg/jpeg/png/webp, max 5MB
- [ ] Đổi tên ngẫu nhiên (random_bytes) + sanitize extension
- [ ] Xóa ảnh cũ chỉ khi upload mới hợp lệ

## Bước 4: Thêm search + filter cho panel
- [ ] Sản phẩm: search theo tên + filter category
- [ ] Đơn hàng: search theo order_id/phone/customer + filter status
- [ ] Người dùng: search theo username/email/phone + filter role
- [ ] Coupon: search theo code + filter trạng thái

## Bước 5: Confirm dialog + CSRF cho mọi form POST
- [ ] Đảm bảo các form trong admin.php (confirm_order/update_status/add/update...) có CSRF
- [ ] Xóa/đổi toggle coupon/form submit... không còn thiếu CSRF

## Bước 6: UI/UX
- [ ] Thêm loading indicator khi mở panel
- [ ] Thêm responsive tweaks cho mobile
- [ ] Làm giao diện sạch, không phá style hiện có

## Bước 7: Refactor code + comment
- [ ] Thêm comment rõ ràng cho từng khối
- [ ] Dọn trùng lặp hàm JS (toggleMaxDisc đang bị trùng)

## Bước 8: Kiểm tra nhanh
- [ ] Test mở panel
- [ ] Test add/update sản phẩm (upload)
- [ ] Test toggle/delete coupon và cập nhật order status

