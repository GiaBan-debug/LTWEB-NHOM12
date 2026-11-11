# Checklist kiểm thử xác thực (Thiên Ân)

Chạy site qua XAMPP/Apache (ví dụ `http://localhost/LTWEB-NHOM12/frontend/index.html`), **không** mở file bằng `file://`.

Tài khoản seed (`database/seed.sql`): mật khẩu `123456`.
- Admin: `admin@ltweb.com`
- User: `user1@ltweb.com`

Người test: Thiên Ân  
Ngày test: 11/11/2026  
Kết quả tổng: **Pass 11/11**

Ghi kết quả vào cột Pass/Fail. Mật khẩu **không** được xuất hiện trong Application → Local Storage.

| # | Ca kiểm thử | Bước | Kỳ vọng | Pass/Fail | Ghi chú / lỗi |
|---|-------------|------|---------|-----------|----------------|
| 1 | Validate đăng ký | Form trống / email sai / mật khẩu < 6 / xác nhận không khớp | Báo lỗi trên field, **không** gọi API thành công | Pass | Lỗi hiện trên field; không tạo tài khoản |
| 2 | Đăng ký thành công | Tên + email mới + mật khẩu ≥ 6 + xác nhận khớp | Thông báo thành công, chuyển `login.html`. DB lưu hash (`password` bắt đầu `$2y$`) | Pass | Chuyển sang login; cột password trong `users` là hash `$2y$` |
| 3 | Email trùng | Đăng ký lại email vừa tạo hoặc `user1@ltweb.com` | Báo email đã dùng, không tạo user mới | Pass | Báo email đã được sử dụng |
| 4 | Login sai mật khẩu | `user1@ltweb.com` + mật khẩu sai | Không vào trang chủ, hiện lỗi, không có `shop_user` chứa password | Pass | `login.php` 401; ở lại trang login |
| 5 | Login đúng | `user1@ltweb.com` / `123456` | Về trang chủ, header: “Xin chào, …”, nút Đăng xuất. **Không** có link Admin | Pass | `login.php` 200; header “Xin chào, Nguyễn Văn A”; không có Admin |
| 6 | Phiên / me.php | Đã login → DevTools → Network `me.php` | HTTP 200, JSON `success: true`, có `name/email/role`, **không** có hash | Pass | Response có id/name/email/role; không có password |
| 7 | Đăng xuất | Bấm Đăng xuất | Header trở lại Đăng nhập / Đăng ký. Gọi lại `me.php` → **401** | Pass | `logout.php` 200; `me.php` sau logout = 401 |
| 8 | Login Admin | `admin@ltweb.com` / `123456` | Header có link **Admin** | Pass | Hiện “Xin chào, Admin” và link Admin |
| 9 | User không vào Admin | Logout, login `user1@ltweb.com`, mở trực tiếp `backend/admin/index.php` | HTTP **403**, không thấy dashboard | Pass | Bị từ chối, không vào dashboard |
| 10 | Guest không vào Admin | Ẩn danh, mở `backend/admin/index.php` | HTTP **401** | Pass | Yêu cầu đăng nhập |
| 11 | SessionStorage | Sau login, Application → Session Storage `shop_user` | Chỉ `id, name, email, role`. Local Storage không có mật khẩu | Pass | `shop_user` không chứa password; Local Storage không lưu mật khẩu |

## Lỗi phát hiện

Không phát hiện lỗi blocking trên 11 ca. Encoding tiếng Việt trên header đã xử lý trước khi test (UTF-8 / `auth.js`).

| Thời điểm | Mức độ | Bước tái hiện | Người phụ trách |
|-----------|--------|----------------|-----------------|
| — | — | Không có | — |
