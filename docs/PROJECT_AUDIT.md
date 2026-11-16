# Các phần còn thiếu để hoàn chỉnh dự án

> Đây là ảnh chụp hiện trạng TRƯỚC khi nâng cấp bản demo. Xem README và `docs/DEMO_VALIDATION.md` cho trạng thái sau triển khai; các lỗi dưới đây không còn được coi là danh sách lỗi hiện tại nếu đã sửa trong bản demo.

Ngày rà soát: 16/11/2026. Phạm vi: mã nguồn và PHP CLI hiện có trong workspace. Mục tiêu: hoàn thiện đồ án cửa hàng điện tử có luồng mua hàng và quản trị hoạt động.

## Kết luận

Dự án đã có trang chủ, tài nguyên ảnh, giao diện đăng ký/đăng nhập, API session và bước đầu hiển thị sản phẩm. Tuy nhiên chưa hoàn chỉnh luồng chọn sản phẩm → thêm giỏ → đặt hàng → quản trị xử lý đơn. Không dùng lại tỷ lệ đánh giá ngày 09/11 vì mã nguồn đã thay đổi. Chưa đủ kết quả chạy tích hợp để đưa ra tỷ lệ nghiệm thu chính xác.

Đợt này chỉ kiểm tra và viết báo cáo, không sửa ứng dụng hoặc database. File tiến độ cũ đang bị xóa trong working tree; báo cáo này là file riêng, không khôi phục thay đổi đó.

## P0 — Lỗi chặn cần sửa trước

### 1. Môi trường PHP/MySQL chưa đủ — TV5, phối hợp TV6

- PHP CLI tại `C:/xampp/php/php.exe` hiện không nạp `php.ini`.
- `PDO::getAvailableDrivers()` trả danh sách rỗng: chưa có driver `pdo_mysql`, nên cấu hình PDO MySQL hiện không thể kết nối trên môi trường CLI này.
- Module `mbstring` cũng chưa được nạp, trong khi code gọi `mb_strlen()` và `mb_strtolower()`.
- Cần chuẩn bị PHP có cấu hình/extension đầy đủ, MySQL chạy được, cấu hình DB đúng và import schema/seed. Trạng thái môi trường hosting khác phải kiểm tra riêng.

### 2. API giỏ và đơn thiếu phụ thuộc — TV5

`BACKEND/api/cart.php` và `orders.php` vẫn require `config/db.php`, `includes/helpers.php`, nhưng các file này không có. Hai API dùng bộ tên `db()`, `run_api()`, `require_login()`, `json_input()`, `json_response()`, `positive_int()`, `ApiException` chưa được định nghĩa trong mã nguồn hiện tại.

- [ ] Hợp nhất với `config/database.php`, `includes/functions.php`, `includes/auth.php`, hoặc triển khai đầy đủ một bộ helper dùng chung.
- [ ] Thống nhất session với đăng nhập hiện đang lưu `$_SESSION['user']`.
- [ ] Xác nhận API giỏ/đơn trả JSON hợp lệ thay vì lỗi PHP.

### 3. API không khớp schema — TV5 + TV6

| API đang dùng | Schema hiện có | Việc cần thống nhất |
| --- | --- | --- |
| `cart_items.user_id` | `cart_items.cart_id` → `carts.user_id` | Sửa truy vấn và thao tác thêm/xóa theo mô hình đã chọn. |
| `cart_items.updated_at` | Không có cột này | Thêm cột hoặc đổi thứ tự truy vấn. |
| `products.image` | `products.thumbnail` | Thống nhất tên cột và trường JSON. |
| `orders.total_amount` | `orders.total_price` | Chọn một tên và sửa đồng bộ. |
| `orders.receiver_name`, `phone` | Chưa có | Bổ sung thông tin giao hàng cần lưu. |
| `order_items.price` | `order_items.unit_price` | Đồng bộ giá tại thời điểm mua. |
| `order_items.product_name`, `image` | Chưa có | Thống nhất lưu thông tin sản phẩm tại thời điểm mua. |

### 4. Mật khẩu seed khác tài liệu — TV6, phối hợp TV3/TV5

Đã dùng `password_verify()` kiểm tra hash trong `database/seed.sql`: `123456` không khớp, còn `password` khớp. Comment seed và `docs/auth-testing.md` đang ghi `123456`.

- [ ] Tạo lại hash cho mật khẩu demo được nhóm chọn hoặc sửa tài liệu theo dữ liệu thực tế.
- [ ] Kiểm thử lại tài khoản trên DB được tạo mới từ seed. Báo cáo Pass 11/11 của nhóm có thể thuộc dữ liệu DB khác; đợt rà soát này không tái xác nhận kết quả đó.

## P1 — Chức năng bắt buộc còn thiếu

### TV1 — Nền tảng giao diện và tích hợp

