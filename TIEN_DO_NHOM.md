# Đối chiếu nhiệm vụ TV1–TV6

Ngày kiểm tra: 09/11/2026. Phạm vi: mã nguồn trong workspace hiện tại.

Đây là đánh giá qua đọc mã nguồn, chưa phải nghiệm thu chạy thực tế. Môi trường hiện tại không tìm thấy PHP/MySQL trong PATH nên chưa chạy API, import SQL hoặc kiểm thử trình duyệt với backend. “Có code” không đồng nghĩa chức năng đã hoàn thành. Phân công dưới đây chỉ rõ đầu việc theo vai trò, không xác nhận ai là tác giả của code.

## Công nghệ thống nhất

Dự án hiện dùng PHP + HTML + CSS + JavaScript + MySQL. Mục “Cấu hình FastAPI” trong phân công TV5 không khớp với stack này; đề xuất thay bằng “Cấu hình backend PHP, môi trường và kết nối MySQL”. Chưa có FastAPI trong repository; không cần bổ sung nếu nhóm tiếp tục dùng PHP.

## TV1 — Frontend nền tảng

| Nhiệm vụ | Hiện trạng | Việc còn phải làm |
| --- | --- | --- |
| Trang chủ | Có giao diện tĩnh trong `frontend/index.html` | Hoàn thiện điều hướng; các liên kết đăng nhập, đăng ký, giỏ hàng, danh mục và xem tất cả đang dùng `#`. |
| Navbar, footer | Có trên trang chủ | Đồng bộ layout giữa các trang; giỏ hàng dùng header và tên cửa hàng khác trang chủ. |
| Banner | Có thẻ ảnh cover | Bổ sung ảnh được tham chiếu; chưa có thư mục `frontend/assets/images` trong mã nguồn hiện tại. `banner.js` trống; nếu cần banner động thì chưa triển khai. |
| Layout chung | Có CSS nền và header | Áp dụng thống nhất cho các trang còn thiếu; sửa đường dẫn asset ở trang giỏ hàng. |
| Responsive desktop/mobile | Có media query trong `style.css` và `cart.css` | Kiểm thử kích thước màn hình thực tế. `responsive.css` trống nhưng không có nghĩa toàn bộ responsive chưa làm. |
| Quy định file, class, branch | Chưa thấy tài liệu; README trống | Viết quy ước, thống nhất chữ hoa/thường `BACKEND` và đường dẫn API. |
| GitHub Project | Chưa xác minh | Kiểm tra trực tiếp Project trên GitHub; không kết luận chưa làm từ mã nguồn. |
| Review Pull Request | Chưa xác minh | Kiểm tra lịch sử review trên GitHub. |
| Merge vào main | Chưa xác minh | Kiểm tra PR và lịch sử merge; không tự merge trong đợt rà soát này. |

## TV2 — Sản phẩm

| Nhiệm vụ | Hiện trạng | Việc còn phải làm |
| --- | --- | --- |
| Danh sách sản phẩm | `frontend/pages/products.html` trống; trang chủ chỉ có sản phẩm viết cứng | Xây dựng danh sách từ API và xử lý tải, rỗng, lỗi. |
| Chi tiết sản phẩm | `frontend/pages/product-detail.html` trống | Hiển thị sản phẩm theo id/slug, ảnh, giá, mô tả, tồn kho. |
| Danh mục | Menu tĩnh trên trang chủ; API đã có code | Hiển thị danh mục từ API và liên kết đến danh sách tương ứng. |
| Tìm kiếm | Có ô nhập trên trang chủ, chưa có xử lý JS | Gửi từ khóa đến API và hiển thị kết quả. |
| Lọc giá/danh mục | Chưa có giao diện hoạt động | Bổ sung bộ lọc và kết nối tham số API. |
| Sắp xếp | Chưa có giao diện hoạt động | Bổ sung chọn sắp xếp theo giá/tên/ngày và gọi API. |
| Nút thêm giỏ hàng | Có nút tĩnh ở trang chủ, không có xử lý | Gắn product_id, gọi POST giỏ hàng, xử lý đăng nhập và tồn kho. |
| Kết nối API sản phẩm | `frontend/assets/js/products.js` trống | Triển khai toàn bộ phần tích hợp; phối hợp TV5 kiểm thử API. |

## TV3 — Tài khoản và giỏ hàng

