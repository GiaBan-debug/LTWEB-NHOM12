# Kết quả kiểm thử bản demo

Ngày: 16/11/2026. Mục tiêu: chạy ổn định local và chuẩn bị mã nguồn cho PHP/MySQL shared hosting. Chưa phải nghiệm thu kinh doanh thật.

## Môi trường thực tế

- Windows, PHP 8.5.12 CLI; script khởi động nạp pdo_mysql và mbstring bằng tham số, không sửa cài đặt PHP toàn hệ thống.
- MariaDB 11.4.5 portable, bind 127.0.0.1:3307; không cài Windows service.
- Database demo `ltweb_nhom12_demo` tách riêng database ghi dữ liệu kiểm thử `ltweb_nhom12_test`.
- Playwright 1.65.1 và Chromium có sẵn trên máy (revision 1248).

## Kết quả

| Nhóm | Kết quả |
| --- | --- |
| Cú pháp PHP | 22 file qua php -l |
| Cú pháp JavaScript | 11 file qua node --check |
| API integration | 73 assertions qua |
| Browser integration | 29 assertions qua |
| Khởi động bằng start-demo.ps1 | Database có sẵn được giữ nguyên, PHP server khởi động được |
| Health / trang chủ | Kết nối DB thành công, trang chủ HTTP 200, demo đủ 8 sản phẩm |
| Dừng bản demo | Dừng PHP dẫn tới cleanup database; log MariaDB xác nhận Shutdown complete |
| Gói hosting | ZIP có .htaccess và .env.example; không có .env, .git, .local-test hoặc node_modules |

## Những hành vi được kiểm tra

- Đăng ký mới, email trùng, mật khẩu sai, đăng nhập, session, logout và cập nhật tên hồ sơ.
- Guest không đọc giỏ, user không gọi API admin, user khác không đọc được đơn không thuộc mình.
- Request ghi thiếu CSRF và JSON đầu vào sai định dạng/kiểu bị từ chối.
- Lấy sản phẩm, tìm kiếm, lọc danh mục/giá, sắp xếp và phân trang phía server.
- Thêm giỏ, cập nhật số lượng, tổng tiền server và giới hạn tồn kho.
- Checkout bỏ qua giá do client gửi, xóa giỏ sau thành công, thử lại cùng key trả cùng đơn.
- Hết hàng làm checkout thất bại và giữ lại giỏ; sản phẩm ngừng bán không đặt được.
- Hủy đơn hoàn kho một lần, không cho chuyển trạng thái sai, giữ snapshot giá/tên sau khi sửa sản phẩm.
- Trình duyệt: khách chọn sản phẩm → chuyển đăng nhập và giữ id sản phẩm → thêm giỏ → đổi số lượng → checkout → xác nhận đơn.
- Trình duyệt: admin xem dashboard, tạo/ngừng bán sản phẩm, cập nhật trạng thái đơn.
- Không có uncaught page errors trong lượt kiểm tra cuối.
- Kiểm tra không tràn ngang trang ở chiều rộng 390/768/1440 px trên home, catalog, giỏ, lịch sử và quản trị sản phẩm. Bảng admin được phép cuộn bên trong.
- Kiểm tra ảnh tải được; ảnh lazy-loading được chuyển eager trong bước chụp screenshot để kiểm tra toàn trang.
- Các đường dẫn riêng tư .env, SQL, config và runtime bị router local chặn.

Screenshot desktop/mobile được lưu trong `.local-test/home-desktop.png` và `.local-test/home-mobile.png`, không đưa vào gói hosting.

## Giới hạn và phần để sau

- Chưa upload hoặc kiểm thử trên tài khoản hosting thực tế. Apache/.htaccess, prefix database và cấu hình host cần xác nhận sau khi upload.
- Chưa kiểm thử tải cao/đồng thời nhiều worker hoặc tính sẵn sàng cho production. Test tồn kho và rollback ở đây là kiểm thử chức năng.
- Chưa kiểm thử migration trên dữ liệu kinh doanh có sẵn; file migration dành cho schema gốc của repository, cần backup và chỉ chạy một lần.
- COD, phí giao hàng demo 0 ₫; không có thanh toán online, gửi email, vận chuyển hoặc hóa đơn thực tế.
- Admin chọn ảnh có sẵn; chưa upload ảnh bằng trình duyệt. Quản lý user, bài viết, banner/settings nâng cao chưa thuộc phạm vi demo hoàn thiện.
- Chưa có cơ chế quên mật khẩu/email verification, giới hạn đăng nhập theo IP, backup tự động hoặc kiểm tra bảo mật độc lập. Không đưa dữ liệu khách hàng thật lên bản demo.

## Cách chạy lại

Xem README cho lệnh `run-demo.bat`, tạo DB test và hai lệnh `node tests/demo-api.mjs`, `node tests/demo-browser.mjs`. Test ghi dữ liệu chỉ chạy trên DB kiểm thử riêng; không trỏ vào DB của buổi demo hoặc cửa hàng thật.