- [ ] Đồng bộ header/footer, tên cửa hàng và điều hướng giữa trang chủ, trang sản phẩm và giỏ hàng.
- [ ] Nối link giỏ hàng trên trang chủ; hiện vẫn là `href="#"` và không có handler tương ứng trong `main.js`.
- [ ] Gắn thao tác cho nút thêm giỏ/mua ngay và tìm kiếm trên trang chủ cùng TV2/TV3.
- [ ] Thống nhất một cấu trúc trang chính thức: `pages/` và `pages/users/` hiện có nhiều trang trùng tên nhưng một bản trống.
- [ ] Thống nhất chữ hoa/thường: thư mục vật lý hiện là `BACKEND`, nhiều URL dùng `backend`; kiểm tra trên hệ thống phân biệt hoa/thường trước khi deploy.
- [ ] Kiểm thử responsive và điều hướng thực tế trên các trang hoàn thiện.
- [ ] Thống nhất menu/dữ liệu: trang chủ quảng bá PC và danh mục PC Gaming/CPU/RAM nhưng seed là điện thoại/laptop/phụ kiện/tablet. Link chi tiết id=1 hiện tương ứng iPhone trong seed chứ không phải PC đang ghi trên trang chủ.

### TV2 — Sản phẩm

- [ ] Sửa API_URL viết cứng `/LTWEB-NHOM12/backend/api/products.php` trong `products.js`; URL này không khớp khi chạy tại gốc `localhost:8000` hoặc host ở domain root.
- [ ] Triển khai tìm kiếm, lọc giá, lọc danh mục, tải danh mục và nút đặt lại. HTML có các control nhưng JS hiện chưa gắn xử lý.
- [ ] Đọc tham số danh mục từ URL và thống nhất `cat`/slug với API đang nhận `category_id`.
- [ ] Phân trang qua API; hiện chỉ tải một trang rồi sắp xếp giá trong dữ liệu trang đó, chưa phải sắp xếp toàn bộ danh sách.
- [ ] Làm trang chi tiết theo id/slug: `users/product-detail.html` có khung rỗng; JS hiện không xử lý khung này.
- [ ] Làm tải danh mục: `users/categories.html` có khung `#category-list`, nhưng JS chưa xử lý.
- [ ] Tách xử lý theo trang: JS hiện luôn gọi tải danh sách và ghi vào `#productList`; trang danh mục/chi tiết không có phần tử này nên có đường chạy gây lỗi JS.
- [ ] Bổ sung link chi tiết và nút thêm giỏ trên thẻ sản phẩm từ API.
- [ ] Chuẩn hóa đường dẫn ảnh; seed chưa gán `thumbnail` dù thư mục uploads đã có ảnh.
- [ ] Escape dữ liệu trước khi đưa tên/danh mục/ảnh từ API vào `innerHTML` để tránh stored XSS khi dữ liệu được nhập qua Admin.

### TV3 — Tài khoản và giỏ hàng

Đã có giao diện login/register tại `frontend/pages/`, auth.js, API đăng ký/đăng nhập/đăng xuất/me và xử lý cookie session; không còn đánh giá các phần này là file trống như lần đầu.

- [ ] Làm trang hồ sơ và API cập nhật hồ sơ; `users/profile.html` hiện trống, `me.php` mới hỗ trợ GET thông tin session.
- [ ] Hoàn thiện giỏ hàng với backend được sửa; HTML/CSS/JS giỏ có sẵn, đường dẫn asset tại `pages/cart.html` đã được sửa.
- [ ] Chọn URL giỏ chính thức: `pages/cart.html` có nội dung nhưng `pages/users/cart.html` đang trống và đang được trang sản phẩm liên kết tới.
- [ ] Thống nhất lịch sử/chi tiết đơn: giỏ đang link/chuyển đến `pages/orders.html` chưa tồn tại; `pages/users/order-history.html` có file nhưng trống.
- [ ] Kiểm thử thêm/xóa, tăng/giảm, nhập số lượng, tổng tiền, tồn kho, đăng xuất và quyền sở hữu giỏ hàng.

### TV4 — Checkout và Admin

- [ ] Hoàn thiện checkout: form giao hàng trong giỏ đã có, nhưng `users/checkout.html` và `checkout.js` vẫn trống. Nhóm có thể dùng form tích hợp, không bắt buộc tách trang nếu luồng hoàn chỉnh.
- [ ] Làm trang xác nhận, lịch sử và chi tiết đơn hoạt động sau khi đặt hàng.
- [ ] Làm Dashboard và giao diện quản lý sản phẩm, thêm/sửa/xóa sản phẩm.
- [ ] Làm danh sách/chi tiết đơn cho admin, cập nhật trạng thái và thông báo kết quả.
- [ ] Tích hợp quyền admin và API do TV5 cung cấp. `BACKEND/admin/index.php` có kiểm tra quyền nhưng nội dung chỉ là trang chào, chưa phải dashboard quản trị nghiệp vụ.
- [ ] Các trang HTML trong `frontend/pages/admin/` hiện đều trống; `admin.js` cũng trống. Có tên file chưa được tính là đã có chức năng.

### TV5 — Backend