| Nhiệm vụ | Hiện trạng | Việc còn phải làm |
| --- | --- | --- |
| Đăng ký | Trang HTML và `auth.js` trống; backend có API | Làm form, kiểm tra dữ liệu và tích hợp API. |
| Đăng nhập | Trang HTML trống; backend có API | Làm form, xử lý lỗi và chuyển hướng sau đăng nhập. |
| Đăng xuất | Backend có API; chưa có frontend xử lý | Bổ sung nút và gọi API đăng xuất. |
| Thông tin cá nhân | Chưa thấy trang hoặc API tương ứng | Làm trang thông tin, API đọc/cập nhật theo phạm vi nhóm thống nhất. |
| Hiển thị giỏ hàng | Có HTML/CSS/JS | Sửa đường dẫn asset, sau đó kiểm thử với backend đã sửa. |
| Thêm/xóa sản phẩm | JS có xóa; backend có code thêm/xóa | Tích hợp thêm từ trang sản phẩm với TV2; sửa lỗi backend cùng TV5. |
| Tăng/giảm số lượng | Có JS và API xử lý | Chưa nghiệm thu vì API đang thiếu phụ thuộc và lệch schema. |
| Tổng tiền | Có phần hiển thị và code tính ở server | Kiểm thử giá, số lượng và cập nhật tổng sau mỗi thao tác. |
| Trạng thái đăng nhập | JS giỏ hàng xử lý HTTP 401; backend có helper session | Bổ sung kiểm tra trạng thái dùng chung và cập nhật navbar; chưa thấy endpoint trả người dùng hiện tại. |

Lỗi đường dẫn cụ thể: `frontend/pages/cart.html` đang gọi `../../assets/css/cart.css` và `../../assets/js/cart.js`, nhưng file thật nằm ở `frontend/assets/`. Đường dẫn tương ứng cần là `../assets/css/cart.css` và `../assets/js/cart.js`.

## TV4 — Checkout và Admin

| Nhiệm vụ | Hiện trạng | Việc còn phải làm |
| --- | --- | --- |
| Trang thanh toán | Có form đặt hàng tích hợp trong giỏ hàng; chưa có trang checkout riêng | Thống nhất dùng form tích hợp hay trang riêng; hoàn thiện luồng hoạt động. |
| Thông tin giao hàng | Có họ tên, điện thoại, địa chỉ, ghi chú và validate | Đồng bộ schema với API, kiểm thử gửi dữ liệu. |
| Xác nhận đơn hàng | JS gọi API rồi chuyển đến `orders.html` | Trang `orders.html` chưa tồn tại; làm trang xác nhận/chi tiết và kiểm thử đặt hàng thành công. |
| Dashboard Admin | `BACKEND/admin/index.php` trống | Xây dựng dashboard và bảo vệ bằng quyền admin. |
| Quản lý sản phẩm | Chưa có giao diện quản trị | Làm danh sách quản trị và tích hợp API. |
| Thêm/sửa/xóa sản phẩm | Chưa có giao diện hoặc API tương ứng | TV4 làm giao diện, TV5 làm API có phân quyền và validate. |
| Quản lý đơn hàng | Chưa có giao diện quản trị | Làm danh sách/chi tiết đơn của khách hàng cho admin. |
| Cập nhật trạng thái đơn | Chưa thấy triển khai | TV4 làm giao diện; TV5 làm API và kiểm soát chuyển trạng thái. |

## TV5 — Backend PHP

| Nhiệm vụ | Hiện trạng | Việc còn phải làm |
| --- | --- | --- |
| Cấu hình backend | Có cấu hình PHP; không có FastAPI | Thống nhất lại phân công theo PHP; ghi yêu cầu phiên bản, extension và cách chạy. |
| Kết nối database | Có PDO trong `BACKEND/config/database.php` | Chuẩn bị cấu hình mẫu không chứa bí mật, kiểm thử kết nối. |
| REST API | Có nhiều endpoint PHP | Thống nhất helper, session, response, xử lý lỗi và phương thức HTTP. |
| Đăng ký/đăng nhập | Có code hash/verify mật khẩu và session | Kết nối frontend, kiểm thử tài khoản và lỗi đầu vào. |
| Sản phẩm | Có GET danh sách/chi tiết, tìm kiếm, lọc, sắp xếp, phân trang | Kiểm thử với MySQL; chú ý bind kiểu số nguyên cho LIMIT/OFFSET khi tắt emulate prepares. |
| Danh mục | Có GET danh sách/chi tiết | Kiểm thử dữ liệu và lỗi id không hợp lệ. |
| Giỏ hàng | Có GET/POST/PUT/DELETE nhưng bị chặn | Sửa file require và các truy vấn không khớp schema; thống nhất helper đăng nhập. |
| Đơn hàng | Có code tạo đơn, lịch sử, chi tiết, transaction và trừ kho nhưng bị chặn | Sửa phụ thuộc và tên cột; kiểm thử rollback, tồn kho và quyền truy cập. |
| Admin | Có `requireAdmin()` nhưng chưa thấy API admin | Làm CRUD sản phẩm, quản lý đơn và cập nhật trạng thái có phân quyền. |
| Authentication | Có session và helper trong `includes/auth.php` | API giỏ/đơn dùng bộ helper khác chưa tồn tại; hợp nhất kiểm tra session và kiểm thử quyền user/admin. |
| Kiểm tra dữ liệu | Có validate ở một số API | Hoàn thiện xử lý JSON sai định dạng/kiểu dữ liệu, giới hạn chuỗi và lỗi trả về nhất quán. |

