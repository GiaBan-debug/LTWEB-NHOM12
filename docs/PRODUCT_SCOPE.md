
# Phạm vi website doanh nghiệp bán đồ điện tử

Trạng thái: đề xuất triển khai, chưa phải xác nhận hệ thống đã sẵn sàng bán hàng thật.
Nền tảng: tiếp tục dự án PHP + MySQL + HTML/CSS/JavaScript hiện tại.
Thương hiệu tạm dùng: Nhóm 12 Store; cần thay bằng thông tin doanh nghiệp được cung cấp.

## Mục tiêu

Website cho một cửa hàng bán đồ điện tử, có trải nghiệm mua hàng hoàn chỉnh, quản trị dữ liệu thật và cấu trúc dễ bảo trì. Không dùng số dòng code, số file hoặc giao diện đẹp làm tiêu chí hoàn thành; nghiệm thu theo luồng hoạt động.

## Bản đầu tiên có thể nghiệm thu

### Khách hàng

- Trang chủ dùng sản phẩm, giá và tồn kho cùng nguồn dữ liệu với danh sách và chi tiết; loại bỏ thông tin sản phẩm viết cứng không khớp database.
- Danh sách theo danh mục, tìm kiếm, lọc giá, sắp xếp và phân trang trên toàn bộ kết quả.
- Chi tiết có ảnh, mô tả, thông số kỹ thuật, giá, trạng thái còn hàng và số lượng mua.
- Đăng ký, đăng nhập, đăng xuất, xem/cập nhật hồ sơ và địa chỉ nhận hàng.
- Giỏ hàng lưu theo tài khoản, chỉnh số lượng và tính lại tiền theo dữ liệu server.
- Checkout có thông tin người nhận, địa chỉ, số điện thoại, phí giao hàng được trình bày rõ và phương thức COD cho bản đầu tiên.
- Xác nhận đặt hàng, lịch sử và chi tiết đơn, hiển thị trạng thái xử lý.
- Trang liên hệ, giao hàng, đổi trả, bảo hành và quyền riêng tư dùng nội dung doanh nghiệp xác nhận; không tự đặt địa chỉ hoặc cam kết bảo hành.

### Quản trị

- Đăng nhập và phân quyền phía server; khách/user không được gọi API quản trị.
- Dashboard từ dữ liệu thực: đơn mới, tình trạng xử lý và chỉ số bán hàng với cách tính rõ ràng.
- Quản lý danh mục, sản phẩm, ảnh, thông số, giá, tồn kho và trạng thái đang bán/ngừng bán.
- Quản lý đơn: danh sách, tìm kiếm/lọc, chi tiết, cập nhật trạng thái theo quy tắc đã thống nhất.
- Hủy đơn/hoàn tồn kho theo quy tắc nhất quán và chỉ thực hiện một lần.
- Giữ giá/tên sản phẩm tại thời điểm đặt hàng; thay đổi sản phẩm hiện tại không làm thay đổi lịch sử đơn.

### Chất lượng triển khai

- Thống nhất URL và cấu trúc trang, không có đường dẫn viết cứng theo thư mục máy cá nhân.
- Giao diện nhất quán, responsive, thao tác bàn phím và nhãn form rõ ràng; có trạng thái đang tải, rỗng, lỗi và thành công.
- PDO prepared statements, validate phía server, escape đầu ra, bảo vệ CSRF cho thao tác dùng session và giới hạn thử đăng nhập.
- Upload ảnh kiểm tra dung lượng, loại file và nơi lưu; không cho chạy script từ thư mục upload.
- Biến cấu hình không chứa bí mật trong Git; có hướng dẫn cài đặt, import/migration database và tài khoản demo.
- Có kiểm thử tích hợp luồng mua hàng, phân quyền, tồn kho và rollback khi thất bại.

## Trước khi bán hàng thật

- Doanh nghiệp xác nhận thương hiệu, nội dung, danh mục, giá, tồn kho, phí giao hàng và chính sách.
- Chọn hosting PHP/MySQL, tên miền, HTTPS, cấu hình môi trường production, log lỗi và cách theo dõi sự cố.
- Có lịch sao lưu database/ảnh và kiểm tra khôi phục.
- Kiểm tra quy trình xử lý đơn thực tế với nhân viên cửa hàng, tình huống hết hàng và đơn bị hủy.
- Tích hợp thanh toán online/giao vận chỉ sau khi chốt nhà cung cấp, tài khoản và môi trường thử nghiệm; không hiển thị thanh toán thành công khi chưa xác minh từ server.
- Xem xét yêu cầu pháp lý áp dụng tại thời điểm đưa vào kinh doanh bằng nguồn chính thức; tài liệu này không xác nhận tuân thủ pháp lý.

## Thứ tự triển khai trên repository

1. Sửa môi trường PHP, helper và các điểm API/schema không khớp theo `PROJECT_AUDIT.md`.
2. Hoàn chỉnh sản phẩm và tài khoản, thống nhất điều hướng và thương hiệu.
3. Hoàn chỉnh giỏ, checkout COD, xác nhận và lịch sử đơn.
4. Hoàn chỉnh quản trị sản phẩm/đơn và quy tắc tồn kho.
5. Kiểm thử toàn bộ, hoàn thiện tài liệu và chuẩn bị gói triển khai.
6. Triển khai môi trường thử nghiệm; chỉ chốt sẵn sàng bán thật sau khi các điều kiện vận hành được kiểm tra.

## Quyết định cần người dùng cung cấp

- Mục tiêu trước mắt: đồ án/portfolio chuyên nghiệp hay vận hành cửa hàng thật.
- Tên thương hiệu, logo và thông tin liên hệ; có thể dùng nhận diện tạm trong thời gian phát triển.
- Nhóm sản phẩm chính và dữ liệu thật; hiện nội dung trang chủ PC chưa đồng nhất với seed điện thoại/laptop.
- Phí giao hàng, bảo hành, đổi trả và cách xử lý đơn; dùng quy tắc được xác nhận trước khi vận hành thật.

Các nội dung này chưa được xem là đã thống nhất chỉ vì có trong tài liệu đề xuất.