- [ ] Sửa toàn bộ P0 về helper, session, schema và môi trường trước.
- [ ] Xây API CRUD sản phẩm cho admin, xác thực quyền ở server, validate dữ liệu và xử lý ảnh sản phẩm.
- [ ] Xây API admin xem đơn hàng của khách và cập nhật trạng thái; API orders hiện chỉ có GET/POST cho khách.
- [ ] Chốt quy tắc chuyển trạng thái, hủy đơn và hoàn tồn kho để tránh cập nhật tùy ý/hoàn kho nhiều lần.
- [ ] Bổ sung cập nhật hồ sơ theo phạm vi TV3 cần.
- [ ] Chuẩn hóa lỗi JSON, kiểm tra kiểu dữ liệu và giới hạn đầu vào. `getJsonBody(): array` chưa kiểm tra JSON scalar, các trường truyền mảng có thể gây TypeError khi gọi trim().
- [ ] Bổ sung bảo vệ CSRF cho thao tác thay đổi dữ liệu dùng session, cùng giới hạn thử đăng nhập.
- [ ] Xử lý email trùng đồng thời bằng bắt lỗi ràng buộc unique thay vì chỉ kiểm tra SELECT trước INSERT.
- [ ] Kiểm thử bind kiểu số nguyên cho LIMIT/OFFSET trong API products với native PDO prepares.
- [ ] Viết hợp đồng API thống nhất để frontend dùng đúng tên trường, URL, mã lỗi và phân trang.

### TV6 — Database, kiểm thử và tài liệu

- [ ] Sửa các điểm schema lệch API theo bảng P0; ghi migration nếu đã có DB chứa dữ liệu, không chỉ sửa CREATE TABLE IF NOT EXISTS.
- [ ] Sửa seed/tài liệu mật khẩu; bổ sung thumbnail và dữ liệu phù hợp nội dung trang chủ/menu.
- [ ] Viết ERD và mô tả quan hệ; chốt cách giữ lịch sử đơn khi sản phẩm bị sửa/ngừng bán.
- [ ] Bổ sung kiểm thử sản phẩm, giỏ, checkout, admin, tồn kho/rollback và quyền xem/sửa dữ liệu của người khác.
- [ ] Kiểm thử xuyên suốt trên DB sạch từ schema/seed và trên hosting mục tiêu.
- [ ] Hoàn thiện README hiện trống: cài/chạy, extension PHP, import SQL, cấu hình môi trường, tài khoản demo và URL truy cập.
- [ ] Thêm cấu hình mẫu `.env.example` không có bí mật, hướng dẫn deploy và danh sách lỗi đã biết.

## P2 — Chốt phạm vi mở rộng

Repository có bảng/file cho banner, bài viết, quản lý user và settings nhưng chưa có chức năng tương ứng. Cần hỏi phạm vi giảng viên/nhóm: nếu bắt buộc thì phân công thêm API và giao diện; nếu không thì loại các link chưa hoạt động khỏi bản demo. Không coi mọi file tạo sẵn là yêu cầu bắt buộc của đồ án.

Thanh toán online, email xác nhận, đánh giá sản phẩm, voucher và tích hợp vận chuyển có thể làm sau nếu không nằm trong yêu cầu môn học. Một đồ án hoàn chỉnh có thể dùng COD, nhưng cần ghi rõ phương thức và xử lý đặt hàng đầy đủ.

## Kiểm tra đã thực hiện trong đợt này

- Kiểm tra cú pháp 13 file PHP: đều qua `php -l`.
- Kiểm tra cú pháp 9 file JavaScript: đều qua `node --check`.
- Kiểm tra PHP CLI: không nạp php.ini, không có PDO driver và mbstring.
- Kiểm tra hash seed bằng password_verify: khớp `password`, không khớp `123456`.
- Đối chiếu file trống, require, schema, API và các control/handler frontend.
- Đọc báo cáo xác thực của nhóm tại `docs/auth-testing.md`; phân biệt kết quả được ghi sẵn với kiểm tra mới.
- Chưa import MySQL, chạy kiểm thử tích hợp hoặc nghiệm thu trình duyệt trong đợt này. Qua kiểm tra cú pháp không có nghĩa ứng dụng chạy đúng nghiệp vụ.

## Điều kiện để chốt bản demo hoàn chỉnh

1. Cài theo README trên môi trường sạch, import DB và đăng nhập bằng tài khoản demo thành công.
2. Khách tìm/lọc/xem chi tiết sản phẩm, thêm giỏ và đổi số lượng đúng tồn kho.
3. Đặt hàng lưu đúng người nhận, sản phẩm, giá và tổng tiền; hiển thị xác nhận/lịch sử.
4. Admin quản lý sản phẩm, xem đơn và cập nhật trạng thái đúng quy tắc.
5. User thường không vào được chức năng admin hoặc xem/sửa giỏ/đơn của người khác.
6. Không còn lỗi chặn, trang nghiệp vụ trống hoặc đường dẫn hỏng trong luồng demo; có kết quả kiểm thử và hướng dẫn chạy.

Thứ tự làm: P0 (TV5 + TV6) → tích hợp sản phẩm/giỏ (TV2 + TV3) → checkout/Admin (TV4 + TV5) → kiểm thử, giao diện và tài liệu (TV1 + TV6 cùng cả nhóm).