Các lỗi chặn TV5 cần phối hợp TV6:

1. `cart.php` và `orders.php` require `config/db.php`, `includes/helpers.php`, nhưng hai file này không tồn tại. Các hàm `db()`, `run_api()`, `require_login()`, `json_input()`, `json_response()`, `positive_int()` và lớp `ApiException` cũng chưa có định nghĩa trong mã nguồn hiện tại. File hiện có dùng bộ tên khác như `getDB()`, `requireLogin()` và `jsonResponse()`.
2. API giỏ hàng truy vấn `cart_items.user_id`, `cart_items.updated_at`; schema chỉ có `cart_id` và không có `updated_at` ở bảng này. Schema tổ chức quan hệ qua `carts.user_id`.
3. API giỏ/đơn dùng `products.image`; schema dùng `products.thumbnail`.
4. API đơn dùng `orders.total_amount`, `receiver_name`, `phone`; schema có `total_price` và chưa có hai cột thông tin người nhận.
5. API chi tiết đơn dùng `order_items.product_name`, `image`, `price`; schema chưa có các cột này, hiện dùng `unit_price`.
6. JS gọi `../../backend/api/` nhưng tên thư mục thật là `BACKEND`. Cần thống nhất chính xác chữ hoa/thường để tránh lỗi khi triển khai trên hệ thống phân biệt hoa/thường.

## TV6 — Database, Testing và Documentation

| Nhiệm vụ | Hiện trạng | Việc còn phải làm |
| --- | --- | --- |
| Thiết kế database | Có `database/schema.sql` | Đồng bộ thiết kế với backend giỏ/đơn; bổ sung sơ đồ ERD và mô tả bảng. |
| users | Có bảng, email unique và role | Kiểm thử đăng ký, đăng nhập và quyền; thống nhất dữ liệu hồ sơ cần lưu. |
| products | Có bảng và khóa ngoại danh mục | Thống nhất `thumbnail`/`image` với TV5. |
| categories | Có bảng | Kiểm thử quan hệ và dữ liệu mẫu. |
| cart | Có `carts` và `cart_items` | Giữ quan hệ hiện tại hoặc thống nhất thay đổi với TV5; sửa toàn bộ truy vấn liên quan. |
| orders | Có `orders` và `order_items` | Thống nhất tổng tiền, thông tin giao hàng và dữ liệu chụp tại thời điểm mua. |
| Quan hệ bảng | Có khóa ngoại và index | Kiểm thử ràng buộc thêm/xóa, thiết kế xóa sản phẩm đã có trong đơn hàng. |
| Dữ liệu mẫu | Có 4 danh mục, 3 tài khoản, 8 sản phẩm | Xác minh mật khẩu mẫu: comment ghi `123456`, chưa kiểm chứng hash. Bổ sung ảnh, giỏ và đơn mẫu nếu cần kiểm thử. |
| Testing | Chưa thấy bộ test hoặc báo cáo test | Viết checklist, chạy kiểm thử API, giao diện, phân quyền, tồn kho và transaction; ghi kết quả thực tế. |
| Documentation | README hiện trống | Viết hướng dẫn cài/chạy, import schema rồi seed, cấu hình DB, tài khoản demo đã xác minh, đặc tả API và quy trình Git. |

## Thứ tự đề xuất

1. TV5 + TV6 thống nhất schema, helper, session và sửa các lỗi chặn API giỏ/đơn.
2. TV1 sửa asset/điều hướng, bổ sung ảnh và quy ước chung; TV3 sửa đường dẫn trang giỏ hàng.
3. TV2 hoàn thiện trang sản phẩm và tích hợp API; TV3 hoàn thiện tài khoản và luồng thêm giỏ.
4. TV4 + TV5 hoàn thiện xác nhận đơn và quản trị.
5. TV6 phối hợp các thành viên kiểm thử xuyên suốt: đăng ký → đăng nhập → tìm sản phẩm → thêm giỏ → đổi số lượng → đặt hàng → admin xử lý đơn; TV1 kiểm tra responsive và review tích hợp.

Chưa có chức năng nào được xác nhận hoàn thành qua chạy thực tế trong đợt rà soát này. Những việc GitHub cần bằng chứng từ GitHub trước khi đánh dấu hoàn thành hoặc chưa làm.
