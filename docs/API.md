# API bản demo

> Mặc định Firebase Google Auth. Xem [cấu hình xác thực trong README](../README.md#firebase-authentication). AUTH_MODE=legacy chỉ dùng thử với SĐT 0916111111 (admin), 0916222222 (user), không cần mật khẩu/OTP. Firebase mode chặn endpoint đăng nhập thử. Thêm GET auth-config.php và POST firebase-session.php với body id_token + CSRF.

Gốc URL: `backend/api/` tương đối với thư mục dự án. Không viết cứng localhost hoặc tên thư mục máy cá nhân trong frontend.

Mọi response JSON có `success`, `message`, và `data` khi có dữ liệu. Các lỗi thường dùng: 401 chưa đăng nhập, 403 thiếu quyền/CSRF, 404 không tìm thấy, 409 xung đột dữ liệu/tồn kho/trạng thái, 422 đầu vào không hợp lệ, 500 lỗi máy chủ.

## Session và CSRF

Frontend và backend chạy cùng origin, gửi cookie session trong mọi request. GET `csrf.php` trước để lấy `data.csrf_token`; gửi giá trị này trong header `X-CSRF-Token` cho POST/PUT/DELETE, kể cả đăng ký/đăng nhập/đăng xuất. JSON body phải là object, không phải array/scalar. API OPTIONS hỗ trợ local CORS nhưng cách chạy được khuyến nghị là cùng origin.

| Endpoint | Method | Dữ liệu / query | Quyền |
| --- | --- | --- | --- |
| csrf.php | GET | Trả csrf_token | Công khai |
| health.php | GET | Kiểm tra kết nối database | Công khai |
| register.php | POST | name, email, password (6–72 byte) | Công khai + CSRF |
| login.php | POST | phone: 0916111111 hoặc 0916222222 | Chỉ AUTH_MODE=legacy + CSRF; trả user/session |
| logout.php | POST | Không cần body | CSRF |
| me.php | GET | Thông tin session; không trả password | Đăng nhập |
| me.php | PUT | name, phone, address; không sửa role/UID/email | Đăng nhập + CSRF |
| reviews.php | GET | product_id, page; 10 đánh giá/trang, rating_average, rating_count, can_review, my_review | Công khai; my_review chỉ của phiên hiện tại |
| reviews.php | POST | product_id, rating (1–5), feedback (1–2.000 ký tự) | Đăng nhập + CSRF + đã mua trong đơn completed |
| reviews.php | PUT | product_id, rating, feedback; sửa đánh giá của chính mình | Đăng nhập + CSRF + đã mua trong đơn completed |
| categories.php | GET | Danh mục kèm product_count đang bán | Công khai |
| products.php | GET | id hoặc slug để lấy chi tiết | Công khai |
| products.php | GET | search, category_id, min_price, max_price, sort_by, sort_dir, page, limit | Công khai |
| cart.php | GET | items, total, total_items, is_empty, can_checkout | Đăng nhập |
| cart.php | POST | product_id, quantity; cộng vào số lượng hiện có | Đăng nhập + CSRF |
| cart.php | PUT | product_id, quantity; đặt lại số lượng | Đăng nhập + CSRF |
| cart.php | DELETE | Query product_id | Đăng nhập + CSRF |
| orders.php | POST | receiver_name, phone, address, note, checkout_key | Đăng nhập + CSRF |
| orders.php | GET | page hoặc id; chỉ đơn của user hiện tại | Đăng nhập |
| orders.php | PUT | id, action=cancel; chỉ đơn pending của chính user | Đăng nhập + CSRF |
| admin.php | GET | resource=dashboard/products/categories/orders; products/orders nhận id | Admin |
| admin.php | GET | products/orders: page, search | Admin |
| admin.php | POST | resource=products, body thông tin sản phẩm | Admin + CSRF |
| admin.php | PUT | resource=products&id=ID, body thông tin sản phẩm | Admin + CSRF |
| admin.php | DELETE | resource=products&id=ID; ngừng bán | Admin + CSRF |
| admin.php | POST | resource=categories, body name, description | Admin + CSRF |
| admin.php | PUT | resource=orders&id=ID, body status | Admin + CSRF |

`sort_by`: price/name/created_at; `sort_dir`: ASC/DESC. List products trả `data.items`, `total`, `page`, `limit`, `total_pages`. List orders/admin products/admin orders trả cùng kiểu pagination, kích thước trang cố định theo endpoint.

Sản phẩm admin nhận `name`, `category_id`, `price`, `stock`, `description`, `thumbnail`, `status`. Giá VND là số nguyên 0–1.000.000.000; tồn kho 0–1.000.000. Ảnh demo phải là đường dẫn tương đối `products/ten-anh.jpg` (hoặc jpeg/png/webp/avif) đã có trong uploads; không nhận đường dẫn tùy ý/URL bên ngoài. Status: active/inactive. Slug tự sinh khi tạo.

Giỏ giới hạn 1–99 sản phẩm mỗi mặt hàng, không vượt tồn kho đang bán. Tổng tiền và giá luôn do server tính. `checkout_key` là chuỗi 16–64 ký tự chữ/số/dấu gạch nối, frontend tạo bằng UUID hoặc random bytes; dùng lại cùng key khi thử lại cùng lượt đặt hàng. Giá trị đã tạo đơn sẽ trả lại mã đơn cũ, không trừ kho lần hai.

Đơn dùng COD, phí ship mặc định 30.000 ₫ dưới 2.000.000 ₫ và miễn ship từ ngưỡng đó. Giỏ trả subtotal/shipping_fee/total; đơn lưu snapshot phí, total_amount đã gồm phí. Chi tiết trả `data.items`, `total_amount`, `receiver_name`, `phone`, `address`, `note`, `status`, `payment_method`, `shipping_fee`. Item lưu tên/ảnh/giá tại lúc mua, không phụ thuộc dữ liệu sản phẩm sau này.

Trạng thái hợp lệ: pending → processing → shipped → completed. pending/processing có thể chuyển cancelled và được hoàn kho trong transaction. Lặp lại trạng thái hiện tại không tạo tác dụng phụ; completed/cancelled không chuyển tiếp.

## Hồ sơ và đánh giá sản phẩm

Firebase Phone: frontend dùng `RecaptchaVerifier` → `signInWithPhoneNumber` → `confirmation.confirm(OTP)` → gửi ID token tới `firebase-session.php` như Google. Backend vẫn xác minh RS256/project/thời hạn, chấp nhận email hợp lệ hoặc phone_number dạng E.164 có chữ ký. Tài khoản chỉ có SĐT lưu email=NULL, không tạo email giả; `users.firebase_phone` là SĐT từ token, tách khỏi `users.phone` (SĐT giao hàng được phép sửa). `publicUser` trả `phone_verified`; SĐT đã xác thực đáp ứng yêu cầu xác minh khi checkout. Role chỉ lấy từ MySQL, không tự gộp tài khoản theo số điện thoại. `auth-config.php` trả thêm `providers.phone`, điều khiển bằng `FIREBASE_PHONE_ENABLED=true`.

`PUT me.php` chỉ cập nhật trường name/phone/address được gửi; trường bị bỏ qua giữ nguyên. Điện thoại được chuẩn hóa về 10 chữ số bắt đầu bằng 0; cho phép để trống điện thoại/địa chỉ khi chưa hoàn thiện hồ sơ. Tên dài 2–100 ký tự, địa chỉ không trống phải dài 5–255 ký tự. Lỗi nhập liệu trả 422 với `data.errors` theo tên trường.

Checkout dùng hồ sơ đủ thông tin để hiển thị địa chỉ mặc định cùng nút “Thay đổi”. `POST orders.php` với `use_profile: true` đọc tên/SĐT/địa chỉ mới nhất từ MySQL của chính phiên đăng nhập, không tin thông tin giao hàng gửi kèm. Vẫn gửi note và checkout_key. Khi thay đổi địa chỉ riêng cho đơn, gửi `use_profile: false` cùng receiver_name/phone/address; hồ sơ không bị thay đổi.

Mỗi tài khoản được có một đánh giá cho mỗi sản phẩm đã mua thuộc đơn **completed**, có thể sửa sau đó. POST trùng trả 409; chưa mua/đơn chưa hoàn thành trả 403. Backend tự xác định user và đơn hợp lệ; không nhận user_id/order_id từ frontend để cấp quyền. Nội dung là văn bản thuần, frontend escape HTML khi hiển thị. `products.php` trả thêm rating_average/rating_count cho cả danh sách và chi tiết. Chưa có đánh giá thì trung bình là 0, giao diện ghi “Chưa có đánh giá”.

Bảng `product_reviews` được thêm bằng `database/migration-reviews.sql`; chạy `scripts/database.php` hoặc trình khởi động hiện có để áp dụng an toàn, không xóa dữ liệu. Schema mới đã bao gồm bảng này.
