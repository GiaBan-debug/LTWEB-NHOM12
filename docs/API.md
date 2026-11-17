# API bản demo

> Cập nhật TV5: mặc định Firebase Google Auth. Đọc `TV5_FIREBASE.md`; login/register bằng mật khẩu PHP chỉ hoạt động trong AUTH_MODE=legacy. Thêm GET auth-config.php và POST firebase-session.php với body id_token + CSRF.

Gốc URL: `backend/api/` tương đối với thư mục dự án. Không viết cứng localhost hoặc tên thư mục máy cá nhân trong frontend.

Mọi response JSON có `success`, `message`, và `data` khi có dữ liệu. Các lỗi thường dùng: 401 chưa đăng nhập, 403 thiếu quyền/CSRF, 404 không tìm thấy, 409 xung đột dữ liệu/tồn kho/trạng thái, 422 đầu vào không hợp lệ, 500 lỗi máy chủ.

## Session và CSRF

Frontend và backend chạy cùng origin, gửi cookie session trong mọi request. GET `csrf.php` trước để lấy `data.csrf_token`; gửi giá trị này trong header `X-CSRF-Token` cho POST/PUT/DELETE, kể cả đăng ký/đăng nhập/đăng xuất. JSON body phải là object, không phải array/scalar. API OPTIONS hỗ trợ local CORS nhưng cách chạy được khuyến nghị là cùng origin.

| Endpoint | Method | Dữ liệu / query | Quyền |
| --- | --- | --- | --- |
| csrf.php | GET | Trả csrf_token | Công khai |
| health.php | GET | Kiểm tra kết nối database | Công khai |
| register.php | POST | name, email, password (6–72 byte) | Công khai + CSRF |
| login.php | POST | email, password | Công khai + CSRF |
| logout.php | POST | Không cần body | CSRF |
| me.php | GET | Thông tin session; không trả password | Đăng nhập |
| me.php | PUT | name, phone, address; không sửa role/UID/email | Đăng nhập + CSRF |
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
